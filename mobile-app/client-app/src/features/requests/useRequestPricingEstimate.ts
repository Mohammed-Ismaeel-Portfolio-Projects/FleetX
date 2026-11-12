import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import {
  calculateClientPricingEstimate,
  ClientPricingEstimateApiError,
} from './pricingEstimateApi';
import {
  describeMissingPricingInputs,
  pricingEstimateSignature,
  resolvePricingEstimatePayload,
} from './pricingEstimateInputs';
import type { ClientPricingEstimate } from './pricingEstimateTypes';
import type { RequestFormValues } from './requestForm';

export type PricingEstimateUiStatus =
  | 'idle'
  | 'incomplete'
  | 'loading'
  | 'success'
  | 'error';

export interface PricingEstimateViewState {
  status: PricingEstimateUiStatus;
  estimate: ClientPricingEstimate | null;
  errorMessage: string | null;
  incompleteMessage: string | null;
  signature: string | null;
  retry: () => void;
}

/**
 * Review-only estimate loader. Local validation first; backend calculate when ready.
 * Sequence tokens ignore stale responses. Same-signature in-flight calls are not duplicated.
 */
export function useRequestPricingEstimate(
  values: RequestFormValues,
  enabled: boolean
): PricingEstimateViewState {
  const [status, setStatus] = useState<PricingEstimateUiStatus>('idle');
  const [estimate, setEstimate] = useState<ClientPricingEstimate | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [incompleteMessage, setIncompleteMessage] = useState<string | null>(null);

  const seqRef = useRef(0);
  const inFlightSigRef = useRef<string | null>(null);
  const successSigRef = useRef<string | null>(null);
  const valuesRef = useRef(values);
  valuesRef.current = values;

  const resolved = useMemo(() => resolvePricingEstimatePayload(values), [
    values.pickupLatitude,
    values.pickupLongitude,
    values.deliveryLatitude,
    values.deliveryLongitude,
    values.cargoType,
    values.trailerType,
    values.cargoWeight,
    values.hazardousCargo,
  ]);

  const signature = resolved.payload ? pricingEstimateSignature(resolved.payload) : null;

  const run = useCallback(async (force: boolean) => {
    if (!enabled) return;

    const current = resolvePricingEstimatePayload(valuesRef.current);
    if (!current.payload) {
      seqRef.current += 1;
      inFlightSigRef.current = null;
      successSigRef.current = null;
      setEstimate(null);
      setErrorMessage(null);
      setIncompleteMessage(describeMissingPricingInputs(current.missing));
      setStatus('incomplete');
      return;
    }

    const sig = pricingEstimateSignature(current.payload);

    if (!force && successSigRef.current === sig) {
      setStatus('success');
      setIncompleteMessage(null);
      setErrorMessage(null);
      return;
    }

    if (!force && inFlightSigRef.current === sig) {
      return;
    }

    const seq = ++seqRef.current;
    inFlightSigRef.current = sig;
    setStatus('loading');
    setIncompleteMessage(null);
    setErrorMessage(null);
    if (successSigRef.current !== sig) {
      setEstimate(null);
    }

    try {
      const result = await calculateClientPricingEstimate(current.payload);
      if (seq !== seqRef.current) return;
      successSigRef.current = sig;
      setEstimate(result);
      setStatus('success');
      setErrorMessage(null);
    } catch (e) {
      if (seq !== seqRef.current) return;
      successSigRef.current = null;
      setEstimate(null);
      setStatus('error');
      setErrorMessage(
        e instanceof ClientPricingEstimateApiError
          ? e.message
          : 'Unable to calculate the estimated cost.'
      );
    } finally {
      if (seq === seqRef.current) {
        inFlightSigRef.current = null;
      }
    }
  }, [enabled]);

  useEffect(() => {
    if (!enabled) {
      seqRef.current += 1;
      inFlightSigRef.current = null;
      setStatus('idle');
      return;
    }
    void run(false);
  }, [enabled, signature, run]);

  const retry = useCallback(() => {
    void run(true);
  }, [run]);

  return {
    status,
    estimate,
    errorMessage,
    incompleteMessage,
    signature,
    retry,
  };
}
