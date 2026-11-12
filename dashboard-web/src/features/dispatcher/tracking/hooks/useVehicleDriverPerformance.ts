import { useEffect, useState } from 'react';
import type {
  CurrentAssignmentEvaluationView,
  OverallPerformanceCompact,
} from '../types/driver-performance.types';
import {
  getCurrentAssignmentEvaluation,
  getOverallPerformanceCompact,
  isValidTrackingId,
  TrackingPerformanceApiError,
} from '../services/driver-performance.service';

export interface VehicleDriverPerformanceState {
  overall: OverallPerformanceCompact | null;
  current: CurrentAssignmentEvaluationView | null;
  loading: boolean;
  error: string | null;
  hasDriver: boolean;
  hasAssignment: boolean;
}

/**
 * Selected-vehicle only — does not fan out across the fleet on poll.
 * Re-fetches when driverId / assignmentId of the selection change.
 */
export function useVehicleDriverPerformance(
  driverId: string | null | undefined,
  assignmentId: string | null | undefined
): VehicleDriverPerformanceState {
  const hasDriver = isValidTrackingId(driverId);
  const hasAssignment = isValidTrackingId(assignmentId);

  const [overall, setOverall] = useState<OverallPerformanceCompact | null>(null);
  const [current, setCurrent] = useState<CurrentAssignmentEvaluationView | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!hasDriver && !hasAssignment) {
      setOverall(null);
      setCurrent(null);
      setError(null);
      setLoading(false);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);

    void (async () => {
      try {
        const [overallResult, currentResult] = await Promise.all([
          hasDriver && driverId
            ? getOverallPerformanceCompact(driverId)
            : Promise.resolve(null),
          hasAssignment && assignmentId
            ? getCurrentAssignmentEvaluation(assignmentId)
            : Promise.resolve(null),
        ]);

        if (cancelled) return;
        setOverall(overallResult);
        setCurrent(currentResult);
      } catch (e) {
        if (cancelled) return;
        setOverall(null);
        setCurrent(null);
        setError(
          e instanceof TrackingPerformanceApiError
            ? e.message
            : e instanceof Error
              ? e.message
              : 'Could not load driver performance'
        );
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [driverId, assignmentId, hasDriver, hasAssignment]);

  return { overall, current, loading, error, hasDriver, hasAssignment };
}
