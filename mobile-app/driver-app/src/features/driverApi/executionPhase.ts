/**
 * Driver execution phase helpers (D4) — server phases are authoritative.
 */

export type DriverExecutionPhase =
  | 'pickup_pending'
  | 'pickup_arrived'
  | 'pickup_loading'
  | 'pickup_verifying'
  | 'pickup_verified'
  | 'pickup_failed'
  | 'delivery_en_route'
  | 'delivery_arrived'
  | 'delivery_unloading'
  | 'delivery_verifying'
  | 'delivery_verified'
  | 'pod_submitted'
  | 'delivery_failed';

export type DriverExecutionCommand =
  | 'pickup_arrive'
  | 'pickup_loading'
  | 'pickup_loading_complete'
  | 'delivery_arrive'
  | 'delivery_unloading'
  | 'delivery_unloading_complete';

export type DriverExecutionAction =
  | { kind: 'mutate'; command: DriverExecutionCommand; label: string }
  | { kind: 'verify'; event: 'pickup' | 'delivery'; label: string }
  | { kind: 'pod'; label: string }
  | { kind: 'complete'; label: string }
  | { kind: 'refresh'; label: string }
  | { kind: 'none'; hint: string };

const PHASE_STATUS: Record<string, string> = {
  pickup_pending: 'En route to pickup',
  pickup_arrived: 'At pickup',
  pickup_loading: 'Loading',
  pickup_verifying: 'Ready for verification',
  pickup_verified: 'Pickup verified',
  pickup_failed: 'Pickup failed',
  delivery_en_route: 'En route to delivery',
  delivery_arrived: 'At delivery',
  delivery_unloading: 'Unloading',
  delivery_verifying: 'Ready for verification',
  delivery_verified: 'Delivery verified',
  pod_submitted: 'Proof submitted',
  delivery_failed: 'Delivery failed',
};

export function phaseStatusLabel(phase: string | null | undefined): string {
  if (!phase) return 'Awaiting phase';
  return PHASE_STATUS[phase] ?? phase;
}

export function phaseStageTitle(stage: string | null | undefined): string {
  if (stage === 'pickup') return 'Pickup';
  if (stage === 'delivery') return 'Delivery';
  return 'Trip';
}

/** One-line operational guidance for the current phase. */
export function phaseWhatNow(phase: string | null | undefined): string {
  switch (phase) {
    case 'pickup_pending':
      return 'Confirm arrival when you reach the pickup site.';
    case 'pickup_arrived':
      return 'Begin loading when the site is ready.';
    case 'pickup_loading':
      return 'Confirm when loading is finished.';
    case 'pickup_verifying':
      return 'Scan the customer QR to verify pickup.';
    case 'pickup_verified':
      return 'Pickup is verified. Continue toward delivery.';
    case 'delivery_en_route':
      return 'Confirm arrival when you reach the delivery site.';
    case 'delivery_arrived':
      return 'Begin unloading when the receiver is ready.';
    case 'delivery_unloading':
      return 'Confirm when unloading is finished.';
    case 'delivery_verifying':
      return 'Scan the customer QR to verify delivery.';
    case 'delivery_verified':
      return 'Delivery is verified. Capture proof of delivery.';
    case 'pod_submitted':
      return 'Proof submitted. Finish the trip when ready.';
    case 'pickup_failed':
    case 'delivery_failed':
      return 'An execution issue was reported. Wait for dispatcher guidance.';
    default:
      return 'Follow the next required action below.';
  }
}

export function nextActionForPhase(phase: string | null | undefined): DriverExecutionAction {
  switch (phase) {
    case 'pickup_pending':
      return { kind: 'mutate', command: 'pickup_arrive', label: 'Confirm arrival at pickup' };
    case 'pickup_arrived':
      return { kind: 'mutate', command: 'pickup_loading', label: 'Start loading' };
    case 'pickup_loading':
      return { kind: 'mutate', command: 'pickup_loading_complete', label: 'Confirm loading complete' };
    case 'pickup_verifying':
      return { kind: 'verify', event: 'pickup', label: 'Scan pickup QR' };
    case 'pickup_verified':
      // Backend advances pickup_verified → delivery_en_route in the same verify
      // transaction; if the client still sees this phase, refresh authoritative state.
      return { kind: 'refresh', label: 'Continue toward delivery' };
    case 'delivery_en_route':
      return { kind: 'mutate', command: 'delivery_arrive', label: 'Confirm arrival at delivery' };
    case 'delivery_arrived':
      return { kind: 'mutate', command: 'delivery_unloading', label: 'Start unloading' };
    case 'delivery_unloading':
      return {
        kind: 'mutate',
        command: 'delivery_unloading_complete',
        label: 'Confirm unloading complete',
      };
    case 'delivery_verifying':
      return { kind: 'verify', event: 'delivery', label: 'Scan delivery QR' };
    case 'delivery_verified':
      return { kind: 'pod', label: 'Capture proof of delivery' };
    case 'pod_submitted':
      return { kind: 'complete', label: 'Finish trip' };
    case 'pickup_failed':
    case 'delivery_failed':
      return {
        kind: 'none',
        hint: 'Execution issue reported. Wait for dispatcher guidance.',
      };
    default:
      return {
        kind: 'none',
        hint: 'No driver action available for this phase.',
      };
  }
}
