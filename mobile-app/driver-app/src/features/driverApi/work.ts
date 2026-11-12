/**
 * Driver work API — D2 reads + D3 acknowledge/start + D4 execution phases.
 * Never calls Ops /assignments mutations or Client APIs.
 */

import { Platform } from 'react-native';
import { apiClient } from '@/lib/api/client';
import type { DriverExecutionCommand } from './executionPhase';
import { mapDriverWorkAssignment } from './workMapper';
import { mapDriverTripMap } from './tripMapper';
import type { DeviceFix, DriverTripMap } from './tripTypes';
import {
  DriverWorkApiError,
  type DriverWorkAssignment,
} from './workTypes';
import { offlineQueueStore } from '@/lib/network/offlineQueueStore';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
}

async function appendFileSpecToFormData(
  form: FormData,
  fieldName: string,
  fileSpec: { uri: string; name: string; type: string }
): Promise<void> {
  if (Platform.OS === 'web') {
    try {
      const res = await fetch(fileSpec.uri);
      const blob = await res.blob();
      const file = new File([blob], fileSpec.name, { type: blob.type || fileSpec.type });
      form.append(fieldName, file);
      return;
    } catch (e) {
      console.warn(`[FormData] Web blob conversion fallback for ${fieldName}:`, e);
    }
  }

  form.append(fieldName, {
    uri: fileSpec.uri,
    name: fileSpec.name,
    type: fileSpec.type,
  } as any);
}

export type DriverOutcome = {
  deliveryVerified: boolean;
  podSubmitted: boolean;
  podConfirmedAt: string | null;
  completeEligible: boolean;
  failEligible: boolean;
  shipmentStatus: string | null;
  executionPhase: string | null;
};

export type DriverIssueTypeCode =
  | 'traffic'
  | 'traffic_light'
  | 'vehicle_problem'
  | 'accident'
  | 'loading_problem'
  | 'delivery_problem'
  | 'other';

/** GET /driver/assignments/{id}/outcome — D7 */
export async function getOutcome(
  assignmentId: number | string
): Promise<{ assignment: DriverWorkAssignment; outcome: DriverOutcome }> {
  try {
    const { data } = await apiClient.get<
      ApiEnvelope<{
        assignment: unknown;
        outcome: {
          delivery_verified?: boolean;
          pod_submitted?: boolean;
          pod_confirmed_at?: string | null;
          complete_eligible?: boolean;
          fail_eligible?: boolean;
          shipment_status?: string | null;
          execution_phase?: string | null;
        };
      }>
    >(`/driver/assignments/${assignmentId}/outcome`);
    const assignment = mapRequired(data.data?.assignment, 'Unexpected outcome response.');
    const o = data.data?.outcome ?? {};
    return {
      assignment,
      outcome: {
        deliveryVerified: Boolean(o.delivery_verified),
        podSubmitted: Boolean(o.pod_submitted),
        podConfirmedAt: o.pod_confirmed_at ?? null,
        completeEligible: Boolean(o.complete_eligible),
        failEligible: Boolean(o.fail_eligible),
        shipmentStatus: o.shipment_status ?? null,
        executionPhase: o.execution_phase ?? null,
      },
    };
  } catch (e) {
    throw toWorkError(e, 'Could not load delivery outcome.');
  }
}

export interface PodEvidenceInput {
  photo: { uri: string; name: string; type: string };
  signature: { uri: string; name: string; type: string };
  receiverName: string;
  receiverRole?: string | null;
  conditionStatus?: 'good' | 'damaged' | 'partial' | 'refused';
  deliveryNotes?: string | null;
  latitude?: number | null;
  longitude?: number | null;
}

/** POST /driver/assignments/{id}/pod — multipart photo + signature + evidence metadata */
export async function submitPod(
  assignmentId: number | string,
  photoOrInput: { uri: string; name: string; type: string } | PodEvidenceInput,
  signatureArg?: { uri: string; name: string; type: string }
): Promise<{ assignment: DriverWorkAssignment; pod: { submitted: boolean; confirmedAt: string | null } }> {
  try {
    const form = new FormData();
    if ('photo' in photoOrInput) {
      const input = photoOrInput as PodEvidenceInput;
      await appendFileSpecToFormData(form, 'photo', input.photo);
      await appendFileSpecToFormData(form, 'signature', input.signature);
      form.append('receiver_name', input.receiverName);
      if (input.receiverRole) form.append('receiver_role', input.receiverRole);
      if (input.conditionStatus) form.append('condition_status', input.conditionStatus);
      if (input.deliveryNotes) form.append('delivery_notes', input.deliveryNotes);
      if (input.latitude != null) form.append('latitude', String(input.latitude));
      if (input.longitude != null) form.append('longitude', String(input.longitude));
    } else {
      await appendFileSpecToFormData(form, 'photo', photoOrInput);
      if (signatureArg) await appendFileSpecToFormData(form, 'signature', signatureArg);
    }

    const { data } = await apiClient.post<
      ApiEnvelope<{
        assignment: unknown;
        pod: { submitted?: boolean; confirmed_at?: string | null };
      }>
    >(`/driver/assignments/${assignmentId}/pod`, form);
    const assignment = mapRequired(data.data?.assignment, 'Unexpected POD response.');
    return {
      assignment,
      pod: {
        submitted: Boolean(data.data?.pod?.submitted),
        confirmedAt: data.data?.pod?.confirmed_at ?? null,
      },
    };
  } catch (e) {
    if (isNetworkError(e)) {
      const payload = 'photo' in photoOrInput ? photoOrInput : { photo: photoOrInput, signature: signatureArg };
      await offlineQueueStore.enqueueEvent('POD_SUBMIT', assignmentId, payload);
    }
    throw toWorkError(e, 'Could not submit proof of delivery.');
  }
}

const EXECUTION_PATHS: Record<DriverExecutionCommand, string> = {
  pickup_arrive: 'pickup/arrive',
  pickup_loading: 'pickup/loading',
  pickup_loading_complete: 'pickup/loading-complete',
  delivery_arrive: 'delivery/arrive',
  delivery_unloading: 'delivery/unloading',
  delivery_unloading_complete: 'delivery/unloading-complete',
};

function isNetworkError(e: unknown): boolean {
  if (!e) return false;
  const err = e as { response?: unknown; code?: string; message?: string };
  if (!err.response && (err.code === 'ERR_NETWORK' || err.message?.includes('Network Error') || err.message?.includes('timeout') || err.message?.includes('Could not connect'))) {
    return true;
  }
  return false;
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

function mapRequired(data: unknown, fallback: string): DriverWorkAssignment {
  const mapped = mapDriverWorkAssignment(data);
  if (!mapped) {
    throw new DriverWorkApiError(fallback);
  }
  return mapped;
}

/** GET /driver/work/active */
export async function getActiveAssignment(): Promise<DriverWorkAssignment | null> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<unknown>>('/driver/work/active');
    if (data.data == null) return null;
    return mapRequired(data.data, 'Unexpected active work response from server.');
  } catch (e) {
    throw toWorkError(e, 'Could not load active assignment.');
  }
}

/** GET /driver/assignments/{id} */
export async function getAssignment(assignmentId: number | string): Promise<DriverWorkAssignment> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<unknown>>(
      `/driver/assignments/${assignmentId}`
    );
    return mapRequired(data.data, 'Unexpected assignment response from server.');
  } catch (e) {
    throw toWorkError(e, 'Could not load assignment.');
  }
}

/** POST /driver/assignments/{id}/acknowledge — Assigned → Ready */
export async function acknowledgeAssignment(
  assignmentId: number | string
): Promise<DriverWorkAssignment> {
  try {
    const { data } = await apiClient.post<ApiEnvelope<unknown>>(
      `/driver/assignments/${assignmentId}/acknowledge`
    );
    return mapRequired(data.data, 'Unexpected acknowledge response from server.');
  } catch (e) {
    if (isNetworkError(e)) {
      await offlineQueueStore.enqueueEvent('ACKNOWLEDGE', assignmentId, {});
    }
    throw toWorkError(e, 'Could not acknowledge assignment.');
  }
}

/** POST /driver/assignments/{id}/start — Ready → In Operation */
export async function startAssignment(
  assignmentId: number | string
): Promise<DriverWorkAssignment> {
  try {
    const { data } = await apiClient.post<ApiEnvelope<unknown>>(
      `/driver/assignments/${assignmentId}/start`
    );
    return mapRequired(data.data, 'Unexpected start response from server.');
  } catch (e) {
    if (isNetworkError(e)) {
      await offlineQueueStore.enqueueEvent('START_TRIP', assignmentId, {});
    }
    throw toWorkError(e, 'Could not start assignment.');
  }
}

/** POST /driver/assignments/{id}/pickup|delivery/... — D4 phase transition */
export async function transitionExecutionPhase(
  assignmentId: number | string,
  command: DriverExecutionCommand
): Promise<DriverWorkAssignment> {
  const suffix = EXECUTION_PATHS[command];
  try {
    const { data } = await apiClient.post<ApiEnvelope<unknown>>(
      `/driver/assignments/${assignmentId}/${suffix}`
    );
    return mapRequired(data.data, 'Unexpected execution response from server.');
  } catch (e) {
    if (isNetworkError(e)) {
      await offlineQueueStore.enqueueEvent('PHASE_TRANSITION', assignmentId, { command });
    }
    throw toWorkError(e, 'Could not update trip phase.');
  }
}

/** POST /driver/assignments/{id}/pickup|delivery/verify — D5 QR */
export async function verifyAssignment(
  assignmentId: number | string,
  event: 'pickup' | 'delivery',
  challenge: string
): Promise<{ assignment: DriverWorkAssignment; verification: { event: string; verified_at: string } }> {
  const path =
    event === 'pickup'
      ? `/driver/assignments/${assignmentId}/pickup/verify`
      : `/driver/assignments/${assignmentId}/delivery/verify`;
  try {
    const { data } = await apiClient.post<
      ApiEnvelope<{
        assignment: unknown;
        verification: { event: string; verified_at: string; challenge_id?: string };
      }>
    >(path, { challenge });
    const assignment = mapRequired(data.data?.assignment, 'Unexpected verify response from server.');
    const verification = data.data?.verification;
    if (!verification || typeof verification.event !== 'string') {
      throw new DriverWorkApiError('Unexpected verify response from server.');
    }
    return {
      assignment,
      verification: {
        event: verification.event,
        verified_at: verification.verified_at,
      },
    };
  } catch (e) {
    throw toWorkError(e, 'Could not verify QR challenge.');
  }
}

/** POST /driver/assignments/{id}/tracking — D6 telemetry */
export async function submitTracking(
  assignmentId: number | string,
  fix: DeviceFix
): Promise<{ position: unknown; deduped: boolean }> {
  try {
    const { data } = await apiClient.post<
      ApiEnvelope<{ position?: unknown; deduped?: boolean }>
    >(`/driver/assignments/${assignmentId}/tracking`, {
      latitude: fix.latitude,
      longitude: fix.longitude,
      accuracy: fix.accuracy,
      speed: fix.speed,
      heading: fix.heading,
      recorded_at: fix.recordedAt,
    });
    return {
      position: data.data?.position ?? null,
      deduped: Boolean(data.data?.deduped),
    };
  } catch (e) {
    throw toWorkError(e, 'Could not submit location.');
  }
}

/** GET /driver/assignments/{id}/trip-map — D6 map DTO */
export async function getTripMap(assignmentId: number | string): Promise<DriverTripMap> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<unknown>>(
      `/driver/assignments/${assignmentId}/trip-map`
    );
    const mapped = mapDriverTripMap(data.data);
    if (!mapped) {
      throw new DriverWorkApiError('Unexpected trip map response from server.');
    }
    return mapped;
  } catch (e) {
    throw toWorkError(e, 'Could not load trip map.');
  }
}


/** POST /driver/assignments/{id}/complete */
export async function completeAssignment(
  assignmentId: number | string
): Promise<{ assignment: DriverWorkAssignment; completed: boolean; completedAt: string | null }> {
  try {
    const { data } = await apiClient.post<
      ApiEnvelope<{
        assignment: unknown;
        completed?: boolean;
        completed_at?: string | null;
      }>
    >(`/driver/assignments/${assignmentId}/complete`);
    const assignment = mapRequired(data.data?.assignment, 'Unexpected complete response.');
    return {
      assignment,
      completed: Boolean(data.data?.completed),
      completedAt: data.data?.completed_at ?? null,
    };
  } catch (e) {
    throw toWorkError(e, 'Could not complete assignment.');
  }
}

/** POST /driver/assignments/{id}/issues */
export async function reportIssue(
  assignmentId: number | string,
  type: DriverIssueTypeCode,
  description?: string
): Promise<void> {
  try {
    await apiClient.post(`/driver/assignments/${assignmentId}/issues`, {
      type,
      description: description || undefined,
    });
  } catch (e) {
    throw toWorkError(e, 'Could not report issue.');
  }
}

/** POST /driver/assignments/{id}/fail-delivery */
export async function failDelivery(
  assignmentId: number | string,
  type: DriverIssueTypeCode,
  description?: string
): Promise<DriverWorkAssignment> {
  try {
    const { data } = await apiClient.post<
      ApiEnvelope<{ assignment: unknown; failed?: boolean }>
    >(`/driver/assignments/${assignmentId}/fail-delivery`, {
      type,
      description: description || undefined,
    });
    return mapRequired(data.data?.assignment, 'Unexpected fail-delivery response.');
  } catch (e) {
    throw toWorkError(e, 'Could not record delivery failure.');
  }
}
