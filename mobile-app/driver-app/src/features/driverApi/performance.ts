/**
 * Driver self-service performance API (DRIVER-EVAL-06 / 08E).
 * Identity is server-resolved — never send a client-supplied driver_id.
 */

import { apiClient } from '@/lib/api/client';
import { DriverWorkApiError } from './workTypes';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
}

export interface DriverSelfPerformance {
  driverId: number | null;
  overallScore: number | null;
  scoreLabel: string | null;
  confidence: string | null;
  maturity: string | null;
  trend: string | null;
  trendDelta: number | null;
  evaluatedAssignmentsCount: number;
  incompleteAssignmentsCount: number;
  categoryScores: Record<string, { score: number | null; sampleCount: number }> | null;
  scoreAvailable: boolean;
  lastEvaluatedAt: string | null;
}

export interface DriverSelfEvaluationItem {
  evaluationId: number;
  assignmentId: number;
  overallScore: number | null;
  scoreLabel: string | null;
  confidence: string | null;
  status: string | null;
  finalizedAt: string | null;
  assignmentCode?: string | null;
}

export interface DriverSelfEvaluationEvent {
  id: number;
  eventCode: string | null;
  category: string | null;
  severity: string | null;
  occurredAt: string | null;
}

export interface DriverSelfCurrentAssignmentEvaluation {
  assignmentId: number;
  assignmentCode: string | null;
  evaluationAvailable: boolean;
  evaluationId: number | null;
  status: string | null;
  uiState: string;
  isMutable: boolean;
  isFinalized: boolean;
  overallScore: number | null;
  scoreLabel: string | null;
  confidence: string | null;
  evidenceQuality: string | null;
  scoreAvailable: boolean;
  eventCount: number;
  recentEvents: DriverSelfEvaluationEvent[];
  finalizedAt: string | null;
}

export interface DriverSelfEvaluationDetail {
  evaluationId: number;
  assignmentId: number;
  overallScore: number | null;
  scoreLabel: string | null;
  confidence: string | null;
  evidenceQuality: string | null;
  status: string | null;
  categoryScores: Record<string, unknown> | null;
  events: DriverSelfEvaluationEvent[];
  assignmentCode: string | null;
  shipmentNumber: string | null;
  finalizedAt: string | null;
}

function toWorkError(error: unknown, fallback: string): DriverWorkApiError {
  if (error instanceof DriverWorkApiError) return error;
  const err = error as {
    response?: { status?: number; data?: { message?: string } };
    message?: string;
  };
  return new DriverWorkApiError(
    err?.response?.data?.message || err?.message || fallback,
    err?.response?.status
  );
}

function mapEvent(event: Record<string, unknown>): DriverSelfEvaluationEvent {
  return {
    id: Number(event.id),
    eventCode: (event.event_code as string | null) ?? null,
    category: (event.category as string | null) ?? null,
    severity: (event.severity as string | null) ?? null,
    occurredAt: (event.occurred_at as string | null) ?? null,
  };
}

/** GET /driver/performance */
export async function getOwnPerformance(): Promise<DriverSelfPerformance> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<Record<string, unknown>>>('/driver/performance');
    const raw = data.data;
    const categories =
      (raw.category_scores as Record<string, { score?: number | null; sample_count?: number }> | null) ??
      null;
    const mappedCategories = categories
      ? Object.fromEntries(
          Object.entries(categories).map(([key, value]) => [
            key,
            { score: value?.score ?? null, sampleCount: Number(value?.sample_count ?? 0) },
          ])
        )
      : null;

    return {
      driverId: raw.driver_id != null ? Number(raw.driver_id) : null,
      overallScore: (raw.overall_score as number | null) ?? null,
      scoreLabel: (raw.score_label as string | null) ?? null,
      confidence: (raw.confidence as string | null) ?? null,
      maturity: (raw.maturity as string | null) ?? null,
      trend: (raw.trend as string | null) ?? null,
      trendDelta: (raw.trend_delta as number | null) ?? null,
      evaluatedAssignmentsCount: Number(raw.evaluated_assignments_count ?? 0),
      incompleteAssignmentsCount: Number(raw.incomplete_assignments_count ?? 0),
      categoryScores: mappedCategories,
      scoreAvailable: Boolean(raw.score_available),
      lastEvaluatedAt: (raw.last_evaluated_at as string | null) ?? null,
    };
  } catch (e) {
    throw toWorkError(e, 'Could not load performance.');
  }
}

/** GET /driver/performance/evaluations */
export async function listOwnEvaluations(page = 1): Promise<DriverSelfEvaluationItem[]> {
  try {
    const { data } = await apiClient.get<
      ApiEnvelope<{ data: Array<Record<string, unknown>> }>
    >('/driver/performance/evaluations', { params: { page, per_page: 10 } });
    return (data.data.data || []).map((row) => ({
      evaluationId: Number(row.evaluation_id),
      assignmentId: Number(row.assignment_id),
      overallScore: (row.overall_score as number | null) ?? null,
      scoreLabel: (row.score_label as string | null) ?? null,
      confidence: (row.confidence as string | null) ?? null,
      status: (row.status as string | null) ?? null,
      finalizedAt: (row.finalized_at as string | null) ?? null,
      assignmentCode: (row.assignment_code as string | null) ?? null,
    }));
  } catch (e) {
    throw toWorkError(e, 'Could not load evaluation history.');
  }
}

/** GET /driver/performance/evaluations/{evaluation} — finalized only. */
export async function getOwnEvaluationDetail(
  evaluationId: number
): Promise<DriverSelfEvaluationDetail> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<Record<string, unknown>>>(
      `/driver/performance/evaluations/${evaluationId}`
    );
    const raw = data.data;
    const events = Array.isArray(raw.events) ? raw.events : [];
    const assignment = (raw.assignment as Record<string, unknown> | null) ?? null;
    const shipment = (raw.shipment as Record<string, unknown> | null) ?? null;

    return {
      evaluationId: Number(raw.evaluation_id),
      assignmentId: Number(raw.assignment_id),
      overallScore: (raw.overall_score as number | null) ?? null,
      scoreLabel: (raw.score_label as string | null) ?? null,
      confidence: (raw.confidence as string | null) ?? null,
      evidenceQuality: (raw.evidence_quality as string | null) ?? null,
      status: (raw.status as string | null) ?? null,
      categoryScores: (raw.category_scores as Record<string, unknown> | null) ?? null,
      events: events.map((event) => mapEvent(event as Record<string, unknown>)),
      assignmentCode: assignment
        ? ((assignment.assignment_code as string | null) ?? null)
        : null,
      shipmentNumber: shipment
        ? ((shipment.shipment_number as string | null) ?? null)
        : null,
      finalizedAt: (raw.finalized_at as string | null) ?? null,
    };
  } catch (e) {
    throw toWorkError(e, 'Could not load evaluation detail.');
  }
}

/**
 * GET /driver/assignments/{assignment}/evaluation/current
 * Self endpoint only — never ops /assignments/... for Driver App.
 */
export async function getOwnCurrentAssignmentEvaluation(
  assignmentId: string
): Promise<DriverSelfCurrentAssignmentEvaluation> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<Record<string, unknown>>>(
      `/driver/assignments/${assignmentId}/evaluation/current`
    );
    const raw = data.data;
    const events = Array.isArray(raw.recent_events) ? raw.recent_events : [];

    return {
      assignmentId: Number(raw.assignment_id),
      assignmentCode: (raw.assignment_code as string | null) ?? null,
      evaluationAvailable: Boolean(raw.evaluation_available),
      evaluationId: raw.evaluation_id != null ? Number(raw.evaluation_id) : null,
      status: (raw.status as string | null) ?? null,
      uiState: String(raw.ui_state ?? 'not_started'),
      isMutable: Boolean(raw.is_mutable),
      isFinalized: Boolean(raw.is_finalized),
      overallScore: (raw.overall_score as number | null) ?? null,
      scoreLabel: (raw.score_label as string | null) ?? null,
      confidence: (raw.confidence as string | null) ?? null,
      evidenceQuality: (raw.evidence_quality as string | null) ?? null,
      scoreAvailable: Boolean(raw.score_available),
      eventCount: Number(raw.event_count ?? 0),
      recentEvents: events.map((event) => mapEvent(event as Record<string, unknown>)),
      finalizedAt: (raw.finalized_at as string | null) ?? null,
    };
  } catch (e) {
    throw toWorkError(e, 'Could not load trip performance.');
  }
}
