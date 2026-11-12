import { useState } from 'react';
import { Link } from 'react-router-dom';
import type { ShipmentRequestDetail } from '../types/request.types';
import {
  ShipmentRequestApiError,
  shipmentRequestsService,
} from '../services/shipmentRequests.service';
import { canConvertRequest, formatRequestStatusLabel } from '../utils/requestMapper';
import ReviewSection from './ReviewSection';

interface RequestConversionPanelProps {
  request: ShipmentRequestDetail;
  onConverted: (next: ShipmentRequestDetail) => void;
}

/**
 * C2.9-C2.6 — Convert approved Request → Shipment via existing convert API.
 * No Assignment / Tracking / status invention.
 */
export default function RequestConversionPanel({
  request,
  onConverted,
}: RequestConversionPanelProps) {
  const canConvert = canConvertRequest(request.status, request.convertedShipmentId);
  const shipmentId = request.convertedShipment?.id || request.convertedShipmentId;
  const isConverted =
    request.status === 'converted' || Boolean(request.convertedShipmentId);

  const [confirming, setConfirming] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const handleConvert = async () => {
    if (!canConvert || isSubmitting) return;

    setIsSubmitting(true);
    setError(null);
    setSuccess(null);

    try {
      const next = await shipmentRequestsService.convertRequest(request.id);
      onConverted(next);
      setSuccess('Request converted to shipment successfully.');
      setConfirming(false);
    } catch (err) {
      const message =
        err instanceof ShipmentRequestApiError
          ? err.message
          : err instanceof Error
            ? err.message
            : 'Conversion failed';
      setError(message);
      // Do not call onConverted — keep current authoritative request state.
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <ReviewSection
      title="Convert to shipment"
      subtitle="Reuse the existing approved → converted conversion endpoint."
    >
      {isConverted && shipmentId ? (
        <div className="space-y-3">
          <p className="text-sm text-emerald-100">
            This request has been converted. Open the created shipment to continue operations.
          </p>
          <ReviewFieldLike
            label="Shipment"
            value={request.convertedShipment?.shipmentNumber || shipmentId}
            hint={request.convertedShipment?.status || null}
          />
          <Link
            to={`/dispatcher/shipments/${shipmentId}`}
            className="inline-flex w-full items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-3 py-2.5 text-xs font-bold uppercase tracking-wider text-cyan-100 hover:bg-cyan-500/20"
          >
            Open shipment
          </Link>
        </div>
      ) : canConvert ? (
        confirming ? (
          <div className="space-y-3 rounded-xl border border-white/10 bg-white/[0.02] p-3">
            <p className="text-sm font-semibold text-white">Confirm conversion</p>
            <p className="text-xs text-slate-400">
              Creates one Shipment from this approved request using the existing conversion
              architecture. Duplicate conversion remains blocked by the backend.
            </p>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                disabled={isSubmitting}
                onClick={() => void handleConvert()}
                className="rounded-xl bg-cyan-500 px-3 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-cyan-600 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {isSubmitting ? 'Converting…' : 'Confirm convert'}
              </button>
              <button
                type="button"
                disabled={isSubmitting}
                onClick={() => {
                  setConfirming(false);
                  setError(null);
                }}
                className="rounded-xl border border-white/10 px-3 py-2 text-xs font-bold uppercase tracking-wider text-slate-300 hover:bg-white/5 disabled:opacity-60"
              >
                Cancel
              </button>
            </div>
          </div>
        ) : (
          <button
            type="button"
            disabled={isSubmitting}
            onClick={() => {
              setSuccess(null);
              setError(null);
              setConfirming(true);
            }}
            className="w-full rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-cyan-100 hover:bg-cyan-500/20 disabled:opacity-60"
          >
            Convert to shipment
          </button>
        )
      ) : (
        <div className="space-y-2 text-sm text-slate-400">
          <p>
            Conversion is available only when status is{' '}
            <span className="font-mono text-slate-200">approved</span> and no shipment has been
            linked yet.
          </p>
          <p>
            Current status:{' '}
            <span className="font-mono text-slate-200">{formatRequestStatusLabel(request.status)}</span>
          </p>
          {request.status === 'rejected' ? (
            <p className="text-xs text-slate-500">Rejected requests cannot be converted.</p>
          ) : null}
          {request.status === 'under_review' ? (
            <p className="text-xs text-slate-500">
              Approve this request before conversion.
            </p>
          ) : null}
        </div>
      )}

      {error ? (
        <p
          className="mt-3 rounded-xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-100"
          role="alert"
        >
          {error}
        </p>
      ) : null}
      {success ? (
        <p
          className="mt-3 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-100"
          role="status"
        >
          {success}
        </p>
      ) : null}
    </ReviewSection>
  );
}

function ReviewFieldLike({
  label,
  value,
  hint,
}: {
  label: string;
  value: string;
  hint: string | null;
}) {
  return (
    <div>
      <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{label}</p>
      <p className="mt-1 text-sm text-slate-100">{value}</p>
      {hint ? <p className="mt-0.5 text-xs text-slate-500">{hint}</p> : null}
    </div>
  );
}
