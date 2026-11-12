/** Canonical operational lifecycle stages for Dispatcher presentation (C2.9-C1.x). */
export const OPERATIONAL_LIFECYCLE_STAGES = [
  'Waiting Assignment',
  'Assigned',
  'In Transit',
  'Delivered',
  'Completed',
] as const;

export type OperationalLifecycleStage = (typeof OPERATIONAL_LIFECYCLE_STAGES)[number];

export type LifecycleStageState = 'completed' | 'current' | 'upcoming' | 'outside';

/**
 * Read-only lifecycle position helper. Does not mutate status.
 */
export function getLifecycleStageState(
  currentStatus: string,
  stage: OperationalLifecycleStage
): LifecycleStageState {
  const currentIndex = OPERATIONAL_LIFECYCLE_STAGES.indexOf(
    currentStatus as OperationalLifecycleStage
  );
  const stageIndex = OPERATIONAL_LIFECYCLE_STAGES.indexOf(stage);

  if (currentIndex < 0) return 'outside';
  if (stageIndex < currentIndex) return 'completed';
  if (stageIndex === currentIndex) return 'current';
  return 'upcoming';
}

export function isWaitingForAssignmentDisplay(hasActiveAssignment: boolean): boolean {
  return !hasActiveAssignment;
}
