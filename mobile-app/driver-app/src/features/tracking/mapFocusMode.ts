/**
 * Phase-aware schematic map focus (MAP-03).
 * Uses existing execution phases only — no new state machine.
 */

export type MapFocusMode = 'pickup' | 'delivery' | 'overview' | 'driver';

const PRE_PICKUP_VERIFY = new Set([
  'pickup_pending',
  'pickup_arrived',
  'pickup_loading',
  'pickup_verifying',
  'pickup_failed',
]);

/**
 * Auto focus before user override:
 * - Before pickup verification → driver + pickup
 * - After pickup verification → driver + delivery
 * - Completed / inactive → full overview
 */
export function resolveAutoMapFocus(
  phase: string | null | undefined,
  statusKey: string | null | undefined,
  isActive: boolean
): MapFocusMode {
  if (!isActive || statusKey === 'completed' || statusKey === 'cancelled') {
    return 'overview';
  }
  if (!phase || PRE_PICKUP_VERIFY.has(phase)) {
    return 'pickup';
  }
  return 'delivery';
}

/** Destination for external maps: pickup before verify, delivery after. */
export function resolveNavigationTarget(
  phase: string | null | undefined,
  statusKey: string | null | undefined,
  isActive: boolean
): 'pickup' | 'delivery' {
  const focus = resolveAutoMapFocus(phase, statusKey, isActive);
  if (focus === 'pickup') return 'pickup';
  return 'delivery';
}
