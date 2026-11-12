/**
 * Engine operational stage helpers (C2.9-C2.8 Step 6).
 * Display-only — does not invent Engine transitions.
 */

export const ENGINE_OPERATIONAL_TIMELINE = [
  'Assigned',
  'Accepted',
  'EnRoutePickup',
  'ArrivedPickup',
  'Loading',
  'Loaded',
  'Departed',
  'EnRouteDelivery',
  'ApproachingRestStop',
  'Resting',
  'ArrivedDelivery',
  'Unloading',
  'Delivered',
  'Completed',
] as const;

export type EngineOperationalStage = (typeof ENGINE_OPERATIONAL_TIMELINE)[number] | string;

const STAGE_LABELS: Record<string, string> = {
  Assigned: 'Assigned',
  Accepted: 'Accepted',
  EnRoutePickup: 'En Route to Pickup',
  ArrivedPickup: 'Arrived at Pickup',
  Loading: 'Loading',
  Loaded: 'Loaded',
  Departed: 'Departed',
  EnRouteDelivery: 'En Route to Delivery',
  ApproachingRestStop: 'Approaching Rest Stop',
  Resting: 'Resting',
  ArrivedDelivery: 'Arrived at Delivery',
  Unloading: 'Unloading',
  Delivered: 'Delivered',
  Completed: 'Completed',
};

export function formatOperationalStage(stage: string | null | undefined): string {
  if (!stage) return 'Unavailable';
  return STAGE_LABELS[stage] || stage;
}

export type EngineTimelineState = 'completed' | 'current' | 'upcoming' | 'skipped';

/**
 * Mark timeline position from Engine current stage.
 * Rest stages are skipped when the journey has no rest stops.
 */
export function getEngineTimelineState(
  currentStage: string | null | undefined,
  stage: string,
  restStopCount: number
): EngineTimelineState {
  if (!currentStage) return 'upcoming';

  const restStages = new Set(['ApproachingRestStop', 'Resting']);
  if (restStopCount <= 0 && restStages.has(stage)) {
    return 'skipped';
  }

  const currentIndex = ENGINE_OPERATIONAL_TIMELINE.indexOf(
    currentStage as (typeof ENGINE_OPERATIONAL_TIMELINE)[number]
  );
  const stageIndex = ENGINE_OPERATIONAL_TIMELINE.indexOf(
    stage as (typeof ENGINE_OPERATIONAL_TIMELINE)[number]
  );

  if (currentIndex < 0 || stageIndex < 0) {
    return currentStage === stage ? 'current' : 'upcoming';
  }
  if (stageIndex < currentIndex) return 'completed';
  if (stageIndex === currentIndex) return 'current';
  return 'upcoming';
}

export function formatMeters(meters: number | null | undefined): string {
  if (meters == null || Number.isNaN(meters)) return 'Unavailable';
  if (meters >= 1000) return `${(meters / 1000).toFixed(1)} km`;
  return `${Math.round(meters)} m`;
}

export function formatSeconds(seconds: number | null | undefined): string {
  if (seconds == null || Number.isNaN(seconds)) return 'Unavailable';
  const s = Math.max(0, Math.round(seconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  if (h > 0) return `${h}h ${m}m`;
  if (m > 0) return `${m}m`;
  return `${s}s`;
}

/** Compact list line: "In Transit · Resting" / "Assigned · Ready for Engine" */
export function formatOpsListIndicator(
  canonicalStatus: string,
  operationalStage: string | null | undefined,
  isResting: boolean,
  opts?: { canEnroll?: boolean; simulationEligible?: boolean }
): string | null {
  if (opts?.simulationEligible) {
    if (isResting || operationalStage === 'Resting') {
      return `${canonicalStatus} · Resting`;
    }
    if (operationalStage) {
      return `${canonicalStatus} · ${formatOperationalStage(operationalStage)}`;
    }
    return `${canonicalStatus} · Engine Active`;
  }
  if (opts?.canEnroll) {
    return `${canonicalStatus} · Ready for Engine`;
  }
  if (!operationalStage) return null;
  const stageLabel = formatOperationalStage(operationalStage);
  if (isResting || operationalStage === 'Resting') {
    return `${canonicalStatus} · Resting`;
  }
  return `${canonicalStatus} · ${stageLabel}`;
}
