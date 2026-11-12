import { Link } from 'react-router-dom';
import type { Shipment } from '../types/shipment.types';
import OpsSection, { OpsField } from './OpsSection';
import ShipmentOperationalHeader from './ShipmentOperationalHeader';
import ShipmentLifecycleStrip from './ShipmentLifecycleStrip';
import ShipmentAssignmentPanel, {
  ShipmentSourceRequestPanel,
  ShipmentStatusHistoryPanel,
} from './ShipmentAssignmentPanel';
import EngineOperationsPanel from './EngineOperationsPanel';

interface ShipmentDetailsPanelProps {
  shipment: Shipment;
  onEngineEnrolled?: () => Promise<void> | void;
}

/**
 * C2.9-C2.7 / C2.9-C2.8 Step 6–7 — Dispatcher operational Shipment workspace.
 * Enrollment is the only Engine write; stages remain observation-only.
 */
export default function ShipmentDetailsPanel({
  shipment,
  onEngineEnrolled,
}: ShipmentDetailsPanelProps) {
  return (
    <div className="flex w-full flex-col gap-4 text-left select-none">
      <ShipmentOperationalHeader shipment={shipment} />
      <ShipmentLifecycleStrip status={shipment.status} />

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <div className="flex flex-col gap-4 xl:col-span-8">
          <EngineOperationsPanel shipment={shipment} onEnrolled={onEngineEnrolled} />

          <OpsSection title="Shipment summary" subtitle="Core identity and operational state.">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <OpsField label="Shipment number" value={shipment.shipmentNumber} />
              <OpsField label="Reference" value={shipment.referenceNumber || 'None'} />
              <OpsField label="Status" value={shipment.status} />
              <OpsField label="Priority" value={shipment.priority || 'Unavailable'} />
            </div>
          </OpsSection>

          <OpsSection title="Route" subtitle="Origin and destination from the shipment API.">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <OpsField label="Origin" value={shipment.origin} />
              <OpsField label="Destination" value={shipment.destination} />
              <OpsField label="Pickup company" value={shipment.pickupCompany || 'Unavailable'} />
              <OpsField
                label="Pickup contact"
                value={shipment.pickupContact || 'Unavailable'}
                hint={shipment.pickupPhone}
              />
              <OpsField label="Pickup address" value={shipment.pickupAddress || 'Unavailable'} />
              <OpsField label="Delivery company" value={shipment.deliveryCompany || 'Unavailable'} />
              <OpsField
                label="Delivery contact"
                value={shipment.deliveryContact || 'Unavailable'}
                hint={shipment.deliveryPhone}
              />
              <OpsField label="Delivery address" value={shipment.deliveryAddress || 'Unavailable'} />
            </div>
          </OpsSection>

          <OpsSection title="Cargo">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <OpsField label="Cargo type" value={shipment.cargoType || 'Unavailable'} />
              <OpsField label="Description" value={shipment.cargoDescription || 'Unavailable'} />
              <OpsField label="Weight" value={shipment.cargoWeight ?? 'Unavailable'} />
              <OpsField label="Volume" value={shipment.cargoVolume ?? 'Unavailable'} />
              <OpsField label="Package count" value={shipment.packageCount ?? 'Unavailable'} />
              <OpsField label="Hazardous" value={shipment.hazardousCargo ? 'Yes' : 'No'} />
              <OpsField
                label="Temperature controlled"
                value={shipment.temperatureControlled ? 'Yes' : 'No'}
              />
            </div>
          </OpsSection>

          <OpsSection title="Customer & Execution">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <OpsField
                label="Customer company"
                value={shipment.customer}
                hint={shipment.customerCompanyCode}
              />
              <OpsField label="Customer id" value={shipment.customerCompanyId || 'Unavailable'} />
              {shipment.collaborationType && (
                <OpsField
                  label="Collaboration mode"
                  value={
                    shipment.collaborationType === 'outbound_partner'
                      ? 'Outbound Partner Execution'
                      : shipment.collaborationType === 'inbound_partner'
                      ? 'Inbound Partner Customer Order'
                      : 'Internal Execution'
                  }
                />
              )}
              {shipment.executingCompanyName && (
                <OpsField
                  label="Executing carrier"
                  value={shipment.executingCompanyName}
                  hint={shipment.executingCompanyCode || undefined}
                />
              )}
            </div>
          </OpsSection>

          <OpsSection title="Dates">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <OpsField label="Planned pickup" value={shipment.plannedPickupDate || 'Unavailable'} />
              <OpsField
                label="Planned delivery"
                value={shipment.plannedDeliveryDate || 'Unavailable'}
              />
              <OpsField label="Actual pickup" value={shipment.actualPickupDate || 'Unavailable'} />
              <OpsField
                label="Actual delivery"
                value={shipment.actualDeliveryDate || 'Unavailable'}
              />
            </div>
          </OpsSection>

          {(shipment.specialInstructions || shipment.notes) && (
            <OpsSection title="Notes">
              <div className="grid grid-cols-1 gap-4">
                {shipment.specialInstructions ? (
                  <OpsField label="Special instructions" value={shipment.specialInstructions} />
                ) : null}
                {shipment.notes ? <OpsField label="Notes" value={shipment.notes} /> : null}
              </div>
            </OpsSection>
          )}
        </div>

        <aside className="flex flex-col gap-4 xl:col-span-4">
          <ShipmentAssignmentPanel shipment={shipment} />
          <ShipmentSourceRequestPanel shipment={shipment} />
          <ShipmentStatusHistoryPanel shipment={shipment} />

          <OpsSection title="Operational metadata">
            <div className="space-y-3">
              <OpsField label="Created at" value={shipment.createdAt || 'Unavailable'} />
              <OpsField label="Updated at" value={shipment.updatedAt || 'Unavailable'} />
              <OpsField label="Shipment id" value={shipment.id} />
              <OpsField label="Shipment order id" value={shipment.shipmentOrderId || 'None'} />
            </div>
          </OpsSection>

          <OpsSection
            title="Tracking"
            subtitle="Existing tracking_logs only — no tracking redesign."
          >
            {!shipment.timeline.length ? (
              <div className="space-y-3">
                <p className="text-sm text-slate-400">
                  No tracking updates on this shipment show payload.
                </p>
                <Link
                  to="/dispatcher/tracking"
                  className="inline-flex text-xs font-bold uppercase tracking-wider text-cyan-200 hover:text-cyan-100"
                >
                  Open Active Tracking
                </Link>
              </div>
            ) : (
              <div className="space-y-3">
                <ul className="space-y-2">
                  {shipment.timeline.slice(0, 8).map((event, idx) => (
                    <li
                      key={`${event.time}-${idx}`}
                      className="rounded-xl border border-white/5 bg-white/[0.02] px-3 py-2"
                    >
                      <p className="text-xs font-semibold text-slate-200">{event.title}</p>
                      <p className="mt-0.5 text-[11px] text-slate-500">{event.time}</p>
                      <p className="mt-1 text-xs text-slate-400">{event.desc}</p>
                    </li>
                  ))}
                </ul>
                <Link
                  to="/dispatcher/tracking"
                  className="inline-flex text-xs font-bold uppercase tracking-wider text-cyan-200 hover:text-cyan-100"
                >
                  Open Active Tracking
                </Link>
              </div>
            )}
          </OpsSection>
        </aside>
      </div>
    </div>
  );
}
