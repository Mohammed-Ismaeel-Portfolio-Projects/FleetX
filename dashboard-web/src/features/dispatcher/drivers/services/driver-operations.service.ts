import { axiosClientWithInterceptors } from '../../../../core/api';
import type {
  Driver,
  DriverKPIs,
  DriverPagination,
  DriverPerformanceSummary,
  EvaluationDetail,
  EvaluationHistoryItem,
} from '../types/driver-operations.types';
import { mapCategoryScores } from '../utils/performanceDisplay';

interface ApiEnvelope<T> {
  data: T;
  message: string;
}

interface DriverApiResponse {
  id: string;
  user_id?: number | string | null;
  employee_number: string;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  nationality: string | null;
  employment_date: string | null;
  license_number: string | null;
  license_class: string | null;
  license_expiry_date: string | null;
  medical_certificate_expiry: string | null;
  years_of_experience: number | null;
  status_id: number | string;
  is_active: boolean;
  emergency_contact_name: string | null;
  emergency_contact_phone: string | null;
  notes: string | null;
  company_id?: string | null;
  company?: {
    id: string;
    company_name?: string;
  } | null;
  status?: {
    id: number | string;
    name: string;
    type?: string;
  } | null;
  has_active_assignment?: boolean;
  dispatch_availability?: string | null;
  active_assignment?: {
    id: string;
    assignment_code?: string;
    is_active?: boolean;
    vehicle?: {
      id: string;
      operational_code: string;
      plate_number: string;
    } | null;
    shipment?: {
      id: string;
      shipment_number: string;
      pickup_city?: string;
      delivery_city?: string;
    } | null;
  } | null;
  tracking_state?: {
    latitude?: number | null;
    longitude?: number | null;
    speed?: number | null;
    heading?: number | null;
    movement_status?: string | null;
    online_status?: string | null;
    signal_quality?: number | null;
    last_update_at?: string | null;
  } | null;
  driver_score?: {
    score?: number | null;
    total_deliveries?: number | null;
    late_deliveries?: number | null;
    score_label?: string | null;
    confidence?: string | null;
    maturity?: string | null;
    trend?: string | null;
    recent_score?: number | null;
    previous_score?: number | null;
    trend_delta?: number | null;
    evaluated_assignments_count?: number;
    incomplete_assignments_count?: number;
    late_evaluations_count?: number;
    category_scores?: Record<string, { score?: number | null; sample_count?: number; evaluable_sample_count?: number }> | null;
    aggregation_version?: string | null;
    last_evaluated_at?: string | null;
    aggregated_at?: string | null;
    score_available?: boolean;
  } | null;
}

class DriverApiError extends Error {
  statusCode?: number;

  constructor(message: string, statusCode?: number) {
    super(message);
    this.name = 'DriverApiError';
    this.statusCode = statusCode;
  }
}

function toDriverApiError(error: any, defaultMessage: string): DriverApiError {
  if (error instanceof DriverApiError) return error;
  const message = error?.response?.data?.message || error?.message || defaultMessage;
  const statusCode = error?.response?.status;
  return new DriverApiError(message, statusCode);
}

/** Pass-through mapper — no status remaps, no fabricated metrics. */
export function mapApiDriverToDriver(api: DriverApiResponse): Driver {
  const assignment = api.active_assignment ?? null;
  const tracking = api.tracking_state ?? null;
  const score = api.driver_score ?? null;

  const linkedUserId =
    api.user_id === null || api.user_id === undefined || api.user_id === ''
      ? null
      : Number(api.user_id);

  return {
    id: api.id,
    userId: linkedUserId != null && Number.isFinite(linkedUserId) ? linkedUserId : null,
    employeeNumber: api.employee_number,
    name: api.full_name || `${api.first_name} ${api.last_name}`.trim(),
    phone: api.phone ?? null,
    email: api.email ?? null,
    nationality: api.nationality ?? null,
    employmentDate: api.employment_date ?? null,
    licenseNumber: api.license_number ?? null,
    licenseClass: api.license_class ?? null,
    licenseExpiry: api.license_expiry_date ?? null,
    medicalCertificateExpiry: api.medical_certificate_expiry ?? null,
    yearsOfExperience: api.years_of_experience ?? null,
    isActive: Boolean(api.is_active),
    status: api.status?.name || 'Unavailable',
    dispatchAvailability: api.dispatch_availability ?? null,
    hasActiveAssignment: Boolean(api.has_active_assignment ?? assignment),
    assignment: assignment
      ? {
          id: assignment.id,
          assignmentCode: assignment.assignment_code ?? null,
          isActive: Boolean(assignment.is_active),
          vehicleCode: assignment.vehicle?.operational_code ?? null,
          vehiclePlate: assignment.vehicle?.plate_number ?? null,
          shipmentId: assignment.shipment?.id ?? null,
          shipmentNumber: assignment.shipment?.shipment_number ?? null,
          pickupCity: assignment.shipment?.pickup_city ?? null,
          deliveryCity: assignment.shipment?.delivery_city ?? null,
        }
      : null,
    tracking: tracking
      ? {
          latitude: tracking.latitude ?? null,
          longitude: tracking.longitude ?? null,
          speed: tracking.speed ?? null,
          heading: tracking.heading ?? null,
          movementStatus: tracking.movement_status ?? null,
          onlineStatus: tracking.online_status ?? null,
          signalQuality: tracking.signal_quality ?? null,
          lastUpdateAt: tracking.last_update_at ?? null,
        }
      : null,
    score: score
      ? {
          score: score.score ?? null,
          totalDeliveries: score.total_deliveries ?? null,
          lateDeliveries: score.late_deliveries ?? null,
          scoreLabel: score.score_label ?? null,
          confidence: score.confidence ?? null,
          maturity: score.maturity ?? null,
          trend: score.trend ?? null,
          recentScore: score.recent_score ?? null,
          previousScore: score.previous_score ?? null,
          trendDelta: score.trend_delta ?? null,
          evaluatedAssignmentsCount: Number(score.evaluated_assignments_count ?? score.total_deliveries ?? 0),
          incompleteAssignmentsCount: Number(score.incomplete_assignments_count ?? 0),
          lateEvaluationsCount: Number(score.late_evaluations_count ?? score.late_deliveries ?? 0),
          categoryScores: mapCategoryScores(score.category_scores),
          aggregationVersion: score.aggregation_version ?? null,
          lastEvaluatedAt: score.last_evaluated_at ?? null,
          aggregatedAt: score.aggregated_at ?? null,
          scoreAvailable: Boolean(score.score_available ?? (score.score != null && score.maturity !== 'unavailable')),
        }
      : null,
    notes: api.notes ?? null,
    emergencyContactName: api.emergency_contact_name ?? null,
    emergencyContactPhone: api.emergency_contact_phone ?? null,
    companyId: api.company_id ?? api.company?.id ?? null,
  };
}

export type DriverQueryParams = {
  search?: string;
  status?: string;
  status_id?: string | number;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_direction?: string;
};

export async function getDrivers(
  params?: DriverQueryParams
): Promise<{ drivers: Driver[]; pagination: DriverPagination }> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<{
      data: DriverApiResponse[];
      meta: DriverPagination;
    }>>('/drivers', { params });

    const payload = response.data.data;
    const rows = payload.data || [];
    const meta = payload.meta;

    return {
      drivers: rows.map(mapApiDriverToDriver),
      pagination: {
        current_page: meta?.current_page || 1,
        per_page: meta?.per_page || 15,
        total: meta?.total || 0,
        last_page: meta?.last_page || 1,
      },
    };
  } catch (error) {
    throw toDriverApiError(error, 'Failed to fetch drivers');
  }
}

export async function getDriverById(id: string): Promise<Driver> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<DriverApiResponse>>(`/drivers/${id}`);
    return mapApiDriverToDriver(response.data.data);
  } catch (error) {
    throw toDriverApiError(error, 'Failed to fetch driver');
  }
}

export async function getDriverKPIs(): Promise<DriverKPIs> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<DriverKPIs>>('/drivers/kpis');
    return response.data.data;
  } catch (error) {
    throw toDriverApiError(error, 'Failed to fetch driver KPIs');
  }
}

/** GET /drivers/{id}/performance — reads driver_scores projection only. */
export async function getDriverPerformance(driverId: string): Promise<DriverPerformanceSummary> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<Record<string, unknown>>>(
      `/drivers/${driverId}/performance`
    );
    const raw = response.data.data;
    return {
      driverId: raw.driver_id != null ? Number(raw.driver_id) : null,
      overallScore: (raw.overall_score as number | null) ?? null,
      score: (raw.overall_score as number | null) ?? (raw.score as number | null) ?? null,
      scoreLabel: (raw.score_label as string | null) ?? null,
      confidence: (raw.confidence as string | null) ?? null,
      maturity: (raw.maturity as string | null) ?? null,
      trend: (raw.trend as string | null) ?? null,
      recentScore: (raw.recent_score as number | null) ?? null,
      previousScore: (raw.previous_score as number | null) ?? null,
      trendDelta: (raw.trend_delta as number | null) ?? null,
      evaluatedAssignmentsCount: Number(raw.evaluated_assignments_count ?? 0),
      incompleteAssignmentsCount: Number(raw.incomplete_assignments_count ?? 0),
      lateEvaluationsCount: Number(raw.late_evaluations_count ?? raw.late_deliveries ?? 0),
      totalDeliveries: Number(raw.total_deliveries ?? raw.evaluated_assignments_count ?? 0),
      lateDeliveries: Number(raw.late_deliveries ?? 0),
      categoryScores: mapCategoryScores(
        raw.category_scores as Record<string, { score?: number | null; sample_count?: number; evaluable_sample_count?: number }> | null
      ),
      aggregationVersion: (raw.aggregation_version as string | null) ?? null,
      lastEvaluatedAt: (raw.last_evaluated_at as string | null) ?? null,
      aggregatedAt: (raw.aggregated_at as string | null) ?? null,
      scoreAvailable: Boolean(raw.score_available),
      scoringVersionsSeen: Array.isArray(raw.scoring_versions_seen)
        ? (raw.scoring_versions_seen as string[])
        : [],
    };
  } catch (error) {
    throw toDriverApiError(error, 'Failed to fetch driver performance');
  }
}

/** GET /drivers/{id}/performance/evaluations */
export async function getDriverEvaluations(
  driverId: string,
  page = 1,
  perPage = 10
): Promise<{ items: EvaluationHistoryItem[]; pagination: DriverPagination }> {
  try {
    const response = await axiosClientWithInterceptors.get<
      ApiEnvelope<{ data: Array<Record<string, unknown>>; meta: DriverPagination }>
    >(`/drivers/${driverId}/performance/evaluations`, { params: { page, per_page: perPage } });
    const payload = response.data.data;
    const items = (payload.data || []).map((row) => ({
      evaluationId: Number(row.evaluation_id),
      assignmentId: Number(row.assignment_id),
      shipmentId: (row.shipment_id as string | null) ?? null,
      revision: Number(row.revision ?? 1),
      status: (row.status as string | null) ?? null,
      overallScore: (row.overall_score as number | null) ?? null,
      scoreLabel: (row.score_label as string | null) ?? null,
      confidence: (row.confidence as string | null) ?? null,
      evidenceQuality: (row.evidence_quality as string | null) ?? null,
      scoringVersion: (row.scoring_version as string | null) ?? null,
      finalizedAt: (row.finalized_at as string | null) ?? null,
      assignmentCode: (row.assignment_code as string | null) ?? null,
      shipmentNumber: (row.shipment_number as string | null) ?? null,
      categoryScores: (row.category_scores as Record<string, unknown> | null) ?? null,
    }));
    return {
      items,
      pagination: {
        current_page: payload.meta?.current_page || 1,
        per_page: payload.meta?.per_page || perPage,
        total: payload.meta?.total || 0,
        last_page: payload.meta?.last_page || 1,
      },
    };
  } catch (error) {
    throw toDriverApiError(error, 'Failed to fetch evaluation history');
  }
}

/** GET /drivers/{id}/performance/evaluations/{evaluationId} */
export async function getDriverEvaluationDetail(
  driverId: string,
  evaluationId: number
): Promise<EvaluationDetail> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<Record<string, unknown>>>(
      `/drivers/${driverId}/performance/evaluations/${evaluationId}`
    );
    const raw = response.data.data;
    const events = Array.isArray(raw.events) ? raw.events : [];
    return {
      evaluationId: Number(raw.evaluation_id),
      assignmentId: Number(raw.assignment_id),
      overallScore: (raw.overall_score as number | null) ?? null,
      scoreLabel: (raw.score_label as string | null) ?? null,
      confidence: (raw.confidence as string | null) ?? null,
      evidenceQuality: (raw.evidence_quality as string | null) ?? null,
      scoringVersion: (raw.scoring_version as string | null) ?? null,
      categoryScores: (raw.category_scores as Record<string, unknown> | null) ?? null,
      configuredWeights: (raw.configured_weights as Record<string, number> | null) ?? null,
      events: events.map((event: Record<string, unknown>) => ({
        id: Number(event.id),
        eventCode: (event.event_code as string | null) ?? null,
        category: (event.category as string | null) ?? null,
        severity: (event.severity as string | null) ?? null,
        occurredAt: (event.occurred_at as string | null) ?? null,
        detectorCode: (event.detector_code as string | null) ?? null,
      })),
      assignment: raw.assignment
        ? {
            id: Number((raw.assignment as Record<string, unknown>).id),
            assignmentCode: ((raw.assignment as Record<string, unknown>).assignment_code as string | null) ?? null,
          }
        : null,
      shipment: raw.shipment
        ? {
            id: String((raw.shipment as Record<string, unknown>).id),
            shipmentNumber: ((raw.shipment as Record<string, unknown>).shipment_number as string | null) ?? null,
            pickupCity: ((raw.shipment as Record<string, unknown>).pickup_city as string | null) ?? null,
            deliveryCity: ((raw.shipment as Record<string, unknown>).delivery_city as string | null) ?? null,
          }
        : null,
    };
  } catch (error) {
    throw toDriverApiError(error, 'Failed to fetch evaluation detail');
  }
}

export { DriverApiError };
