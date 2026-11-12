import type { TrackingStatistics } from '../types/tracking.types';

interface StatisticsCardsProps {
  statistics: TrackingStatistics;
}

export default function StatisticsCards({ statistics }: StatisticsCardsProps) {
  const cards = [
    {
      label: 'Total Tracking States',
      value: statistics.total,
      color: 'text-cyan-400',
    },
    {
      label: 'Moving',
      value: statistics.moving,
      color: 'text-green-400',
    },
    {
      label: 'Stopped',
      value: statistics.stopped,
      color: 'text-yellow-400',
    },
    {
      label: 'Idle',
      value: statistics.idle,
      color: 'text-slate-400',
    },
    {
      label: 'Offline',
      value: statistics.offline,
      color: 'text-red-400',
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
      {cards.map((card) => (
        <div
          key={card.label}
          className="rounded-2xl border border-white/5 bg-[#0E1320] p-4 shadow-md"
        >
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
            {card.label}
          </p>
          <p className={`mt-2 font-mono text-2xl font-bold ${card.color}`}>
            {card.value}
          </p>
        </div>
      ))}
    </div>
  );
}
