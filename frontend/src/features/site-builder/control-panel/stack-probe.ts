import type { StackCheckState } from '@/lib/api/site-builder';

/**
 * Badge state for one stack-health probe.
 * Missing diagnostics (light panel) stay "not_run" even when legacy booleans are false.
 */
export function stackProbeState(
  diagnosticsRan: boolean | undefined,
  check: string | undefined,
  legacyOk?: boolean | null,
): StackCheckState {
  if (check === 'ok' || check === 'fail' || check === 'skipped' || check === 'not_run') {
    return check;
  }
  if (diagnosticsRan !== true) {
    return 'not_run';
  }
  if (legacyOk === true) return 'ok';
  if (legacyOk === false) return 'fail';
  return 'not_run';
}
