/**
 * Driver assignment history API (D11) — read-only Driver-scoped contracts.
 * Never calls Ops /assignments or Client APIs. Never sends a client-supplied driver identity.
 */

import { apiClient } from '@/lib/api/client';
import { mapAssignmentSummary, mapHistoryPage } from './historyMapper';
import type { DriverAssignmentSummary, DriverHistoryPage } from './historyTypes';
import { DriverWorkApiError } from './workTypes';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
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

/** GET /driver/assignments/history */
export async function listAssignmentHistory(page = 1): Promise<DriverHistoryPage> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<unknown>>('/driver/assignments/history', {
      params: { page, per_page: 20 },
    });
    return mapHistoryPage(data.data);
  } catch (e) {
    throw toWorkError(e, 'Could not load assignment history.');
  }
}

/** GET /driver/assignments/{id}/summary */
export async function getAssignmentSummary(
  assignmentId: number | string
): Promise<DriverAssignmentSummary> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<unknown>>(
      `/driver/assignments/${assignmentId}/summary`
    );
    const mapped = mapAssignmentSummary(data.data);
    if (!mapped) {
      throw new DriverWorkApiError('Unexpected summary response from server.');
    }
    return mapped;
  } catch (e) {
    throw toWorkError(e, 'Could not load trip summary.');
  }
}
