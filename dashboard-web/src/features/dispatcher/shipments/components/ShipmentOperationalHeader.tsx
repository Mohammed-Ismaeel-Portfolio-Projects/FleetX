import { Link } from 'react-router-dom';
import type { Shipment } from '../types/shipment.types';
import StatusBadge from './ui/StatusBadge';
import PriorityBadge from './ui/PriorityBadge';

interface ShipmentOperationalHeaderProps {
  shipment: Shipment;
}

/**
 * C2.9-C2.7 — Operational header. Status is prominent; no free-form selector.
 */
export default function ShipmentOperationalHeader({ shipment }: ShipmentOperationalHeaderProps) {
  const assignment = shipment.activeAssignment;

  return (
    <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-5">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div className="min-w-0">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Shipment</p>
          <h2 className="mt-1 truncate text-2xl font-bold tracking-tight text-white">
            {shipment.shipmentNumber}
          </h2>
          {shipment.referenceNumber ? (
            <p className="mt-1 text-xs font-mono text-slate-400">Ref {shipment.referenceNumber}</p>
          ) : null}
        </div>

        <div className="flex flex-col items-start gap-2 lg:items-end">
          <StatusBadge status={shipment.status} size="lg" />
          <PriorityBadge priority={shipment.priority} size="md" />
          {shipment.collaborationType === 'outbound_partner' ? (
            <p className="rounded-lg border border-amber-400/30 bg-amber-500/10 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-200">
              Outbound Partner Carrier {shipment.executingCompanyName ? `· ${shipment.executingCompanyName}` : ''}
            </p>
          ) : shipment.collaborationType === 'inbound_partner' ? (
            <p className="rounded-lg border border-purple-400/30 bg-purple-500/10 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-purple-200">
              Inbound Partner Customer Order
            </p>
          ) : null}
          {shipment.engineOps?.operationalStage ? (
            <p className="rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-200">
              Ops · {shipment.engineOps.isResting ? 'Resting' : shipment.engineOps.operationalStage}
            </p>
          ) : null}
        </div>
      </div>

      <div className="mt-5 grid grid-cols-1 gap-4 border-t border-white/5 pt-4 sm:grid-cols-2 xl:grid-cols-4">
        <HeaderFact label="Customer" value={shipment.customer} hint={shipment.customerCompanyCode} />
        <HeaderFact label="Origin" value={shipment.origin} />
        <HeaderFact label="Destination" value={shipment.destination} />
        <HeaderFact
          label="Planned pickup / delivery"
          value={[shipment.plannedPickupDate || '—', shipment.plannedDeliveryDate || '—'].join(' → ')}
        />
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <HeaderFact
          label="Active assignment"
          value={
            assignment
              ? [assignment.driverName || 'Driver unavailable', assignment.vehicleCode || assignment.vehiclePlate || 'Vehicle unavailable']
                  .filter(Boolean)
                  .join(' · ')
              : 'Waiting for Assignment'
          }
          hint={assignment?.assignmentCode || null}
        />
        {shipment.sourceRequest ? (
          <div>
            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
              Converted from request
            </p>
            <Link
              to={`/dispatcher/requests/${shipment.sourceRequest.id}`}
              className="mt-1 inline-flex text-sm font-semibold text-cyan-200 hover:text-cyan-100"
            >
              {shipment.sourceRequest.requestNumber || shipment.sourceRequest.id}
            </Link>
          </div>
        ) : null}
      </div>
    </div>
  );
}

function HeaderFact({
  label,
  value,
  hint,
}: {
  label: string;
  value: string;
  hint?: string | null;
}) {
  return (
    <div>
      <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{label}</p>
      <p className="mt-1 text-sm font-semibold text-slate-100">{value}</p>
      {hint ? <p className="mt-0.5 text-xs text-slate-500">{hint}</p> : null}
    </div>
  );
}
