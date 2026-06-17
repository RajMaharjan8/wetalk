import { useState } from "react";
import {
  DARES_PER_MODE,
  MAX_HOUSES,
  RULES_PER_PLAYER,
  TASK_PENALTY,
  TASK_REWARD,
  TEMPLATE_PROPERTIES,
  defaultConfig,
  rentFor,
  sellValueFor,
  type DareRule,
  type GameConfig,
  type GameMode,
  type ShuffleMode,
  type Tile,
} from "./monopolyCore";

// ---- Shared setup UI for both online & offline Monopoly ----
// Two pieces players use to customise a game (all pre-filled with the defaults,
// so leaving everything untouched is the "auto-assign" path):
//   • GameSettingsForm — start money, pass-GO, and per-property price/rent.
//   • DareEditor       — the dare text plus its own reward (if done) and
//                        penalty (if flaked).

const numberField =
  "w-20 border border-stone-300 dark:border-stone-700 rounded-md p-1.5 text-sm text-right outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100";

interface SettingsProps {
  // Called with the finished config when the player taps continue.
  onConfirm: (config: GameConfig) => void;
  confirmLabel?: string;
}

// A scrollable settings sheet. Starts from the defaults; "Auto-assign" resets
// every field back to them. Editing any field just overrides that one value.
export function GameSettingsForm({ onConfirm, confirmLabel = "Continue" }: SettingsProps) {
  const [config, setConfig] = useState<GameConfig>(defaultConfig);

  const setProp = (name: string, key: "price" | "rent", value: number) =>
    setConfig((c) => ({
      ...c,
      properties: {
        ...c.properties,
        [name]: { ...c.properties[name], [key]: Math.max(0, value || 0) },
      },
    }));

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h4 className="font-serif text-xl text-stone-800 dark:text-stone-100">Game settings</h4>
        <button
          onClick={() => setConfig(defaultConfig())}
          className="text-xs text-stone-500 dark:text-stone-400 underline underline-offset-2 hover:text-stone-700 cursor-pointer"
          title="Reset every value to the standard defaults"
        >
          Auto-assign
        </button>
      </div>
      <p className="text-sm text-stone-500 dark:text-stone-400 -mt-2 leading-relaxed">
        Everything is pre-filled with sensible defaults — keep them, or set your
        own prices, rents, and starting cash.
      </p>

      <div className="flex gap-3">
        <label className="flex-1 text-sm text-stone-600 dark:text-stone-300">
          Starting money
          <input
            type="number"
            inputMode="numeric"
            value={config.startMoney}
            onChange={(e) =>
              setConfig((c) => ({
                ...c,
                startMoney: Math.max(0, Number(e.target.value) || 0),
              }))
            }
            className="mt-1 w-full border border-stone-300 dark:border-stone-700 rounded-md p-2 text-sm outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
          />
        </label>
        <label className="flex-1 text-sm text-stone-600 dark:text-stone-300">
          Pass GO bonus
          <input
            type="number"
            inputMode="numeric"
            value={config.passGo}
            onChange={(e) =>
              setConfig((c) => ({
                ...c,
                passGo: Math.max(0, Number(e.target.value) || 0),
              }))
            }
            className="mt-1 w-full border border-stone-300 dark:border-stone-700 rounded-md p-2 text-sm outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
          />
        </label>
      </div>

      {/* Target score — first to reach it wins. 0/blank = no target (play
          until someone goes bankrupt). Must be above the starting money. */}
      <label className="text-sm text-stone-600 dark:text-stone-300">
        Win at (target money) — leave 0 for no target
        <input
          type="number"
          inputMode="numeric"
          value={config.targetScore ?? 0}
          onChange={(e) =>
            setConfig((c) => ({
              ...c,
              targetScore: Math.max(0, Number(e.target.value) || 0) || null,
            }))
          }
          className="mt-1 w-full border border-stone-300 dark:border-stone-700 rounded-md p-2 text-sm outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
        />
        {config.targetScore !== null &&
          config.targetScore <= config.startMoney && (
            <span className="mt-1 block text-xs text-rose-600 dark:text-rose-400">
              Target must be higher than the starting money (${config.startMoney}).
            </span>
          )}
      </label>

      <div>
        <div className="flex items-center justify-between text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-[0.15em] mb-2 px-1">
          <span>Property</span>
          <span className="flex gap-2">
            <span className="w-20 text-right">Price</span>
            <span className="w-20 text-right">Rent</span>
          </span>
        </div>
        <div className="flex flex-col gap-1.5">
          {TEMPLATE_PROPERTIES.map((p) => (
            <div
              key={p.name}
              className="flex items-center justify-between gap-2 text-sm"
            >
              <span className="text-stone-700 dark:text-stone-200 truncate flex-1">{p.name}</span>
              <input
                type="number"
                inputMode="numeric"
                value={config.properties[p.name]?.price ?? p.price}
                onChange={(e) => setProp(p.name, "price", Number(e.target.value))}
                className={numberField}
              />
              <input
                type="number"
                inputMode="numeric"
                value={config.properties[p.name]?.rent ?? p.rent}
                onChange={(e) => setProp(p.name, "rent", Number(e.target.value))}
                className={numberField}
              />
            </div>
          ))}
        </div>
      </div>

      <button
        onClick={() => onConfirm(config)}
        disabled={
          config.targetScore !== null &&
          config.targetScore <= config.startMoney
        }
        className="mt-1 px-4 py-2 bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 transition-colors cursor-pointer tracking-wide disabled:opacity-40 disabled:cursor-not-allowed"
      >
        {confirmLabel}
      </button>
    </div>
  );
}

interface DareEditorProps {
  dares: DareRule[];
  setDares: (next: DareRule[]) => void;
  onSubmit: () => void;
  submitLabel?: string;
  heading: string;
}

// The dare list, each row with its own reward/penalty. Reward/penalty default
// to the standard values — leave them to "auto-assign", or set per dare.
export function DareEditor({
  dares,
  setDares,
  onSubmit,
  submitLabel = "Lock in dares",
  heading,
}: DareEditorProps) {
  const update = (i: number, patch: Partial<DareRule>) => {
    const next = dares.map((d, j) => (j === i ? { ...d, ...patch } : d));
    setDares(next);
  };
  // Every row must be filled (the array is already sized to the mode's count).
  const ready = dares.every((d) => d.text.trim());

  return (
    <div>
      <h4 className="font-serif text-xl text-stone-800 dark:text-stone-100 mb-1">{heading}</h4>
      <p className="text-sm text-stone-500 dark:text-stone-400 mb-5 leading-relaxed">
        Each dare is fixed onto the board. The player who lands on it must do it
        for real — earning the reward if the other player confirms, or losing
        the penalty if they flake. Amounts default to ${TASK_REWARD} / $
        {TASK_PENALTY}; change them per dare if you like.
      </p>
      <div className="flex flex-col gap-3">
        {dares.map((d, i) => (
          <div key={i} className="rounded-md border border-stone-200 dark:border-stone-700 p-2.5 bg-white dark:bg-stone-900">
            <input
              value={d.text}
              onChange={(e) => update(i, { text: e.target.value })}
              placeholder={`Dare ${i + 1} (e.g. "Do 20 pushups", "Sing a song")`}
              className="w-full border border-stone-300 dark:border-stone-700 rounded-md p-2.5 text-sm outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
            />
            <div className="flex gap-2 mt-2 text-xs text-stone-500 dark:text-stone-400">
              <label className="flex items-center gap-1.5">
                Reward
                <input
                  type="number"
                  inputMode="numeric"
                  value={d.reward}
                  onChange={(e) =>
                    update(i, { reward: Math.max(0, Number(e.target.value) || 0) })
                  }
                  className="w-20 border border-stone-300 dark:border-stone-700 rounded-md p-1.5 text-right outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
                />
              </label>
              <label className="flex items-center gap-1.5">
                Penalty
                <input
                  type="number"
                  inputMode="numeric"
                  value={d.penalty}
                  onChange={(e) =>
                    update(i, { penalty: Math.max(0, Number(e.target.value) || 0) })
                  }
                  className="w-20 border border-stone-300 dark:border-stone-700 rounded-md p-1.5 text-right outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
                />
              </label>
            </div>
          </div>
        ))}
        <button
          onClick={onSubmit}
          disabled={!ready}
          className="mt-1 px-4 py-2 bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed tracking-wide"
        >
          {submitLabel}
        </button>
      </div>
    </div>
  );
}

export const freshDares = (count: number = RULES_PER_PLAYER): DareRule[] =>
  Array.from({ length: count }, () => ({
    text: "",
    reward: TASK_REWARD,
    penalty: TASK_PENALTY,
  }));

// ---- Mode picker: choose Classic+Dares or Pure Dares before setup ----
// onPick gives the chosen mode plus (for Pure Dares) how often to reshuffle.
const SHUFFLE_OPTIONS: { value: ShuffleMode; label: string; hint: string }[] = [
  { value: "off", label: "Off", hint: "Fixed board — tiles show their dare" },
  { value: "roll", label: "Every roll", hint: "Reshuffle before each turn — max chaos" },
  { value: "lap", label: "Every lap", hint: "Reshuffle when someone passes GO" },
];

// Options the Pure Dares path carries back (Classic uses GameSettingsForm).
export interface DareOpts {
  shuffle: ShuffleMode;
  revealDares: boolean;
  targetScore: number | null;
  risingStakes: boolean;
  doubleOrNothing: boolean;
}

export function ModePicker({
  onPick,
  startMoney = 2000,
}: {
  onPick: (mode: GameMode, dareOpts: DareOpts) => void;
  startMoney?: number;
}) {
  const [shuffle, setShuffle] = useState<ShuffleMode>("off");
  const [reveal, setReveal] = useState(true);
  const [rising, setRising] = useState(true);
  const [doubleOrNothing, setDoubleOrNothing] = useState(true);
  const [target, setTarget] = useState(0); // 0 = no target
  const targetInvalid = target !== 0 && target <= startMoney;

  const classicOpts: DareOpts = {
    shuffle: "off",
    revealDares: true,
    targetScore: null,
    risingStakes: false,
    doubleOrNothing: false,
  };
  return (
    <div className="flex flex-col gap-3">
      <h4 className="font-serif text-xl text-stone-800 dark:text-stone-100">Choose a game mode</h4>
      <button
        onClick={() => onPick("classic", classicOpts)}
        className="text-left rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4 hover:border-stone-800 transition-colors cursor-pointer"
      >
        <div className="font-serif text-lg text-stone-800 dark:text-stone-100">Classic + Dares</div>
        <p className="text-sm text-stone-500 dark:text-stone-400 mt-0.5 leading-relaxed">
          The full board — buy properties, build, collect rent — with{" "}
          {DARES_PER_MODE.classic} dares each mixed in. Set prices and money.
        </p>
      </button>

      <div className="rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        <div className="font-serif text-lg text-stone-800 dark:text-stone-100">Pure Dares</div>
        <p className="text-sm text-stone-500 dark:text-stone-400 mt-0.5 leading-relaxed">
          The whole board is dares — {DARES_PER_MODE.dares} each. No properties;
          do dares to earn money, flake and you pay. Go bankrupt and you lose.
        </p>

        {/* Auto-shuffle frequency — creator's choice, Pure Dares only. */}
        <p className="mt-3 text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-[0.15em]">
          Auto-shuffle dares
        </p>
        <div className="mt-1.5 flex flex-col gap-1.5">
          {SHUFFLE_OPTIONS.map((o) => (
            <label
              key={o.value}
              className={`flex items-start gap-2 rounded-md border p-2 cursor-pointer select-none transition-colors ${
                shuffle === o.value
                  ? "border-stone-800 dark:border-stone-300 bg-stone-50 dark:bg-stone-800"
                  : "border-stone-200 dark:border-stone-700 hover:border-stone-400"
              }`}
            >
              <input
                type="radio"
                name="shuffle"
                checked={shuffle === o.value}
                onChange={() => setShuffle(o.value)}
                className="mt-0.5 h-4 w-4 accent-stone-800 dark:accent-stone-300"
              />
              <span>
                <span className="text-sm text-stone-700 dark:text-stone-200">{o.label}</span>
                <span className="block text-xs text-stone-400 dark:text-stone-400">{o.hint}</span>
              </span>
            </label>
          ))}
        </div>

        {/* Reveal style — show the dares, or keep them a mystery. */}
        <p className="mt-4 text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-[0.15em]">
          On the board, show
        </p>
        <div className="mt-1.5 flex gap-2">
          <button
            onClick={() => setReveal(true)}
            className={`flex-1 rounded-md border p-2 text-sm transition-colors cursor-pointer ${
              reveal
                ? "border-stone-800 dark:border-stone-300 bg-stone-50 dark:bg-stone-800 text-stone-800 dark:text-stone-100"
                : "border-stone-200 dark:border-stone-700 text-stone-500 dark:text-stone-400 hover:border-stone-400"
            }`}
          >
            The dares
            <span className="block text-xs text-stone-400 dark:text-stone-400">see what's coming</span>
          </button>
          <button
            onClick={() => setReveal(false)}
            className={`flex-1 rounded-md border p-2 text-sm transition-colors cursor-pointer ${
              !reveal
                ? "border-stone-800 dark:border-stone-300 bg-stone-50 dark:bg-stone-800 text-stone-800 dark:text-stone-100"
                : "border-stone-200 dark:border-stone-700 text-stone-500 dark:text-stone-400 hover:border-stone-400"
            }`}
          >
            Just “?”
            <span className="block text-xs text-stone-400 dark:text-stone-400">mystery — more thrill</span>
          </button>
        </div>

        {/* Thrill toggles — escalate the stakes so games end decisively. */}
        <div className="mt-4 flex flex-col gap-2">
          <label className="flex items-start gap-2 text-sm text-stone-600 dark:text-stone-300 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={rising}
              onChange={(e) => setRising(e.target.checked)}
              className="mt-0.5 h-4 w-4 accent-stone-800 dark:accent-stone-300"
            />
            <span>
              Rising stakes
              <span className="block text-xs text-stone-400 dark:text-stone-400">
                dare amounts grow +50% each lap
              </span>
            </span>
          </label>
          <label className="flex items-start gap-2 text-sm text-stone-600 dark:text-stone-300 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={doubleOrNothing}
              onChange={(e) => setDoubleOrNothing(e.target.checked)}
              className="mt-0.5 h-4 w-4 accent-stone-800 dark:accent-stone-300"
            />
            <span>
              Double-or-nothing
              <span className="block text-xs text-stone-400 dark:text-stone-400">
                gamble a dare for 2× reward — or 2× penalty
              </span>
            </span>
          </label>
        </div>

        {/* Target score — first to reach it wins. 0 = no target. */}
        <label className="mt-4 block text-sm text-stone-600 dark:text-stone-300">
          Win at (target money) — leave 0 for none
          <input
            type="number"
            inputMode="numeric"
            value={target}
            onChange={(e) => setTarget(Math.max(0, Number(e.target.value) || 0))}
            className="mt-1 w-full border border-stone-300 dark:border-stone-700 rounded-md p-2 text-sm outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
          />
          {targetInvalid && (
            <span className="mt-1 block text-xs text-rose-600 dark:text-rose-400">
              Target must be higher than the starting money (${startMoney}).
            </span>
          )}
        </label>

        <button
          onClick={() =>
            onPick("dares", {
              shuffle,
              revealDares: reveal,
              targetScore: target || null,
              risingStakes: rising,
              doubleOrNothing,
            })
          }
          disabled={targetInvalid}
          className="mt-3 w-full px-4 py-2 bg-stone-900 text-amber-50 rounded-md hover:bg-stone-700 transition-colors cursor-pointer tracking-wide disabled:opacity-40 disabled:cursor-not-allowed"
        >
          Start Pure Dares
        </button>
      </div>
    </div>
  );
}

interface OwnedPropertyProps {
  tile: Tile;
  index: number;
  // null disables the actions (e.g. not your turn / a decision is pending).
  enabled: boolean;
  money: number;
  onBuild: (i: number) => void;
  onSell: (i: number) => void;
  onReprice: (i: number, rent: number, sellValue: number) => void;
}

// One row in the "Your properties" panel: shows rent/level, with Build, Sell,
// and an expandable Edit form to re-price rent and sell value mid-game.
export function OwnedProperty({
  tile,
  index,
  enabled,
  money,
  onBuild,
  onSell,
  onReprice,
}: OwnedPropertyProps) {
  const [editing, setEditing] = useState(false);
  const [rent, setRent] = useState(rentFor(tile));
  const [sell, setSell] = useState(sellValueFor(tile));

  return (
    <div className="rounded-md border border-stone-100 dark:border-stone-700 bg-stone-50/60 dark:bg-stone-800 p-2">
      <div className="flex items-center justify-between gap-2 text-sm">
        <span className="text-stone-700 dark:text-stone-200 truncate">
          {tile.name}{" "}
          <span className="text-stone-400 dark:text-stone-400">
            rent ${rentFor(tile)}
            {tile.houses > 0 && ` · level ${tile.houses}`}
          </span>
        </span>
        <div className="flex gap-1.5 shrink-0">
          <button
            onClick={() => onBuild(index)}
            disabled={
              !enabled || tile.houses >= MAX_HOUSES || money < (tile.houseCost ?? 0)
            }
            className="px-2.5 py-1 bg-emerald-800 text-amber-50 rounded-md text-xs cursor-pointer hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed"
            title={
              tile.houses >= MAX_HOUSES ? "Max level" : `Build for $${tile.houseCost}`
            }
          >
            Build ${tile.houseCost}
          </button>
          <button
            onClick={() => {
              setRent(rentFor(tile));
              setSell(sellValueFor(tile));
              setEditing((e) => !e);
            }}
            disabled={!enabled}
            className="px-2.5 py-1 bg-stone-200 dark:bg-stone-700 text-stone-700 dark:text-stone-200 rounded-md text-xs cursor-pointer hover:bg-stone-300 dark:hover:bg-stone-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Edit
          </button>
          <button
            onClick={() => onSell(index)}
            disabled={!enabled}
            className="px-2.5 py-1 bg-stone-200 dark:bg-stone-700 text-stone-700 dark:text-stone-200 rounded-md text-xs cursor-pointer hover:bg-stone-300 dark:hover:bg-stone-600 disabled:opacity-50 disabled:cursor-not-allowed"
            title={enabled ? `Sell for $${sellValueFor(tile)}` : "Sell on your turn"}
          >
            Sell
          </button>
        </div>
      </div>

      {editing && (
        <div className="mt-2 flex flex-wrap items-end gap-2 text-xs text-stone-500 dark:text-stone-400">
          <label className="flex items-center gap-1.5">
            Rent
            <input
              type="number"
              inputMode="numeric"
              value={rent}
              onChange={(e) => setRent(Math.max(0, Number(e.target.value) || 0))}
              className="w-20 border border-stone-300 dark:border-stone-700 rounded-md p-1.5 text-right outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
            />
          </label>
          <label className="flex items-center gap-1.5">
            Sells for
            <input
              type="number"
              inputMode="numeric"
              value={sell}
              onChange={(e) => setSell(Math.max(0, Number(e.target.value) || 0))}
              className="w-20 border border-stone-300 dark:border-stone-700 rounded-md p-1.5 text-right outline-stone-800 focus-within:outline-2 bg-white dark:bg-stone-800 dark:text-stone-100"
            />
          </label>
          <button
            onClick={() => {
              onReprice(index, rent, sell);
              setEditing(false);
            }}
            className="px-3 py-1.5 bg-stone-900 text-amber-50 rounded-md cursor-pointer hover:bg-stone-700"
          >
            Save
          </button>
        </div>
      )}
    </div>
  );
}
