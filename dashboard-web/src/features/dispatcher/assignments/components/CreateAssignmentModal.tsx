/**
 * CreateAssignmentModal — Dispatcher Assignment Creation Modal
 * Supports both FleetX Internal Dispatchers & Partner Carrier Dispatchers.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { X, Truck, User, Container, AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useAuth } from '../../../../core/auth';
import type {
  AssignmentDriver,
  AssignmentVehicle,
  AssignmentTrailer,
  AssignmentFormState,
} from '../../requests/types/assignment.types';
import { assignmentResourcesService } from '../../requests/services/assignmentResources.service';
import ResourceSelector, { type ResourceItem } from '../../requests/components/ResourceSelector';

export interface ShipmentAssignmentTarget {
  id: string;
  shipmentNumber?: string;
  executingCompanyId?: string | null;
  executing_company_id?: string | null;
  status?: string;
}

export interface CreateAssignmentModalProps {
  shipment: ShipmentAssignmentTarget | null;
  isOpen: boolean;
  onClose: () => void;
  onAssigned?: () => void;
}

function driverToItem(d: AssignmentDriver): ResourceItem {
  const available = d.dispatchAvailability === 'Available' && !d.hasActiveAssignment;
  const identityHint = d.email || (d.userId ? `User ${d.userId}` : 'No login linked');
  return {
    id: d.id,
    label: d.fullName,
    subtitle: `#${d.employeeNumber}${d.phone ? ` · ${d.phone}` : ''} · ${identityHint}`,
    badge: d.dispatchAvailability || d.status || 'Unknown',
    badgeTone: available ? 'success' : d.hasActiveAssignment ? 'danger' : 'warning',
    disabled: d.hasActiveAssignment || !d.userId,
    disabledReason: d.hasActiveAssignment
      ? `Active assignment: ${d.activeAssignmentCode || 'in progress'}`
      : !d.userId
      ? 'Driver has no linked User account'
      : null,
  };
}

function vehicleToItem(v: AssignmentVehicle): ResourceItem {
  const available = v.status === 'Available' && !v.hasActiveAssignment && v.isActive;
  return {
    id: v.id,
    label: v.operationalCode || v.plateNumber,
    subtitle: [v.manufacturer, v.model, v.payloadCapacityKg ? `${v.payloadCapacityKg} kg` : null]
      .filter(Boolean)
      .join(' · ') || v.plateNumber,
    badge: v.hasActiveAssignment ? 'Assigned' : v.status || 'Unknown',
    badgeTone: available ? 'success' : v.hasActiveAssignment ? 'danger' : 'warning',
    disabled: v.hasActiveAssignment,
    disabledReason: v.hasActiveAssignment ? 'Currently assigned' : null,
  };
}

function trailerToItem(t: AssignmentTrailer): ResourceItem {
  const available = t.isActive && t.status !== 'Assigned';
  return {
    id: t.id,
    label: t.trailerCode || t.plateNumber,
    subtitle: [t.trailerType, t.manufacturer, t.payloadCapacityKg ? `${t.payloadCapacityKg} kg` : null]
      .filter(Boolean)
      .join(' · ') || t.plateNumber,
    badge: t.trailerType || (t.isActive ? 'Active' : 'Inactive'),
    badgeTone: available ? 'success' : 'neutral',
    disabled: !t.isActive,
    disabledReason: !t.isActive ? 'Trailer is inactive' : null,
  };
}

function generateAssignmentCode(): string {
  const now = new Date();
  const y = now.getFullYear().toString().slice(-2);
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  const rand = Math.random().toString(36).slice(2, 7).toUpperCase();
  return `ASG-${y}${m}${d}-${rand}`;
}

export default function CreateAssignmentModal({
  shipment,
  isOpen,
  onClose,
  onAssigned,
}: CreateAssignmentModalProps) {
  const { user } = useAuth();
  const isPartner = Boolean(user?.isPartnerCarrier);
  const companyId = user?.companyId ?? null;

  const [form, setForm] = useState<AssignmentFormState>({
    driverId: null,
    vehicleId: null,
    trailerId: null,
    notes: '',
  });

  const [drivers, setDrivers] = useState<AssignmentDriver[]>([]);
  const [vehicles, setVehicles] = useState<AssignmentVehicle[]>([]);
  const [trailers, setTrailers] = useState<AssignmentTrailer[]>([]);

  const [loadingDrivers, setLoadingDrivers] = useState(false);
  const [loadingVehicles, setLoadingVehicles] = useState(false);
  const [loadingTrailers, setLoadingTrailers] = useState(false);

  const [errorDrivers, setErrorDrivers] = useState<string | null>(null);
  const [errorVehicles, setErrorVehicles] = useState<string | null>(null);
  const [errorTrailers, setErrorTrailers] = useState<string | null>(null);

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  // Execution consistency check
  const executingCompanyId =
    shipment?.executingCompanyId || shipment?.executing_company_id || null;

  const isExecutionMismatch = Boolean(
    isPartner && executingCompanyId && companyId && executingCompanyId !== companyId
  );

  // Fail-closed rule for partner carriers without companyId
  const isPartnerUnauthenticated = Boolean(isPartner && !companyId);

  const fetchDrivers = useCallback(async (search?: string) => {
    if (isPartnerUnauthenticated || isExecutionMismatch) {
      setDrivers([]);
      return;
    }
    setLoadingDrivers(true);
    setErrorDrivers(null);
    try {
      const data = await assignmentResourcesService.fetchDrivers(search);
      setDrivers(data);
    } catch (e) {
      setErrorDrivers(e instanceof Error ? e.message : 'Failed to load drivers');
    } finally {
      setLoadingDrivers(false);
    }
  }, [isPartnerUnauthenticated, isExecutionMismatch]);

  const fetchVehicles = useCallback(async (search?: string) => {
    if (isPartnerUnauthenticated || isExecutionMismatch) {
      setVehicles([]);
      return;
    }
    setLoadingVehicles(true);
    setErrorVehicles(null);
    try {
      const data = await assignmentResourcesService.fetchVehicles(search);
      setVehicles(data);
    } catch (e) {
      setErrorVehicles(e instanceof Error ? e.message : 'Failed to load vehicles');
    } finally {
      setLoadingVehicles(false);
    }
  }, [isPartnerUnauthenticated, isExecutionMismatch]);

  const fetchTrailers = useCallback(async (search?: string) => {
    if (isPartnerUnauthenticated || isExecutionMismatch) {
      setTrailers([]);
      return;
    }
    setLoadingTrailers(true);
    setErrorTrailers(null);
    try {
      const data = await assignmentResourcesService.fetchTrailers(search);
      setTrailers(data);
    } catch (e) {
      setErrorTrailers(e instanceof Error ? e.message : 'Failed to load trailers');
    } finally {
      setLoadingTrailers(false);
    }
  }, [isPartnerUnauthenticated, isExecutionMismatch]);

  useEffect(() => {
    if (isOpen && shipment) {
      setForm({ driverId: null, vehicleId: null, trailerId: null, notes: '' });
      setSubmitError(null);
      setSuccess(false);
      void fetchDrivers();
      void fetchVehicles();
      void fetchTrailers();
    }
  }, [isOpen, shipment, fetchDrivers, fetchVehicles, fetchTrailers]);

  // Resource Scoping Logic
  const scopedDrivers = useMemo(() => {
    if (!isPartner) return drivers;
    if (!companyId) return [];
    return drivers.filter((d) => !d.companyId || d.companyId === companyId);
  }, [drivers, isPartner, companyId]);

  const scopedVehicles = useMemo(() => {
    if (!isPartner) return vehicles;
    if (!companyId) return [];
    return vehicles.filter((v) => !v.companyId || v.companyId === companyId);
  }, [vehicles, isPartner, companyId]);

  const scopedTrailers = useMemo(() => {
    if (!isPartner) return trailers;
    if (!companyId) return [];
    return trailers.filter((t) => !t.companyId || t.companyId === companyId);
  }, [trailers, isPartner, companyId]);

  const driverItems = useMemo(() => scopedDrivers.map(driverToItem), [scopedDrivers]);
  const vehicleItems = useMemo(() => scopedVehicles.map(vehicleToItem), [scopedVehicles]);
  const trailerItems = useMemo(() => scopedTrailers.map(trailerToItem), [scopedTrailers]);

  const selectedVehicle = scopedVehicles.find((v) => v.id === form.vehicleId) ?? null;

  // Handle vehicle selection
  const handleSelectVehicle = (vehicleId: string | null) => {
    if (!vehicleId) {
      setForm((f) => ({ ...f, vehicleId: null, driverId: isPartner ? f.driverId : null }));
      return;
    }

    const veh = scopedVehicles.find((v) => v.id === vehicleId);

    if (isPartner) {
      // Dynamic pairing: do NOT override driver if user picked one independently
      setForm((f) => ({ ...f, vehicleId }));
    } else {
      // Internal dispatcher: resolve paired driver from vehicle
      const resolvedDriverId = veh?.driverId
        ? String(veh.driverId)
        : veh?.driver?.id
        ? String(veh.driver.id)
        : null;

      setForm((f) => ({
        ...f,
        vehicleId,
        driverId: resolvedDriverId,
      }));
    }
  };

  const canProceed = Boolean(
    shipment &&
    form.vehicleId &&
    form.driverId &&
    !isExecutionMismatch &&
    !isPartnerUnauthenticated
  );

  const handleSubmit = async () => {
    if (!shipment || !form.driverId || !form.vehicleId || submitting || !canProceed) return;

    setSubmitting(true);
    setSubmitError(null);

    try {
      let statusId: number;
      try {
        statusId = await assignmentResourcesService.fetchAssignmentStatusId();
      } catch {
        statusId = 0;
      }

      if (!statusId) {
        setSubmitError('Could not resolve assignment status. Please contact support.');
        setSubmitting(false);
        return;
      }

      await assignmentResourcesService.createAssignment({
        shipment_id: shipment.id,
        assignment_code: generateAssignmentCode(),
        driver_id: form.driverId,
        vehicle_id: form.vehicleId,
        trailer_id: form.trailerId,
        status_id: statusId,
        assigned_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
        notes: form.notes.trim() || null,
      });

      setSuccess(true);
      onAssigned?.();
    } catch (e) {
      setSubmitError(e instanceof Error ? e.message : 'Assignment failed');
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen || !shipment) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div className="w-full max-w-xl rounded-2xl border border-white/10 bg-[#0E1320] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        {/* Modal Header */}
        <div className="flex items-center justify-between p-5 border-b border-white/5">
          <div>
            <h3 className="text-lg font-bold text-white">Create Assignment</h3>
            <p className="text-xs text-slate-400">
              {shipment.shipmentNumber ? `Shipment: ${shipment.shipmentNumber}` : `Shipment ID: ${shipment.id}`}
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-white/5 rounded-lg transition-colors"
            aria-label="Close"
          >
            <X className="w-5 h-5 text-slate-400" />
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-6 overflow-y-auto space-y-5 flex-1">
          {success ? (
            <div className="space-y-4 text-center py-6">
              <CheckCircle2 className="w-12 h-12 text-emerald-400 mx-auto" />
              <h4 className="text-lg font-bold text-white">Assignment Created</h4>
              <p className="text-sm text-slate-300">
                The load has been successfully assigned.
              </p>
              <button
                type="button"
                onClick={onClose}
                className="rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-2.5 text-xs font-bold uppercase text-cyan-200"
              >
                Close Modal
              </button>
            </div>
          ) : isExecutionMismatch ? (
            <div className="flex items-start gap-3 rounded-xl border border-rose-400/30 bg-rose-500/10 p-4 text-rose-200">
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-rose-400" />
              <div>
                <p className="text-sm font-bold">Assignment Restricted</p>
                <p className="mt-1 text-xs text-rose-200/80">
                  This shipment belongs to another executing company. Assignment creation is restricted.
                </p>
              </div>
            </div>
          ) : isPartnerUnauthenticated ? (
            <div className="flex items-start gap-3 rounded-xl border border-rose-400/30 bg-rose-500/10 p-4 text-rose-200">
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-rose-400" />
              <div>
                <p className="text-sm font-bold">Partner Context Missing</p>
                <p className="mt-1 text-xs text-rose-200/80">
                  Your partner company identity is temporarily unavailable. No partner resources can be loaded.
                </p>
              </div>
            </div>
          ) : (
            <>
              {/* Vehicle Selection */}
              <ResourceSelector
                testId="assignment-vehicle"
                title="Vehicle"
                placeholder="Search vehicle by code, plate…"
                selectedId={form.vehicleId}
                items={vehicleItems}
                loading={loadingVehicles}
                error={errorVehicles}
                emptyStateMessage={
                  isPartner
                    ? 'No vehicles are available for your partner fleet.'
                    : 'No eligible vehicles are currently available.'
                }
                onSearch={(q) => void fetchVehicles(q)}
                onSelect={(id) => handleSelectVehicle(id)}
                onRetry={() => void fetchVehicles()}
                required
              />

              {/* Driver Selection */}
              {isPartner ? (
                /* Partner Carrier: Dynamic Driver Selection */
                <ResourceSelector
                  testId="assignment-driver"
                  title="Driver (Dynamic Selection)"
                  placeholder="Search driver by name, employee number…"
                  selectedId={form.driverId}
                  items={driverItems}
                  loading={loadingDrivers}
                  error={errorDrivers}
                  emptyStateMessage="No drivers are available for your partner fleet."
                  onSearch={(q) => void fetchDrivers(q)}
                  onSelect={(id) => setForm((f) => ({ ...f, driverId: id }))}
                  onRetry={() => void fetchDrivers()}
                  required
                />
              ) : (
                /* FleetX Internal: Paired Driver Read-Only / Resolution */
                selectedVehicle ? (
                  selectedVehicle.driver ? (
                    <div className="space-y-1.5 rounded-xl border border-cyan-400/20 bg-cyan-500/10 p-3.5">
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-bold uppercase tracking-wider text-cyan-300">
                          Paired Driver (Read-Only)
                        </span>
                        <span className="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-300">
                          ✓ Paired to Vehicle
                        </span>
                      </div>
                      <div className="flex items-center gap-3">
                        <User className="h-5 w-5 shrink-0 text-cyan-400" />
                        <div>
                          <p className="text-sm font-semibold text-white">
                            {selectedVehicle.driver.fullName}
                          </p>
                          <p className="text-xs text-slate-400">
                            #{selectedVehicle.driver.employeeNumber}
                          </p>
                        </div>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-start gap-2.5 rounded-xl border border-rose-400/30 bg-rose-500/10 p-3.5 text-rose-200">
                      <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-rose-400" />
                      <div>
                        <p className="text-xs font-bold">Vehicle Has No Assigned Driver</p>
                        <p className="mt-0.5 text-xs text-rose-200/80">
                          An administrator must pair a driver before this vehicle can be assigned.
                        </p>
                      </div>
                    </div>
                  )
                ) : (
                  <div className="rounded-xl border border-white/5 bg-white/[0.02] p-3 text-center text-xs text-slate-500">
                    Select a vehicle above to automatically resolve its paired driver.
                  </div>
                )
              )}

              {/* Trailer Selection */}
              <ResourceSelector
                testId="assignment-trailer"
                title="Trailer (Optional)"
                placeholder="Search trailer by code, plate…"
                selectedId={form.trailerId}
                items={trailerItems}
                loading={loadingTrailers}
                error={errorTrailers}
                emptyStateMessage={
                  isPartner
                    ? 'No trailers are available for your partner fleet.'
                    : 'No eligible trailers are currently available.'
                }
                onSearch={(q) => void fetchTrailers(q)}
                onSelect={(id) => setForm((f) => ({ ...f, trailerId: id }))}
                onRetry={() => void fetchTrailers()}
              />

              {/* Notes */}
              <div className="space-y-1">
                <label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                  Dispatch Notes
                </label>
                <textarea
                  value={form.notes}
                  onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
                  rows={2}
                  placeholder="Optional instructions for the driver…"
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 outline-none placeholder:text-slate-500 focus:border-cyan-400/40"
                />
              </div>

              {submitError ? (
                <div className="flex items-start gap-2 rounded-xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-100">
                  <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-rose-400" />
                  {submitError}
                </div>
              ) : null}
            </>
          )}
        </div>

        {/* Modal Footer */}
        {!success && !isExecutionMismatch && !isPartnerUnauthenticated && (
          <div className="p-5 border-t border-white/5 flex items-center justify-end gap-3">
            <button
              type="button"
              onClick={onClose}
              className="rounded-xl border border-white/10 px-4 py-2.5 text-xs font-bold uppercase text-slate-300 hover:bg-white/5"
            >
              Cancel
            </button>
            <button
              type="button"
              disabled={!canProceed || submitting}
              onClick={() => void handleSubmit()}
              className="rounded-xl bg-cyan-500 px-4 py-2.5 text-xs font-bold uppercase text-white hover:bg-cyan-600 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              {submitting ? 'Assigning…' : 'Create Assignment'}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
