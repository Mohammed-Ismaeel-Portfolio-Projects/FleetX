import { CheckCircle2, FileText, UserCircle2 } from 'lucide-react';
import type { Incident } from '../../types/notifications.types';
import { displaySsotValue } from '../../utils/issueAdapter';

interface IncidentDetailsPanelProps {
  incident: Incident | null;
  canResolve: boolean;
  isResolving: boolean;
  resolveError: string | null;
  resolvedStatusIdAvailable: boolean;
  onResolve: () => void;
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-3">
      <dt>{label}</dt>
      <dd className="text-right text-slate-200">{value}</dd>
    </div>
  );
}

export default function IncidentDetailsPanel({
  incident,
  canResolve,
  isResolving,
  resolveError,
  resolvedStatusIdAvailable,
  onResolve,
}: IncidentDetailsPanelProps) {
  if (!incident) {
    return (
      <section className="rounded-3xl border border-slate-800/80 bg-slate-950/70 p-6 text-sm text-slate-400 shadow-lg shadow-black/10">
        Select an issue to inspect Issues SSOT details.
      </section>
    );
  }

  const alreadyResolved = incident.status === 'Resolved';

  return (
    <section className="space-y-4 rounded-3xl border border-slate-800/80 bg-slate-950/70 p-5 shadow-lg shadow-black/10">
      <div className="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.3em] text-sky-400">{incident.id}</p>
            <h2 className="mt-1 text-xl font-semibold text-white">{displaySsotValue(incident.type)}</h2>
            <p className="mt-2 text-sm text-slate-400">{displaySsotValue(incident.description)}</p>
          </div>
          <div className="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-sm text-slate-300">
            {displaySsotValue(incident.status)}
          </div>
        </div>
      </div>

      <div className="grid gap-4 xl:grid-cols-2">
        <div className="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
          <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-white">
            <UserCircle2 className="h-4 w-4 text-sky-400" /> Driver
          </div>
          <dl className="space-y-2 text-sm text-slate-400">
            <Field label="ID" value={displaySsotValue(incident.driverId)} />
            <Field label="Name" value={displaySsotValue(incident.driverName)} />
          </dl>
        </div>
        <div className="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
          <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-white">
            <FileText className="h-4 w-4 text-emerald-400" /> Shipment
          </div>
          <dl className="space-y-2 text-sm text-slate-400">
            <Field label="ID" value={displaySsotValue(incident.shipmentId)} />
            <Field label="Number" value={displaySsotValue(incident.shipmentNumber)} />
          </dl>
        </div>
      </div>

      <div className="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
        <dl className="space-y-2 text-sm text-slate-400">
          <Field label="Type" value={displaySsotValue(incident.type)} />
          <Field label="Status" value={displaySsotValue(incident.status)} />
          <Field label="Status ID" value={displaySsotValue(incident.statusId)} />
          <Field label="Created" value={displaySsotValue(incident.createdAt)} />
        </dl>
      </div>

      {!alreadyResolved ? (
        <div className="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
          {canResolve ? (
            <button
              type="button"
              onClick={onResolve}
              disabled={isResolving}
              className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <CheckCircle2 className="h-4 w-4" />
              {isResolving ? 'Resolving…' : 'Resolve Issue'}
            </button>
          ) : !resolvedStatusIdAvailable ? (
            <p className="text-sm text-slate-400">
              Resolve unavailable — Resolved status ID has not been observed in loaded Issues SSOT
              data.
            </p>
          ) : null}
          {resolveError ? <p className="mt-2 text-sm text-red-400">{resolveError}</p> : null}
        </div>
      ) : null}
    </section>
  );
}
