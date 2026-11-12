/**
 * Tracking-surface performance types (DRIVER-EVAL-08B).
 * Display-only — backend remains source of truth.
 */

export interface OverallPerformanceCompact {
  driverId: number | null;
  overallScore: number | null;
  score: number | null;
  scoreLabel: string | null;
  confidence: string | null;
  maturity: string | null;
  trend: string | null;
  trendDelta: number | null;
  scoreAvailable: boolean;
}

export interface CurrentAssignmentEventSummary {
  id: number;
  eventCode: string | null;
  category: string | null;
  severity: string | null;
  occurredAt: string | null;
}

export interface CurrentAssignmentEvaluationView {
  assignmentId: number;
  assignmentCode: string | null;
  shipmentId: string | null;
  driverId: number | null;
  vehicleId: string | null;
  evaluationAvailable: boolean;
  evaluationId: number | null;
  revision: number | null;
  status: string | null;
  evaluationMode: string | null;
  isMutable: boolean;
  isFinalized: boolean;
  uiState: string;
  overallScore: number | null;
  scoreLabel: string | null;
  confidence: string | null;
  evidenceQuality: string | null;
  scoringVersion: string | null;
  scoreAvailable: boolean;
  eventCount: number;
  recentEvents: CurrentAssignmentEventSummary[];
  startedAt: string | null;
  completedAt: string | null;
  finalizedAt: string | null;
}
