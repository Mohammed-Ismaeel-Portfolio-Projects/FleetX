/**
 * Driver device registration API (D12) — never sends driver_id.
 */

import { apiClient } from '@/lib/api/client';
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

export async function registerDriverDevice(input: {
  pushToken: string;
  platform: 'android' | 'ios';
  deviceId?: string | null;
}): Promise<void> {
  try {
    await apiClient.post<ApiEnvelope<unknown>>('/driver/devices/register', {
      push_token: input.pushToken,
      platform: input.platform,
      device_id: input.deviceId ?? undefined,
    });
  } catch (e) {
    throw toWorkError(e, 'Could not register this device for alerts.');
  }
}

export async function unregisterDriverDevice(pushToken: string): Promise<void> {
  try {
    await apiClient.post('/driver/devices/unregister', { push_token: pushToken });
  } catch (e) {
    throw toWorkError(e, 'Could not unregister this device.');
  }
}
