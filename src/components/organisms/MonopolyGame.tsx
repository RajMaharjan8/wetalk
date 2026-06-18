import { useContext, useEffect, useRef, useState } from "react";
import { deleteDoc, doc, onSnapshot, setDoc } from "firebase/firestore";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import CasinoIcon from "@mui/icons-material/Casino";
import RestartAltIcon from "@mui/icons-material/RestartAlt";
import CloseIcon from "@mui/icons-material/Close";
import { db } from "../../firebase";
import { ThemeContext } from "../../hooks/ThemeContext";
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
  TEMPLATE_PROPERTIES,
  buildBoard,
  defaultConfig,
  normalizeGame,
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

interface Props {
  chatId: string;
  opponentUid: string;
  opponentName: string;
  onClose: () => void;
  // Called when the player quits — clears the chat's "play" invite card too.
  onQuit: () => void;
}

export default function MonopolyGame({
  chatId,
  opponentUid,
  opponentName,
  onClose,
  onQuit,
}: Props) {
  const { currentUser } = useContext(ThemeContext);
  const myUid: string = currentUser.uid;
  const myName: string = currentUser.displayName ?? "You";

  const [game, setGame] = useState<GameState | null>(null);
  const [loaded, setLoaded] = useState(false);
  // Each dare carries its own reward/penalty the author chooses (or the
  // defaults, if left as-is — that's the "auto-assign" behaviour).
  const [ruleInputs, setRuleInputs] = useState<DareRule[]>(freshDares);
  // Creator's lobby flow: pick a mode → (classic only) price settings → dares.
  const [lobbyStep, setLobbyStep] = useState<"idle" | "mode" | "settings">(
    "idle"
  );
  const [chosenMode, setChosenMode] = useState<GameMode>("classic");
  const [chosenOpts, setChosenOpts] = useState<DareOpts>({
    shuffle: "off",
    revealDares: true,
    targetScore: null,
    risingStakes: false,
    doubleOrNothing: false,
  });

  // Animation state: where each token is *displayed* (hops toward the real
  // position), plus a spinning dice while rolling.
  const [displayPos, setDisplayPos] = useState<Record<string, number>>({});
  const [rolling, setRolling] = useState(false);
  const [fakeDie, setFakeDie] = useState(1);
  const [confirmReset, setConfirmReset] = useState(false);
  // Dramatic floating "+$200 / -$150" banner.
  const [flash, setFlash] = useState<{ text: string; good: boolean } | null>(
    null
  );
  const targetRef = useRef<Record<string, number>>({});

  const ref = doc(db, "chats", chatId, "game", "monopoly");

  useEffect(() => {
    const unsub = onSnapshot(ref, (snap) => {
      // normalizeGame backfills fields older docs may lack (config, pot, …) so
      // reading game.config.* never crashes.
      setGame(snap.exists() ? normalizeGame(snap.data() as GameState) : null);
      setLoaded(true);
    });
    return () => unsub();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chatId]);

  // Keep my dare inputs sized to the game's mode. The creator's startGame()
  // already sizes them, but the OTHER player only learns the mode from the
  // snapshot — without this their list stays at the default 6 and they can
  // never finish writing all 12 (so the game would never start).
  useEffect(() => {
    if (game?.status !== "collecting_rules") return;
    const need = DARES_PER_MODE[game.mode];
    setRuleInputs((prev) => (prev.length === need ? prev : freshDares(need)));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [game?.status, game?.mode]);

  const save = (g: GameState) => setDoc(ref, g);
  const otherUid = (g: GameState) =>
    g.playerOrder.find((u) => u !== myUid) as string;

  // Leaving a FINISHED game deletes its Firestore doc so old games (board,
  // rules, all of it) don't pile up and keep getting read. An in-progress
  // game is left intact so the other player can keep going.
  const closeGame = () => {
    if (game?.status === "ended") {
      deleteDoc(ref).catch(() => {
        /* best-effort cleanup */
      });
      onQuit(); // game's over — also clear the chat's invite card
      return;
    }
    onClose();
  };

  // Quit ends the game for BOTH players right now and deletes the Firestore
  // doc entirely (board + rules), so nothing lingers loading Firebase.
  const [confirmQuit, setConfirmQuit] = useState(false);
  const quitGame = () => {
    setConfirmQuit(false);
    deleteDoc(ref).catch(() => {
      /* best-effort cleanup */
    });
    onQuit();
  };

  const showFlash = (text: string, good: boolean) => {
    setFlash({ text, good });
    setTimeout(() => setFlash(null), 1400);
  };

  // ---- Lobby: create a fresh game in the chosen mode + config ----
  // Classic mode opens in "awaiting_config" (the OTHER player reviews prices);
  // Pure Dares has no prices, so it skips straight to writing dares.
  const startGame = (mode: GameMode, config: GameConfig, dareOpts: DareOpts) => {
    const dareCount = DARES_PER_MODE[mode];
    // For Pure Dares the thrill/target options come from the mode picker; fold
    // them into the config so they're stored on the game.
    const fullConfig: GameConfig =
      mode === "dares"
        ? {
            ...config,
            targetScore: dareOpts.targetScore,
            risingStakes: dareOpts.risingStakes,
            doubleOrNothing: dareOpts.doubleOrNothing,
          }
        : config;
    save({
      status: mode === "dares" ? "collecting_rules" : "awaiting_config",
      mode,
      shuffle: mode === "dares" ? dareOpts.shuffle : "off",
      revealDares: dareOpts.revealDares,
      pot: 0,
      laps: 0,
      createdBy: myUid,
      playerOrder: [myUid, opponentUid],
      players: {
        [myUid]: {
          uid: myUid,
          name: myName,
          money: fullConfig.startMoney,
          position: 0,
          jailed: false,
        },
        [opponentUid]: {
          uid: opponentUid,
          name: opponentName,
          money: fullConfig.startMoney,
          position: 0,
          jailed: false,
        },
      },
      rules: {},
      config: fullConfig,
      board: [],
      currentTurn: myUid,
      lastRoll: null,
      pending: null,
      winner: null,
      log: [
        mode === "dares"
          ? `${myName} started a Pure Dares game! Both write ${dareCount} dares.`
          : `${myName} set up a game. ${opponentName} reviews the prices next.`,
      ],
    });
    setRuleInputs(freshDares(dareCount));
    setLobbyStep("idle");
    setConfirmReset(false);
  };

  // The OTHER player accepts the creator's prices → on to writing dares.
  const acceptConfig = () => {
    if (!game) return;
    save({
      ...game,
      status: "collecting_rules",
      log: [
        ...game.log,
        `${myName} accepted the prices. Both players write ${
          DARES_PER_MODE[game.mode]
        } dares.`,
      ].slice(-8),
    });
  };

  // ...or rejects, bouncing it back to the creator to re-set the prices.
  const rejectConfig = () => {
    if (!game) return;
    save({
      ...game,
      status: "ended",
      winner: null,
      log: [
        ...game.log,
        `${myName} wants different prices — ${game.players[game.createdBy]?.name} can start a new game.`,
      ].slice(-8),
    });
  };

  const submitRules = () => {
    if (!game) return;
    const need = DARES_PER_MODE[game.mode];
    const cleaned: DareRule[] = ruleInputs
      .map((r) => ({
        text: r.text.trim(),
        reward: Math.max(0, Math.round(r.reward) || 0),
        penalty: Math.max(0, Math.round(r.penalty) || 0),
      }))
      .filter((r) => r.text);
    if (cleaned.length < need) return;
    save({
      ...game,
      rules: { ...game.rules, [myUid]: cleaned.slice(0, need) },
      log: [
        ...game.log,
        `${myName} locked in their ${need} dares.`,
      ].slice(-8),
    });
  };

  // ---- When both players have submitted, the creator builds the board ----
  const builtRef = useRef(false);
  useEffect(() => {
    if (!game || game.status !== "collecting_rules") {
      builtRef.current = false;
      return;
    }
    const need = DARES_PER_MODE[game.mode];
    const a = game.rules[game.playerOrder[0]];
    const b = game.rules[game.playerOrder[1]];
    if (
      a?.length === need &&
      b?.length === need &&
      myUid === game.createdBy &&
      !builtRef.current
    ) {
      builtRef.current = true;
      save({
        ...game,
        board: buildBoard(
          a,
          b,
          game.playerOrder[0],
          game.playerOrder[1],
          game.config,
          game.mode
        ),
        status: "playing",
        currentTurn: game.playerOrder[0],
        log: [...game.log, "Dares shuffled onto the board. Roll to start!"].slice(-8),
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
      setFakeDie(2 + Math.floor(Math.random() * 11));
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

    const oppUid = otherUid(game);
    const players = { ...game.players };
    const me = { ...players[myUid] };
    const opp = { ...players[oppUid] };
    // Auto-shuffle (Pure Dares): "roll" re-deals the dares before every roll.
    // ("lap" is handled below, only when the mover passes GO.)
    let board =
      game.shuffle === "roll"
        ? reshuffleDares(game.board)
        : game.board.map((x) => ({ ...x }));
    const log = [...game.log];

    // Jailed? You lose this roll, then you're free.
    if (me.jailed) {
      me.jailed = false;
      players[myUid] = me;
      log.push(`${me.name} sits out a turn in Jail.`);
      save({
        ...game,
        players,
        board,
        lastRoll: null,
        pending: null,
        currentTurn: oppUid,
        log: log.slice(-8),
      });
      return;
    }

    // Two dice — bigger swings, classic feel.
    const die = 2 + Math.floor(Math.random() * 11);

    const passGo = game.config?.passGo ?? PASS_GO;
    let laps = game.laps;
    const prev = me.position;
    if (prev + die >= BOARD_SIZE) {
      me.money += passGo;
      laps += 1; // a full lap completed — drives rising stakes
      log.push(`${me.name} passed GO (+$${passGo})`);
      showFlash(`+$${passGo} GO`, true);
      // "lap" auto-shuffle: re-deal the dares now that a lap was completed.
      if (game.shuffle === "lap") board = reshuffleDares(board);
    }
    me.position = (prev + die) % BOARD_SIZE;
    let tile = board[me.position];
    log.push(`${me.name} rolled ${die} → ${tile.name}`);

    let pending: Pending | null = null;
    let nextTurn = oppUid;

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
      pending = {
        type: "chance",
        tileIndex: me.position,
        chanceText: card.text,
      };
      nextTurn = myUid; // stay to read the card
      card.delta >= 0 ? sfx.cheer() : sfx.boo();
      vibe([20, 30, 20]);
      showFlash(`${card.delta >= 0 ? "+" : "-"}$${Math.abs(card.delta)}`, card.delta >= 0);
    } else if (tile.type === "PROPERTY") {
      if (!tile.owner) {
        pending = { type: "buy", tileIndex: me.position };
        nextTurn = myUid; // stay to decide buy/skip
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
      // The headline mechanic: lander performs the dare, then the game FREEZES
      // until the OPPONENT confirms. Turn does NOT pass until they decide.
      pending = { type: "task", tileIndex: me.position, claimedDone: false };
      nextTurn = myUid; // lander holds the turn while performing
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
      log.push(`${me.name} went bankrupt — ${opp.name} wins!`);
    }

    const next: GameState = {
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
    };
    save(applyTarget(next));
  };

  // If anyone has reached the target score, end the game in their favour. Run
  // this on every state save that changes money.
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
      showFlash(`-$${tile.price}`, false);
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
      log.push(`${me.name} went bankrupt — ${players[oppUid].name} wins!`);
    }
    save(
      applyTarget({
        ...game,
        players,
        board,
        pending: null,
        currentTurn: nextTurn,
        status,
        winner,
        log: log.slice(-8),
      })
    );
  };

  const acknowledgeChance = () => {
    if (!game?.pending || game.pending.type !== "chance") return;
    if (game.currentTurn !== myUid) return;
    save({
      ...game,
      pending: null,
      currentTurn: otherUid(game),
      log: game.log.slice(-8),
    });
  };

  // ---- Task flow ----
  // Step 1: lander, after doing the dare in real life, taps "I did it". This
  // does NOT pass the turn — it just flips a flag so the opponent gets asked.
  const claimTaskDone = (double = false) => {
    if (!game?.pending || game.pending.type !== "task") return;
    if (game.currentTurn !== myUid || game.pending.claimedDone) return;
    save({
      ...game,
      pending: { ...game.pending, claimedDone: true, doubled: double },
      log: [
        ...game.log,
        `${game.players[myUid].name} says they did the dare${
          double ? " (double-or-nothing!)" : ""
        } — waiting on ${game.players[otherUid(game)].name} to confirm…`,
      ].slice(-8),
    });
  };

  // Step 2: the OPPONENT (not the lander) judges. Only they can call this.
  // "yes" → lander is rewarded and the turn finally passes.
  // "no"  → lander pays a penalty and the turn passes. Either way the game was
  //         FROZEN until this moment — nothing could move without their call.
  const judgeTask = (didIt: boolean) => {
    if (!game?.pending || game.pending.type !== "task") return;
    const lander = game.currentTurn; // the lander still holds the turn
    if (lander === myUid) return; // only the OPPONENT may judge
    if (!game.pending.claimedDone) return; // lander must claim first

    const players = { ...game.players };
    const landerP = { ...players[lander] };
    const log = [...game.log];
    let pot = game.pot;
    // Use this dare's own reward/penalty (author-set, or the default), scaled
    // by rising stakes (per lap) and double-or-nothing (the lander's gamble).
    const dareTile = game.board[game.pending.tileIndex];
    const mult =
      stakeMultiplier(game.laps, game.config.risingStakes) *
      (game.pending.doubled ? 2 : 1);
    const reward = Math.round((dareTile.ruleReward ?? TASK_REWARD) * mult);
    const penalty = Math.round((dareTile.rulePenalty ?? TASK_PENALTY) * mult);

    if (didIt) {
      // Doing the dare pays the reward AND scoops the jackpot pot.
      landerP.money += reward + pot;
      const potMsg = pot > 0 ? ` + $${pot} pot` : "";
      log.push(`${myName} confirmed ${landerP.name} did it (+$${reward}${potMsg})`);
      sfx.cheer();
      vibe([20, 30, 40]);
      showFlash(`+$${reward + pot}`, true);
      pot = 0;
    } else {
      // Flaking pays the penalty into the jackpot pot.
      landerP.money -= penalty;
      pot += penalty;
      log.push(
        `${myName} says ${landerP.name} flaked — $${penalty} into the pot`
      );
      sfx.boo();
      vibe([60, 40, 60]);
      showFlash(`-$${penalty}`, false);
    }
    players[lander] = landerP;

    let status: GameState["status"] = game.status;
    let winner: string | null = game.winner;
    // Task settled — the turn now passes to me (the judge / opponent).
    let nextTurn = myUid;
    if (landerP.money < 0) {
      status = "ended";
      winner = myUid;
      nextTurn = lander;
      log.push(`${landerP.name} went bankrupt — ${myName} wins!`);
    }

    save(
      applyTarget({
        ...game,
        players,
        pot,
        pending: null,
        currentTurn: nextTurn,
        status,
        winner,
        log: log.slice(-8),
      })
    );
  };

  // Build a house on a property you own (raises its rent). Costs houseCost,
  // does NOT use your turn — allowed on your turn when nothing is pending.
  const buildHouse = (tileIndex: number) => {
    if (!game || game.status !== "playing") return;
    if (game.currentTurn !== myUid || game.pending) return;
    const board = game.board.map((x) => ({ ...x }));
    const tile = board[tileIndex];
    if (tile.type !== "PROPERTY" || tile.owner !== myUid) return;
    if (tile.houses >= MAX_HOUSES) return;
    const players = { ...game.players };
    const me = { ...players[myUid] };
    const cost = tile.houseCost ?? 0;
    if (me.money < cost) return;
    me.money -= cost;
    tile.houses += 1;
    players[myUid] = me;
    sfx.build();
    vibe([20, 30, 20]);
    showFlash(`-$${cost}`, false);
    save({
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

  // Sell a property you own back to the bank for its price plus what you spent
  // on houses; it becomes unowned again. Does NOT use up your turn.
  const sellProperty = (tileIndex: number) => {
    if (!game || game.status !== "playing") return;
    if (game.currentTurn !== myUid || game.pending) return;
    const board = game.board.map((x) => ({ ...x }));
    const tile = board[tileIndex];
    if (tile.type !== "PROPERTY" || tile.owner !== myUid) return;
    const players = { ...game.players };
    const me = { ...players[myUid] };
    const refund = sellValueFor(tile);
    me.money += refund;
    tile.owner = null;
    tile.houses = 0;
    tile.sellValue = null;
    players[myUid] = me;
    sfx.buy();
    vibe([20, 30]);
    showFlash(`+$${refund}`, true);
    save({
      ...game,
      players,
      board,
      log: [...game.log, `${me.name} sold ${tile.name} for $${refund}`].slice(-8),
    });
  };

  // Re-price a property you own: set its rent and/or sell value mid-game.
  // Allowed on your turn while nothing is pending; doesn't use up the turn.
  const repriceProperty = (
    tileIndex: number,
    rent: number,
    sellValue: number
  ) => {
    if (!game || game.status !== "playing") return;
    if (game.currentTurn !== myUid || game.pending) return;
    const board = game.board.map((x) => ({ ...x }));
    const tile = board[tileIndex];
    if (tile.type !== "PROPERTY" || tile.owner !== myUid) return;
    // Rent is stored as baseRent (houses still multiply on top), so divide the
    // displayed rent back out by the house multiplier.
    tile.baseRent = Math.max(0, Math.round(rent / (1 + tile.houses)) || 0);
    tile.sellValue = Math.max(0, Math.round(sellValue) || 0);
    sfx.build();
    vibe([15, 25]);
    save({
      ...game,
      board,
      log: [
        ...game.log,
        `${myName} re-priced ${tile.name}: rent $${rentFor(tile)}, sells for $${sellValueFor(
          tile
        )}`,
      ].slice(-8),
    });
  };

  // ---- Rendering ----
  if (!loaded) {
    return (
      <div className="h-full w-full flex items-center justify-center text-gray-400">
        Loading game…
      </div>
    );
  }

  // Two refined token colours: deep emerald and warm gold.
  const tokenColor = (uid: string) =>
    game && game.playerOrder[0] === uid ? "bg-emerald-800" : "bg-amber-600";

  const Header = (
    <div className="flex items-center gap-2 px-4 py-3 bg-stone-900 text-stone-100 shrink-0">
      <button
        onClick={closeGame}
        className="flex items-center gap-1.5 pl-1.5 pr-3 py-1.5 rounded-md text-sm text-stone-300 hover:bg-stone-800 transition-colors cursor-pointer"
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
            className="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-sm text-stone-300 hover:bg-rose-900/40 hover:text-rose-200 transition-colors cursor-pointer"
            title="Quit game"
          >
            <CloseIcon fontSize="small" />
            <span className="hidden sm:inline">Quit</span>
          </button>
          <button
            onClick={() => {
              setConfirmQuit(false);
              setConfirmReset(true);
            }}
            className="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-sm text-stone-300 hover:bg-stone-800 transition-colors cursor-pointer"
            title="Reset game"
          >
            <RestartAltIcon fontSize="small" />
            <span className="hidden sm:inline">Reset</span>
          </button>
        </>
      )}
      <CasinoIcon className="text-amber-500" fontSize="small" />
      <h3 className="font-serif font-semibold tracking-wide text-amber-50">
        Monopoly
      </h3>
    </div>
  );

  // Reset confirmation bar — restarting wipes the game for BOTH players.
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
          setLobbyStep("mode");
        }}
        className="px-3 py-1.5 text-sm bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 cursor-pointer"
      >
        New game
      </button>
    </div>
  );

  // Quit confirmation bar — ends the game for BOTH players and deletes the doc.
  const QuitBar = confirmQuit && (
    <div className="flex items-center gap-3 px-4 py-3 bg-rose-50 dark:bg-rose-950/40 border-b border-rose-100 dark:border-rose-900 shrink-0">
      <span className="text-sm text-rose-800 dark:text-rose-200 flex-1">
        Quit and end this game for both players? It can't be resumed.
      </span>
      <button
        onClick={() => setConfirmQuit(false)}
        className="px-3 py-1.5 text-sm text-stone-600 dark:text-stone-300 rounded-md hover:bg-white cursor-pointer"
      >
        Cancel
      </button>
      <button
        onClick={quitGame}
        className="px-3 py-1.5 text-sm bg-rose-600 text-white rounded-md hover:bg-rose-700 cursor-pointer"
      >
        Quit game
      </button>
    </div>
  );

  // Mode picker — the creator chooses Classic+Dares or Pure Dares.
  if (lobbyStep === "mode") {
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
        {Header}
        <div className="flex-1 min-h-0 overflow-y-auto p-6 pb-28">
          <ModePicker
            onPick={(mode, dareOpts) => {
              setChosenMode(mode);
              setChosenOpts(dareOpts);
              // Pure Dares has no prices, so skip the settings screen.
              if (mode === "dares")
                startGame("dares", defaultConfig(), dareOpts);
              else setLobbyStep("settings");
            }}
          />
        </div>
      </div>
    );
  }

  // Settings screen — Classic only: the creator picks prices/money.
  if (lobbyStep === "settings") {
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
        {Header}
        <div className="flex-1 min-h-0 overflow-y-auto p-6 pb-28">
          <GameSettingsForm
            onConfirm={(config) => startGame(chosenMode, config, chosenOpts)}
            confirmLabel="Continue to dares"
          />
        </div>
      </div>
    );
  }

  // Lobby — no game yet
  if (!game) {
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
        {Header}
        <div className="flex-1 flex flex-col items-center justify-center gap-5 p-8 text-center">
          <div className="h-16 w-16 rounded-full bg-stone-900 flex items-center justify-center">
            <CasinoIcon style={{ fontSize: 34 }} className="text-amber-500" />
          </div>
          <h2 className="font-serif text-2xl text-stone-800 dark:text-stone-100">Monopoly</h2>
          <p className="text-stone-500 dark:text-stone-400 max-w-xs leading-relaxed">
            A two-player game with{" "}
            <span className="font-medium text-stone-700 dark:text-stone-200">{opponentName}</span>.
            Pick a mode, write your dares, and land on one to do it for real —
            your opponent decides whether it counts.
          </p>
          <button
            onClick={() => setLobbyStep("mode")}
            className="px-6 py-2.5 bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 transition-colors cursor-pointer tracking-wide"
          >
            Start a new game
          </button>
        </div>
      </div>
    );
  }

  // Config-approval phase: the non-creator reviews the prices the creator set.
  if (game.status === "awaiting_config") {
    const iAmCreator = myUid === game.createdBy;
    const props = TEMPLATE_PROPERTIES;
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
        {Header}
        {ResetBar}
        {QuitBar}
        <div className="flex-1 min-h-0 overflow-y-auto p-6 pb-28">
          <h4 className="font-serif text-xl text-stone-800 dark:text-stone-100 mb-1">
            {iAmCreator ? "Waiting for approval" : "Review the game settings"}
          </h4>
          <p className="text-sm text-stone-500 dark:text-stone-400 mb-5 leading-relaxed">
            {iAmCreator
              ? `You set the prices and money. ${opponentName} needs to accept them before you both write dares.`
              : `${game.players[game.createdBy]?.name} chose these prices and money. Accept to play with them, or reject to ask for a different setup.`}
          </p>

          <div className="rounded-md border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-3 text-sm">
            <div className="flex justify-between py-1.5 border-b border-stone-100 dark:border-stone-700">
              <span className="text-stone-500 dark:text-stone-400">Starting money</span>
              <span className="font-serif text-stone-800 dark:text-stone-100">
                ${game.config.startMoney.toLocaleString()}
              </span>
            </div>
            <div className="flex justify-between py-1.5 border-b border-stone-100 dark:border-stone-700">
              <span className="text-stone-500 dark:text-stone-400">Pass GO bonus</span>
              <span className="font-serif text-stone-800 dark:text-stone-100">
                ${game.config.passGo.toLocaleString()}
              </span>
            </div>
            <div className="flex justify-between text-[11px] uppercase tracking-[0.15em] text-stone-400 dark:text-stone-400 pt-2 pb-1">
              <span>Property</span>
              <span className="flex gap-4">
                <span className="w-12 text-right">Price</span>
                <span className="w-12 text-right">Rent</span>
              </span>
            </div>
            {props.map((p) => {
              const o = game.config.properties[p.name];
              return (
                <div key={p.name} className="flex justify-between py-1 text-stone-700 dark:text-stone-200">
                  <span className="truncate">{p.name}</span>
                  <span className="flex gap-4">
                    <span className="w-12 text-right">${o?.price ?? p.price}</span>
                    <span className="w-12 text-right">${o?.rent ?? p.rent}</span>
                  </span>
                </div>
              );
            })}
          </div>

          {!iAmCreator && (
            <div className="flex gap-2 mt-5">
              <button
                onClick={acceptConfig}
                className="flex-1 px-4 py-2.5 bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 transition-colors cursor-pointer tracking-wide"
              >
                Accept &amp; write dares
              </button>
              <button
                onClick={rejectConfig}
                className="px-4 py-2.5 bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-200 rounded-md hover:bg-stone-200 transition-colors cursor-pointer"
              >
                Reject
              </button>
            </div>
          )}
        </div>
      </div>
    );
  }

  const dareCount = DARES_PER_MODE[game.mode];
  const myRulesDone = game.rules[myUid]?.length === dareCount;
  const oppRulesDone = game.rules[opponentUid]?.length === dareCount;

  // Rule-collection phase
  if (game.status === "collecting_rules") {
    return (
      <div className="h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
        {Header}
        {ResetBar}
        {QuitBar}
        <div className="flex-1 min-h-0 overflow-y-auto p-6 pb-28">
          {myRulesDone ? (
            <div className="rounded-md bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 p-4 text-sm text-emerald-800 dark:text-emerald-200">
              Your dares are locked in. Waiting for {opponentName}…
            </div>
          ) : (
            <DareEditor
              dares={ruleInputs}
              setDares={setRuleInputs}
              onSubmit={submitRules}
              submitLabel="Lock in my dares"
              heading={`Write your ${dareCount} dares`}
            />
          )}

          <div className="mt-6 text-sm text-stone-500 dark:text-stone-400">
            {opponentName}: {oppRulesDone ? "ready" : "still writing dares…"}
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
  const isTask = game.pending?.type === "task";
  const iAmLander = game.currentTurn === myUid;

  // Dares are FIXED on the board — each tile always shows the exact dare that
  // was shuffled onto it and it never changes, even if you wrote it yourself.
  // You still have to perform it.
  const pendingRuleText: string | null =
    pendingTile && pendingTile.type === "TASK" ? pendingTile.ruleText : null;

  // Properties this player currently owns (for the build/sell panel).
  const myProperties = game.board
    .map((tile, i) => ({ tile, i }))
    .filter(({ tile }) => tile.type === "PROPERTY" && tile.owner === myUid);

  return (
    <div className="relative h-full w-full flex flex-col overflow-hidden bg-stone-50 dark:bg-stone-950">
      {Header}
      {ResetBar}
        {QuitBar}

      {/* Floating money banner — subtle fade, no bounce */}
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
        {[me, opp].map(
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
                    {p.uid === myUid ? "You" : p.name}
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

      {/* Pot / target / stakes strip — only when something's active */}
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
            <span>
              Stakes ×
              {stakeMultiplier(game.laps, true).toFixed(1)}
            </span>
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
            // The colour band sits on the INNER edge, facing the centre.
            const bandSide =
              side === "top"
                ? "bottom-0 left-0 w-full h-2.5"
                : side === "bottom"
                ? "top-0 left-0 w-full h-2.5"
                : side === "left"
                ? "right-0 top-0 h-full w-2.5"
                : "left-0 top-0 h-full w-2.5"; // right column
            // Pure Dares tiles: show the dare text when the board is fixed
            // (you see what's coming); a mystery "?" when auto-shuffle hides it.
            const isDareTile = tile.type === "TASK";
            // Show the dare text when the creator chose "reveal" — but never
            // when it's reshuffling every roll (the text would change each roll,
            // so keep it a mystery "?").
            const showDareText =
              isDareTile && game.revealDares && game.shuffle !== "roll";
            // Subtle alternating tint so the all-dare board looks lively.
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
                {/* colour band (properties only) */}
                {tile.type === "PROPERTY" && tile.color && (
                  <span
                    className={`absolute ${bandSide} ${
                      GROUP_COLOR[tile.color] ?? "bg-stone-300"
                    }`}
                  />
                )}
                {/* owner dot in the corner */}
                {tile.owner && (
                  <span
                    className={`absolute top-0.5 right-0.5 h-2 w-2 rounded-full ring-1 ring-white ${tokenColor(
                      tile.owner
                    )}`}
                  />
                )}
                {showDareText ? (
                  // Fixed-board dare: a tiny "DARE" caption + the actual text.
                  <>
                    <div className="text-[7px] sm:text-[8px] font-semibold uppercase tracking-wide text-violet-500">
                      Dare
                    </div>
                    <div className="text-[8px] sm:text-[10px] font-medium text-stone-700 line-clamp-3">
                      {tile.ruleText}
                    </div>
                  </>
                ) : isDareTile ? (
                  // Auto-shuffle mystery tile.
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

          {/* animated player tokens (hop tile-by-tile) */}
          <div className="absolute inset-0 pointer-events-none">
            {game.playerOrder.map((uid, idx) => {
              const p = game.players[uid];
              if (!p) return null;
              const pos = displayPos[uid] ?? p.position;
              const { r, col } = COORDS[pos];
              const left = ((col - 0.5) / 8) * 100;
              const top = ((r - 0.5) / 8) * 100;
              const dx = idx === 0 ? -8 : 8; // separate two tokens on one tile
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
                  title={p.uid === myUid ? "You" : p.name}
                >
                  {(p.uid === myUid ? "You" : p.name)?.[0]?.toUpperCase() ?? "?"}
                </div>
              );
            })}
          </div>

          {/* center: diagonal MONOPOLY wordmark + dice/turn */}
          <div
            style={{ gridRow: "2 / 8", gridColumn: "2 / 8" }}
            className="relative flex flex-col items-center justify-center gap-2 text-center bg-white"
          >
            {/* big diagonal wordmark behind everything */}
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
                  {game.winner === myUid ? "You win" : `${opp?.name} wins`}
                </div>
                <button
                  onClick={() => setLobbyStep("mode")}
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
                  {myTurn ? "Your turn" : `${turnName}'s turn`}
                </div>
                {myTurn && !game.pending && (
                  <button
                    onClick={roll}
                    disabled={rolling}
                    className="px-5 py-2 bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 transition-colors cursor-pointer text-sm font-medium tracking-wide disabled:opacity-60 active:scale-95"
                  >
                    {rolling
                      ? "Rolling…"
                      : me.jailed
                      ? "Roll (in jail)"
                      : "Roll dice"}
                  </button>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Pending: BUY */}
        {game.pending?.type === "buy" &&
          game.currentTurn === myUid &&
          pendingTile && (
            <div className="mt-4 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4 shadow-sm">
              <p className="text-sm text-stone-700 dark:text-stone-200 mb-3">
                Buy <span className="font-serif font-semibold">{pendingTile.name}</span> for{" "}
                <span className="font-serif font-semibold">${pendingTile.price}</span>?
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
        {game.pending?.type === "chance" &&
          game.currentTurn === myUid &&
          game.pending.chanceText && (
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

        {/* Pending: TASK — the freeze-until-confirmed mechanic */}
        {isTask && pendingTile && (
          <div className="mt-4 rounded-md border border-stone-800 bg-stone-900 text-stone-100 p-4 shadow">
            <p className="text-xs font-medium text-amber-400 uppercase tracking-[0.15em] mb-2">
              Dare · the game is frozen until this is settled
            </p>

            {iAmLander ? (
              // I landed on the task: I must do it, then claim done.
              <>
                <p className="text-base font-serif text-amber-50 mb-3">
                  {pendingRuleText}
                </p>
                {game.pending?.claimedDone ? (
                  <p className="text-sm text-stone-300">
                    Waiting for{" "}
                    <span className="text-amber-200">{opp?.name}</span> to
                    confirm you did it…
                  </p>
                ) : (
                  <>
                    <p className="text-xs text-stone-400 mb-3">
                      Do it for real, then tap below. {opp?.name} decides if it
                      counts — the game won't move until they do.
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
                )}
              </>
            ) : (
              // I'm the opponent: I judge whether the lander did the dare.
              <>
                <p className="text-sm text-stone-300 mb-1">
                  <span className="text-amber-200">
                    {game.players[game.currentTurn]?.name}
                  </span>{" "}
                  must do:
                </p>
                <p className="text-base font-serif text-amber-50 mb-3">
                  {pendingTile.ruleText}
                </p>
                {game.pending?.claimedDone ? (
                  <>
                    <p className="text-sm text-stone-300 mb-3">
                      They say they did it. Did they really? Your call decides
                      whether the game moves on.
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
                ) : (
                  <p className="text-sm text-stone-400">
                    Waiting for them to perform the dare…
                  </p>
                )}
              </>
            )}
          </div>
        )}

        {/* Your properties — build or sell */}
        {game.status === "playing" && myProperties.length > 0 && (
          <div className="mt-4 rounded-md border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-3">
            <p className="text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-[0.15em] mb-2">
              Your properties
            </p>
            <div className="flex flex-col gap-2">
              {myProperties.map(({ tile, i }) => (
                <OwnedProperty
                  key={i}
                  tile={tile}
                  index={i}
                  enabled={myTurn && !game.pending}
                  money={me.money}
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
