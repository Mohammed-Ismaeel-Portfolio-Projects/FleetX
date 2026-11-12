/**
 * Driving motion heuristics (D9).
 * Uses GPS speed when available — technical safety constraint only.
 */

/** km/h — above this threshold, interactive CTAs are deferred. */
export const MOVING_SPEED_KMH = 8;

export function isVehicleMoving(speedKmh: number | null | undefined): boolean {
  return typeof speedKmh === 'number' && Number.isFinite(speedKmh) && speedKmh >= MOVING_SPEED_KMH;
}

export const MOTION_DEFER_HINT =
  'Stop the vehicle safely before continuing. Interaction is available when stationary.';
