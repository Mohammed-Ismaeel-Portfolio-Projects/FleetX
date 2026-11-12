import { axiosClientWithInterceptors } from '../../../../core/api';
import type {
  CurrentAssignmentEvaluationView,
  OverallPerformanceCompact,
} from '../types/driver-performance.types';

interface ApiEnvelope<T> {
  data: T;
  message: string;
}

class TrackingPerformanceApiError extends Error {
  statusCode?: number;

  constructor(message: string, statusCode?: number) {
    super(message);
    this.name = 'TrackingPerformanceApiError';
    this.statusCode = statusCode;
  }
}

function toError(error: unknown, fallback: string): TrackingPerformanceApiError {
  if (error && typeof error === 'object' && 'response' in error) {
    const response = (error as { response?: { status?: number; data?: { message?: string } } }).response;
    return new TrackingPerformanceApiError(
      response?.data?.message || fallback,
      response?.status
    );
  }
  if (error instanceof Error) {
    return new TrackingPerformanceApiError(error.message);
  }
  return new TrackingPerformanceApiError(fallback);
}

/** GET /drivers/performance/summary?ids= — compact overall from driver_scores (08A). */
export async function getOverallPerformanceCompact(driverId: string): Promise<OverallPerformanceCompact> {
  try {
    const response = await axiosClientWithInterceptors.get<
      ApiEnvelope<{ data: Array<Record<string, unknown>> }>
    >('/drivers/performance/summary', { params: { ids: driverId } });

    const row = response.data.data?.data?.[0] ?? null;
    if (!row) {
      return {
        driverId: Number(driverId) || null,
        overallScore: null,
        score: null,
        scoreLabel: null,
        confidence: null,
        maturity: 'unavailable',
        trend: 'insufficient_data',
        trendDelta: null,
        scoreAvailable: false,
      };
    }

    const overall = (row.overall_score as number | null) ?? (row.score as number | null) ?? null;

    return {
      driverId: row.driver_id != null ? Number(row.driver_id) : Number(driverId) || null,
      overallScore: overall,
      score: overall,
      scoreLabel: (row.score_label as string | null) ?? null,
      confidence: (row.confidence as string | null) ?? null,
      maturity: (row.maturity as string | null) ?? 'unavailable',
      trend: (row.trend as string | null) ?? 'insufficient_data',
      trendDelta: (row.trend_delta as number | null) ?? null,
      scoreAvailable: Boolean(row.score_available),
    };
  } catch (error) {
    throw toError(error, 'Failed to load driver performance');
  }
}

/** GET /assignments/{id}/evaluation/current — current trip evaluation (08A). */
export async function getCurrentAssignmentEvaluation(
  assignmentId: string
): Promise<CurrentAssignmentEvaluationView> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<Record<string, unknown>>>(
      `/assignments/${assignmentId}/evaluation/current`
    );
    const raw = response.data.data;
    const events = Array.isArray(raw.recent_events) ? raw.recent_events : [];

    return {
      assignmentId: Number(raw.assignment_id),
      assignmentCode: (raw.assignment_code as string | null) ?? null,
      shipmentId: raw.shipment_id != null ? String(raw.shipment_id) : null,
      driverId: raw.driver_id != null ? Number(raw.driver_id) : null,
      vehicleId: raw.vehicle_id != null ? String(raw.vehicle_id) : null,
      evaluationAvailable: Boolean(raw.evaluation_available),
      evaluationId: raw.evaluation_id != null ? Number(raw.evaluation_id) : null,
      revision: raw.revision != null ? Number(raw.revision) : null,
      status: (raw.status as string | null) ?? null,
      evaluationMode: (raw.evaluation_mode as string | null) ?? null,
      isMutable: Boolean(raw.is_mutable),
      isFinalized: Boolean(raw.is_finalized),
      uiState: String(raw.ui_state ?? 'not_started'),
      overallScore: (raw.overall_score as number | null) ?? null,
      scoreLabel: (raw.score_label as string | null) ?? null,
      confidence: (raw.confidence as string | null) ?? null,
      evidenceQuality: (raw.evidence_quality as string | null) ?? null,
      scoringVersion: (raw.scoring_version as string | null) ?? null,
      scoreAvailable: Boolean(raw.score_available),
      eventCount: Number(raw.event_count ?? 0),
      recentEvents: events.map((event: Record<string, unknown>) => ({
        id: Number(event.id),
        eventCode: (event.event_code as string | null) ?? null,
        category: (event.category as string | null) ?? null,
        severity: (event.severity as string | null) ?? null,
        occurredAt: (event.occurred_at as string | null) ?? null,
      })),
      startedAt: (raw.started_at as string | null) ?? null,
      completedAt: (raw.completed_at as string | null) ?? null,
      finalizedAt: (raw.finalized_at as string | null) ?? null,
    };
  } catch (error) {
    throw toError(error, 'Failed to load current assignment evaluation');
  }
}

export function isValidTrackingId(value: string | null | undefined): boolean {
  if (!value) return false;
  const trimmed = value.trim();
  return trimmed !== '' && trimmed !== 'null' && trimmed !== 'undefined' && trimmed !== '0';
}

export { TrackingPerformanceApiError };
