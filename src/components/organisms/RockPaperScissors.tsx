import { useContext, useEffect, useRef, useState } from "react";
import { deleteDoc, doc, onSnapshot, setDoc } from "firebase/firestore";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import RestartAltIcon from "@mui/icons-material/RestartAlt";
import SportsMmaIcon from "@mui/icons-material/SportsMma";
import CloseIcon from "@mui/icons-material/Close";
import { db } from "../../firebase";
import { ThemeContext } from "../../hooks/ThemeContext";

// ---- A 2-player "Rock · Paper · Scissors" with custom forfeits ----
// State lives in one Firestore doc (chats/{chatId}/game/rps) so BOTH players
// see every move in real time — no game server needed.
//
// Each player secretly adds 3 "tasks" (dares). A game is 3 decisive rounds:
// each round both players throw a hand, the loser must perform a random task
// from the WINNER's task list. Best score after 3 rounds wins the game. Ties
// don't count as a round and are simply re-thrown.
//
// Headline rule (matches Monopoly): when a round has a loser, the match FREEZES
// on the dare. The loser performs it for real and taps "I did it"; then the
// WINNER must confirm ("did they really?") before the next round can begin.
// Nothing moves until they decide — and flaking costs you the round you won.

type Choice = "rock" | "paper" | "scissors";

interface RPSPlayer {
  uid: string;
  name: string;
  score: number;
}

// Set once a round is resolved. winner === null means the round was a tie.
interface Reveal {
  winner: string | null;
  loser: string | null;
  task: string | null;
  // Dare flow: loser flips claimedDone, then the winner sets confirmed
  // true (did it) or false (flaked → the winner's round point is revoked).
  claimedDone: boolean;
  confirmed: boolean | null;
}

interface RPSState {
  status: "collecting_tasks" | "playing" | "ended";
  createdBy: string;
  playerOrder: string[]; // [player0, player1]
  players: Record<string, RPSPlayer>;
  tasks: Record<string, string[]>; // uid -> 3 tasks
  round: number; // current decisive round, 1..ROUNDS
  choices: Record<string, Choice | null>; // this round's throws (null = not yet)
  reveal: Reveal | null; // set when both have thrown & the round is resolved
  winner: string | null;
  log: string[];
}

const ROUNDS = 3;
const TASKS_PER_PLAYER = 3;

const EMOJI: Record<Choice, string> = {
  rock: "✊",
  paper: "✋",
  scissors: "✌️",
};
const CHOICES: Choice[] = ["rock", "paper", "scissors"];

// Pick a random dare from a pool (module scope keeps the impure call out of
// the component body — same pattern as Monopoly's shuffle()).
function pickTask(pool: string[]): string {
  if (!pool.length) return "No dare — lucky you!";
  return pool[Math.floor(Math.random() * pool.length)];
}

// Returns 0 = tie, 1 = a beats b, 2 = b beats a.
function decide(a: Choice, b: Choice): 0 | 1 | 2 {
  if (a === b) return 0;
  const aWins =
    (a === "rock" && b === "scissors") ||
    (a === "scissors" && b === "paper") ||
    (a === "paper" && b === "rock");
  return aWins ? 1 : 2;
}

// ---- Simple sound effects (Web Audio — no files, works offline) ----
let audioCtx: AudioContext | null = null;
function tone(
  freq: number,
  durMs: number,
  type: OscillatorType = "sine",
  vol = 0.15,
  delay = 0
) {
  try {
    const Ctx =
      window.AudioContext ||
      (window as unknown as { webkitAudioContext: typeof AudioContext })
        .webkitAudioContext;
    audioCtx = audioCtx || new Ctx();
    if (audioCtx.state === "suspended") audioCtx.resume();
    const ctx = audioCtx;
    const start = ctx.currentTime + delay;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(vol, start);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + durMs / 1000);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(start);
    osc.stop(start + durMs / 1000);
  } catch {
    /* audio unavailable — ignore */
  }
}
const sfx = {
  // "Rock… paper… scissors — shoot!" three rising beats then a pop.
  throw: () => {
    [0, 0.08, 0.16].forEach((d) => tone(360, 50, "square", 0.1, d));
    tone(560, 80, "triangle", 0.13, 0.26);
  },
  // Bright rising win sting.
  win: () => {
    tone(659, 90, "triangle", 0.15);
    tone(880, 110, "triangle", 0.15, 0.08);
    tone(1175, 150, "triangle", 0.14, 0.17);
  },
  // Descending "aww" for a loss.
  lose: () => {
    tone(392, 130, "sawtooth", 0.13);
    tone(294, 170, "sawtooth", 0.13, 0.11);
    tone(196, 220, "sawtooth", 0.12, 0.24);
  },
  // Flat double-blip for a tie.
  tie: () => {
    tone(440, 90, "sine", 0.12);
    tone(440, 90, "sine", 0.12, 0.13);
  },
  // Triumphant match-win fanfare.
  fanfare: () =>
    [523, 659, 784, 1047, 1319, 1047, 1319].forEach((f, i) =>
      tone(f, 170, "triangle", 0.16, i * 0.11)
    ),
};

const vibe = (pattern: number | number[]) => {
  try {
    navigator.vibrate?.(pattern);
  } catch {
    /* not supported */
  }
};

interface Props {
  chatId: string;
  opponentUid: string;
  opponentName: string;
  onClose: () => void;
  // Called when the player quits — clears the chat's "play" invite card too.
  onQuit: () => void;
}

export default function RockPaperScissors({
  chatId,
  opponentUid,
  opponentName,
  onClose,
  onQuit,
}: Props) {
  const { currentUser } = useContext(ThemeContext);
  const myUid: string = currentUser.uid;
  const myName: string = currentUser.displayName ?? "You";

  const [game, setGame] = useState<RPSState | null>(null);
  const [loaded, setLoaded] = useState(false);
  const [taskInputs, setTaskInputs] = useState(["", "", ""]);
  const [confirmReset, setConfirmReset] = useState(false);

  const ref = doc(db, "chats", chatId, "game", "rps");

  useEffect(() => {
    const unsub = onSnapshot(ref, (snap) => {
      setGame(snap.exists() ? (snap.data() as RPSState) : null);
      setLoaded(true);
    });
    return () => unsub();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chatId]);

  const save = (g: RPSState) => setDoc(ref, g);
  const otherUid = (g: RPSState) =>
    g.playerOrder.find((u) => u !== myUid) as string;

  // Leaving a FINISHED match deletes its Firestore doc so old matches (tasks
  // and all) don't pile up. An in-progress match is left intact.
  const closeGame = () => {
    if (game?.status === "ended") {
      deleteDoc(ref).catch(() => {
        /* best-effort cleanup */
      });
      onQuit(); // match's over — also clear the chat's invite card
      return;
    }
    onClose();
  };

  // Quit ends the match for BOTH players and deletes the doc (tasks and all).
  const [confirmQuit, setConfirmQuit] = useState(false);
  const quitGame = () => {
    setConfirmQuit(false);
    deleteDoc(ref).catch(() => {
      /* best-effort cleanup */
    });
    onQuit();
  };

  // ---- Start / reset: a fresh game back to the task-collection phase ----
  const startGame = () => {
    save({
      status: "collecting_tasks",
      createdBy: myUid,
      playerOrder: [myUid, opponentUid],
      players: {
        [myUid]: { uid: myUid, name: myName, score: 0 },
        [opponentUid]: { uid: opponentUid, name: opponentName, score: 0 },
      },
      tasks: {},
      round: 1,
      choices: { [myUid]: null, [opponentUid]: null },
      reveal: null,
      winner: null,
      log: [`New match! Each player adds ${TASKS_PER_PLAYER} dares.`],
    });
    setTaskInputs(["", "", ""]);
    setConfirmReset(false);
  };

  const submitTasks = () => {
    if (!game) return;
    const cleaned = taskInputs.map((r) => r.trim()).filter(Boolean);
    if (cleaned.length < TASKS_PER_PLAYER) return;
    save({
      ...game,
      tasks: {
        ...game.tasks,
        [myUid]: cleaned.slice(0, TASKS_PER_PLAYER),
      },
      log: [...game.log, `${myName} locked in their dares.`].slice(-8),
    });
  };

  // ---- Creator flips to "playing" once both task lists are in ----
  const startedRef = useRef(false);
  useEffect(() => {
    if (!game || game.status !== "collecting_tasks") {
      startedRef.current = false;
      return;
    }
    const a = game.tasks[game.playerOrder[0]];
    const b = game.tasks[game.playerOrder[1]];
    if (
      a?.length === TASKS_PER_PLAYER &&
      b?.length === TASKS_PER_PLAYER &&
      myUid === game.createdBy &&
      !startedRef.current
    ) {
      startedRef.current = true;
      save({
        ...game,
        status: "playing",
        round: 1,
        choices: { [game.playerOrder[0]]: null, [game.playerOrder[1]]: null },
        reveal: null,
        log: [...game.log, "Dares are in. Round 1 — throw your hand!"].slice(-8),
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [game]);

  // ---- Round / match end sounds ----
  const revealedRef = useRef(false);
  useEffect(() => {
    if (game?.status === "ended") {
      // handled below
    }
    const r = game?.reveal;
    if (r && !revealedRef.current) {
      revealedRef.current = true;
      if (r.winner === null) {
        sfx.tie();
        vibe(30);
      } else if (r.winner === myUid) {
        sfx.win();
        vibe([20, 30, 50]);
      } else {
        sfx.lose();
        vibe([30, 40, 30]);
      }
    } else if (!r) {
      revealedRef.current = false;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [game?.reveal]);

  const wonRef = useRef(false);
  useEffect(() => {
    if (game?.status === "ended" && !wonRef.current) {
      wonRef.current = true;
      sfx.fanfare();
      vibe([60, 50, 60, 50, 120]);
    } else if (game?.status !== "ended") {
      wonRef.current = false;
    }
  }, [game?.status]);

  // ---- Throw a hand. The player who completes the pair resolves the round. ----
  const throwHand = (choice: Choice) => {
    if (!game || game.status !== "playing" || game.reveal) return;
    if (game.choices[myUid]) return; // already thrown this round
    sfx.throw();
    vibe(20);

    const oppUid = otherUid(game);
    const oppChoice = game.choices[oppUid];
    const choices = { ...game.choices, [myUid]: choice };

    // Opponent hasn't thrown yet — just record my throw and wait.
    if (!oppChoice) {
      save({ ...game, choices });
      return;
    }

    // Both have thrown — resolve the round in this same write.
    const players = {
      ...game.players,
      [myUid]: { ...game.players[myUid] },
      [oppUid]: { ...game.players[oppUid] },
    };
    const outcome = decide(choice, oppChoice); // 1 = I win, 2 = opp wins
    const log = [...game.log];
    let reveal: Reveal;

    if (outcome === 0) {
      reveal = {
        winner: null,
        loser: null,
        task: null,
        claimedDone: false,
        confirmed: null,
      };
      log.push(`Round ${game.round}: both threw ${EMOJI[choice]} — tie, re-throw!`);
    } else {
      const winnerUid = outcome === 1 ? myUid : oppUid;
      const loserUid = outcome === 1 ? oppUid : myUid;
      players[winnerUid].score += 1;
      const task = pickTask(game.tasks[winnerUid] ?? []);
      reveal = {
        winner: winnerUid,
        loser: loserUid,
        task,
        claimedDone: false,
        confirmed: null,
      };
      log.push(
        `Round ${game.round}: ${players[winnerUid].name} ${EMOJI[winnerUid === myUid ? choice : oppChoice]} beats ${EMOJI[winnerUid === myUid ? oppChoice : choice]} ${players[loserUid].name}`
      );
    }

    save({ ...game, players, choices, reveal, log: log.slice(-8) });
  };

  // ---- Dare flow (mirrors Monopoly) ----
  // Step 1: the LOSER, after doing the dare for real, taps "I did it". This
  // does not advance the round — it just asks the winner to confirm.
  const claimTaskDone = () => {
    if (!game?.reveal || game.reveal.winner === null) return;
    if (game.reveal.loser !== myUid || game.reveal.claimedDone) return;
    save({
      ...game,
      reveal: { ...game.reveal, claimedDone: true },
      log: [
        ...game.log,
        `${myName} says they did the dare — waiting on ${
          game.players[game.reveal.winner]?.name
        } to confirm…`,
      ].slice(-8),
    });
  };

  // Step 2: only the WINNER judges. "no" revokes the round point they earned,
  // so flaking actually costs the loser the round.
  const judgeTask = (didIt: boolean) => {
    if (!game?.reveal || game.reveal.winner === null) return;
    if (game.reveal.winner !== myUid) return; // only the winner may judge
    if (!game.reveal.claimedDone) return; // loser must claim first

    const players = {
      ...game.players,
      [myUid]: { ...game.players[myUid] },
    };
    const log = [...game.log];
    if (didIt) {
      sfx.win();
      vibe([20, 30, 50]);
      log.push(`${myName} confirmed the dare was done.`);
    } else {
      // Revoke the point the winner was awarded when the round resolved.
      players[myUid].score = Math.max(0, players[myUid].score - 1);
      sfx.lose();
      vibe([40, 30, 40]);
      log.push(`${myName} says they flaked — round point revoked!`);
    }
    save({
      ...game,
      players,
      reveal: { ...game.reveal, confirmed: didIt },
      log: log.slice(-8),
    });
  };

  // ---- Advance to the next round (or end the match). Idempotent: both
  // players may tap it, and computing from `round` avoids double-counting. ----
  const nextRound = () => {
    if (!game || !game.reveal) return;
    const tie = game.reveal.winner === null;
    const oppUid = otherUid(game);
    const clearedChoices = { [myUid]: null, [oppUid]: null };

    // A tie doesn't burn a round — just re-throw the same round.
    if (tie) {
      save({ ...game, choices: clearedChoices, reveal: null });
      return;
    }

    // A decisive round can't advance until the winner has confirmed the dare.
    if (game.reveal.confirmed === null) return;

    const nextRoundNo = game.round + 1;
    if (nextRoundNo > ROUNDS) {
      const [p0, p1] = game.playerOrder;
      const s0 = game.players[p0].score;
      const s1 = game.players[p1].score;
      const winner = s0 === s1 ? null : s0 > s1 ? p0 : p1;
      save({
        ...game,
        status: "ended",
        reveal: null,
        choices: clearedChoices,
        winner,
        log: [
          ...game.log,
          winner
            ? `Match over — ${game.players[winner].name} wins ${Math.max(s0, s1)}–${Math.min(s0, s1)}!`
            : `Match over — it's a draw ${s0}–${s1}!`,
        ].slice(-8),
      });
      return;
    }

    save({
      ...game,
      round: nextRoundNo,
      choices: clearedChoices,
      reveal: null,
      log: [...game.log, `Round ${nextRoundNo} — throw your hand!`].slice(-8),
    });
  };

  // ---- Rendering ----
  if (!loaded) {
    return (
      <div className="h-full w-full flex items-center justify-center text-gray-400 dark:text-stone-400">
        Loading game…
      </div>
    );
  }

  const Header = (
    <div className="flex items-center gap-2 px-4 py-3 bg-light-bg dark:bg-stone-900 border-b border-gray-200 dark:border-stone-700 shrink-0">
      <button
        onClick={closeGame}
        className="flex items-center gap-1.5 pl-1.5 pr-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-stone-300 hover:bg-gray-200 dark:hover:bg-stone-700 transition-colors cursor-pointer"
        title="Back to chat"
      >
        <ArrowBackIcon fontSize="small" />
        <span>Back to chat</span>
      </button>
      <div className="flex-1" />
      {game && (
        <>
          <button
            onClick={() => {
              setConfirmReset(false);
              setConfirmQuit(true);
            }}
            className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-sm text-gray-600 dark:text-stone-300 hover:bg-red-50 dark:hover:bg-red-950/40 hover:text-red-600 transition-colors cursor-pointer"
            title="Quit match"
          >
            <CloseIcon fontSize="small" />
            <span className="hidden sm:inline">Quit</span>
          </button>
          <button
            onClick={() => {
              setConfirmQuit(false);
              setConfirmReset(true);
            }}
            className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-sm text-gray-600 dark:text-stone-300 hover:bg-gray-200 dark:hover:bg-stone-700 transition-colors cursor-pointer"
            title="Reset game"
          >
            <RestartAltIcon fontSize="small" />
            <span className="hidden sm:inline">Reset</span>
          </button>
        </>
      )}
      <SportsMmaIcon className="text-primary" />
      <h3 className="font-semibold text-gray-800 dark:text-stone-100">Rock · Paper · Scissors</h3>
    </div>
  );

  // Quit confirmation bar — ends the match for BOTH players and deletes it.
  const QuitBar = confirmQuit && (
    <div className="flex items-center gap-3 px-4 py-3 bg-red-50 dark:bg-red-950/40 border-b border-red-100 dark:border-red-900 shrink-0">
      <span className="text-sm text-red-700 dark:text-red-300 flex-1">
        Quit and end this match for both players? It can't be resumed.
      </span>
      <button
        onClick={() => setConfirmQuit(false)}
        className="px-3 py-1.5 text-sm text-gray-600 dark:text-stone-300 rounded-lg hover:bg-white dark:hover:bg-stone-800 cursor-pointer"
      >
        Cancel
      </button>
      <button
        onClick={quitGame}
        className="px-3 py-1.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 cursor-pointer"
      >
        Quit match
      </button>
    </div>
  );

  // Reset confirmation bar — restarting wipes the match for BOTH players.
  const ResetBar = confirmReset && (
    <div className="flex items-center gap-3 px-4 py-3 bg-amber-50 dark:bg-amber-950/40 border-b border-amber-100 dark:border-amber-900 shrink-0">
      <span className="text-sm text-amber-800 dark:text-amber-200 flex-1">
        Start a brand-new match? Both players add new dares.
      </span>
      <button
        onClick={() => setConfirmReset(false)}
        className="px-3 py-1.5 text-sm text-gray-600 dark:text-stone-300 rounded-lg hover:bg-gray-200 dark:hover:bg-stone-700 cursor-pointer"
      >
        Cancel
      </button>
      <button
        onClick={startGame}
        className="px-3 py-1.5 text-sm bg-primary text-white rounded-lg hover:bg-black cursor-pointer"
      >
        New match
      </button>
    </div>
  );

  // Lobby — no game yet
  if (!game) {
    return (
      <div className="h-full w-full flex flex-col bg-white dark:bg-stone-900">
        {Header}
        <div className="flex-1 flex flex-col items-center justify-center gap-4 p-6 text-center">
          <div className="text-5xl">✊✋✌️</div>
          <p className="text-gray-600 dark:text-stone-300 max-w-xs">
            Play a best-of-{ROUNDS} Rock · Paper · Scissors with{" "}
            <span className="font-semibold">{opponentName}</span>. You'll each
            add {TASKS_PER_PLAYER} dares — the loser of every round performs one
            of the winner's dares!
          </p>
          <button
            onClick={startGame}
            className="px-5 py-2.5 bg-primary text-white rounded-lg hover:bg-black transition-colors cursor-pointer"
          >
            Start a new match
          </button>
        </div>
      </div>
    );
  }

  const myTasksDone = game.tasks[myUid]?.length === TASKS_PER_PLAYER;
  const oppTasksDone = game.tasks[opponentUid]?.length === TASKS_PER_PLAYER;

  // Task-collection phase
  if (game.status === "collecting_tasks") {
    return (
      <div className="h-full w-full flex flex-col bg-white dark:bg-stone-900">
        {Header}
        {ResetBar}
        {QuitBar}
        <div className="flex-1 min-h-0 overflow-y-auto p-5 pb-28">
          <h4 className="font-semibold text-gray-800 dark:text-stone-100 mb-1">
            Add your {TASKS_PER_PLAYER} dares
          </h4>
          <p className="text-sm text-gray-500 dark:text-stone-400 mb-4">
            Whoever loses a round has to do one of these — picked at random. Keep
            them fun!
          </p>

          {myTasksDone ? (
            <div className="rounded-lg bg-green-50 dark:bg-green-950/40 border border-green-200 dark:border-green-900 p-4 text-sm text-green-700 dark:text-green-300">
              Your dares are in. Waiting for {opponentName}…
            </div>
          ) : (
            <div className="flex flex-col gap-2">
              {taskInputs.map((val, i) => (
                <input
                  key={i}
                  value={val}
                  onChange={(e) => {
                    const next = [...taskInputs];
                    next[i] = e.target.value;
                    setTaskInputs(next);
                  }}
                  placeholder={`Dare ${i + 1} (e.g. "Sing a song")`}
                  className="w-full border border-[#ddd] dark:border-stone-700 rounded-lg p-2 text-sm outline-primary focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
                />
              ))}
              <button
                onClick={submitTasks}
                disabled={
                  taskInputs.filter((r) => r.trim()).length < TASKS_PER_PLAYER
                }
                className="mt-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-black transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Lock in my dares
              </button>
            </div>
          )}

          <div className="mt-5 text-sm text-gray-500 dark:text-stone-400">
            {opponentName}: {oppTasksDone ? "ready" : "still adding dares…"}
          </div>
        </div>
      </div>
    );
  }

  // Playing / ended
  const me = game.players[myUid];
  const opp = game.players[opponentUid];
  const myChoice = game.choices[myUid];
  const oppChoice = game.choices[opponentUid];
  const reveal = game.reveal;
  const waitingForOpp = !!myChoice && !oppChoice && !reveal;

  return (
    <div className="h-full w-full flex flex-col bg-white dark:bg-stone-900">
      {Header}
      {ResetBar}
      {QuitBar}

      {/* Scoreboard */}
      <div className="flex gap-2 px-3 py-2 border-b border-gray-100 dark:border-stone-700 shrink-0">
        {[me, opp].map(
          (p) =>
            p && (
              <div
                key={p.uid}
                className="flex-1 rounded-lg p-2 text-sm border border-gray-200 dark:border-stone-700 flex items-center justify-between"
              >
                <span className="font-semibold text-gray-800 dark:text-stone-100 truncate">
                  {p.uid === myUid ? "You" : p.name}
                </span>
                <span className="text-lg font-bold text-primary">{p.score}</span>
              </div>
            )
        )}
      </div>

      <div className="flex-1 min-h-0 overflow-y-auto p-4 pb-28 flex flex-col items-center">
        {game.status === "ended" ? (
          <div className="flex-1 flex flex-col items-center justify-center gap-4 text-center py-8">
            <div className="text-sm uppercase tracking-[0.2em] text-gray-400 dark:text-stone-400">
              Match complete
            </div>
            <div className="text-xl font-bold text-gray-800 dark:text-stone-100">
              {game.winner === null
                ? "It's a draw!"
                : game.winner === myUid
                ? "You win the match!"
                : `${opp?.name} wins the match!`}
            </div>
            <div className="text-gray-500 dark:text-stone-400">
              Final score {me?.score}–{opp?.score}
            </div>
            <button
              onClick={startGame}
              className="px-5 py-2.5 bg-primary text-white rounded-lg hover:bg-black transition-colors cursor-pointer"
            >
              New match (new dares)
            </button>
          </div>
        ) : (
          <>
            <div className="text-sm font-semibold text-gray-500 dark:text-stone-400 mb-4">
              Round {game.round} of {ROUNDS}
            </div>

            {/* Hands */}
            <div className="flex items-center justify-center gap-6 mb-6">
              <div className="flex flex-col items-center gap-2">
                {/* You always see your own hand. */}
                <div className="h-20 w-20 rounded-2xl border-2 border-gray-200 dark:border-stone-700 flex items-center justify-center text-4xl bg-gray-50 dark:bg-stone-800">
                  {myChoice ? EMOJI[myChoice] : "…"}
                </div>
                <span className="text-xs text-gray-500 dark:text-stone-400 max-w-20 truncate">
                  You
                </span>
              </div>
              <span className="text-gray-300 dark:text-stone-600 font-bold text-lg">vs</span>
              <div className="flex flex-col items-center gap-2">
                {/* Opponent's hand stays hidden until BOTH have thrown
                    (reveal is only set once both choices are in). A pulsing
                    fist + "Ready" shows they've locked in without revealing it. */}
                <div
                  className={`h-20 w-20 rounded-2xl border-2 flex items-center justify-center text-4xl transition-all ${
                    oppChoice && !reveal
                      ? "border-primary bg-primary/5 animate-pulse"
                      : "border-gray-200 dark:border-stone-700 bg-gray-50 dark:bg-stone-800"
                  }`}
                >
                  {reveal && oppChoice ? EMOJI[oppChoice] : oppChoice ? "•" : "…"}
                </div>
                <span className="text-xs text-gray-500 dark:text-stone-400 max-w-20 truncate">
                  {oppChoice && !reveal ? "Ready" : opp?.name}
                </span>
              </div>
            </div>

            {/* Reveal / forfeit panel */}
            {reveal ? (
              <div className="w-full max-w-sm">
                {reveal.winner === null ? (
                  <>
                    <div className="rounded-lg border border-gray-200 dark:border-stone-700 bg-gray-50 dark:bg-stone-800 p-4 text-center">
                      <p className="text-gray-700 dark:text-stone-200 font-semibold">Tie!</p>
                      <p className="text-sm text-gray-500 dark:text-stone-400">
                        Same hand — throw again.
                      </p>
                    </div>
                    <button
                      onClick={nextRound}
                      className="mt-3 w-full px-4 py-2.5 bg-primary text-white rounded-lg hover:bg-black transition-colors cursor-pointer"
                    >
                      Throw again
                    </button>
                  </>
                ) : (
                  (() => {
                    const iWon = reveal.winner === myUid;
                    const iLost = reveal.loser === myUid;
                    const loserName =
                      game.players[reveal.loser ?? ""]?.name ?? "";
                    return (
                      <>
                        <div
                          className={`rounded-lg border p-4 text-center ${
                            iWon
                              ? "border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950/40"
                              : "border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/40"
                          }`}
                        >
                          <p className="font-semibold text-gray-800 dark:text-stone-100 mb-2">
                            {iWon
                              ? "You won the round!"
                              : `${opp?.name} won the round.`}
                          </p>
                          <p className="text-xs font-semibold text-primary uppercase tracking-wide mb-1">
                            {iLost ? "Your dare" : `${loserName}'s dare`}
                          </p>
                          <p className="text-sm text-gray-800 dark:text-stone-100">{reveal.task}</p>
                        </div>

                        {/* Freeze-until-confirmed dare flow */}
                        <div className="mt-3 rounded-lg border-2 border-primary/30 bg-primary/5 dark:bg-primary/10 p-3 text-center">
                          {reveal.confirmed === null ? (
                            iLost ? (
                              // I lost — I must do the dare, then claim done.
                              reveal.claimedDone ? (
                                <p className="text-sm text-gray-600 dark:text-stone-300">
                                  Waiting for{" "}
                                  <b>{game.players[reveal.winner]?.name}</b> to
                                  confirm you did it…
                                </p>
                              ) : (
                                <>
                                  <p className="text-xs text-gray-500 dark:text-stone-400 mb-2">
                                    Do it for real, then tap below.{" "}
                                    {game.players[reveal.winner]?.name} decides
                                    if it counts — the match won't move until
                                    they do.
                                  </p>
                                  <button
                                    onClick={claimTaskDone}
                                    className="px-4 py-2 bg-primary text-white rounded-lg text-sm cursor-pointer hover:bg-black"
                                  >
                                    I did it
                                  </button>
                                </>
                              )
                            ) : // I won — I judge once the loser has claimed.
                            reveal.claimedDone ? (
                              <>
                                <p className="text-sm text-gray-600 dark:text-stone-300 mb-2">
                                  <b>{loserName}</b> says they did it. Did they
                                  really?
                                </p>
                                <div className="flex gap-2 justify-center">
                                  <button
                                    onClick={() => judgeTask(true)}
                                    className="px-4 py-1.5 bg-green-600 text-white rounded-lg text-sm cursor-pointer hover:bg-green-700"
                                  >
                                    Yes, they did
                                  </button>
                                  <button
                                    onClick={() => judgeTask(false)}
                                    className="px-4 py-1.5 bg-red-600 text-white rounded-lg text-sm cursor-pointer hover:bg-red-700"
                                  >
                                    No
                                  </button>
                                </div>
                              </>
                            ) : (
                              <p className="text-sm text-gray-500 dark:text-stone-400">
                                Waiting for {loserName} to perform the dare…
                              </p>
                            )
                          ) : (
                            // Confirmed — show outcome + the advance button.
                            <>
                              <p className="text-sm text-gray-700 dark:text-stone-200 mb-2">
                                {reveal.confirmed
                                  ? "Dare confirmed!"
                                  : "Flaked — the round point was revoked."}
                              </p>
                              <button
                                onClick={nextRound}
                                className="w-full px-4 py-2.5 bg-primary text-white rounded-lg hover:bg-black transition-colors cursor-pointer"
                              >
                                {game.round >= ROUNDS
                                  ? "See result"
                                  : "Next round"}
                              </button>
                            </>
                          )}
                        </div>
                      </>
                    );
                  })()
                )}
              </div>
            ) : waitingForOpp ? (
              <div className="text-sm text-gray-500 dark:text-stone-400 animate-pulse">
                Waiting for {opp?.name} to throw…
              </div>
            ) : (
              <div className="w-full max-w-sm">
                <p className="text-center text-sm text-gray-500 dark:text-stone-400 mb-3">
                  Pick your hand
                </p>
                <div className="grid grid-cols-3 gap-3">
                  {CHOICES.map((c) => (
                    <button
                      key={c}
                      onClick={() => throwHand(c)}
                      className="flex flex-col items-center gap-1 py-4 rounded-xl border-2 border-gray-200 dark:border-stone-700 hover:border-primary hover:bg-primary/5 transition-colors cursor-pointer active:scale-95"
                    >
                      <span className="text-3xl">{EMOJI[c]}</span>
                      <span className="text-xs text-gray-500 dark:text-stone-400 capitalize">{c}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}
          </>
        )}

        {/* Log */}
        <div className="mt-4 w-full rounded-lg bg-gray-50 dark:bg-stone-800 border border-gray-100 dark:border-stone-700 p-2 text-xs text-gray-500 dark:text-stone-400 space-y-0.5">
          {game.log.slice(-6).map((line, i) => (
            <div key={i}>{line}</div>
          ))}
        </div>
      </div>
    </div>
  );
}
