/**
 * Customer 360 phase 3 — thin API layer (orders / accounting / SMS).
 * SKELETON: PHASE3_API.md is not written yet, so no endpoint paths or
 * field names are assumed. Each area is a typed placeholder; when the
 * contract lands, fill `path` and flip `ready` — the tabs start fetching.
 */
import type { Row } from '../hub-api';

export type ContractArea = { key: string; path: string | null; ready: boolean };

export const P3_AREAS = {
  orders: { key: 'orders', path: null, ready: false },
  accounting: { key: 'accounting', path: null, ready: false },
  sms: { key: 'sms', path: null, ready: false },
} satisfies Record<string, ContractArea>;

/** Generic list row until the contract defines the shape. */
export type P3Row = Row;

/** Money fields come back null when the viewer lacks the money permission — hide them. */
export const hasMoney = (v: unknown) => v !== null && v !== undefined;
