import { useContext, useEffect, useRef, useState } from "react";
import { doc, onSnapshot, setDoc } from "firebase/firestore";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import CasinoIcon from "@mui/icons-material/Casino";
import { db } from "../../firebase";
import { ThemeContext } from "../../hooks/ThemeContext";

// ---- A simple 2-player "Custom Rules Monopoly" ----
// State lives in one Firestore doc (chats/{chatId}/game/monopoly) so BOTH
// players see every move in real time — no game server needed.

type TileType = "GO" | "PROPERTY" | "RULE" | "TAX";

interface Tile {
  type: TileType;
  name: string;
  price: number | null;
  rent: number | null;
  ruleText: string | null;
  owner: string | null; // uid of the owner, or null
}

interface Player {
  uid: string;
  name: string;
  money: number;
  position: number;
}

interface Pending {
  type: "buy" | "rule";
  tileIndex: number;
}

interface GameState {
  status: "collecting_rules" | "playing" | "ended";
  createdBy: string;
  playerOrder: string[]; // [player0, player1]
  players: Record<string, Player>;
  rules: Record<string, string[]>; // uid -> 4 rules
  board: Tile[];
  currentTurn: string;
  lastRoll: number | null;
  pending: Pending | null;
  winner: string | null;
  log: string[];
}

const START_MONEY = 1500;
const PASS_GO = 200;
const TAX_AMOUNT = 100;

// Tile factory keeps every field defined (Firestore rejects `undefined`).
const t = (
  type: TileType,
  name: string,
  price: number | null = null,
  rent: number | null = null
): Tile => ({ type, name, price, rent, ruleText: null, owner: null });

// 20-tile board (a 6×6 ring). The 8 RULE tiles get the players' custom rules.
const BOARD_TEMPLATE: Tile[] = [
  t("GO", "GO"),
  t("PROPERTY", "Maple Ave", 100, 20),
  t("RULE", "Rule"),
  t("PROPERTY", "Oak St", 100, 20),
  t("TAX", "Tax"),
  t("RULE", "Rule"),
  t("PROPERTY", "Pine Rd", 120, 25),
  t("RULE", "Rule"),
  t("PROPERTY", "Cedar Ln", 120, 25),
  t("RULE", "Rule"),
  t("PROPERTY", "Elm Blvd", 140, 30),
  t("RULE", "Rule"),
  t("PROPERTY", "Birch Way", 140, 30),
  t("RULE", "Rule"),
  t("TAX", "Tax"),
  t("PROPERTY", "Willow Ct", 160, 35),
  t("RULE", "Rule"),
  t("PROPERTY", "Aspen Dr", 160, 35),
  t("RULE", "Rule"),
  t("PROPERTY", "Spruce St", 180, 40),
];

// Grid positions (row/col, 1-indexed) for each board index — a clockwise ring.
const COORDS = [
  { r: 1, c: 1 }, { r: 1, c: 2 }, { r: 1, c: 3 }, { r: 1, c: 4 }, { r: 1, c: 5 }, { r: 1, c: 6 },
  { r: 2, c: 6 }, { r: 3, c: 6 }, { r: 4, c: 6 }, { r: 5, c: 6 }, { r: 6, c: 6 },
  { r: 6, c: 5 }, { r: 6, c: 4 }, { r: 6, c: 3 }, { r: 6, c: 2 }, { r: 6, c: 1 },
  { r: 5, c: 1 }, { r: 4, c: 1 }, { r: 3, c: 1 }, { r: 2, c: 1 },
];

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
  dice: () => {
    tone(180, 70, "square", 0.12);
    tone(240, 70, "square", 0.1, 0.07);
  },
  step: () => tone(620, 45, "square", 0.07),
  buy: () => {
    tone(523, 90, "triangle", 0.15);
    tone(784, 130, "triangle", 0.15, 0.09);
  },
  pay: () => {
    tone(380, 120, "sawtooth", 0.12);
    tone(280, 150, "sawtooth", 0.12, 0.1);
  },
  rule: () => {
    tone(660, 90, "sine", 0.16);
    tone(880, 130, "sine", 0.16, 0.09);
  },
  win: () =>
    [523, 659, 784, 1047].forEach((f, i) =>
      tone(f, 180, "triangle", 0.16, i * 0.12)
    ),
};

// Haptics — vibrates on supported devices (Android/Chrome); no-op elsewhere.
const vibe = (pattern: number | number[]) => {
  try {
    navigator.vibrate?.(pattern);
  } catch {
    /* not supported */
  }
};

function shuffle<T>(arr: T[]): T[] {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

// Drop the 8 custom rules (shuffled) onto the 8 RULE tiles.
function buildBoard(rulesA: string[], rulesB: string[]): Tile[] {
  const texts = shuffle([...rulesA, ...rulesB]);
  let i = 0;
  return BOARD_TEMPLATE.map((tile) =>
    tile.type === "RULE"
      ? { ...tile, ruleText: texts[i++] ?? "House rule!" }
      : { ...tile }
  );
}

interface Props {
  chatId: string;
  opponentUid: string;
  opponentName: string;
  onClose: () => void;
}

export default function MonopolyGame({
  chatId,
  opponentUid,
  opponentName,
  onClose,
}: Props) {
  const { currentUser } = useContext(ThemeContext);
  const myUid: string = currentUser.uid;
  const myName: string = currentUser.displayName ?? "You";

  const [game, setGame] = useState<GameState | null>(null);
  const [loaded, setLoaded] = useState(false);
  const [ruleInputs, setRuleInputs] = useState(["", "", "", ""]);

  // Animation state: where each token is *displayed* (hops toward the real
  // position), plus a spinning dice while rolling.
  const [displayPos, setDisplayPos] = useState<Record<string, number>>({});
  const [rolling, setRolling] = useState(false);
  const [fakeDie, setFakeDie] = useState(1);
  const targetRef = useRef<Record<string, number>>({});

  const ref = doc(db, "chats", chatId, "game", "monopoly");

  useEffect(() => {
    const unsub = onSnapshot(ref, (snap) => {
      setGame(snap.exists() ? (snap.data() as GameState) : null);
      setLoaded(true);
    });
    return () => unsub();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chatId]);

  const save = (g: GameState) => setDoc(ref, g);
  const otherUid = (g: GameState) =>
    g.playerOrder.find((u) => u !== myUid) as string;

  // ---- Lobby: create a fresh game ----
  const startGame = () => {
    save({
      status: "collecting_rules",
      createdBy: myUid,
      playerOrder: [myUid, opponentUid],
      players: {
        [myUid]: { uid: myUid, name: myName, money: START_MONEY, position: 0 },
        [opponentUid]: {
          uid: opponentUid,
          name: opponentName,
          money: START_MONEY,
          position: 0,
        },
      },
      rules: {},
      board: [],
      currentTurn: myUid,
      lastRoll: null,
      pending: null,
      winner: null,
      log: ["New game! Each player adds 4 rules."],
    });
    setRuleInputs(["", "", "", ""]);
  };

  const submitRules = () => {
    if (!game) return;
    const cleaned = ruleInputs.map((r) => r.trim()).filter(Boolean);
    if (cleaned.length < 4) return;
    save({
      ...game,
      rules: { ...game.rules, [myUid]: cleaned.slice(0, 4) },
      log: [...game.log, `${myName} locked in their 4 rules.`].slice(-8),
    });
  };

  // ---- When both players have submitted, the creator builds the board ----
  const builtRef = useRef(false);
  useEffect(() => {
    if (!game || game.status !== "collecting_rules") {
      builtRef.current = false;
      return;
    }
    const a = game.rules[game.playerOrder[0]];
    const b = game.rules[game.playerOrder[1]];
    if (
      a?.length === 4 &&
      b?.length === 4 &&
      myUid === game.createdBy &&
      !builtRef.current
    ) {
      builtRef.current = true;
      save({
        ...game,
        board: buildBoard(a, b),
        status: "playing",
        currentTurn: game.playerOrder[0],
        log: [...game.log, "Rules shuffled onto the board. Roll to start!"].slice(-8),
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [game]);

  // ---- Win fanfare (fires once when the game ends) ----
  const wonRef = useRef(false);
  useEffect(() => {
    if (game?.status === "ended" && !wonRef.current) {
      wonRef.current = true;
      sfx.win();
      vibe([60, 50, 60, 50, 120]);
    } else if (game?.status !== "ended") {
      wonRef.current = false;
    }
  }, [game?.status]);

  // ---- Animate a token hopping forward, one tile at a time ----
  const animateMove = (uid: string, from: number, to: number) => {
    const steps = (to - from + 20) % 20;
    if (steps === 0) {
      setDisplayPos((d) => ({ ...d, [uid]: to }));
      return;
    }
    let cur = from;
    let n = 0;
    const stepOnce = () => {
      cur = (cur + 1) % 20;
      setDisplayPos((d) => ({ ...d, [uid]: cur }));
      sfx.step();
      vibe(8);
      if (++n < steps) setTimeout(stepOnce, 250);
    };
    setTimeout(stepOnce, 150);
  };

  // Watch each player's real position; when it changes, hop the token there.
  useEffect(() => {
    if (!game?.players) return;
    Object.values(game.players).forEach((p) => {
      const prev = targetRef.current[p.uid];
      if (prev === undefined) {
        targetRef.current[p.uid] = p.position;
        setDisplayPos((d) => ({ ...d, [p.uid]: p.position }));
      } else if (prev !== p.position) {
        targetRef.current[p.uid] = p.position;
        animateMove(p.uid, prev, p.position);
      }
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [game]);

  // ---- Roll button: spin the dice for fun, then actually roll ----
  const roll = () => {
    if (!game || game.status !== "playing") return;
    if (game.currentTurn !== myUid || game.pending || rolling) return;
    setRolling(true);
    sfx.dice();
    vibe(40);
    let ticks = 0;
    const spin = setInterval(() => {
      setFakeDie(1 + Math.floor(Math.random() * 6));
      if (++ticks > 8) {
        clearInterval(spin);
        setRolling(false);
        doRoll();
      }
    }, 70);
  };

  // ---- Resolve the tile after the dice settles ----
  const doRoll = () => {
    if (!game || game.status !== "playing") return;
    if (game.currentTurn !== myUid || game.pending) return;

    const die = 1 + Math.floor(Math.random() * 6);
    const oppUid = otherUid(game);
    const players = { ...game.players };
    const me = { ...players[myUid] };
    const opp = { ...players[oppUid] };
    const board = game.board.map((x) => ({ ...x }));
    const log = [...game.log];

    const prev = me.position;
    if (prev + die >= 20) {
      me.money += PASS_GO;
      log.push(`${me.name} passed GO (+$${PASS_GO})`);
    }
    me.position = (prev + die) % 20;
    const tile = board[me.position];
    log.push(`${me.name} rolled ${die} → ${tile.name}`);

    let pending: Pending | null = null;
    let nextTurn = oppUid;

    if (tile.type === "TAX") {
      me.money -= TAX_AMOUNT;
      log.push(`${me.name} paid $${TAX_AMOUNT} tax`);
      sfx.pay();
      vibe([30, 40, 30]);
    } else if (tile.type === "PROPERTY") {
      if (!tile.owner) {
        pending = { type: "buy", tileIndex: me.position };
        nextTurn = myUid; // stay to decide buy/skip
      } else if (tile.owner === oppUid) {
        const rent = tile.rent ?? 0;
        me.money -= rent;
        opp.money += rent;
        log.push(`${me.name} paid $${rent} rent to ${opp.name}`);
        sfx.pay();
        vibe([30, 40, 30]);
      }
    } else if (tile.type === "RULE") {
      pending = { type: "rule", tileIndex: me.position };
      nextTurn = myUid; // stay to read the rule
      sfx.rule();
      vibe([20, 30, 20, 30]);
    }

    players[myUid] = me;
    players[oppUid] = opp;

    let status: GameState["status"] = game.status;
    let winner: string | null = game.winner;
    if (me.money < 0) {
      status = "ended";
      winner = oppUid;
      pending = null;
      nextTurn = myUid;
      log.push(`${me.name} went bankrupt — ${opp.name} wins! 🎉`);
    }

    save({
      ...game,
      players,
      board,
      lastRoll: die,
      pending,
      currentTurn: nextTurn,
      status,
      winner,
      log: log.slice(-8),
    });
  };

  const decideBuy = (buy: boolean) => {
    if (!game?.pending || game.pending.type !== "buy") return;
    if (game.currentTurn !== myUid) return;
    const oppUid = otherUid(game);
    const players = { ...game.players };
    const me = { ...players[myUid] };
    const board = game.board.map((x) => ({ ...x }));
    const tile = board[game.pending.tileIndex];
    const log = [...game.log];

    if (buy) {
      me.money -= tile.price ?? 0;
      tile.owner = myUid;
      log.push(`${me.name} bought ${tile.name} for $${tile.price}`);
      sfx.buy();
      vibe([20, 30, 50]);
    } else {
      log.push(`${me.name} skipped ${tile.name}`);
    }
    players[myUid] = me;

    let status: GameState["status"] = game.status;
    let winner: string | null = game.winner;
    let nextTurn = oppUid;
    if (me.money < 0) {
      status = "ended";
      winner = oppUid;
      nextTurn = myUid;
      log.push(`${me.name} went bankrupt — wins reversed! 🎉`);
    }
    save({
      ...game,
      players,
      board,
      pending: null,
      currentTurn: nextTurn,
      status,
      winner,
      log: log.slice(-8),
    });
  };

  const acknowledgeRule = () => {
    if (!game?.pending || game.pending.type !== "rule") return;
    if (game.currentTurn !== myUid) return;
    save({ ...game, pending: null, currentTurn: otherUid(game), log: game.log.slice(-8) });
  };

  // ---- Rendering ----
  if (!loaded) {
    return (
      <div className="h-full w-full flex items-center justify-center text-gray-400">
        Loading game…
      </div>
    );
  }

  const colorFor = (uid: string) =>
    game && game.playerOrder[0] === uid ? "bg-primary" : "bg-amber-500";

  const Header = (
    <div className="flex items-center gap-2 px-4 py-3 bg-light-bg border-b border-gray-200 shrink-0">
      <button
        onClick={onClose}
        className="flex items-center gap-1.5 pl-1.5 pr-3 py-1.5 rounded-lg text-sm text-gray-600 hover:bg-gray-200 transition-colors cursor-pointer"
        title="Back to chat"
      >
        <ArrowBackIcon fontSize="small" />
        <span>Back to chat</span>
      </button>
      <div className="flex-1" />
      <CasinoIcon className="text-primary" />
      <h3 className="font-semibold text-gray-800">Monopoly</h3>
    </div>
  );

  // Lobby — no game yet
  if (!game) {
    return (
      <div className="h-full w-full flex flex-col bg-white">
        {Header}
        <div className="flex-1 flex flex-col items-center justify-center gap-4 p-6 text-center">
          <CasinoIcon style={{ fontSize: 56 }} className="text-primary" />
          <p className="text-gray-600 max-w-xs">
            Play a quick 2-player Monopoly with{" "}
            <span className="font-semibold">{opponentName}</span>. You'll each
            add 4 custom rules that get shuffled onto the board.
          </p>
          <button
            onClick={startGame}
            className="px-5 py-2.5 bg-primary text-white rounded-lg hover:bg-black transition-colors cursor-pointer"
          >
            Start a new game
          </button>
        </div>
      </div>
    );
  }

  const myRulesDone = game.rules[myUid]?.length === 4;
  const oppRulesDone = game.rules[opponentUid]?.length === 4;

  // Rule-collection phase
  if (game.status === "collecting_rules") {
    return (
      <div className="h-full w-full flex flex-col bg-white">
        {Header}
        <div className="flex-1 overflow-y-auto p-5">
          <h4 className="font-semibold text-gray-800 mb-1">Add your 4 rules</h4>
          <p className="text-sm text-gray-500 mb-4">
            Anything goes — they'll be shuffled randomly onto the board. When a
            player lands on a rule tile, the rule pops up.
          </p>

          {myRulesDone ? (
            <div className="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">
              ✓ Your rules are in. Waiting for {opponentName}…
            </div>
          ) : (
            <div className="flex flex-col gap-2">
              {ruleInputs.map((val, i) => (
                <input
                  key={i}
                  value={val}
                  onChange={(e) => {
                    const next = [...ruleInputs];
                    next[i] = e.target.value;
                    setRuleInputs(next);
                  }}
                  placeholder={`Rule ${i + 1} (e.g. "Pay $50 to the other player")`}
                  className="w-full border border-[#ddd] rounded-lg p-2 text-sm outline-primary focus-within:outline-2 bg-white"
                />
              ))}
              <button
                onClick={submitRules}
                disabled={ruleInputs.filter((r) => r.trim()).length < 4}
                className="mt-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-black transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Lock in my rules
              </button>
            </div>
          )}

          <div className="mt-5 text-sm text-gray-500">
            {opponentName}: {oppRulesDone ? "✓ ready" : "still adding rules…"}
          </div>
        </div>
      </div>
    );
  }

  // Playing / ended
  const me = game.players[myUid];
  const opp = game.players[opponentUid];
  const myTurn = game.currentTurn === myUid && game.status === "playing";
  const turnName = game.players[game.currentTurn]?.name ?? "";
  const pendingTile =
    game.pending != null ? game.board[game.pending.tileIndex] : null;

  return (
    <div className="h-full w-full flex flex-col bg-white">
      {Header}

      {/* Players / money */}
      <div className="flex gap-2 px-3 py-2 border-b border-gray-100 shrink-0">
        {[me, opp].map(
          (p) =>
            p && (
              <div
                key={p.uid}
                className={`flex-1 rounded-lg p-2 text-sm border ${
                  game.currentTurn === p.uid
                    ? "border-primary bg-primary/5"
                    : "border-gray-200"
                }`}
              >
                <div className="flex items-center gap-1.5">
                  <span className={`h-2.5 w-2.5 rounded-full ${colorFor(p.uid)}`} />
                  <span className="font-semibold text-gray-800 truncate">
                    {p.uid === myUid ? "You" : p.name}
                  </span>
                </div>
                <div className="text-gray-600">${p.money}</div>
              </div>
            )
        )}
      </div>

      <div className="flex-1 overflow-y-auto p-3">
        {/* Board ring */}
        <div
          className="relative grid gap-1 mx-auto aspect-square w-full max-w-md"
          style={{
            gridTemplateColumns: "repeat(6, 1fr)",
            gridTemplateRows: "repeat(6, 1fr)",
          }}
        >
          {game.board.map((tile, i) => {
            // highlight the tile a token is currently sitting on
            const occupied = Object.keys(game.players).some(
              (uid) => (displayPos[uid] ?? game.players[uid].position) === i
            );
            return (
              <div
                key={i}
                style={{ gridRow: COORDS[i].r, gridColumn: COORDS[i].c }}
                className={`relative rounded-md border text-[9px] leading-tight p-1 overflow-hidden transition-all ${
                  occupied ? "ring-2 ring-primary/60 scale-[1.04] z-10" : ""
                } ${
                  tile.type === "RULE"
                    ? "bg-purple-50 border-purple-200"
                    : tile.type === "GO"
                    ? "bg-green-50 border-green-300"
                    : tile.type === "TAX"
                    ? "bg-red-50 border-red-200"
                    : "bg-gray-50 border-gray-200"
                }`}
                title={tile.type === "RULE" ? tile.ruleText ?? "" : tile.name}
              >
                {tile.owner && (
                  <span
                    className={`absolute top-0 left-0 h-full w-1 ${colorFor(
                      tile.owner
                    )}`}
                  />
                )}
                <div className="font-semibold text-gray-700 truncate">
                  {tile.type === "RULE" ? "★ Rule" : tile.name}
                </div>
                {tile.type === "PROPERTY" && (
                  <div className="text-gray-400">${tile.price}</div>
                )}
              </div>
            );
          })}

          {/* animated player tokens (hop tile-by-tile) */}
          <div className="absolute inset-0 pointer-events-none">
            {game.playerOrder.map((uid, idx) => {
              const p = game.players[uid];
              if (!p) return null;
              const pos = displayPos[uid] ?? p.position;
              const { r, c } = COORDS[pos];
              const left = ((c - 0.5) / 6) * 100;
              const top = ((r - 0.5) / 6) * 100;
              const dx = idx === 0 ? -9 : 9; // separate two tokens on one tile
              return (
                <div
                  key={uid}
                  className={`absolute h-7 w-7 rounded-full border-2 border-white ring-2 ring-black/20 shadow-lg flex items-center justify-center text-white text-xs font-bold ${colorFor(
                    uid
                  )} transition-all duration-200 ease-in-out`}
                  style={{
                    left: `${left}%`,
                    top: `${top}%`,
                    transform: `translate(calc(-50% + ${dx}px), -50%)`,
                    zIndex: 20,
                  }}
                  title={p.uid === myUid ? "You" : p.name}
                >
                  {(p.uid === myUid ? "You" : p.name)?.[0]?.toUpperCase() ?? "?"}
                </div>
              );
            })}
          </div>

          {/* center: dice + turn */}
          <div
            style={{ gridRow: "2 / 6", gridColumn: "2 / 6" }}
            className="flex flex-col items-center justify-center gap-2 text-center"
          >
            {game.status === "ended" ? (
              <>
                <div className="text-2xl">🏆</div>
                <div className="font-bold text-gray-800">
                  {game.winner === myUid ? "You win!" : `${opp?.name} wins!`}
                </div>
                <button
                  onClick={startGame}
                  className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-black transition-colors cursor-pointer text-sm"
                >
                  Play again
                </button>
              </>
            ) : (
              <>
                <div
                  className={`text-4xl font-bold text-primary transition-transform ${
                    rolling ? "animate-spin" : ""
                  }`}
                >
                  🎲
                </div>
                <div className="text-lg font-bold text-gray-700 -mt-1">
                  {rolling ? fakeDie : game.lastRoll ?? ""}
                </div>
                <div className="text-xs text-gray-500">
                  {myTurn ? "Your turn" : `${turnName}'s turn`}
                </div>
                {myTurn && !game.pending && (
                  <button
                    onClick={roll}
                    disabled={rolling}
                    className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-black transition-colors cursor-pointer text-sm disabled:opacity-60 active:scale-95"
                  >
                    {rolling ? "Rolling…" : "Roll dice"}
                  </button>
                )}
              </>
            )}
          </div>
        </div>

        {/* Pending action */}
        {game.pending && game.currentTurn === myUid && pendingTile && (
          <div className="mt-3 rounded-lg border border-primary/30 bg-primary/5 p-3">
            {game.pending.type === "buy" ? (
              <>
                <p className="text-sm text-gray-700 mb-2">
                  Buy <b>{pendingTile.name}</b> for{" "}
                  <b>${pendingTile.price}</b>?
                </p>
                <div className="flex gap-2">
                  <button
                    onClick={() => decideBuy(true)}
                    className="px-3 py-1.5 bg-primary text-white rounded-lg text-sm cursor-pointer hover:bg-black"
                  >
                    Buy
                  </button>
                  <button
                    onClick={() => decideBuy(false)}
                    className="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-sm cursor-pointer hover:bg-gray-200"
                  >
                    Skip
                  </button>
                </div>
              </>
            ) : (
              <>
                <p className="text-xs font-semibold text-primary uppercase tracking-wide mb-1">
                  ★ House rule
                </p>
                <p className="text-sm text-gray-800 mb-2">
                  {pendingTile.ruleText}
                </p>
                <button
                  onClick={acknowledgeRule}
                  className="px-3 py-1.5 bg-primary text-white rounded-lg text-sm cursor-pointer hover:bg-black"
                >
                  Done
                </button>
              </>
            )}
          </div>
        )}

        {/* Log */}
        <div className="mt-3 rounded-lg bg-gray-50 border border-gray-100 p-2 text-xs text-gray-500 space-y-0.5">
          {game.log.slice(-6).map((line, i) => (
            <div key={i}>{line}</div>
          ))}
        </div>
      </div>
    </div>
  );
}
