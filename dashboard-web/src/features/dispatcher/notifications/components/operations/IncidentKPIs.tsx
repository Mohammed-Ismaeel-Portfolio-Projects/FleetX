import { Activity, CircleDot, Loader, CheckCircle2 } from 'lucide-react';
import type { IncidentMetrics } from '../../types/notifications.types';

interface IncidentKPIsProps {
  metrics: IncidentMetrics;
}

export default function IncidentKPIs({ metrics }: IncidentKPIsProps) {
  const cards = [
    {
      label: 'Matching query',
      value: metrics.queryTotal,
      hint: 'Issues pagination total for current filters',
      icon: Activity,
      accent: 'from-sky-500 to-cyan-400',
    },
    {
      label: 'Open (this page)',
      value: metrics.pageOpen,
      hint: 'Current loaded page only',
      icon: CircleDot,
      accent: 'from-amber-500 to-orange-400',
    },
    {
      label: 'In Progress (this page)',
      value: metrics.pageInProgress,
      hint: 'Current loaded page only',
      icon: Loader,
      accent: 'from-violet-500 to-fuchsia-400',
    },
    {
      label: 'Resolved (this page)',
      value: metrics.pageResolved,
      hint: 'Current loaded page only',
      icon: CheckCircle2,
      accent: 'from-emerald-500 to-lime-400',
    },
  ];

  return (
    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      {cards.map((card) => {
        const Icon = card.icon;
        return (
          <div
            key={card.label}
            className="rounded-2xl border border-slate-800/80 bg-slate-950/60 p-4 shadow-lg shadow-black/10"
          >
            <div
              className={`mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br ${card.accent} text-white`}
            >
              <Icon className="h-5 w-5" />
            </div>
            <p className="text-sm text-slate-400">{card.label}</p>
            <p className="mt-2 text-2xl font-semibold text-white">{card.value}</p>
            <p className="mt-2 text-[10px] text-slate-500">{card.hint}</p>
          </div>
        );
      })}
    </section>
  );
}
