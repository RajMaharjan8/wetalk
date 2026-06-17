import { useEffect, useRef, useState } from "react";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import CasinoIcon from "@mui/icons-material/Casino";
import RestartAltIcon from "@mui/icons-material/RestartAlt";
import {
  BOARD_SIZE,
  CHANCE_CARDS,
  COORDS,
  DARES_PER_MODE,
  FREE_PARKING_BONUS,
  GROUP_COLOR,
  JAIL_INDEX,
  MAX_HOUSES,
  PASS_GO,
  TASK_PENALTY,
  TASK_REWARD,
  TAX_AMOUNT,
  buildBoard,
  defaultConfig,
  rentFor,
  reshuffleDares,
  sellValueFor,
  sfx,
  sideOf,
  stakeMultiplier,
  targetWinner,
  tileLabel,
  vibe,
  type DareRule,
  type GameConfig,
  type GameMode,
  type GameState,
  type Pending,
} from "./monopolyCore";
import {
  DareEditor,
  GameSettingsForm,
  ModePicker,
  OwnedProperty,
  freshDares,
  type DareOpts,
} from "./monopolySetup";

// ---- Offline "pass-and-play" Monopoly ----
// Same board, rules, money, and sounds as the online MonopolyGame, but for two
// people sharing ONE device. State lives in local React state — no Firestore.
//
// Because both players use the same screen, dare lists must stay secret, so the
// flow is gated by "handoff" screens: a neutral "pass the device to X" gate
// that the next player taps through before they see anything. Handoffs appear
// (1) between the two players entering their dares, (2) at the start of each
// turn, and (3) when a dare needs the OTHER player to confirm it.

// The two local players. p0 always goes first.
const P0 = "p0";
const P1 = "p1";

interface Props {
  onClose: () => void;
}

// What the current handoff gate is for — drives its message + what comes next.
type Handoff =
  | { kind: "rules"; player: string } // hand to `player` to enter their dares
  | { kind: "turn"; player: string } // hand to `player` to take their turn
  | { kind: "judge"; player: string } // hand to `player` to judge a dare
  | null;

export default function MonopolyOffline({ onClose }: Props) {
  const [names, setNames] = useState<[string, string]>(["Player 1", "Player 2"]);
  // Lobby steps: "names" → "mode" → ("settings" for classic) → game begins.
  const [setupStep, setSetupStep] = useState<"names" | "mode" | "settings">(
    "names"
  );
  const [chosenMode, setChosenMode] = useState<GameMode>("classic");
  const [chosenOpts, setChosenOpts] = useState<DareOpts>({
    shuffle: "off",
    revealDares: true,
    targetScore: null,
    risingStakes: false,
    doubleOrNothing: false,
  });
  const [setupDone, setSetupDone] = useState(false);

  const [game, setGame] = useState<GameState | null>(null);
  const [ruleInputs, setRuleInputs] = useState<DareRule[]>(freshDares);
  const [handoff, setHandoff] = useState<Handoff>(null);
  const [confirmReset, setConfirmReset] = useState(false);

  // Animation: where each token is *displayed* (hops toward real position).
  const [displayPos, setDisplayPos] = useState<Record<string, number>>({});
  const [rolling, setRolling] = useState(false);
  const [fakeDie, setFakeDie] = useState(2);
  const [flash, setFlash] = useState<{ text: string; good: boolean } | null>(
    null
  );
  const targetRef = useRef<Record<string, number>>({});

  const nameOf = (uid: string) => game?.players[uid]?.name ?? "Player";
  const other = (uid: string) => (uid === P0 ? P1 : P0);

  const showFlash = (text: string, good: boolean) => {
    setFlash({ text, good });
    setTimeout(() => setFlash(null), 1400);
  };

  // ---- Create a fresh game (with the chosen config) and kick off rule
  // collection for player 1 ----
  const startGame = (mode: GameMode, config: GameConfig, dareOpts: DareOpts) => {
    const dareCount = DARES_PER_MODE[mode];
    const fullConfig: GameConfig =
      mode === "dares"
        ? {
            ...config,
            targetScore: dareOpts.targetScore,
            risingStakes: dareOpts.risingStakes,
            doubleOrNothing: dareOpts.doubleOrNothing,
          }
        : config;
    setGame({
      status: "collecting_rules",
      mode,
      shuffle: mode === "dares" ? dareOpts.shuffle : "off",
      revealDares: dareOpts.revealDares,
      pot: 0,
      laps: 0,
      createdBy: P0,
      playerOrder: [P0, P1],
      players: {
        [P0]: {
          uid: P0,
          name: names[0] || "Player 1",
          money: fullConfig.startMoney,
          position: 0,
          jailed: false,
        },
        [P1]: {
          uid: P1,
          name: names[1] || "Player 2",
          money: fullConfig.startMoney,
          position: 0,
          jailed: false,
        },
      },
      rules: {},
      config: fullConfig,
      board: [],
      currentTurn: P0,
      lastRoll: null,
      pending: null,
      winner: null,
      log: [`New game! Each player writes ${dareCount} dares.`],
    });
    setRuleInputs(freshDares(dareCount));
    setDisplayPos({ [P0]: 0, [P1]: 0 });
    targetRef.current = { [P0]: 0, [P1]: 0 };
    setConfirmReset(false);
    setSetupDone(true);
    // First handoff: pass the device to player 1 to enter their dares.
    setHandoff({ kind: "rules", player: P0 });
  };

  // Whose dares are we currently collecting? (the rules handoff target, once
  // tapped through) — tracked separately so we know who "owns" the inputs.
  const [rulesFor, setRulesFor] = useState<string | null>(null);

  const submitRules = () => {
    if (!game || !rulesFor) return;
    const need = DARES_PER_MODE[game.mode];
    const cleaned: DareRule[] = ruleInputs
      .map((r) => ({
        text: r.text.trim(),
        reward: Math.max(0, Math.round(r.reward) || 0),
        penalty: Math.max(0, Math.round(r.penalty) || 0),
      }))
      .filter((r) => r.text);
    if (cleaned.length < need) return;
    const rules = {
      ...game.rules,
      [rulesFor]: cleaned.slice(0, need),
    };
    const log = [
      ...game.log,
      `${nameOf(rulesFor)} locked in their ${need} dares.`,
    ].slice(-8);

    // Player 1 just finished → hand off to player 2 for their dares.
    if (rulesFor === P0) {
      setGame({ ...game, rules, log });
      setRuleInputs(freshDares(need));
      setRulesFor(null);
      setHandoff({ kind: "rules", player: P1 });
      return;
    }

    // Both done → build the board and start play with player 1's turn.
    const board = buildBoard(
      rules[P0] ?? [],
      rules[P1] ?? [],
      P0,
      P1,
      game.config,
      game.mode
    );
    setGame({
      ...game,
      rules,
      board,
      status: "playing",
      currentTurn: P0,
      log: [...log, "Dares shuffled onto the board. Roll to start!"].slice(-8),
    });
    setRulesFor(null);
    setHandoff({ kind: "turn", player: P0 });
  };

  // ---- Win fanfare ----
  const wonRef = useRef(false);
  useEffect(() => {
    if (game?.status === "ended" && !wonRef.current) {
      wonRef.current = true;
      sfx.win();
      vibe([60, 50, 60, 50, 120, 60, 200]);
    } else if (game?.status !== "ended") {
      wonRef.current = false;
    }
  }, [game?.status]);

  // ---- Animate a token hopping forward, one tile at a time ----
  const animateMove = (uid: string, from: number, to: number) => {
    const steps = (to - from + BOARD_SIZE) % BOARD_SIZE;
    if (steps === 0) {
      setDisplayPos((d) => ({ ...d, [uid]: to }));
      return;
    }
    let cur = from;
    let n = 0;
    const stepOnce = () => {
      cur = (cur + 1) % BOARD_SIZE;
      setDisplayPos((d) => ({ ...d, [uid]: cur }));
      sfx.step();
      vibe(8);
      if (++n < steps) setTimeout(stepOnce, 200);
    };
    setTimeout(stepOnce, 150);
  };

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

  // ---- Roll: spin the dice, then resolve ----
  const roll = () => {
    if (!game || game.status !== "playing" || game.pending || rolling) return;
    setRolling(true);
    sfx.dice();
    vibe(40);
    let ticks = 0;
    const spin = setInterval(() => {
      setFakeDie(2 + Math.floor(Math.random() * 11));
      if (++ticks > 8) {
        clearInterval(spin);
        setRolling(false);
        doRoll();
      }
    }, 70);
  };

  const doRoll = () => {
    if (!game || game.status !== "playing" || game.pending) return;

    const meUid = game.currentTurn;
    const oppUid = other(meUid);
    const players = { ...game.players };
    const me = { ...players[meUid] };
    const opp = { ...players[oppUid] };
    // Auto-shuffle: "roll" re-deals before every roll ("lap" handled below).
    let board =
      game.shuffle === "roll"
        ? reshuffleDares(game.board)
        : game.board.map((x) => ({ ...x }));
    const log = [...game.log];

    // Jailed? Lose this roll, then you're free — turn passes.
    if (me.jailed) {
      me.jailed = false;
      players[meUid] = me;
      log.push(`${me.name} sits out a turn in Jail.`);
      setGame({
        ...game,
        players,
        board,
        lastRoll: null,
        pending: null,
        currentTurn: oppUid,
        log: log.slice(-8),
      });
      setHandoff({ kind: "turn", player: oppUid });
      return;
    }

    const die = 2 + Math.floor(Math.random() * 11);
    const passGo = game.config?.passGo ?? PASS_GO;
    let laps = game.laps;
    const prev = me.position;
    if (prev + die >= BOARD_SIZE) {
      me.money += passGo;
      laps += 1;
      log.push(`${me.name} passed GO (+$${passGo})`);
      showFlash(`+$${passGo} GO`, true);
      if (game.shuffle === "lap") board = reshuffleDares(board);
    }
    me.position = (prev + die) % BOARD_SIZE;
    let tile = board[me.position];
    log.push(`${me.name} rolled ${die} → ${tile.name}`);

    let pending: Pending | null = null;
    let nextTurn = oppUid;
    let nextHandoff: Handoff = { kind: "turn", player: oppUid };

    if (tile.type === "TAX") {
      me.money -= TAX_AMOUNT;
      log.push(`${me.name} paid $${TAX_AMOUNT} ${tile.name.toLowerCase()}`);
      sfx.pay();
      vibe([30, 40, 30]);
      showFlash(`-$${TAX_AMOUNT}`, false);
    } else if (tile.type === "FREE_PARKING") {
      me.money += FREE_PARKING_BONUS;
      log.push(`${me.name} hit Free Parking (+$${FREE_PARKING_BONUS})`);
      sfx.cheer();
      vibe([20, 30, 40]);
      showFlash(`+$${FREE_PARKING_BONUS}`, true);
    } else if (tile.type === "GOTO_JAIL") {
      me.position = JAIL_INDEX;
      me.jailed = true;
      log.push(`${me.name} was sent to Jail!`);
      sfx.jail();
      vibe([60, 40, 60, 40, 120]);
      showFlash("Off to Jail", false);
      tile = board[JAIL_INDEX];
    } else if (tile.type === "CHANCE") {
      const card = CHANCE_CARDS[Math.floor(Math.random() * CHANCE_CARDS.length)];
      me.money += card.delta;
      pending = { type: "chance", tileIndex: me.position, chanceText: card.text };
      nextTurn = meUid; // stay to read the card
      nextHandoff = null;
      card.delta >= 0 ? sfx.cheer() : sfx.boo();
      vibe([20, 30, 20]);
      showFlash(
        `${card.delta >= 0 ? "+" : "-"}$${Math.abs(card.delta)}`,
        card.delta >= 0
      );
    } else if (tile.type === "PROPERTY") {
      if (!tile.owner) {
        pending = { type: "buy", tileIndex: me.position };
        nextTurn = meUid; // stay to decide buy/skip
        nextHandoff = null;
      } else if (tile.owner === oppUid) {
        const rent = rentFor(tile);
        me.money -= rent;
        opp.money += rent;
        const lvl = tile.houses ? ` (lvl ${tile.houses})` : "";
        log.push(`${me.name} paid $${rent} rent to ${opp.name}${lvl}`);
        sfx.pay();
        vibe([30, 40, 30]);
        showFlash(`-$${rent} rent`, false);
      }
    } else if (tile.type === "TASK") {
      // Lander performs the dare; the game freezes until the OTHER player
      // judges it. The lander keeps the turn while performing.
      pending = { type: "task", tileIndex: me.position, claimedDone: false };
      nextTurn = meUid;
      nextHandoff = null;
      sfx.rule();
      vibe([20, 30, 20, 30]);
    }

    players[meUid] = me;
    players[oppUid] = opp;

    let status: GameState["status"] = game.status;
    let winner: string | null = game.winner;
    if (me.money < 0) {
      status = "ended";
      winner = oppUid;
      pending = null;
      nextHandoff = null;
      log.push(`${me.name} went bankrupt — ${opp.name} wins!`);
    }

    const settled = applyTarget({
      ...game,
      players,
      board,
      laps,
      lastRoll: die,
      pending,
      currentTurn: nextTurn,
      status,
      winner,
      log: log.slice(-8),
    });
    setGame(settled);
    // If the target ended the game, no handoff.
    if (nextHandoff && settled.status === "playing") setHandoff(nextHandoff);
  };

  // End the game if anyone reached the target score. Run on money changes.
  const applyTarget = (g: GameState): GameState => {
    if (g.status !== "playing") return g;
    const w = targetWinner(g);
    if (!w) return g;
    return {
      ...g,
      status: "ended",
      winner: w,
      pending: null,
      log: [
        ...g.log,
        `${g.players[w].name} hit the $${g.config.targetScore} target — wins!`,
      ].slice(-8),
    };
  };

  const decideBuy = (buy: boolean) => {
    if (!game?.pending || game.pending.type !== "buy") return;
    const meUid = game.currentTurn;
    const oppUid = other(meUid);
    const players = { ...game.players };
    const me = { ...players[meUid] };
    const board = game.board.map((x) => ({ ...x }));
    const tile = board[game.pending.tileIndex];
    const log = [...game.log];

    if (buy) {
      me.money -= tile.price ?? 0;
      tile.owner = meUid;
      log.push(`${me.name} bought ${tile.name} for $${tile.price}`);
      sfx.buy();
      vibe([20, 30, 50]);
      showFlash(`-$${tile.price}`, false);
    } else {
      log.push(`${me.name} skipped ${tile.name}`);
    }
    players[meUid] = me;

    let status: GameState["status"] = game.status;
    let winner: string | null = game.winner;
    if (me.money < 0) {
      status = "ended";
      winner = oppUid;
      log.push(`${me.name} went bankrupt — ${players[oppUid].name} wins!`);
    }
    const settled = applyTarget({
      ...game,
      players,
      board,
      pending: null,
      currentTurn: status === "ended" ? meUid : oppUid,
      status,
      winner,
      log: log.slice(-8),
    });
    setGame(settled);
    if (settled.status !== "ended")
      setHandoff({ kind: "turn", player: oppUid });
  };

  const acknowledgeChance = () => {
    if (!game?.pending || game.pending.type !== "chance") return;
    const meUid = game.currentTurn;
    const oppUid = other(meUid);
    setGame({
      ...game,
      pending: null,
      currentTurn: oppUid,
      log: game.log.slice(-8),
    });
    setHandoff({ kind: "turn", player: oppUid });
  };

  // ---- Task flow ----
  // Lander, after doing the dare, taps "I did it" → hand to the OTHER player
  // to judge. Game stays frozen until they decide.
  const claimTaskDone = (double = false) => {
    if (!game?.pending || game.pending.type !== "task") return;
    const meUid = game.currentTurn;
    setGame({
      ...game,
      pending: { ...game.pending, claimedDone: true, doubled: double },
      log: [
        ...game.log,
        `${nameOf(meUid)} says they did the dare${
          double ? " (double-or-nothing!)" : ""
        } — ${nameOf(other(meUid))} to confirm…`,
      ].slice(-8),
    });
    setHandoff({ kind: "judge", player: other(meUid) });
  };

  // The OTHER player judges. Yes → lander rewarded; No → penalty. Turn then
  // passes to the judge (the opponent of the lander).
  const judgeTask = (didIt: boolean) => {
    if (!game?.pending || game.pending.type !== "task") return;
    const lander = game.currentTurn;
    const judge = other(lander);
    const players = { ...game.players };
    const landerP = { ...players[lander] };
    const log = [...game.log];
    let pot = game.pot;
    const dareTile = game.board[game.pending.tileIndex];
    const mult =
      stakeMultiplier(game.laps, game.config.risingStakes) *
      (game.pending.doubled ? 2 : 1);
    const reward = Math.round((dareTile.ruleReward ?? TASK_REWARD) * mult);
    const penalty = Math.round((dareTile.rulePenalty ?? TASK_PENALTY) * mult);

    if (didIt) {
      landerP.money += reward + pot;
      const potMsg = pot > 0 ? ` + $${pot} pot` : "";
      log.push(
        `${nameOf(judge)} confirmed ${landerP.name} did it (+$${reward}${potMsg})`
      );
      sfx.cheer();
      vibe([20, 30, 40]);
      showFlash(`+$${reward + pot}`, true);
      pot = 0;
    } else {
      landerP.money -= penalty;
      pot += penalty;
      log.push(
        `${nameOf(judge)} says ${landerP.name} flaked — $${penalty} into the pot`
      );
      sfx.boo();
      vibe([60, 40, 60]);
      showFlash(`-$${penalty}`, false);
    }
    players[lander] = landerP;

    let status: GameState["status"] = game.status;
    let winner: string | null = game.winner;
    if (landerP.money < 0) {
      status = "ended";
      winner = judge;
      log.push(`${landerP.name} went bankrupt — ${nameOf(judge)} wins!`);
    }
    const settled = applyTarget({
      ...game,
      players,
      pot,
      pending: null,
      currentTurn: status === "ended" ? lander : judge,
      status,
      winner,
      log: log.slice(-8),
    });
    setGame(settled);
    // Turn now belongs to the judge — they're already holding the device.
    if (settled.status !== "ended") setHandoff({ kind: "turn", player: judge });
  };

  const buildHouse = (tileIndex: number) => {
    if (!game || game.status !== "playing" || game.pending) return;
    const meUid = game.currentTurn;
    const board = game.board.map((x) => ({ ...x }));
    const tile = board[tileIndex];
    if (tile.type !== "PROPERTY" || tile.owner !== meUid) return;
    if (tile.houses >= MAX_HOUSES) return;
    const players = { ...game.players };
    const me = { ...players[meUid] };
    const cost = tile.houseCost ?? 0;
    if (me.money < cost) return;
    me.money -= cost;
    tile.houses += 1;
    players[meUid] = me;
    sfx.build();
    vibe([20, 30, 20]);
    showFlash(`-$${cost}`, false);
    setGame({
      ...game,
      players,
      board,
      log: [
        ...game.log,
        `${me.name} built on ${tile.name} (lvl ${tile.houses}, rent now $${rentFor(
          tile
        )})`,
      ].slice(-8),
    });
  };

  const sellProperty = (tileIndex: number) => {
    if (!game || game.status !== "playing" || game.pending) return;
    const meUid = game.currentTurn;
    const board = game.board.map((x) => ({ ...x }));
    const tile = board[tileIndex];
    if (tile.type !== "PROPERTY" || tile.owner !== meUid) return;
    const players = { ...game.players };
    const me = { ...players[meUid] };
    const refund = sellValueFor(tile);
    me.money += refund;
    tile.owner = null;
    tile.houses = 0;
    tile.sellValue = null;
    players[meUid] = me;
    sfx.buy();
    vibe([20, 30]);
    showFlash(`+$${refund}`, true);
    setGame({
      ...game,
      players,
      board,
      log: [...game.log, `${me.name} sold ${tile.name} for $${refund}`].slice(-8),
    });
  };

  // Re-price a property the current player owns: rent and/or sell value.
  const repriceProperty = (
    tileIndex: number,
    rent: number,
    sellValue: number
  ) => {
    if (!game || game.status !== "playing" || game.pending) return;
    const meUid = game.currentTurn;
    const board = game.board.map((x) => ({ ...x }));
    const tile = board[tileIndex];
    if (tile.type !== "PROPERTY" || tile.owner !== meUid) return;
    tile.baseRent = Math.max(0, Math.round(rent / (1 + tile.houses)) || 0);
    tile.sellValue = Math.max(0, Math.round(sellValue) || 0);
    sfx.build();
    vibe([15, 25]);
    setGame({
      ...game,
      board,
      log: [
        ...game.log,
        `${nameOf(meUid)} re-priced ${tile.name}: rent $${rentFor(
          tile
        )}, sells for $${sellValueFor(tile)}`,
      ].slice(-8),
    });
  };

  // ---- Rendering ----
  const tokenColor = (uid: string) =>
    uid === P0 ? "bg-emerald-800" : "bg-amber-600";

  const Header = (
    <div className="flex items-center gap-2 px-4 py-3 bg-stone-900 text-stone-100 shrink-0">
      <button
        onClick={onClose}
        className="flex items-center gap-1.5 pl-1.5 pr-3 py-1.5 rounded-md text-sm text-stone-300 hover:bg-stone-800 transition-colors cursor-pointer"
        title="Back to chat"
      >
        <ArrowBackIcon fontSize="small" />
        <span>Back to chat</span>
      </button>
      <div className="flex-1" />
      {game && (
        <button
          onClick={() => setConfirmReset(true)}
          className="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-sm text-stone-300 hover:bg-stone-800 transition-colors cursor-pointer"
          title="Reset game"
        >
          <RestartAltIcon fontSize="small" />
          <span className="hidden sm:inline">Reset</span>
        </button>
      )}
      <CasinoIcon className="text-amber-500" fontSize="small" />
      <h3 className="font-serif font-semibold tracking-wide text-amber-50">
        Monopoly · Offline
      </h3>
    </div>
  );

  const ResetBar = confirmReset && (
    <div className="flex items-center gap-3 px-4 py-3 bg-stone-100 dark:bg-stone-800 border-b border-stone-200 dark:border-stone-700 shrink-0">
      <span className="text-sm text-stone-700 dark:text-stone-200 flex-1">
        Start a brand-new game? Both players write new dares.
      </span>
      <button
        onClick={() => setConfirmReset(false)}
        className="px-3 py-1.5 text-sm text-stone-600 dark:text-stone-300 rounded-md hover:bg-stone-200 cursor-pointer"
      >
        Cancel
      </button>
      <button
        onClick={() => {
          setConfirmReset(false);
          setSetupDone(false);
          setSetupStep("names");
          setGame(null);
          setHandoff(null);
        }}
        className="px-3 py-1.5 text-sm bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 cursor-pointer"
      >
        New game
      </button>
    </div>
  );

  // ---- Setup step 1: enter the two players' names ----
  if (!setupDone && setupStep === "names") {
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
        {Header}
        <div className="flex-1 flex flex-col items-center justify-center gap-5 p-8 text-center">
          <div className="h-16 w-16 rounded-full bg-stone-900 flex items-center justify-center">
            <CasinoIcon style={{ fontSize: 34 }} className="text-amber-500" />
          </div>
          <h2 className="font-serif text-2xl text-stone-800 dark:text-stone-100">Pass &amp; Play</h2>
          <p className="text-stone-500 dark:text-stone-400 max-w-xs leading-relaxed">
            Two players, one device. Pick a mode, each secretly write your
            dares, then take turns — the device asks you to hand it over so
            nobody peeks.
          </p>
          <div className="w-full max-w-xs flex flex-col gap-2.5">
            <input
              value={names[0]}
              onChange={(e) => setNames([e.target.value, names[1]])}
              placeholder="Player 1 name"
              className="w-full border border-stone-300 dark:border-stone-700 rounded-md p-2.5 text-sm outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
            />
            <input
              value={names[1]}
              onChange={(e) => setNames([names[0], e.target.value])}
              placeholder="Player 2 name"
              className="w-full border border-stone-300 dark:border-stone-700 rounded-md p-2.5 text-sm outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
            />
          </div>
          <button
            onClick={() => setSetupStep("mode")}
            className="px-6 py-2.5 bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 transition-colors cursor-pointer tracking-wide"
          >
            Next: choose mode
          </button>
        </div>
      </div>
    );
  }

  // ---- Setup step 2: pick the game mode ----
  if (!setupDone && setupStep === "mode") {
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
        {Header}
        <div className="flex-1 min-h-0 overflow-y-auto p-6 pb-28">
          <ModePicker
            onPick={(mode, dareOpts) => {
              setChosenMode(mode);
              setChosenOpts(dareOpts);
              // Pure Dares has no prices — start straight away.
              if (mode === "dares")
                startGame("dares", defaultConfig(), dareOpts);
              else setSetupStep("settings");
            }}
          />
        </div>
      </div>
    );
  }

  // ---- Setup step 3 (classic only): prices / money. Confirm → start. ----
  if (!setupDone && setupStep === "settings") {
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
        {Header}
        <div className="flex-1 min-h-0 overflow-y-auto p-6 pb-28">
          <GameSettingsForm
            onConfirm={(config) => startGame(chosenMode, config, chosenOpts)}
            confirmLabel="Start — Player 1 writes dares"
          />
        </div>
      </div>
    );
  }

  if (!game) {
    // Reset path cleared the game but kept us out of setup — restart cleanly.
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">{Header}</div>
    );
  }

  // ---- Handoff gate — neutral screen so the next player can take over ----
  if (handoff) {
    const label =
      handoff.kind === "rules"
        ? "to write their dares"
        : handoff.kind === "judge"
        ? "to judge the dare"
        : "to take their turn";
    const onContinue = () => {
      if (handoff.kind === "rules") {
        setRulesFor(handoff.player);
        setRuleInputs(freshDares(game ? DARES_PER_MODE[game.mode] : undefined));
      }
      setHandoff(null);
    };
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-900">
        {Header}
        <div className="flex-1 flex flex-col items-center justify-center gap-6 p-8 text-center">
          <div
            className={`h-20 w-20 rounded-full flex items-center justify-center text-amber-50 text-2xl font-serif font-semibold ${tokenColor(
              handoff.player
            )}`}
          >
            {nameOf(handoff.player)[0]?.toUpperCase() ?? "?"}
          </div>
          <div>
            <p className="text-stone-400 text-sm uppercase tracking-[0.2em] mb-1">
              Pass the device
            </p>
            <h2 className="font-serif text-2xl text-amber-50">
              {nameOf(handoff.player)}
            </h2>
            <p className="text-stone-400 mt-1">{label}</p>
          </div>
          <button
            onClick={onContinue}
            className="px-6 py-2.5 bg-amber-500 text-stone-900 rounded-md hover:bg-amber-400 transition-colors cursor-pointer tracking-wide font-medium"
          >
            I'm {nameOf(handoff.player)}
          </button>
        </div>
      </div>
    );
  }

  // ---- Rule-collection phase (for whichever player tapped through) ----
  if (game.status === "collecting_rules" && rulesFor) {
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
        {Header}
        {ResetBar}
        <div className="flex-1 min-h-0 overflow-y-auto p-6 pb-28">
          <DareEditor
            dares={ruleInputs}
            setDares={setRuleInputs}
            onSubmit={submitRules}
            submitLabel="Lock in dares"
            heading={`${nameOf(rulesFor)} — write your ${
              DARES_PER_MODE[game.mode]
            } dares`}
          />
        </div>
      </div>
    );
  }

  // ---- Playing / ended ----
  const meUid = game.currentTurn;
  const p0 = game.players[P0];
  const p1 = game.players[P1];
  const turnName = nameOf(meUid);
  const pendingTile =
    game.pending != null ? game.board[game.pending.tileIndex] : null;
  const isTask = game.pending?.type === "task";
  const pendingRuleText =
    pendingTile && pendingTile.type === "TASK" ? pendingTile.ruleText : null;

  const myProperties = game.board
    .map((tile, i) => ({ tile, i }))
    .filter(({ tile }) => tile.type === "PROPERTY" && tile.owner === meUid);

  return (
    <div className="relative h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
      {Header}
      {ResetBar}

      {flash && (
        <div className="pointer-events-none absolute inset-x-0 top-1/3 z-50 flex justify-center">
          <span
            className={`px-4 py-1.5 rounded-full text-lg font-serif font-semibold shadow-lg ${
              flash.good
                ? "bg-emerald-800 text-amber-50"
                : "bg-stone-900 text-rose-200"
            }`}
          >
            {flash.text}
          </span>
        </div>
      )}

      {/* Players / money */}
      <div className="flex gap-3 px-4 py-3 bg-stone-100 dark:bg-stone-800 border-b border-stone-200 dark:border-stone-700 shrink-0">
        {[p0, p1].map(
          (p) =>
            p && (
              <div
                key={p.uid}
                className={`flex-1 rounded-md px-3 py-2 text-sm border transition-colors ${
                  game.currentTurn === p.uid
                    ? "border-stone-800 bg-white dark:bg-stone-900 shadow-sm"
                    : "border-stone-200 dark:border-stone-700 bg-stone-50 dark:bg-stone-800"
                }`}
              >
                <div className="flex items-center gap-2">
                  <span
                    className={`h-2.5 w-2.5 rounded-full ${tokenColor(p.uid)}`}
                  />
                  <span className="font-medium text-stone-700 dark:text-stone-200 truncate">
                    {p.name}
                  </span>
                  {p.jailed && (
                    <span className="text-[10px] uppercase tracking-wide text-stone-400">
                      jailed
                    </span>
                  )}
                </div>
                <div className="font-serif text-lg text-stone-900 dark:text-stone-100">
                  ${p.money.toLocaleString()}
                </div>
              </div>
            )
        )}
      </div>

      {/* Pot / target / stakes strip */}
      {(game.pot > 0 ||
        game.config.targetScore ||
        (game.config.risingStakes && game.laps > 0)) && (
        <div className="flex items-center justify-center gap-4 px-4 py-1.5 bg-stone-900 text-amber-50 text-xs shrink-0">
          {game.pot > 0 && (
            <span>
              Pot <span className="font-serif font-semibold">${game.pot}</span>
            </span>
          )}
          {game.config.targetScore && (
            <span>
              Target{" "}
              <span className="font-serif font-semibold">
                ${game.config.targetScore.toLocaleString()}
              </span>
            </span>
          )}
          {game.config.risingStakes && game.laps > 0 && (
            <span>Stakes ×{stakeMultiplier(game.laps, true).toFixed(1)}</span>
          )}
        </div>
      )}

      <div className="flex-1 min-h-0 overflow-y-auto p-4">
        {/* Classic white Monopoly board */}
        <div
          className="relative grid mx-auto aspect-square w-full max-w-2xl bg-white border-2 border-stone-800"
          style={{
            gridTemplateColumns: "repeat(8, 1fr)",
            gridTemplateRows: "repeat(8, 1fr)",
          }}
        >
          {game.board.map((tile, i) => {
            const occupied = Object.keys(game.players).some(
              (uid) => (displayPos[uid] ?? game.players[uid].position) === i
            );
            const side = sideOf(i);
            const bandSide =
              side === "top"
                ? "bottom-0 left-0 w-full h-2.5"
                : side === "bottom"
                ? "top-0 left-0 w-full h-2.5"
                : side === "left"
                ? "right-0 top-0 h-full w-2.5"
                : "left-0 top-0 h-full w-2.5";
            const isDareTile = tile.type === "TASK";
            const showDareText =
              isDareTile && game.revealDares && game.shuffle !== "roll";
            const dareTint =
              isDareTile && (i % 2 === 0 ? "bg-violet-50" : "bg-fuchsia-50");
            return (
              <div
                key={i}
                style={{ gridRow: COORDS[i].r, gridColumn: COORDS[i].col }}
                className={`relative flex flex-col items-center justify-center text-center border border-stone-700 leading-tight p-0.5 overflow-hidden ${
                  dareTint || "bg-white"
                } ${occupied ? "ring-2 ring-inset ring-amber-500 z-10" : ""}`}
                title={isDareTile ? tile.ruleText ?? "Dare" : tile.name}
              >
                {tile.type === "PROPERTY" && tile.color && (
                  <span
                    className={`absolute ${bandSide} ${
                      GROUP_COLOR[tile.color] ?? "bg-stone-300"
                    }`}
                  />
                )}
                {tile.owner && (
                  <span
                    className={`absolute top-0.5 right-0.5 h-2 w-2 rounded-full ring-1 ring-white ${tokenColor(
                      tile.owner
                    )}`}
                  />
                )}
                {showDareText ? (
                  <>
                    <div className="text-[7px] sm:text-[8px] font-semibold uppercase tracking-wide text-violet-500">
                      Dare
                    </div>
                    <div className="text-[8px] sm:text-[10px] font-medium text-stone-700 line-clamp-3">
                      {tile.ruleText}
                    </div>
                  </>
                ) : isDareTile ? (
                  <>
                    <div className="text-base sm:text-lg font-bold text-violet-500">
                      ?
                    </div>
                    <div className="text-[7px] sm:text-[8px] uppercase tracking-wide text-violet-400">
                      Dare
                    </div>
                  </>
                ) : (
                  <div
                    className={`font-semibold text-[9px] sm:text-[11px] ${
                      tile.type === "PROPERTY"
                        ? "text-stone-800"
                        : "text-stone-500 uppercase tracking-wide text-[8px] sm:text-[10px]"
                    }`}
                  >
                    {tileLabel(tile)}
                  </div>
                )}
                {tile.type === "PROPERTY" && (
                  <div className="text-[8px] sm:text-[10px] text-stone-400">
                    ${tile.price}
                    {tile.houses > 0 && (
                      <span className="text-emerald-700 font-semibold">
                        {" "}
                        {"|".repeat(tile.houses)}
                      </span>
                    )}
                  </div>
                )}
              </div>
            );
          })}

          {/* tokens */}
          <div className="absolute inset-0 pointer-events-none">
            {game.playerOrder.map((uid, idx) => {
              const p = game.players[uid];
              if (!p) return null;
              const pos = displayPos[uid] ?? p.position;
              const { r, col } = COORDS[pos];
              const left = ((col - 0.5) / 8) * 100;
              const top = ((r - 0.5) / 8) * 100;
              const dx = idx === 0 ? -8 : 8;
              return (
                <div
                  key={uid}
                  className={`absolute h-6 w-6 rounded-full border-2 border-white ring-1 ring-black/40 shadow-md flex items-center justify-center text-white text-[10px] font-serif font-semibold ${tokenColor(
                    uid
                  )} transition-all duration-200 ease-in-out`}
                  style={{
                    left: `${left}%`,
                    top: `${top}%`,
                    transform: `translate(calc(-50% + ${dx}px), -50%)`,
                    zIndex: 20,
                  }}
                  title={p.name}
                >
                  {p.name[0]?.toUpperCase() ?? "?"}
                </div>
              );
            })}
          </div>

          {/* center: diagonal MONOPOLY wordmark + dice/turn */}
          <div
            style={{ gridRow: "2 / 8", gridColumn: "2 / 8" }}
            className="relative flex flex-col items-center justify-center gap-2 text-center bg-white"
          >
            <span className="pointer-events-none absolute inset-0 flex items-center justify-center">
              <span className="font-serif font-bold text-stone-900/10 text-4xl sm:text-6xl -rotate-45 tracking-tight select-none">
                MONOPOLY
              </span>
            </span>

            {game.status === "ended" ? (
              <div className="relative z-10 flex flex-col items-center gap-2">
                <div className="font-serif text-stone-400 text-sm uppercase tracking-[0.2em]">
                  Game over
                </div>
                <div className="font-serif font-semibold text-stone-900 text-2xl">
                  {nameOf(game.winner ?? "")} wins
                </div>
                <button
                  onClick={() => {
                    setSetupDone(false);
                    setSetupStep("mode");
                  }}
                  className="mt-1 px-4 py-2 bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 transition-colors cursor-pointer text-sm font-medium tracking-wide"
                >
                  Play again
                </button>
              </div>
            ) : (
              <div className="relative z-10 flex flex-col items-center gap-2">
                <div
                  className={`h-16 w-16 sm:h-20 sm:w-20 rounded-2xl bg-white border-2 border-stone-800 shadow-lg flex items-center justify-center font-serif text-3xl sm:text-4xl text-stone-900 ${
                    rolling ? "animate-pulse" : ""
                  }`}
                >
                  {rolling ? fakeDie : game.lastRoll ?? "—"}
                </div>
                <div className="text-xs uppercase tracking-wide text-stone-500">
                  {turnName}'s turn
                </div>
                {!game.pending && (
                  <button
                    onClick={roll}
                    disabled={rolling}
                    className="px-5 py-2 bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 transition-colors cursor-pointer text-sm font-medium tracking-wide disabled:opacity-60 active:scale-95"
                  >
                    {rolling
                      ? "Rolling…"
                      : game.players[meUid].jailed
                      ? "Roll (in jail)"
                      : "Roll dice"}
                  </button>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Pending: BUY */}
        {game.pending?.type === "buy" && pendingTile && (
          <div className="mt-4 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4 shadow-sm">
            <p className="text-sm text-stone-700 dark:text-stone-200 mb-3">
              {turnName}, buy{" "}
              <span className="font-serif font-semibold">{pendingTile.name}</span>{" "}
              for <span className="font-serif font-semibold">${pendingTile.price}</span>?
            </p>
            <div className="flex gap-2">
              <button
                onClick={() => decideBuy(true)}
                className="px-4 py-1.5 bg-stone-900 text-amber-50 rounded-md text-sm cursor-pointer hover:bg-stone-700 tracking-wide"
              >
                Buy
              </button>
              <button
                onClick={() => decideBuy(false)}
                className="px-4 py-1.5 bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-200 rounded-md text-sm cursor-pointer hover:bg-stone-200"
              >
                Skip
              </button>
            </div>
          </div>
        )}

        {/* Pending: CHANCE */}
        {game.pending?.type === "chance" && game.pending.chanceText && (
          <div className="mt-4 rounded-md border border-amber-300 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/40 p-4 shadow-sm">
            <p className="text-xs font-medium text-amber-700 dark:text-amber-300 uppercase tracking-[0.15em] mb-1.5">
              Chance
            </p>
            <p className="text-sm text-stone-800 dark:text-stone-100 mb-3 font-serif">
              {game.pending.chanceText}
            </p>
            <button
              onClick={acknowledgeChance}
              className="px-4 py-1.5 bg-stone-900 text-amber-50 rounded-md text-sm cursor-pointer hover:bg-stone-700 tracking-wide"
            >
              Continue
            </button>
          </div>
        )}

        {/* Pending: TASK — lander performs, then hands off to judge */}
        {isTask && pendingTile && (
          <div className="mt-4 rounded-md border border-stone-800 bg-stone-900 text-stone-100 p-4 shadow">
            <p className="text-xs font-medium text-amber-400 uppercase tracking-[0.15em] mb-2">
              Dare · the game is frozen until this is settled
            </p>
            {!game.pending?.claimedDone ? (
              // Lander performs the dare, then claims done.
              <>
                <p className="text-base font-serif text-amber-50 mb-3">
                  {pendingRuleText}
                </p>
                <p className="text-xs text-stone-400 mb-3">
                  {turnName}, do it for real — then tap below and hand the device
                  to {nameOf(other(meUid))} to confirm.
                </p>
                <div className="flex flex-wrap gap-2">
                  <button
                    onClick={() => claimTaskDone(false)}
                    className="px-4 py-1.5 bg-amber-500 text-stone-900 rounded-md text-sm cursor-pointer hover:bg-amber-400 font-medium tracking-wide"
                  >
                    I did it
                  </button>
                  {game.config.doubleOrNothing && (
                    <button
                      onClick={() => claimTaskDone(true)}
                      className="px-4 py-1.5 bg-fuchsia-600 text-white rounded-md text-sm cursor-pointer hover:bg-fuchsia-500 font-medium tracking-wide"
                      title="2× reward if confirmed — but 2× penalty if they say you flaked"
                    >
                      Double or nothing
                    </button>
                  )}
                </div>
              </>
            ) : (
              // After the handoff gate, the device is in the OTHER player's
              // hands — they judge whether the lander pulled it off.
              <>
                <p className="text-sm text-stone-300 mb-1">
                  <span className="text-amber-200">{turnName}</span> says they
                  did:
                </p>
                <p className="text-base font-serif text-amber-50 mb-3">
                  {pendingRuleText}
                </p>
                <p className="text-sm text-stone-300 mb-3">
                  {nameOf(other(meUid))}, did they really pull it off?
                </p>
                <div className="flex gap-2">
                  <button
                    onClick={() => judgeTask(true)}
                    className="px-4 py-1.5 bg-emerald-700 text-amber-50 rounded-md text-sm cursor-pointer hover:bg-emerald-600 tracking-wide"
                  >
                    Yes (+${pendingTile.ruleReward ?? TASK_REWARD})
                  </button>
                  <button
                    onClick={() => judgeTask(false)}
                    className="px-4 py-1.5 bg-rose-800 text-rose-50 rounded-md text-sm cursor-pointer hover:bg-rose-700 tracking-wide"
                  >
                    No (−${pendingTile.rulePenalty ?? TASK_PENALTY})
                  </button>
                </div>
              </>
            )}
          </div>
        )}

        {/* Your properties — build or sell */}
        {game.status === "playing" && myProperties.length > 0 && !game.pending && (
          <div className="mt-4 rounded-md border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-3">
            <p className="text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-[0.15em] mb-2">
              {turnName}'s properties
            </p>
            <div className="flex flex-col gap-2">
              {myProperties.map(({ tile, i }) => (
                <OwnedProperty
                  key={i}
                  tile={tile}
                  index={i}
                  enabled={!game.pending}
                  money={game.players[meUid].money}
                  onBuild={buildHouse}
                  onSell={sellProperty}
                  onReprice={repriceProperty}
                />
              ))}
            </div>
          </div>
        )}

        {/* Log */}
        <div className="mt-4 rounded-md bg-stone-100 dark:bg-stone-800 border border-stone-200 dark:border-stone-700 p-3 text-xs text-stone-500 dark:text-stone-400 space-y-1">
          {game.log.slice(-6).map((line, i) => (
            <div key={i}>{line}</div>
          ))}
        </div>
      </div>
    </div>
  );
}
