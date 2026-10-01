import { describe, expect, it } from 'vitest';
import { stackProbeState } from './stack-probe';

describe('stackProbeState', () => {
  it('does not treat a light payload of false flags as a failure', () => {
    expect(stackProbeState(false, 'not_run', false)).toBe('not_run');
    expect(stackProbeState(undefined, undefined, false)).toBe('not_run');
    expect(stackProbeState(false, undefined, null)).toBe('not_run');
  });

  it('keeps a budget skip distinct from a real fail', () => {
    expect(stackProbeState(true, 'skipped', false)).toBe('skipped');
    expect(stackProbeState(true, 'fail', false)).toBe('fail');
    expect(stackProbeState(true, 'ok', true)).toBe('ok');
  });

  it('falls back to legacy booleans only after diagnostics ran', () => {
    expect(stackProbeState(true, undefined, true)).toBe('ok');
    expect(stackProbeState(true, undefined, false)).toBe('fail');
    expect(stackProbeState(true, undefined, null)).toBe('not_run');
  });
});
