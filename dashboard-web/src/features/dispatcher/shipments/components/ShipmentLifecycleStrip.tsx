import {
  OPERATIONAL_LIFECYCLE_STAGES,
  getLifecycleStageState,
} from '../utils/shipmentLifecycle';

interface ShipmentLifecycleStripProps {
  status: string;
}

/**
 * Read-only lifecycle presentation. No mutation controls (deferred to C2.8).
 */
export default function ShipmentLifecycleStrip({ status }: ShipmentLifecycleStripProps) {
  return (
    <section className="rounded-2xl border border-white/5 bg-[#0E1320] p-5">
      <div className="border-b border-white/5 pb-3">
        <h3 className="text-xs font-bold uppercase tracking-widest text-slate-500">Lifecycle</h3>
        <p className="mt-1 text-sm text-slate-400">
          Waiting Assignment → Assigned → In Transit → Delivered → Completed (display only)
        </p>
      </div>

      <ol className="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-5">
        {OPERATIONAL_LIFECYCLE_STAGES.map((stage) => {
          const state = getLifecycleStageState(status, stage);
          const styles =
            state === 'current'
              ? 'border-cyan-400/40 bg-cyan-500/15 text-cyan-100'
              : state === 'completed'
                ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100'
                : state === 'upcoming'
                  ? 'border-white/10 bg-white/[0.02] text-slate-500'
                  : 'border-white/10 bg-white/[0.02] text-slate-500';

          return (
            <li
              key={stage}
              className={`rounded-xl border px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wider ${styles}`}
            >
              {stage}
              {state === 'current' ? (
                <span className="mt-1 block text-[9px] font-semibold normal-case tracking-normal text-cyan-200">
                  Current
                </span>
              ) : null}
              {state === 'completed' ? (
                <span className="mt-1 block text-[9px] font-semibold normal-case tracking-normal text-emerald-200/80">
                  Done
                </span>
              ) : null}
            </li>
          );
        })}
      </ol>
    </section>
  );
}
