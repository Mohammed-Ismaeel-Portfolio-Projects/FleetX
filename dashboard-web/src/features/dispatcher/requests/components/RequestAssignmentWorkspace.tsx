/**
 * Dispatcher Assignment Workspace — Manual Driver + Vehicle + Trailer selection.
 *
 * Visible only after conversion (shipment exists in Waiting Assignment).
 * Uses existing POST /assignments endpoint via CreateAssignmentAction.
 * Backend remains authoritative for conflict validation.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Truck, User, Container, AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useAuth } from '../../../../core/auth';
import type { ShipmentRequestDetail } from '../types/request.types';
import type {
  AssignmentDriver,
  AssignmentVehicle,
  AssignmentTrailer,
  AssignmentFormState,
} from '../types/assignment.types';
import { assignmentResourcesService } from '../services/assignmentResources.service';
import ResourceSelector, { type ResourceItem } from './ResourceSelector';
import ReviewSection from './ReviewSection';

type WorkspaceStep = 'select' | 'confirm' | 'done';

interface RequestAssignmentWorkspaceProps {
  request: ShipmentRequestDetail;
  onAssigned: () => void;
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
      ? `Active assignment: ${d.activeAssignmentCode || 'in progress'}${d.activeVehicleCode ? ` · ${d.activeVehicleCode}` : ''}`
      : !d.userId
        ? 'Driver has no linked User account (cannot log into Driver App)'
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

function trailerToItem(t: AssignmentTrailer, requestedType?: string | null): ResourceItem {
  const available = t.isActive && t.status !== 'Assigned';
  const isMatch =
    t.isMatchingRequestedType === true ||
    Boolean(
      requestedType &&
      t.trailerType &&
      t.trailerType.toLowerCase() === requestedType.toLowerCase()
    );

  return {
    id: t.id,
    label: t.trailerCode || t.plateNumber,
    subtitle: [t.trailerType, t.manufacturer, t.payloadCapacityKg ? `${t.payloadCapacityKg} kg` : null]
      .filter(Boolean)
      .join(' · ') || t.plateNumber,
    badge: isMatch ? 'Matching' : t.trailerType || (t.isActive ? 'Active' : 'Inactive'),
    badgeTone: isMatch ? 'success' : available ? 'warning' : 'neutral',
    disabled: !t.isActive,
    disabledReason: !t.isActive ? 'Trailer is inactive' : null,
    isPrimary: isMatch,
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

export default function RequestAssignmentWorkspace({
  request,
  onAssigned,
}: RequestAssignmentWorkspaceProps) {
  const shipmentId = request.convertedShipment?.id || request.convertedShipmentId;
  const isConverted = request.status === 'converted' && Boolean(shipmentId);

  if (!isConverted || !shipmentId) return null;

  return (
    <ReviewSection
      title="Assignment"
      subtitle="Select a real driver account (linked User), vehicle, and optional trailer. Backend validates conflicts."
    >
      <AssignmentWorkspaceInner
        shipmentId={shipmentId}
        request={request}
        onAssigned={onAssigned}
      />
    </ReviewSection>
  );
}

function AssignmentWorkspaceInner({
  shipmentId,
  request,
  onAssigned,
}: {
  shipmentId: string;
  request: ShipmentRequestDetail;
  onAssigned: () => void;
}) {
  const { user } = useAuth();
  const isPartner = Boolean(user?.isPartnerCarrier);
  const companyId = user?.companyId ?? null;

  const [step, setStep] = useState<WorkspaceStep>('select');
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
  const [assignmentResult, setAssignmentResult] = useState<{ id: string; code: string } | null>(null);

  // Execution consistency check
  const executingCompanyId =
    (request.convertedShipment as any)?.executingCompanyId ||
    (request.convertedShipment as any)?.executing_company_id ||
    null;

  const isExecutionMismatch = Boolean(
    isPartner && executingCompanyId && companyId && executingCompanyId !== companyId
  );

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

  const fetchTrailers = useCallback(
    async (search?: string) => {
      if (isPartnerUnauthenticated || isExecutionMismatch) {
        setTrailers([]);
        return;
      }
      setLoadingTrailers(true);
      setErrorTrailers(null);
      try {
        const data = await assignmentResourcesService.fetchTrailers(search, request.trailerType);
        setTrailers(data);
      } catch (e) {
        setErrorTrailers(e instanceof Error ? e.message : 'Failed to load trailers');
      } finally {
        setLoadingTrailers(false);
      }
    },
    [request.trailerType, isPartnerUnauthenticated, isExecutionMismatch]
  );

  useEffect(() => {
    void fetchDrivers();
    void fetchVehicles();
    void fetchTrailers();
  }, [fetchDrivers, fetchVehicles, fetchTrailers]);

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
  const trailerItems = useMemo(
    () => scopedTrailers.map((t) => trailerToItem(t, request.trailerType)),
    [scopedTrailers, request.trailerType]
  );

  const selectedVehicle = scopedVehicles.find((v) => v.id === form.vehicleId) ?? null;
  const pairedDriverFromVeh = selectedVehicle?.driver ?? null;

  const selectedDriver =
    scopedDrivers.find((d) => String(d.id) === String(form.driverId)) ||
    (pairedDriverFromVeh && !isPartner
      ? {
        id: String(pairedDriverFromVeh.id),
        employeeNumber: pairedDriverFromVeh.employeeNumber || '',
        fullName: pairedDriverFromVeh.fullName,
        email: null,
        userId: pairedDriverFromVeh.userId ? String(pairedDriverFromVeh.userId) : null,
        phone: pairedDriverFromVeh.phone ?? null,
        status: pairedDriverFromVeh.status ?? null,
        dispatchAvailability: pairedDriverFromVeh.status ?? null,
        hasActiveAssignment: false,
        activeAssignmentCode: null,
        activeVehicleCode: null,
      }
      : null);

  const selectedTrailer = scopedTrailers.find((t) => t.id === form.trailerId) ?? null;

  const canProceed = Boolean(
    form.vehicleId &&
    form.driverId &&
    !isExecutionMismatch &&
    !isPartnerUnauthenticated
  );

  const handleSelectVehicle = (vehicleId: string | null) => {
    if (!vehicleId) {
      setForm((f) => ({ ...f, vehicleId: null, driverId: isPartner ? f.driverId : null }));
      return;
    }

    const veh = scopedVehicles.find((v) => v.id === vehicleId);

    if (isPartner) {
      // Dynamic pairing: do NOT lock/override driver if partner dispatcher picked one
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

  const isTrailerMismatch = Boolean(
    selectedTrailer &&
    request.trailerType &&
    selectedTrailer.trailerType &&
    selectedTrailer.trailerType.toLowerCase() !== request.trailerType.toLowerCase()
  );

  const handleSubmit = async () => {
    if (!form.driverId || submitting || !canProceed) return;

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

      const result = await assignmentResourcesService.createAssignment({
        shipment_id: shipmentId,
        assignment_code: generateAssignmentCode(),
        driver_id: form.driverId,
        vehicle_id: form.vehicleId,
        trailer_id: form.trailerId,
        status_id: statusId,
        assigned_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
        notes: form.notes.trim() || null,
      });

      setAssignmentResult({ id: result.id, code: result.assignmentCode });
      setStep('done');
      onAssigned();
    } catch (e) {
      setSubmitError(e instanceof Error ? e.message : 'Assignment failed');
    } finally {
      setSubmitting(false);
    }
  };

  if (isExecutionMismatch) {
    return (
      <div className="flex items-start gap-3 rounded-xl border border-rose-400/30 bg-rose-500/10 p-4 text-rose-200">
        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-rose-400" />
        <div>
          <p className="text-sm font-bold">Assignment Restricted</p>
          <p className="mt-1 text-xs text-rose-200/80">
            This shipment belongs to another executing company. Assignment creation is restricted.
          </p>
        </div>
      </div>
    );
  }

  if (isPartnerUnauthenticated) {
    return (
      <div className="flex items-start gap-3 rounded-xl border border-rose-400/30 bg-rose-500/10 p-4 text-rose-200">
        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-rose-400" />
        <div>
          <p className="text-sm font-bold">Partner Context Missing</p>
          <p className="mt-1 text-xs text-rose-200/80">
            Your partner company identity is temporarily unavailable. No partner resources can be loaded.
          </p>
        </div>
      </div>
    );
  }

  if (step === 'done' && assignmentResult) {
    return (
      <div data-testid="assignment-success" className="space-y-4">
        <div className="flex items-center gap-3 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3">
          <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-400" />
          <div>
            <p className="text-sm font-semibold text-emerald-200">Assignment created</p>
            <p className="text-xs text-emerald-200/70">
              Code: {assignmentResult.code} — Shipment is now Assigned.
            </p>
          </div>
        </div>
        <Link
          to={`/dispatcher/shipments/${shipmentId}`}
          className="inline-flex w-full items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-3 py-2.5 text-xs font-bold uppercase tracking-wider text-cyan-100 hover:bg-cyan-500/20"
        >
          Open shipment
        </Link>
      </div>
    );
  }

  if (step === 'confirm') {
    return (
      <div data-testid="assignment-review" className="space-y-4">
        <p className="text-sm font-semibold text-white">Confirm assignment</p>
        <p className="text-xs text-slate-400">
          Review the resources below. The backend will validate availability and conflicts before creating the assignment.
        </p>

        {isTrailerMismatch ? (
          <div className="flex items-start gap-3 rounded-xl border border-amber-400/30 bg-amber-500/10 p-3.5">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-400" />
            <div className="space-y-1">
              <p className="text-xs font-semibold text-amber-200">Operational Preference Override Notice</p>
              <p className="text-xs text-amber-200/80">
                Customer requested a <span className="font-semibold underline">{request.trailerType}</span> trailer, but you selected{' '}
                <span className="font-semibold underline">{selectedTrailer?.trailerType}</span> ({selectedTrailer?.trailerCode || selectedTrailer?.plateNumber}).
              </p>
            </div>
          </div>
        ) : null}

        <div className="space-y-3 rounded-xl border border-white/10 bg-white/[0.02] p-4">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Assignment summary</p>
          <ConfirmField
            icon={<User className="h-4 w-4 text-cyan-400" />}
            label="Driver"
            value={selectedDriver?.fullName || '—'}
            detail={
              selectedDriver
                ? `#${selectedDriver.employeeNumber}${selectedDriver.email ? ` · ${selectedDriver.email}` : ''}`
                : null
            }
          />
          <ConfirmField
            icon={<Truck className="h-4 w-4 text-cyan-400" />}
            label="Vehicle"
            value={selectedVehicle ? (selectedVehicle.operationalCode || selectedVehicle.plateNumber) : 'None selected'}
            detail={selectedVehicle ? selectedVehicle.plateNumber : null}
          />
          <ConfirmField
            icon={<Container className="h-4 w-4 text-cyan-400" />}
            label="Trailer"
            value={selectedTrailer ? (selectedTrailer.trailerCode || selectedTrailer.plateNumber) : 'None selected'}
            detail={selectedTrailer ? selectedTrailer.trailerType || 'Standard' : null}
          />
        </div>

        {submitError ? (
          <div className="flex items-start gap-2 rounded-xl border border-rose-400/20 bg-rose-500/10 px-3 py-2">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-rose-400" />
            <p className="text-xs text-rose-100">{submitError}</p>
          </div>
        ) : null}

        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            data-testid="assignment-confirm"
            disabled={submitting}
            onClick={() => void handleSubmit()}
            className="rounded-xl bg-cyan-500 hover:bg-cyan-600 px-4 py-2.5 text-xs font-bold uppercase text-white disabled:opacity-60"
          >
            {submitting ? 'Creating assignment…' : 'Confirm Assignment'}
          </button>
          <button
            type="button"
            disabled={submitting}
            onClick={() => {
              setStep('select');
              setSubmitError(null);
            }}
            className="rounded-xl border border-white/10 px-4 py-2.5 text-xs font-bold uppercase text-slate-300 hover:bg-white/5 disabled:opacity-60"
          >
            Back
          </button>
        </div>
      </div>
    );
  }

  return (
    <div data-testid="dispatcher-assignment" className="space-y-5">
      <ResourceSelector
        testId="assignment-vehicle"
        title="Vehicle"
        placeholder="Search vehicle by code, plate, manufacturer…"
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
                  ✓ Assigned to Vehicle
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
                  An administrator must pair a driver before vehicle <span className="font-semibold text-white">{selectedVehicle.operationalCode || selectedVehicle.plateNumber}</span> can be assigned.
                </p>
              </div>
            </div>
          )
        ) : (
          <div className="rounded-xl border border-white/5 bg-white/[0.02] p-3 text-center text-xs text-slate-500">
            Select a vehicle above to automatically resolve its permanently paired driver.
          </div>
        )
      )}

      <ResourceSelector
        testId="assignment-trailer"
        title="Trailer"
        placeholder="Search trailer by code, plate, type…"
        selectedId={form.trailerId}
        items={trailerItems}
        loading={loadingTrailers}
        error={errorTrailers}
        hasCategories={true}
        emptyStateMessage={
          isPartner
            ? 'No trailers are available for your partner fleet.'
            : 'No eligible trailers are currently available.'
        }
        onSearch={(q) => void fetchTrailers(q)}
        onSelect={(id) => setForm((f) => ({ ...f, trailerId: id }))}
        onRetry={() => void fetchTrailers()}
      />

      <div className="space-y-1">
        <label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
          Dispatch notes
        </label>
        <textarea
          data-testid="assignment-notes"
          value={form.notes}
          onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
          rows={2}
          placeholder="Optional instructions for the driver…"
          className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 outline-none placeholder:text-slate-500 focus:border-cyan-400/40"
        />
      </div>

      {submitError ? (
        <div data-testid="assignment-error" className="flex items-start gap-2 rounded-xl border border-rose-400/20 bg-rose-500/10 px-3 py-2">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-rose-400" />
          <p className="text-xs text-rose-100">{submitError}</p>
        </div>
      ) : null}

      <button
        type="button"
        data-testid="assignment-proceed"
        disabled={!canProceed}
        onClick={() => setStep('confirm')}
        className="w-full rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-3 py-2.5 text-xs font-bold uppercase tracking-wider text-cyan-100 hover:bg-cyan-500/20 disabled:cursor-not-allowed disabled:opacity-40"
      >
        Review &amp; confirm assignment
      </button>
    </div>
  );
}

function ConfirmField({
  icon,
  label,
  value,
  detail,
}: {
  icon: React.ReactNode;
  label: string;
  value: string;
  detail: string | null;
}) {
  return (
    <div className="flex items-center gap-3">
      {icon}
      <div className="min-w-0 flex-1">
        <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{label}</p>
        <p className="text-sm font-semibold text-slate-100">{value}</p>
        {detail ? <p className="text-xs text-slate-400">{detail}</p> : null}
      </div>
    </div>
  );
}
