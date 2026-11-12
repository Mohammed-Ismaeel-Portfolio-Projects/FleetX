/**
 * Driver assignment operational state helpers (D9).
 */

import type { DriverWorkAssignment } from '@/features/driverApi/workTypes';

export function isTripStale(
  assignment: DriverWorkAssignment,
  awarenessCritical = false
): boolean {
  return (
    !assignment.isActive ||
    assignment.status?.key !== 'in_operation' ||
    awarenessCritical
  );
}

/** Work Home operational label — server-authoritative, no invented states. */
export function workOperationalLabel(assignment: DriverWorkAssignment | null): string {
  if (!assignment?.isActive) {
    return 'Available';
  }
  const key = assignment.status?.key;
  if (key === 'assigned') return 'Assigned';
  if (key === 'ready') return 'Ready';
  if (key === 'in_operation') return 'In Operation';
  if (key === 'cancelled') return 'Unavailable';
  return assignment.status?.name ?? 'Unavailable';
}

export function nextWorkActionHint(assignment: DriverWorkAssignment | null): string | null {
  if (!assignment?.isActive) {
    return 'You are available and awaiting work from your dispatcher.';
  }
  const key = assignment.status?.key;
  if (key === 'assigned') return 'Acknowledge your assignment to continue.';
  if (key === 'ready') return 'Start your trip when you are ready.';
  if (key === 'in_operation') return 'Continue the trip — complete the current step.';
  return null;
}

export function workHomePrimaryCta(assignment: DriverWorkAssignment): {
  label: string;
  kind: 'assignment' | 'trip';
} {
  const key = assignment.status?.key;
  if (key === 'in_operation') return { label: 'Continue Trip', kind: 'trip' };
  if (key === 'ready') return { label: 'Start Trip', kind: 'assignment' };
  if (key === 'assigned') return { label: 'Acknowledge Assignment', kind: 'assignment' };
  return { label: 'View Assignment', kind: 'assignment' };
}

export function verifyPhaseAllowed(
  phase: string | null | undefined,
  event: 'pickup' | 'delivery'
): boolean {
  if (event === 'pickup') return phase === 'pickup_verifying';
  return phase === 'delivery_verifying';
}

export function podEntryAllowed(phase: string | null | undefined): boolean {
  return phase === 'delivery_verified';
}
