import { memo } from 'react';

function MapLegendComponent() {
  const legendItems = [
    { color: '#22C55E', label: 'Moving' },
    { color: '#EAB308', label: 'Idle' },
    { color: '#EF4444', label: 'Offline' },
    { color: '#F97316', label: 'Alert' },
    { color: '#0250C5', label: 'Selected' },
  ];

  return (
    <div className="absolute bottom-20 left-4 z-[1000] rounded-xl border border-white/10 bg-[#0E1320] p-3 shadow-lg">
      <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">
        Legend
      </p>
      <div className="space-y-2">
        {legendItems.map((item) => (
          <div key={item.label} className="flex items-center gap-2">
            <div
              className="h-3 w-3 rounded-full"
              style={{ backgroundColor: item.color }}
            />
            <span className="text-xs text-slate-300">{item.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

export default memo(MapLegendComponent);
