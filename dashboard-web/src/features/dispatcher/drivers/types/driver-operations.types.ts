/** Official driver status vocabulary (DriverSeeder). */
export type DriverStatus = 'Available' | 'Busy' | 'Offline' | 'On Leave' | 'Suspended' | string;

/** Dispatch availability from backend SSOT (GetDriverAvailabilityAction rules). */
export type DispatchAvailability = 'Available' | 'Busy' | 'Offline' | 'On Leave' | 'Suspended' | string;

export interface DriverTracking {
  latitude: number | null;
  longitude: number | null;
  speed: number | null;
  heading: number | null;
  movementStatus: string | null;
  onlineStatus: string | null;
  signalQuality: number | null;
  lastUpdateAt: string | null;
}

/** Category projection from driver_scores (backend-authoritative). */
export interface DriverCategoryScore {
  score: number | null;
  sampleCount: number;
  evaluableSampleCount: number;
}

/**
 * Driver performance projection (DRIVER-EVAL-05/06).
 * Frontend never recalculates scores — display-only.
 */
export interface DriverScore {
  score: number | null;
  /** @deprecated Prefer evaluatedAssignmentsCount — same EVAL-05 semantics. */
  totalDeliveries: number | null;
  /** @deprecated Prefer lateEvaluationsCount — late arrival/departure evaluations. */
  lateDeliveries: number | null;
  scoreLabel: string | null;
  confidence: string | null;
  maturity: string | null;
  trend: string | null;
  recentScore: number | null;
  previousScore: number | null;
  trendDelta: number | null;
  evaluatedAssignmentsCount: number;
  incompleteAssignmentsCount: number;
  lateEvaluationsCount: number;
  categoryScores: Record<string, DriverCategoryScore> | null;
  aggregationVersion: string | null;
  lastEvaluatedAt: string | null;
  aggregatedAt: string | null;
  scoreAvailable: boolean;
}

export interface DriverPerformanceSummary extends DriverScore {
  driverId: number | null;
  overallScore: number | null;
  scoringVersionsSeen: string[];
}

export interface EvaluationHistoryItem {
  evaluationId: number;
  assignmentId: number;
  shipmentId: string | null;
  revision: number;
  status: string | null;
  overallScore: number | null;
  scoreLabel: string | null;
  confidence: string | null;
  evidenceQuality: string | null;
  scoringVersion: string | null;
  finalizedAt: string | null;
  assignmentCode: string | null;
  shipmentNumber: string | null;
  categoryScores: Record<string, unknown> | null;
}

export interface EvaluationDetail {
  evaluationId: number;
  assignmentId: number;
  overallScore: number | null;
  scoreLabel: string | null;
  confidence: string | null;
  evidenceQuality: string | null;
  scoringVersion: string | null;
  categoryScores: Record<string, unknown> | null;
  configuredWeights: Record<string, number> | null;
  events: Array<{
    id: number;
    eventCode: string | null;
    category: string | null;
    severity: string | null;
    occurredAt: string | null;
    detectorCode: string | null;
  }>;
  assignment: { id: number; assignmentCode: string | null } | null;
  shipment: {
    id: string;
    shipmentNumber: string | null;
    pickupCity: string | null;
    deliveryCity: string | null;
  } | null;
}

export interface DriverAssignment {
  id: string;
  assignmentCode: string | null;
  isActive: boolean;
  vehicleCode: string | null;
  vehiclePlate: string | null;
  shipmentId: string | null;
  shipmentNumber: string | null;
  pickupCity: string | null;
  deliveryCity: string | null;
}

export interface Driver {
  id: string;
  /** Linked login User id for messaging (M1); null = account not linked. */
  userId: number | null;
  employeeNumber: string;
  name: string;
  phone: string | null;
  email: string | null;
  nationality: string | null;
  employmentDate: string | null;
  licenseNumber: string | null;
  licenseClass: string | null;
  licenseExpiry: string | null;
  medicalCertificateExpiry: string | null;
  yearsOfExperience: number | null;
  isActive: boolean;
  status: DriverStatus;
  /** Dispatch availability — backend SSOT, not is_active */
  dispatchAvailability: DispatchAvailability | null;
  hasActiveAssignment: boolean;
  assignment: DriverAssignment | null;
  tracking: DriverTracking | null;
  score: DriverScore | null;
  notes: string | null;
  emergencyContactName: string | null;
  emergencyContactPhone: string | null;
  companyId?: string | null;
}

export interface DriverKPIs {
  total_drivers: number;
  available: number;
  busy: number;
  offline: number;
  on_leave: number;
  suspended: number;
  available_percentage: number;
  busy_percentage: number;
  offline_percentage: number;
  on_leave_percentage: number;
  suspended_percentage: number;
}

export interface DriverPagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}
