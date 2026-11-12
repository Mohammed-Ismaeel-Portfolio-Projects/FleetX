import { Link } from 'react-router-dom';
import { ClipboardList, Info, MapPin, Package, Phone, Truck, UserRound } from 'lucide-react';
import type { Driver } from '../../drivers/types/driver-operations.types';
import type { Shipment } from '../../shipments/types/shipment.types';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';
import { StatusBadge } from '../../../../shared/components/dispatcher/StatusBadge';
import { displayOrUnavailable, telHref } from '../utils/contactActions';
import { ContactActionButtons } from './ContactActionButtons';

interface ConversationDetailsProps {
  driver: Driver | null;
  shipment: Shipment | null;
  shipmentError?: string | null;
  shipmentLoading?: boolean;
}

function availabilityTone(
  value: string | null | undefined
): 'success' | 'info' | 'warning' | 'danger' | 'neutral' {
  const v = (value || '').toLowerCase();
  if (v === 'available') return 'success';
  if (v === 'busy') return 'info';
  if (v === 'on leave') return 'warning';
  if (v === 'suspended') return 'danger';
  return 'neutral';
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0">
      <p className="text-[11px] uppercase tracking-[0.12em] text-slate-500">{label}</p>
      <p className="mt-0.5 truncate text-sm text-slate-200">{value}</p>
    </div>
  );
}

function SiteContactCard({
  title,
  company,
  contact,
  phone,
  cityCountry,
}: {
  title: string;
  company: string | null;
  contact: string | null;
  phone: string | null;
  cityCountry?: string | null;
}) {
  const callHref = telHref(phone);
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{title}</p>
      <p className="mt-1.5 text-sm font-medium text-slate-200">{displayOrUnavailable(company)}</p>
      {cityCountry ? <p className="mt-1 text-xs text-slate-400">{cityCountry}</p> : null}
      <p className="mt-1 text-xs text-slate-400">Contact: {displayOrUnavailable(contact)}</p>
      <p className="mt-1 text-xs text-slate-400">Phone: {displayOrUnavailable(phone)}</p>
      {callHref ? (
        <a
          href={callHref}
          className={cn(dispatcherDesignTokens.buttonSecondary, 'mt-2 px-2.5 py-1.5 text-xs')}
        >
          <Phone className="h-3 w-3" aria-hidden /> Call site
        </a>
      ) : null}
    </div>
  );
}

/**
 * Selected driver operational context: Driver → Assignment → Shipment (SSOTs only).
 * Contact actions preserved; no messaging simulation.
 */
export function ConversationDetails({
  driver,
  shipment,
  shipmentError = null,
  shipmentLoading = false,
}: ConversationDetailsProps) {
  if (!driver) {
    return (
      <div className={cn(dispatcherDesignTokens.surface, 'p-4 md:p-5')}>
        <div className="flex items-center gap-2">
          <Info className="h-5 w-5 text-cyan-300" aria-hidden />
          <h2 className="text-lg font-semibold text-white">Operational Context</h2>
        </div>
        {shipment ? (
          <div className="mt-4 space-y-3">
            <p className="text-sm text-slate-400">
              Shipment context from the open conversation (no driver contact selected).
            </p>
            <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
              <Field label="Shipment #" value={displayOrUnavailable(shipment.shipmentNumber)} />
              <div className="mt-2">
                <Field label="Status" value={displayOrUnavailable(shipment.status)} />
              </div>
              <div className="mt-2">
                <Field
                  label="Route"
                  value={
                    shipment.origin || shipment.destination
                      ? `${displayOrUnavailable(shipment.origin)} → ${displayOrUnavailable(shipment.destination)}`
                      : 'Unavailable'
                  }
                />
              </div>
            </div>
            {shipmentError ? (
              <p className="text-xs text-amber-200">Shipment refresh: {shipmentError}</p>
            ) : null}
          </div>
        ) : (
          <div className="mt-4 rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-slate-500">
            <p>No driver contact selected.</p>
            <p className="mt-2 text-slate-600">
              Select a Driver Contact for Call / Email and assignment detail, or open a Conversation
              for shipment-scoped context.
            </p>
          </div>
        )}
      </div>
    );
  }

  const availability = driver.dispatchAvailability || driver.status || 'Unavailable';
  const rosterStatus = driver.status?.trim() || 'Unavailable';
  const assignment = driver.assignment;
  const shipmentHref = assignment?.shipmentId
    ? `/dispatcher/shipments/${assignment.shipmentId}`
    : '/dispatcher/shipments';

  const origin =
    shipment?.origin && shipment.origin !== 'Unavailable'
      ? shipment.origin
      : [assignment?.pickupCity].filter(Boolean).join(', ') || null;
  const destination =
    shipment?.destination && shipment.destination !== 'Unavailable'
      ? shipment.destination
      : [assignment?.deliveryCity].filter(Boolean).join(', ') || null;

  return (
    <div className={cn(dispatcherDesignTokens.surface, 'p-4 md:p-5')}>
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Info className="h-5 w-5 text-cyan-300" aria-hidden />
          <h2 className="text-lg font-semibold text-white">Operational Context</h2>
        </div>
        <StatusBadge label={availability} tone={availabilityTone(availability)} />
      </div>

      <div className="mt-4 space-y-3">
        {/* Driver */}
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
          <div className="flex items-center gap-2 text-sm text-slate-400">
            <UserRound className="h-4 w-4" /> Driver
          </div>
          <p className="mt-2 font-semibold text-white">{displayOrUnavailable(driver.name)}</p>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <Field label="Employee #" value={displayOrUnavailable(driver.employeeNumber)} />
            <Field label="Roster status" value={rosterStatus} />
            <Field label="Dispatch availability" value={displayOrUnavailable(driver.dispatchAvailability)} />
            <Field
              label="Active assignment"
              value={driver.hasActiveAssignment || assignment ? 'Yes' : 'No'}
            />
            <Field label="Phone" value={displayOrUnavailable(driver.phone)} />
            <Field label="Email" value={displayOrUnavailable(driver.email)} />
          </div>
          <ContactActionButtons
            phone={driver.phone}
            email={driver.email}
            callLabel="Call driver"
            emailLabel="Email driver"
            className="mt-3"
            compact
          />
        </div>

        {/* Assignment */}
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
          <div className="flex items-center gap-2 text-sm text-slate-400">
            <ClipboardList className="h-4 w-4" /> Current assignment
          </div>
          {assignment ? (
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              <Field label="Assignment code" value={displayOrUnavailable(assignment.assignmentCode)} />
              <Field label="Assignment ID" value={displayOrUnavailable(assignment.id)} />
              <Field label="Active" value={assignment.isActive ? 'Yes' : 'No'} />
              <Field
                label="Vehicle"
                value={displayOrUnavailable(
                  assignment.vehiclePlate || assignment.vehicleCode
                )}
              />
              <Field label="Shipment #" value={displayOrUnavailable(assignment.shipmentNumber)} />
              <Field label="Shipment ID" value={displayOrUnavailable(assignment.shipmentId)} />
            </div>
          ) : (
            <p className="mt-2 text-sm text-slate-500">Unavailable — no active assignment on this driver</p>
          )}
        </div>

        {/* Vehicle (explicit) */}
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
          <div className="flex items-center gap-2 text-sm text-slate-400">
            <Truck className="h-4 w-4" /> Vehicle
          </div>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <Field
              label="Plate"
              value={displayOrUnavailable(assignment?.vehiclePlate)}
            />
            <Field
              label="Operational code"
              value={displayOrUnavailable(assignment?.vehicleCode)}
            />
          </div>
        </div>

        {/* Shipment */}
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
          <div className="flex items-center gap-2 text-sm text-slate-400">
            <Package className="h-4 w-4" /> Shipment
          </div>

          {shipmentLoading ? (
            <p className="mt-2 text-sm text-slate-500">Loading shipment context…</p>
          ) : null}

          {shipmentError ? (
            <p className="mt-2 rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
              Unavailable — {shipmentError}
            </p>
          ) : null}

          {!assignment?.shipmentId && !shipmentLoading ? (
            <p className="mt-2 text-sm text-slate-500">
              Unavailable — no shipment linked on the active assignment
            </p>
          ) : null}

          {(shipment || assignment?.shipmentNumber) && !shipmentLoading ? (
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              <Field
                label="Shipment #"
                value={displayOrUnavailable(
                  shipment?.shipmentNumber || assignment?.shipmentNumber
                )}
              />
              <Field
                label="Shipment ID"
                value={displayOrUnavailable(shipment?.id || assignment?.shipmentId)}
              />
              <Field label="Status" value={displayOrUnavailable(shipment?.status)} />
              <Field label="Priority" value={displayOrUnavailable(shipment?.priority)} />
              <Field label="Customer company" value={displayOrUnavailable(shipment?.customer)} />
              <Field
                label="Planned delivery"
                value={displayOrUnavailable(shipment?.plannedDeliveryDate)}
              />
              <div className="sm:col-span-2">
                <div className="flex items-start gap-2 text-sm text-slate-400">
                  <MapPin className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
                  <div className="min-w-0 space-y-1">
                    <p>
                      <span className="text-slate-500">Pickup: </span>
                      {displayOrUnavailable(origin)}
                    </p>
                    <p>
                      <span className="text-slate-500">Delivery: </span>
                      {displayOrUnavailable(destination)}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          ) : null}
        </div>

        {/* Emergency */}
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
          <div className="flex items-center gap-2 text-sm text-slate-400">
            <Phone className="h-4 w-4" /> Emergency contact
          </div>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <Field label="Name" value={displayOrUnavailable(driver.emergencyContactName)} />
            <Field label="Phone" value={displayOrUnavailable(driver.emergencyContactPhone)} />
          </div>
          <ContactActionButtons
            phone={driver.emergencyContactPhone}
            showEmail={false}
            callLabel="Call emergency"
            className="mt-3"
            compact
          />
        </div>

        {/* Site contacts from Shipment SSOT */}
        {assignment?.shipmentId ? (
          <div className="space-y-2">
            <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">
              Pickup / delivery site contacts
            </p>
            {shipment && !shipmentLoading ? (
              <div className="grid gap-2">
                <SiteContactCard
                  title="Pickup"
                  company={shipment.pickupCompany}
                  contact={shipment.pickupContact}
                  phone={shipment.pickupPhone}
                  cityCountry={shipment.origin !== 'Unavailable' ? shipment.origin : null}
                />
                <SiteContactCard
                  title="Delivery"
                  company={shipment.deliveryCompany}
                  contact={shipment.deliveryContact}
                  phone={shipment.deliveryPhone}
                  cityCountry={
                    shipment.destination !== 'Unavailable' ? shipment.destination : null
                  }
                />
              </div>
            ) : !shipmentLoading && !shipmentError ? (
              <p className="text-sm text-slate-500">Unavailable — shipment contacts not loaded</p>
            ) : null}
          </div>
        ) : null}

        <div className="grid gap-2">
          <Link
            to="/dispatcher/drivers"
            className={cn(dispatcherDesignTokens.buttonSecondary, 'w-full justify-center px-3 py-2.5')}
          >
            Open in Driver Operations
          </Link>
          {assignment?.shipmentId ? (
            <Link
              to={shipmentHref}
              className={cn(dispatcherDesignTokens.buttonSecondary, 'w-full justify-center px-3 py-2.5')}
            >
              Open shipment record
            </Link>
          ) : (
            <Link
              to="/dispatcher/shipments"
              className={cn(dispatcherDesignTokens.buttonSecondary, 'w-full justify-center px-3 py-2.5')}
            >
              Open Shipments Center
            </Link>
          )}
          <Link
            to="/dispatcher/tracking"
            className={cn(dispatcherDesignTokens.buttonSecondary, 'w-full justify-center px-3 py-2.5')}
          >
            Open Active Tracking
          </Link>
        </div>
      </div>
    </div>
  );
}
