// ---- Shared core for the 2-player "Custom Rules Monopoly" ----
// Pure types, board data, sounds, haptics, and helpers used by BOTH the online
// (Firestore-synced) MonopolyGame and the offline (pass-and-play) MonopolyOffline
// components. Nothing here touches Firestore or React, so both modes stay in
// lock-step on rules, board layout, money, and sounds.

export type TileType =
  | "GO"
  | "JAIL"
  | "FREE_PARKING"
  | "GOTO_JAIL"
  | "PROPERTY"
  | "TASK"
  | "TAX"
  | "CHANCE";

export const BOARD_SIZE = 28;
export const JAIL_INDEX = 7; // corner where you sit when jailed

export interface Tile {
  type: TileType;
  name: string;
  color: string | null; // colour-group key for PROPERTY tiles
  price: number | null;
  baseRent: number | null;
  houseCost: number | null;
  houses: number; // 0–4 upgrades, each adds rent
  // Owner-set override of what this property sells back for. null → use the
  // default (price + houses spent). Lets an owner re-price their place mid-game.
  sellValue: number | null;
  ruleText: string | null;
  ruleOwner: string | null; // uid of the player who WROTE this task (TASK tiles)
  ruleReward: number | null; // money the lander gains if they perform the dare
  rulePenalty: number | null; // money the lander loses if they flake
  owner: string | null; // uid of the owner, or null
}

// One dare = its text plus the reward/penalty its author chose for it.
export interface DareRule {
  text: string;
  reward: number;
  penalty: number;
}

// Player-chosen game settings (or the defaults, if they "auto-assign").
export interface GameConfig {
  startMoney: number;
  passGo: number;
  // Per-property overrides keyed by the property's name. Missing → template
  // default. Each holds the buy price and base rent.
  properties: Record<string, { price: number; rent: number }>;
  // Reach this much money to win instantly (both modes). null = no target, so
  // the only way to end is bankrupting the opponent. Must be > startMoney.
  targetScore: number | null;
  // Pure Dares thrill toggles (ignored in classic):
  risingStakes: boolean; // dare reward/penalty grows each lap
  doubleOrNothing: boolean; // lander may gamble a dare for 2× stakes
}

export interface Player {
  uid: string;
  name: string;
  money: number;
  position: number;
  jailed: boolean; // skips their next roll
}

export interface Pending {
  // "buy"   — lander decides buy/skip (lander acts)
  // "task"  — lander performs the dare, then OPPONENT must confirm
  // "chance"— lander reads a drawn chance card
  type: "buy" | "task" | "chance";
  tileIndex: number;
  // task-specific: has the lander declared they're done? (waiting on opponent)
  claimedDone?: boolean;
  // task-specific: lander chose double-or-nothing (2× reward AND 2× penalty)
  doubled?: boolean;
  // chance-specific: the drawn card text + the money delta it applied
  chanceText?: string;
}

// Two ways to play:
//  "classic" — the full board: properties, buildings, money, with dare tiles
//              mixed in (6 dares per player).
//  "dares"   — the WHOLE board is dares (12 per player); money still tracked so
//              doing/flaking dares can bankrupt you, but no properties/rent.
export type GameMode = "classic" | "dares";

// How often Pure Dares reshuffles the board (creator's choice).
export type ShuffleMode = "off" | "roll" | "lap";

// How many dares each player writes, per mode.
export const DARES_PER_MODE: Record<GameMode, number> = {
  classic: 6,
  dares: 12,
};

export interface GameState {
  // "awaiting_config" — creator set the prices/money; the OTHER player must
  //   review and accept them before dares are written (online only; offline
  //   skips straight to collecting_rules since it's one device).
  status: "awaiting_config" | "collecting_rules" | "playing" | "ended";
  mode: GameMode;
  // Pure Dares only: how often the dares on the board are reshuffled, so you
  // never know which dare a tile holds (extra thrill).
  //   "off"  — fixed board (dares stay put; tiles show their text)
  //   "roll" — reshuffle before every roll
  //   "lap"  — reshuffle each time a player passes GO (a full lap)
  shuffle: ShuffleMode;
  // Pure Dares only: show each dare's text on its tile, or keep it a mystery
  // "?" until you land (more thrill). Creator's choice.
  revealDares: boolean;
  // Jackpot pot: flaked-dare penalties feed it; doing a dare can win it.
  pot: number;
  // How many laps (pass-GO events) have happened — drives rising stakes.
  laps: number;
  createdBy: string;
  playerOrder: string[]; // [player0, player1]
  players: Record<string, Player>;
  rules: Record<string, DareRule[]>; // uid -> the player's custom dares
  config: GameConfig; // player-chosen prices / money (or defaults)
  board: Tile[];
  currentTurn: string;
  lastRoll: number | null;
  pending: Pending | null;
  winner: string | null;
  log: string[];
}

export const START_MONEY = 2000;
export const PASS_GO = 250;
export const TAX_AMOUNT = 150;
export const FREE_PARKING_BONUS = 200;
export const RULES_PER_PLAYER = 6;
export const TASK_REWARD = 200; // default dare reward (the "auto" value)
export const TASK_PENALTY = 250; // default dare penalty (the "auto" value)
export const MAX_HOUSES = 4;

// Tile factory keeps every field defined (Firestore rejects `undefined`).
const t = (type: TileType, name: string, opts: Partial<Tile> = {}): Tile => ({
  type,
  name,
  color: opts.color ?? null,
  price: opts.price ?? null,
  baseRent: opts.baseRent ?? null,
  houseCost: opts.houseCost ?? null,
  houses: 0,
  sellValue: null,
  ruleText: null,
  ruleOwner: null,
  ruleReward: null,
  rulePenalty: null,
  owner: null,
});

const prop = (
  name: string,
  color: string,
  price: number,
  baseRent: number
): Tile =>
  t("PROPERTY", name, { color, price, baseRent, houseCost: Math.round(price / 2) });

// 28-tile board: 4 corners + 6 tiles per side. The TASK tiles get the
// players' custom dares shuffled onto them.
export const BOARD_TEMPLATE: Tile[] = [
  t("GO", "GO"), //                                            0  corner
  prop("Maple Ave", "brown", 120, 25),
  t("TASK", "Task"),
  prop("Oak St", "brown", 120, 25),
  t("CHANCE", "Chance"),
  prop("Pine Rd", "brown", 140, 30),
  t("TAX", "Income Tax"),
  t("JAIL", "Jail"), //                                        7  corner
  prop("Cedar Ln", "cyan", 160, 35),
  t("TASK", "Task"),
  prop("Elm Blvd", "cyan", 160, 35),
  t("CHANCE", "Chance"),
  prop("Birch Way", "cyan", 180, 40),
  t("TASK", "Task"),
  t("FREE_PARKING", "Free Parking"), //                        14 corner
  prop("Willow Ct", "pink", 200, 50),
  t("TASK", "Task"),
  prop("Aspen Dr", "pink", 200, 50),
  t("CHANCE", "Chance"),
  prop("Spruce St", "pink", 220, 55),
  t("TAX", "Luxury Tax"),
  t("GOTO_JAIL", "Go To Jail"), //                             21 corner
  prop("Palm Pkwy", "orange", 260, 70),
  t("TASK", "Task"),
  prop("Coral Way", "orange", 260, 70),
  t("CHANCE", "Chance"),
  prop("Gold Coast", "orange", 300, 90),
  t("TASK", "Task"),
];

// Colour-group swatches for the property stripe.
export const GROUP_COLOR: Record<string, string> = {
  brown: "bg-amber-700",
  cyan: "bg-cyan-400",
  pink: "bg-pink-400",
  orange: "bg-orange-500",
};

// Chance cards — drawn at random when you land on a Chance tile. Delta is the
// money change; some are dramatic swings.
export const CHANCE_CARDS: { text: string; delta: number }[] = [
  { text: "Bank pays you a dividend!", delta: 150 },
  { text: "You won a beauty contest!", delta: 100 },
  { text: "Tax refund — collect!", delta: 120 },
  { text: "It's your lucky day. Jackpot!", delta: 300 },
  { text: "Doctor's fee — pay up.", delta: -120 },
  { text: "Speeding fine!", delta: -100 },
  { text: "Pay school fees.", delta: -150 },
  { text: "Stock market crash!", delta: -250 },
];

// Grid positions (row/col, 1-indexed) for each board index — a clockwise ring
// around an 8×8 board (corners + 6 per side).
export const COORDS = (() => {
  const c: { r: number; col: number }[] = [];
  for (let col = 1; col <= 8; col++) c.push({ r: 1, col }); // top row L→R: 0..7
  for (let r = 2; r <= 8; r++) c.push({ r, col: 8 }); //       right col: 8..14
  for (let col = 7; col >= 1; col--) c.push({ r: 8, col }); //  bottom R→L: 15..21
  for (let r = 7; r >= 2; r--) c.push({ r, col: 1 }); //        left col: 22..27
  return c;
})();

// ---- Sound effects (Web Audio — no files, works offline) ----
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

export const sfx = {
  // A rattling shake — a quick cluster of clacks that speed up, like dice
  // tumbling in a cup before they land.
  dice: () => {
    [0, 0.05, 0.09, 0.12, 0.14].forEach((d, i) =>
      tone(140 + i * 30, 50, "square", 0.1 - i * 0.012, d)
    );
  },
  // A soft wooden tap as the token hops to the next square.
  step: () => {
    tone(520, 35, "triangle", 0.08);
    tone(260, 50, "sine", 0.05, 0.01);
  },
  // Bright cash-register "ka-ching" rising arpeggio for a purchase.
  buy: () => {
    tone(659, 70, "triangle", 0.14);
    tone(988, 90, "triangle", 0.14, 0.07);
    tone(1319, 130, "triangle", 0.13, 0.15);
  },
  // A heavy descending "pay up" thunk.
  pay: () => {
    tone(330, 110, "sawtooth", 0.13);
    tone(220, 170, "sawtooth", 0.13, 0.09);
  },
  // A little chime when a dare tile is hit.
  rule: () => {
    tone(784, 90, "sine", 0.15);
    tone(1047, 120, "sine", 0.15, 0.08);
    tone(1319, 150, "sine", 0.13, 0.17);
  },
  // Hammering — three quick knocks as a house goes up.
  build: () => {
    [0, 0.09, 0.18].forEach((d) => tone(300, 40, "square", 0.13, d));
    tone(700, 120, "triangle", 0.13, 0.26);
  },
  // Clanging jail door — two dull metallic hits.
  jail: () => {
    tone(160, 180, "square", 0.16);
    tone(120, 240, "square", 0.16, 0.16);
    tone(90, 300, "sawtooth", 0.12, 0.3);
  },
  // Quick happy three-note rise.
  cheer: () =>
    [659, 880, 1175].forEach((f, i) => tone(f, 110, "triangle", 0.16, i * 0.08)),
  // Sad descending "wah-wah".
  boo: () => {
    tone(330, 180, "sawtooth", 0.15);
    tone(247, 220, "sawtooth", 0.15, 0.16);
    tone(196, 300, "sawtooth", 0.14, 0.34);
  },
  // Triumphant fanfare for winning the game.
  win: () =>
    [523, 659, 784, 1047, 1319, 1047, 1319].forEach((f, i) =>
      tone(f, 180, "triangle", 0.16, i * 0.11)
    ),
};

// Haptics — vibrates on supported devices (Android/Chrome); no-op elsewhere.
export const vibe = (pattern: number | number[]) => {
  try {
    navigator.vibrate?.(pattern);
  } catch {
    /* not supported */
  }
};

export function shuffle<T>(arr: T[]): T[] {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

// Rent grows with houses: base, then ×2, ×3, ×4, ×5. (Owners can re-price by
// changing baseRent mid-game.)
export const rentFor = (tile: Tile) => (tile.baseRent ?? 0) * (1 + tile.houses);

// What a property sells back for: the owner's override if set, otherwise the
// price paid plus what was spent on houses.
export const sellValueFor = (tile: Tile) =>
  tile.sellValue ?? (tile.price ?? 0) + tile.houses * (tile.houseCost ?? 0);

// The properties on the template, in board order — used to pre-fill the
// price/rent setup form with the defaults players can keep ("auto") or edit.
export const TEMPLATE_PROPERTIES = BOARD_TEMPLATE.filter(
  (t) => t.type === "PROPERTY"
).map((t) => ({
  name: t.name,
  color: t.color as string,
  price: t.price as number,
  rent: t.baseRent as number,
}));

// A config with every default filled in — the "auto-assign" result.
export const defaultConfig = (): GameConfig => ({
  startMoney: START_MONEY,
  passGo: PASS_GO,
  properties: Object.fromEntries(
    TEMPLATE_PROPERTIES.map((p) => [p.name, { price: p.price, rent: p.rent }])
  ),
  targetScore: null,
  risingStakes: false,
  doubleOrNothing: false,
});

// Backfill any fields a game doc might be missing — older games saved before
// these fields existed have no `config`/`pot`/`laps`/`mode`/etc., so reading
// `game.config.targetScore` would crash. Run this on every doc read so old and
// new games both render safely.
export function normalizeGame(g: GameState): GameState {
  const cfg = g.config ?? ({} as Partial<GameConfig>);
  return {
    ...g,
    mode: g.mode ?? "classic",
    shuffle: g.shuffle ?? "off",
    revealDares: g.revealDares ?? true,
    pot: g.pot ?? 0,
    laps: g.laps ?? 0,
    config: {
      startMoney: cfg.startMoney ?? START_MONEY,
      passGo: cfg.passGo ?? PASS_GO,
      properties:
        cfg.properties ??
        Object.fromEntries(
          TEMPLATE_PROPERTIES.map((p) => [p.name, { price: p.price, rent: p.rent }])
        ),
      targetScore: cfg.targetScore ?? null,
      risingStakes: cfg.risingStakes ?? false,
      doubleOrNothing: cfg.doubleOrNothing ?? false,
    },
  };
}

// Stakes multiplier for rising-stakes Pure Dares: +50% per completed lap.
export const stakeMultiplier = (laps: number, rising: boolean) =>
  rising ? 1 + 0.5 * laps : 1;

// Has anyone hit the target score? Returns the winning uid, or null.
export const targetWinner = (g: GameState): string | null => {
  const target = g.config?.targetScore;
  if (!target) return null;
  for (const uid of g.playerOrder) {
    if ((g.players[uid]?.money ?? 0) >= target) return uid;
  }
  return null;
};

// Drop the custom dares (shuffled) onto the TASK tiles — carrying each dare's
// author + its reward/penalty — and apply the config's price/rent overrides to
// the PROPERTY tiles. The `mode` picks which board template to use.
export function buildBoard(
  daresA: DareRule[],
  daresB: DareRule[],
  uidA: string,
  uidB: string,
  config: GameConfig,
  mode: GameMode = "classic"
): Tile[] {
  const pool = shuffle([
    ...daresA.map((d) => ({ ...d, owner: uidA })),
    ...daresB.map((d) => ({ ...d, owner: uidB })),
  ]);
  let i = 0;
  const template = mode === "dares" ? DARES_BOARD_TEMPLATE : BOARD_TEMPLATE;
  return template.map((tile) => {
    if (tile.type === "PROPERTY") {
      const o = config.properties[tile.name];
      const price = o?.price ?? tile.price ?? 0;
      const rent = o?.rent ?? tile.baseRent ?? 0;
      return {
        ...tile,
        price,
        baseRent: rent,
        houseCost: Math.round(price / 2),
      };
    }
    if (tile.type !== "TASK") return { ...tile };
    // Wrap if there are more dare tiles than dares written (cycles to fill).
    const r = pool.length ? pool[i++ % pool.length] : undefined;
    return {
      ...tile,
      ruleText: r?.text ?? "Do a silly dance!",
      ruleOwner: r?.owner ?? null,
      ruleReward: r?.reward ?? TASK_REWARD,
      rulePenalty: r?.penalty ?? TASK_PENALTY,
    };
  });
}

// Reshuffle the dares already on the board among the TASK tiles — keeps each
// dare's text/owner/reward/penalty together, just moves them to new tiles.
// Used by auto-shuffle so the board changes every roll. Non-TASK tiles (and a
// board with no TASK tiles) are returned unchanged.
export function reshuffleDares(board: Tile[]): Tile[] {
  const dares = shuffle(
    board
      .filter((t) => t.type === "TASK")
      .map((t) => ({
        ruleText: t.ruleText,
        ruleOwner: t.ruleOwner,
        ruleReward: t.ruleReward,
        rulePenalty: t.rulePenalty,
      }))
  );
  let i = 0;
  return board.map((tile) =>
    tile.type === "TASK" ? { ...tile, ...dares[i++] } : { ...tile }
  );
}

// Which edge a tile sits on around the ring — drives where its colour band
// goes (always on the INNER edge, facing the board centre, like real Monopoly).
export type Side = "top" | "right" | "bottom" | "left" | "corner";
export const sideOf = (i: number): Side => {
  const { r, col } = COORDS[i];
  const corner =
    (r === 1 && col === 1) ||
    (r === 1 && col === 8) ||
    (r === 8 && col === 8) ||
    (r === 8 && col === 1);
  if (corner) return "corner";
  if (r === 1) return "top";
  if (col === 8) return "right";
  if (r === 8) return "bottom";
  return "left";
};

// Pure-Dares board: the four corners stay, every other tile is a dare. With 4
// corners that's 24 dare tiles — filled uniquely by 12 dares per player.
export const DARES_BOARD_TEMPLATE: Tile[] = BOARD_TEMPLATE.map((tile, i) =>
  sideOf(i) === "corner" ? { ...tile } : t("TASK", "Task")
);

// Short single-word labels for the board tiles so nothing wraps or clips on the
// narrow ring. Properties show just their first word ("Maple Ave" → "Maple").
export const tileLabel = (tile: Tile) => {
  switch (tile.type) {
    case "TASK":
      return "Dare";
    case "CHANCE":
      return "Chance";
    case "GO":
      return "GO";
    case "JAIL":
      return "Jail";
    case "FREE_PARKING":
      return "Parking";
    case "GOTO_JAIL":
      return "Go Jail";
    case "TAX":
      return tile.name.replace(/ ?(Tax|tax)$/, " Tax");
    case "PROPERTY":
      return tile.name.split(" ")[0]; // "Maple Ave" → "Maple"
    default:
      return tile.name;
  }
};
