import type { Shipment } from '../types/shipment.types';
import OpsSection, { OpsField } from './OpsSection';
import EngineEnrollmentControls from './EngineEnrollmentControls';
import {
  ENGINE_OPERATIONAL_TIMELINE,
  formatMeters,
  formatOperationalStage,
  formatSeconds,
  getEngineTimelineState,
} from '../utils/engineOperationalStage';

interface EngineOperationsPanelProps {
  shipment: Shipment;
  onEnrolled?: () => Promise<void> | void;
}

/**
 * C2.9-C2.8 Step 6/7 — Engine operational visibility + enrollment CTA.
 * Does not mutate operational stages or canonical Shipment status (enrollment only).
 */
export default function EngineOperationsPanel({ shipment, onEnrolled }: EngineOperationsPanelProps) {
  const ops = shipment.engineOps;
  const restCount = ops?.journey?.restStopCount ?? 0;

  return (
    <div className="flex flex-col gap-4">
      <OpsSection
        title="Engine operations"
        subtitle="Simulation Engine — enroll once, then observe. Canonical status stays separate."
      >
        <div className="space-y-4">
          <EngineEnrollmentControls
            shipment={shipment}
            onEnrolled={onEnrolled ?? (() => undefined)}
          />

          {!ops ? (
            <p className="text-sm text-slate-400">Engine operational data is not available.</p>
          ) : (
            <>
              <div className="rounded-xl border border-cyan-400/20 bg-cyan-500/10 px-3 py-3">
                <p className="text-[10px] font-bold uppercase tracking-wider text-cyan-200/80">
                  Status vs stage
                </p>
                <p className="mt-1 text-sm font-semibold text-cyan-50">
                  Shipment status: {ops.canonicalStatus || shipment.status}
                </p>
                <p className="mt-1 text-sm font-semibold text-white">
                  Operational stage: {formatOperationalStage(ops.operationalStage)}
                </p>
                {ops.transitionReason ? (
                  <p className="mt-1 text-xs text-cyan-100/70">Reason: {ops.transitionReason}</p>
                ) : null}
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <OpsField label="Engine enrollment" value={ops.enrollment.label} />
                <OpsField
                  label="Enrollment status"
                  value={ops.enrollment.status || 'Not enrolled'}
                />
                <OpsField
                  label="Simulation eligible"
                  value={ops.simulationEligible ? 'Yes' : 'No'}
                />
                <OpsField label="Engine updated" value={ops.engineUpdatedAt || 'Unavailable'} />
                <OpsField
                  label="Pickup proximity"
                  value={ops.pickupGeofenceReached ? 'Reached / passed' : 'Not reached'}
                />
                <OpsField
                  label="Delivery proximity"
                  value={ops.deliveryGeofenceReached ? 'Reached / passed' : 'Not reached'}
                />
              </div>
            </>
          )}
        </div>
      </OpsSection>

      <OpsSection title="Journey" subtitle="Route progress from Engine cache when present.">
        {!ops?.routeProgress ? (
          <p className="text-sm text-slate-400">
            {ops?.simulationEligible
              ? 'No route progress cached yet for this shipment.'
              : 'Engine journey unavailable until enrollment and simulation progress exist.'}
          </p>
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <OpsField
              label="Route progress"
              value={
                ops.routeProgress.completionPercentage != null
                  ? `${ops.routeProgress.completionPercentage.toFixed(1)}%`
                  : 'Unavailable'
              }
            />
            <OpsField
              label="Distance travelled"
              value={formatMeters(ops.routeProgress.distanceTravelledM)}
            />
            <OpsField
              label="Remaining distance"
              value={formatMeters(ops.routeProgress.remainingDistanceM)}
            />
            <OpsField
              label="Total distance"
              value={formatMeters(ops.routeProgress.totalDistanceM)}
            />
            <OpsField
              label="Current position"
              value={
                ops.routeProgress.latitude != null && ops.routeProgress.longitude != null
                  ? `${ops.routeProgress.latitude.toFixed(5)}, ${ops.routeProgress.longitude.toFixed(5)}`
                  : 'Unavailable'
              }
            />
            <OpsField
              label="ETA remaining"
              value={
                ops.eta?.estimatedRemainingSeconds != null
                  ? formatSeconds(ops.eta.estimatedRemainingSeconds)
                  : 'Unavailable'
              }
            />
            <OpsField
              label="Driving since last rest"
              value={formatMeters(ops.drivingSinceRestM)}
            />
          </div>
        )}
      </OpsSection>

      <OpsSection title="Rest" subtitle="Operational rest only — shipment remains In Transit.">
        {!ops ? (
          <p className="text-sm text-slate-400">Unavailable</p>
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <OpsField label="Rest status" value={ops.rest.message} />
            <OpsField
              label="Planned rest stops"
              value={ops.rest.restStopCount ?? ops.journey?.restStopCount ?? 0}
            />
            <OpsField
              label="Distance to next rest"
              value={formatMeters(ops.rest.distanceToNextRestM)}
            />
            <OpsField
              label="Remaining rest duration"
              value={
                ops.rest.active
                  ? formatSeconds(ops.rest.remainingRestSeconds)
                  : 'Not resting'
              }
            />
          </div>
        )}
      </OpsSection>

      <OpsSection
        title="Operational timeline"
        subtitle="Engine stage path (display only — not a second lifecycle)."
      >
        <ol className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          {ENGINE_OPERATIONAL_TIMELINE.map((stage) => {
            const state = getEngineTimelineState(ops?.operationalStage, stage, restCount);
            const styles =
              state === 'current'
                ? 'border-cyan-400/40 bg-cyan-500/15 text-cyan-100'
                : state === 'completed'
                  ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100'
                  : state === 'skipped'
                    ? 'border-white/5 bg-white/[0.01] text-slate-600 line-through'
                    : 'border-white/10 bg-white/[0.02] text-slate-500';

            return (
              <li
                key={stage}
                className={`rounded-xl border px-2 py-2 text-center text-[9px] font-bold uppercase tracking-wider ${styles}`}
              >
                {formatOperationalStage(stage)}
                {state === 'current' ? (
                  <span className="mt-1 block text-[8px] font-semibold normal-case tracking-normal text-cyan-200">
                    Current
                  </span>
                ) : null}
              </li>
            );
          })}
        </ol>
      </OpsSection>
    </div>
  );
}
