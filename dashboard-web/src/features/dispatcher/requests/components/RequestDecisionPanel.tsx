import { useState } from 'react';
import type { ShipmentRequestDetail } from '../types/request.types';
import {
  ShipmentRequestApiError,
  shipmentRequestsService,
} from '../services/shipmentRequests.service';
import {
  canApproveOrRejectRequest,
  canRequestChangesRequest,
  canStartReviewRequest,
  formatRequestStatusLabel,
} from '../utils/requestMapper';
import ReviewSection from './ReviewSection';

type DecisionMode = 'idle' | 'approve' | 'reject' | 'changes';

interface RequestDecisionPanelProps {
  request: ShipmentRequestDetail;
  onDecided: (next: ShipmentRequestDetail) => void;
}

/**
 * Dispatcher operational decisions. Buttons are presented from the current
 * server status; the backend remains the authority for whether a transition succeeds.
 */
export default function RequestDecisionPanel({ request, onDecided }: RequestDecisionPanelProps) {
  const canStartReview = canStartReviewRequest(request.status);
  const canDecide = canApproveOrRejectRequest(request.status);
  const canRequestChanges = canRequestChangesRequest(request.status);

  const [mode, setMode] = useState<DecisionMode>('idle');
  const [reason, setReason] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const resetForm = () => {
    setMode('idle');
    setReason('');
    setError(null);
  };

  const failMessage = (err: unknown, fallback: string): string => {
    if (err instanceof ShipmentRequestApiError) return err.message;
    if (err instanceof Error) return err.message;
    return fallback;
  };

  const handleStartReview = async () => {
    if (!canStartReview || isSubmitting) return;

    setIsSubmitting(true);
    setError(null);
    setSuccess(null);

    try {
      const next = await shipmentRequestsService.startReview(request.id);
      onDecided(next);
      setSuccess('Review started successfully.');
      resetForm();
    } catch (err) {
      setError(failMessage(err, 'Failed to start review'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleConfirm = async () => {
    if (isSubmitting) return;

    if (mode === 'approve' && !canDecide) return;
    if (mode === 'reject' && !canDecide) return;
    if (mode === 'changes' && !canRequestChanges) return;

    if ((mode === 'reject' || mode === 'changes') && reason.trim().length < 3) {
      setError(
        mode === 'reject'
          ? 'Rejection reason is required (minimum 3 characters).'
          : 'A reason is required (minimum 3 characters).'
      );
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setSuccess(null);

    try {
      const next =
        mode === 'approve'
          ? await shipmentRequestsService.approveRequest(request.id, reason)
          : mode === 'reject'
            ? await shipmentRequestsService.rejectRequest(request.id, reason.trim())
            : await shipmentRequestsService.requestChanges(request.id, reason.trim());

      onDecided(next);
      setSuccess(
        mode === 'approve'
          ? 'Request approved successfully.'
          : mode === 'reject'
            ? 'Request rejected successfully.'
            : 'Changes requested successfully.'
      );
      resetForm();
    } catch (err) {
      setError(failMessage(err, 'Decision failed'));
      // Do not call onDecided — keep current authoritative request state.
    } finally {
      setIsSubmitting(false);
    }
  };

  const openMode = (next: DecisionMode) => {
    setSuccess(null);
    setError(null);
    setReason('');
    setMode(next);
  };

  const idleActions = canDecide || canRequestChanges ? (
    <div className="flex flex-col gap-2">
      {canDecide ? (
        <>
          <button
            type="button"
            data-testid="decision-approve"
            onClick={() => openMode('approve')}
            className="w-full rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-emerald-200 hover:bg-emerald-500/20"
          >
            Approve
          </button>
          <button
            type="button"
            data-testid="decision-reject"
            onClick={() => openMode('reject')}
            className="w-full rounded-xl border border-rose-400/30 bg-rose-500/10 px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-rose-200 hover:bg-rose-500/20"
          >
            Reject
          </button>
        </>
      ) : null}
      {canRequestChanges ? (
        <button
          type="button"
          data-testid="decision-request-changes"
          onClick={() => openMode('changes')}
          className="w-full rounded-xl border border-amber-400/30 bg-amber-500/10 px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-amber-100 hover:bg-amber-500/20"
        >
          Request Changes
        </button>
      ) : null}
    </div>
  ) : null;

  const confirmCopy =
    mode === 'approve'
      ? {
          title: 'Confirm approval',
          hint: 'Optional reason will be stored with status history.',
          label: 'Reason (optional)',
          placeholder: 'Optional note…',
          confirm: 'Confirm approve',
        }
      : mode === 'reject'
        ? {
            title: 'Confirm rejection',
            hint: 'A rejection reason is required by the API (min 3 characters).',
            label: 'Reason (required)',
            placeholder: 'Explain why this request is rejected…',
            confirm: 'Confirm reject',
          }
        : {
            title: 'Request changes',
            hint: 'A reason is required by the API (min 3 characters). The client must resubmit after this.',
            label: 'Reason (required)',
            placeholder: 'Explain what the client needs to change…',
            confirm: 'Confirm request changes',
          };

  return (
    <ReviewSection
      title="Operational decision"
      subtitle="Available actions follow the current request status. The API decides whether a transition is valid."
    >
      {canStartReview ? (
        <div className="flex flex-col gap-2">
          <p className="text-sm text-slate-400">
            This request is submitted. Start review to move it into dispatcher review.
          </p>
          <button
            type="button"
            data-testid="decision-start-review"
            disabled={isSubmitting}
            onClick={() => void handleStartReview()}
            className="w-full rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-cyan-100 hover:bg-cyan-500/20 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {isSubmitting ? 'Starting review…' : 'Start Review'}
          </button>
        </div>
      ) : canDecide || canRequestChanges ? (
        mode === 'idle' ? (
          idleActions
        ) : (
          <div className="space-y-3 rounded-xl border border-white/10 bg-white/[0.02] p-3">
            <p className="text-sm font-semibold text-white">{confirmCopy.title}</p>
            <p className="text-xs text-slate-400">{confirmCopy.hint}</p>
            <label className="block">
              <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                {confirmCopy.label}
              </span>
              <textarea
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                rows={3}
                disabled={isSubmitting}
                aria-label={confirmCopy.label}
                className="mt-1 w-full rounded-xl border border-white/10 bg-[#0B0F19] px-3 py-2 text-sm text-slate-200 outline-none focus:border-cyan-400/40"
                placeholder={confirmCopy.placeholder}
              />
            </label>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                disabled={isSubmitting}
                onClick={() => void handleConfirm()}
                className={`rounded-xl px-3 py-2 text-xs font-bold uppercase tracking-wider disabled:cursor-not-allowed disabled:opacity-60 ${
                  mode === 'approve'
                    ? 'bg-emerald-500 text-white hover:bg-emerald-600'
                    : mode === 'reject'
                      ? 'bg-rose-500 text-white hover:bg-rose-600'
                      : 'bg-amber-500 text-slate-950 hover:bg-amber-400'
                }`}
              >
                {isSubmitting ? 'Saving…' : confirmCopy.confirm}
              </button>
              <button
                type="button"
                disabled={isSubmitting}
                onClick={resetForm}
                className="rounded-xl border border-white/10 px-3 py-2 text-xs font-bold uppercase tracking-wider text-slate-300 hover:bg-white/5 disabled:opacity-60"
              >
                Cancel
              </button>
            </div>
          </div>
        )
      ) : (
        <div className="space-y-2 text-sm text-slate-400">
          <p>
            Current status:{' '}
            <span className="font-mono text-slate-200">{formatRequestStatusLabel(request.status)}</span>
          </p>
          {request.status === 'changes_requested' ? (
            <p data-testid="decision-awaiting-client" className="text-xs text-amber-100">
              Changes were requested. Waiting for the client to resubmit. No dispatcher action is available.
            </p>
          ) : null}
          {request.status === 'approved' ? (
            <p className="text-xs text-slate-500">
              Use Convert to shipment below when this approved request is ready.
            </p>
          ) : null}
          {request.status === 'rejected' || request.status === 'cancelled' || request.status === 'converted' ? (
            <p data-testid="decision-closed" className="text-xs text-slate-500">
              This request is closed. No further review decisions are available.
            </p>
          ) : null}
        </div>
      )}

      {error ? (
        <p
          data-testid="decision-error"
          className="mt-3 rounded-xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-100"
          role="alert"
        >
          {error}
        </p>
      ) : null}
      {success ? (
        <p
          data-testid="decision-success"
          className="mt-3 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-100"
          role="status"
        >
          {success}
        </p>
      ) : null}
    </ReviewSection>
  );
}
