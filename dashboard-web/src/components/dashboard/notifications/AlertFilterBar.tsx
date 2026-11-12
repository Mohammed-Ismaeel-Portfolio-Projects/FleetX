import { useState } from 'react';
import { Sparkles } from 'lucide-react';
import { cn } from '../../../lib/utils';

type FilterType = 'all' | 'critical' | 'high' | 'medium' | 'low' | 'ai';

interface FilterItem {
  id: FilterType;
  label: string;
  activeClass: string;
  hasIcon?: boolean;
}

const filterOptions: FilterItem[] = [
  { id: 'all', label: 'All Alerts', activeClass: 'bg-[#0250C5] text-white border-[#0250C5]' },
  { id: 'critical', label: 'Critical', activeClass: 'bg-red-500/10 text-red-400 border-red-500/30' },
  { id: 'high', label: 'High', activeClass: 'bg-amber-500/10 text-amber-400 border-amber-500/30' },
  { id: 'medium', label: 'Medium', activeClass: 'bg-blue-500/10 text-blue-400 border-blue-500/30' },
  { id: 'low', label: 'Low', activeClass: 'bg-slate-700/20 text-slate-400 border-slate-700/30' },
  { id: 'ai', label: 'AI Alerts', activeClass: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30', hasIcon: true },
];

export default function AlertFilterBar() {
  const [activeFilter, setActiveFilter] = useState<FilterType>('all');

  return (
    <div className="flex flex-wrap items-center gap-2 mb-6 w-full select-none justify-start">
      {filterOptions.map((opt) => (
        <button
          key={opt.id}
          onClick={() => setActiveFilter(opt.id)}
          className={cn(
            'px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all duration-200 border cursor-pointer select-none font-sans flex items-center gap-1.5',
            activeFilter === opt.id 
              ? opt.activeClass
              : 'bg-white/2 border-white/5 text-slate-400 hover:text-white hover:border-white/10'
          )}
        >
          {opt.hasIcon && <Sparkles className="w-3 h-3 flex-shrink-0" />}
          <span>{opt.label}</span>
        </button>
      ))}
    </div>
  );
}
