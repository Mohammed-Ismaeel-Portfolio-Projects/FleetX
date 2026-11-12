import { useState } from 'react';
import type { Shipment } from '../types/shipment.types';
import { assignmentsService, AssignmentApiError } from '../services/assignments.service';
import { formatOperationalStage } from '../utils/engineOperationalStage';

interface EngineEnrollmentControlsProps {
  shipment: Shipment;
  onEnrolled: () => Promise<void> | void;
}

/**
 * C2.9-C2.8 Step 7 — controlled Engine enrollment CTA.
 * Eligibility comes from backend engine_ops.enrollment; no frontend rule invention.
 */
export default function EngineEnrollmentControls({
  shipment,
  onEnrolled,
}: EngineEnrollmentControlsProps) {
  const ops = shipment.engineOps;
  const enrollment = ops?.enrollment;
  const [isEnrolling, setIsEnrolling] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!enrollment) {
    return (
      <div className="rounded-xl border border-white/10 bg-white/[0.02] px-3 py-3 text-sm text-slate-400">
        Engine enrollment state unavailable.
      </div>
    );
  }

  const handleEnroll = async () => {
    if (!enrollment.assignmentId || isEnrolling || !enrollment.canEnroll) return;
    setIsEnrolling(true);
    setError(null);
    try {
      await assignmentsService.enrollInEngine(enrollment.assignmentId);
      await onEnrolled();
    } catch (err) {
      const message =
        err instanceof AssignmentApiError
          ? err.message
          : err instanceof Error
            ? err.message
            : 'Enrollment failed';
      setError(message);
    } finally {
      setIsEnrolling(false);
    }
  };

  if (enrollment.alreadyEnrolled || ops?.simulationEligible) {
    return (
      <div className="rounded-xl border border-emerald-400/25 bg-emerald-500/10 px-3 py-3">
        <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-200/80">Engine</p>
        <p className="mt-1 text-sm font-semibold text-emerald-50">
          {enrollment.status === 'active' ? 'Active' : 'Enrolled'}
        </p>
        {enrollment.enrolledAt ? (
          <p className="mt-1 text-xs text-emerald-100/70">Enrolled: {enrollment.enrolledAt}</p>
        ) : null}
        {!ops?.available ? (
          <p className="mt-2 text-xs text-emerald-100/80">
            Waiting for first simulation update…
          </p>
        ) : (
          <p className="mt-2 text-xs text-emerald-100/70">
            Current stage: {formatOperationalStage(ops.operationalStage)}
          </p>
        )}
      </div>
    );
  }

  if (enrollment.canEnroll) {
    return (
      <div className="rounded-xl border border-cyan-400/25 bg-cyan-500/10 px-3 py-3">
        <p className="text-[10px] font-bold uppercase tracking-wider text-cyan-200/80">Engine</p>
        <p className="mt-1 text-sm font-semibold text-cyan-50">Not Enrolled</p>
        <p className="mt-1 text-xs text-cyan-100/70">
          Assignment is ready for simulation.
        </p>
        <button
          type="button"
          disabled={isEnrolling}
          onClick={() => void handleEnroll()}
          className="mt-3 inline-flex rounded-xl border border-cyan-300/40 bg-cyan-500/20 px-4 py-2 text-xs font-bold uppercase tracking-wider text-cyan-50 transition hover:bg-cyan-500/30 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {isEnrolling ? 'Enrolling…' : 'Enroll in Engine'}
        </button>
        {error ? <p className="mt-2 text-xs text-rose-300">{error}</p> : null}
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-3">
      <p className="text-[10px] font-bold uppercase tracking-wider text-amber-200/80">Engine</p>
      <p className="mt-1 text-sm font-semibold text-amber-50">Unavailable</p>
      <p className="mt-1 text-xs text-amber-100/70">
        {enrollment.blockReason || enrollment.label || 'Engine enrollment is not available.'}
      </p>
      {error ? <p className="mt-2 text-xs text-rose-300">{error}</p> : null}
    </div>
  );
}
