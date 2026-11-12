/**
 * Driver self-service availability API (D13) — never sends driver_id.
 * Server response is authoritative. Do not cache a local toggle.
 */

import { apiClient } from '@/lib/api/client';
import { DriverWorkApiError } from './workTypes';

export type DriverAvailabilityKey = 'available' | 'unavailable';

export type DriverAvailability = {
  availability: DriverAvailabilityKey;
  canChange: boolean;
  reason: string | null;
};

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
  errors?: { code?: string };
}

type AvailabilityDto = {
  availability?: string;
  can_change?: boolean;
  reason?: string | null;
};

function toWorkError(error: unknown, fallback: string): DriverWorkApiError {
  if (error instanceof DriverWorkApiError) return error;
  const err = error as {
    response?: {
      status?: number;
      data?: { message?: string; errors?: { code?: string } };
    };
    message?: string;
  };
  return new DriverWorkApiError(
    err?.response?.data?.message || err?.message || fallback,
    err?.response?.status,
    err?.response?.data?.errors?.code ?? null
  );
}

export function mapAvailabilityReason(reason: string | null, fallback?: string): string {
  switch (reason) {
    case 'DRIVER_AVAILABILITY_BLOCKED_DURING_TRIP':
      return "You can't change availability while you're on an active trip.";
    case 'DRIVER_AVAILABILITY_LOCKED':
      return 'Availability is managed by operations. Contact your dispatcher.';
    default:
      return fallback ?? 'Could not update availability.';
  }
}

export function availabilityExplanation(key: DriverAvailabilityKey): string {
  return key === 'available'
    ? 'Available — you can receive assignments.'
    : "Unavailable — you won't be considered for new work.";
}

export function mapDriverAvailability(raw: unknown): DriverAvailability | null {
  if (!raw || typeof raw !== 'object') return null;
  const dto = raw as AvailabilityDto;
  if (dto.availability !== 'available' && dto.availability !== 'unavailable') {
    return null;
  }
  return {
    availability: dto.availability,
    canChange: Boolean(dto.can_change),
    reason: typeof dto.reason === 'string' ? dto.reason : null,
  };
}

/** GET /driver/availability */
export async function getDriverAvailability(): Promise<DriverAvailability> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<unknown>>('/driver/availability');
    const mapped = mapDriverAvailability(data.data);
    if (!mapped) {
      throw new DriverWorkApiError('Unexpected availability response from server.');
    }
    return mapped;
  } catch (e) {
    throw toWorkError(e, 'Could not load availability.');
  }
}

/** PATCH /driver/availability — identity comes from the session, never the body. */
export async function updateDriverAvailability(
  availability: DriverAvailabilityKey
): Promise<DriverAvailability> {
  try {
    const { data } = await apiClient.patch<ApiEnvelope<unknown>>('/driver/availability', {
      availability,
    });
    const mapped = mapDriverAvailability(data.data);
    if (!mapped) {
      throw new DriverWorkApiError('Unexpected availability response from server.');
    }
    return mapped;
  } catch (e) {
    throw toWorkError(e, 'Could not update availability.');
  }
}
