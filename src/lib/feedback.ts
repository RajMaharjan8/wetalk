// ---------------------------------------------------------------------------
// Lightweight UI feedback — tiny Web Audio "sound effects" + device haptics.
// ---------------------------------------------------------------------------
// No audio files (works fully offline), and everything is a no-op when the
// platform doesn't support it (e.g. iOS has no navigator.vibrate). Sounds are
// short and quiet so they feel like UI chrome, not alerts.
//
//   sound.send()  / sound.receive() / sound.delete() / sound.tap() / sound.blocked()
//   haptic([10, 20])
//
// Browsers require a user gesture before audio can start; since these only fire
// from taps/sends that's already satisfied.

let audioCtx: AudioContext | null = null;

function getCtx(): AudioContext | null {
  try {
    const Ctx =
      window.AudioContext ||
      (window as unknown as { webkitAudioContext: typeof AudioContext })
        .webkitAudioContext;
    if (!Ctx) return null;
    audioCtx = audioCtx || new Ctx();
    if (audioCtx.state === "suspended") void audioCtx.resume();
    return audioCtx;
  } catch {
    return null;
  }
}

function tone(
  freq: number,
  durMs: number,
  type: OscillatorType = "sine",
  vol = 0.12,
  delay = 0
): void {
  const ctx = getCtx();
  if (!ctx) return;
  try {
    const start = ctx.currentTime + delay;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, start);
    // quick attack, exponential decay — a soft "blip" rather than a beep
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(vol, start + 0.008);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + durMs / 1000);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(start);
    osc.stop(start + durMs / 1000 + 0.02);
  } catch {
    /* audio unavailable — ignore */
  }
}

export const sound = {
  // A bright upward "whoosh" — your message left.
  send: () => {
    tone(523, 70, "sine", 0.1);
    tone(784, 90, "sine", 0.09, 0.05);
  },
  // A soft two-note chime — a message arrived.
  receive: () => {
    tone(880, 80, "sine", 0.09);
    tone(587, 120, "sine", 0.08, 0.07);
  },
  // A low descending pair — something was removed.
  delete: () => {
    tone(320, 90, "sawtooth", 0.09);
    tone(180, 120, "sawtooth", 0.08, 0.07);
  },
  // A tiny tick — a light tap/toggle.
  tap: () => tone(440, 35, "sine", 0.05),
  // A flat buzz — action not allowed (e.g. send limit reached).
  blocked: () => {
    tone(200, 110, "square", 0.07);
    tone(150, 130, "square", 0.07, 0.09);
  },
};

export function haptic(pattern: number | number[]): void {
  try {
    navigator.vibrate?.(pattern);
  } catch {
    /* not supported */
  }
}
