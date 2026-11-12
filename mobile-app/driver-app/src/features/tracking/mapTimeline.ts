/**
 * Compact trip timeline from existing execution phases (MAP-04).
 * Presentation only — reuses tripProgressIndex; no new lifecycle states.
 */

import { tripProgressIndex } from '@/components/driver/TripProgress';

export type TimelineMark = 'done' | 'current' | 'todo';

export type TimelineMilestone = {
  id: string;
  label: string;
  mark: TimelineMark;
};

/**
 * Maps server execution.phase (+ optional assignment status key) to demo-friendly milestones.
 */
export function buildTripTimelineMilestones(
  phase: string | null | undefined,
  statusKey: string | null | undefined
): TimelineMilestone[] {
  const idx = tripProgressIndex(phase);
  const tripCompleted = statusKey === 'completed';
  const podSubmitted = phase === 'pod_submitted';

  const pickupMark: TimelineMark =
    idx >= 1 ? 'done' : 'current';
  const transitMark: TimelineMark =
    idx > 1 ? 'done' : idx === 1 ? 'current' : 'todo';
  const deliveryMark: TimelineMark =
    idx > 2 || tripCompleted ? 'done' : idx === 2 ? 'current' : 'todo';
  let completedMark: TimelineMark = 'todo';
  if (tripCompleted) {
    completedMark = 'done';
  } else if (podSubmitted || idx >= 3) {
    completedMark = 'current';
  }

  return [
    { id: 'assigned', label: 'Assignment received', mark: 'done' },
    { id: 'pickup', label: 'Pickup verified', mark: pickupMark },
    { id: 'transit', label: 'In transit', mark: transitMark },
    { id: 'delivery', label: 'Delivery verification', mark: deliveryMark },
    { id: 'completed', label: 'Completed', mark: completedMark },
  ];
}

export function markGlyph(mark: TimelineMark): string {
  if (mark === 'done') return '✓';
  if (mark === 'current') return '●';
  return '○';
}
