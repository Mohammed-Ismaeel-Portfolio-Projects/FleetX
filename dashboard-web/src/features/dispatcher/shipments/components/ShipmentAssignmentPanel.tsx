import { Link } from 'react-router-dom';
import type { Shipment } from '../types/shipment.types';
import { isWaitingForAssignmentDisplay } from '../utils/shipmentLifecycle';
import OpsSection, { OpsField } from './OpsSection';

interface ShipmentAssignmentPanelProps {
  shipment: Shipment;
}

/** Read-only assignment display. No create/edit in C2.7. */
export default function ShipmentAssignmentPanel({ shipment }: ShipmentAssignmentPanelProps) {
  const waiting = isWaitingForAssignmentDisplay(shipment.hasActiveAssignment);
  const assignment = shipment.activeAssignment;

  return (
    <OpsSection title="Assignment" subtitle="Active operational commitment when present.">
      {waiting || !assignment ? (
        <div className="rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-3 text-sm text-amber-100">
          Waiting for Assignment
          <p className="mt-1 text-xs text-amber-100/70">
            No active assignment is linked to this shipment. Assignment actions are deferred to a
            later phase.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <OpsField label="Assignment status" value={assignment.status || 'Unavailable'} />
          <OpsField label="Assignment code" value={assignment.assignmentCode || 'Unavailable'} />
          <OpsField
            label="Driver"
            value={assignment.driverName || 'Unavailable'}
            hint={assignment.driverEmployeeNumber || assignment.driverPhone}
          />
          <OpsField
            label="Vehicle"
            value={assignment.vehicleCode || assignment.vehiclePlate || 'Unavailable'}
            hint={assignment.vehiclePlate && assignment.vehicleCode ? assignment.vehiclePlate : null}
          />
          <OpsField
            label="Trailer"
            value={assignment.trailerPlate || assignment.trailerType || 'Unavailable'}
            hint={assignment.trailerType}
          />
          <OpsField label="Active" value={assignment.isActive ? 'Yes' : 'No'} />
          <OpsField
            label="Engine enrollment"
            value={
              shipment.engineOps?.enrollment.label ||
              assignment.engineEnrollmentStatus ||
              'Not enrolled'
            }
          />
          <OpsField
            label="Enrollment code"
            value={assignment.engineEnrollmentStatus || 'None'}
          />
        </div>
      )}
    </OpsSection>
  );
}

interface ShipmentStatusHistoryPanelProps {
  shipment: Shipment;
}

export function ShipmentStatusHistoryPanel({ shipment }: ShipmentStatusHistoryPanelProps) {
  const history = shipment.statusHistory;

  return (
    <OpsSection title="Status history" subtitle="Canonical transitions from the shipment show payload.">
      {!history.length ? (
        <p className="text-sm text-slate-400">
          No status history rows available for this shipment yet.
        </p>
      ) : (
        <ul className="space-y-3">
          {[...history].reverse().map((row) => (
            <li
              key={String(row.id)}
              className="rounded-xl border border-white/5 bg-white/[0.02] px-3 py-2 text-sm"
            >
              <p className="font-semibold text-slate-100">
                {(row.fromStatus || '—')} → {(row.toStatus || '—')}
              </p>
              <p className="mt-1 text-xs text-slate-500">
                {row.changedBy?.name || 'Unknown actor'}
                {row.createdAt ? ` · ${row.createdAt}` : ''}
              </p>
            </li>
          ))}
        </ul>
      )}
    </OpsSection>
  );
}

interface ShipmentSourceRequestPanelProps {
  shipment: Shipment;
}

export function ShipmentSourceRequestPanel({ shipment }: ShipmentSourceRequestPanelProps) {
  if (!shipment.sourceRequest) return null;

  const req = shipment.sourceRequest;

  return (
    <OpsSection
      title="Converted from request"
      subtitle="Request and Shipment remain distinct entities."
    >
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <OpsField label="Request number" value={req.requestNumber || req.id} />
        <OpsField label="Request status" value={req.status || 'Unavailable'} />
        <OpsField label="Request reference" value={req.referenceNumber || 'None'} />
        <OpsField label="Request id" value={req.id} />
      </div>
      <Link
        to={`/dispatcher/requests/${req.id}`}
        className="mt-4 inline-flex rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-3 py-2 text-xs font-bold uppercase tracking-wider text-cyan-100 hover:bg-cyan-500/20"
      >
        Open request
      </Link>
    </OpsSection>
  );
}
