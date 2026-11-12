import { ClipboardList, Edit, Trash2 } from 'lucide-react';
import { cn } from '../../../lib/utils';

interface PricingRow {
  route: string;
  currentPrice: string;
  suggestedPrice: string;
  demand: 'High' | 'Optimal' | 'Low';
  competition: 'High' | 'Moderate' | 'Low';
  aiDecision: string;
  status: 'Pending' | 'Applied';
}

const tableRows: PricingRow[] = [
  {
    route: 'California Long Haul',
    currentPrice: '$2,450.00',
    suggestedPrice: '$2,680.00',
    demand: 'High',
    competition: 'Low',
    aiDecision: 'Increase +9.3%',
    status: 'Pending',
  },
  {
    route: 'Midwest Express Line',
    currentPrice: '$1,200.00',
    suggestedPrice: '$1,150.00',
    demand: 'Low',
    competition: 'High',
    aiDecision: 'Discount -4.1%',
    status: 'Applied',
  },
  {
    route: 'Texas Gulf Shuttle',
    currentPrice: '$950.00',
    suggestedPrice: '$950.00',
    demand: 'Optimal',
    competition: 'Moderate',
    aiDecision: 'Maintain Rate',
    status: 'Applied',
  },
  {
    route: 'Southeast Freight Loop',
    currentPrice: '$1,820.00',
    suggestedPrice: '$1,950.00',
    demand: 'High',
    competition: 'Moderate',
    aiDecision: 'Increase +7.1%',
    status: 'Pending',
  },
];

export default function PricingTable() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col shadow-md select-none w-full text-left">
      
      {/* Header Panel */}
      <div className="flex items-center gap-2 mb-6 flex-shrink-0">
        <ClipboardList className="w-4.5 h-4.5 text-[#0250C5]" />
        <h3 className="text-sm font-bold text-white font-sans tracking-tight">
          Dynamic Pricing suggested Adjustments
        </h3>
      </div>

      {/* Overflow Table Frame */}
      <div className="overflow-x-auto w-full scrollbar-none">
        <table className="w-full min-w-[900px] border-collapse">
          <thead>
            <tr className="border-b border-white/5 pb-3">
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[22%]">
                Route Corridor
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[13%]">
                Current Price
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[13%]">
                Suggested Price
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[11%]">
                Demand
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[11%]">
                Competition
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[14%]">
                AI Decision
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[10%]">
                Status
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[6%] text-center">
                Actions
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/2">
            {tableRows.map((row) => (
              <tr 
                key={row.route}
                className="hover:bg-white/2 transition-colors duration-150 cursor-pointer"
              >
                {/* Route */}
                <td className="py-3.5 text-xs font-bold text-slate-200 font-sans">
                  {row.route}
                </td>

                {/* Current */}
                <td className="py-3.5 text-xs font-medium text-slate-300 font-mono">
                  {row.currentPrice}
                </td>

                {/* Suggested */}
                <td className="py-3.5 text-xs font-bold text-slate-200 font-mono">
                  {row.suggestedPrice}
                </td>

                {/* Demand */}
                <td className="py-3.5 text-xs font-semibold font-sans">
                  <span className={cn(
                    row.demand === 'High' && 'text-red-400',
                    row.demand === 'Optimal' && 'text-blue-400',
                    row.demand === 'Low' && 'text-slate-500'
                  )}>
                    {row.demand}
                  </span>
                </td>

                {/* Competition */}
                <td className="py-3.5 text-xs font-medium text-slate-400 font-sans">
                  {row.competition}
                </td>

                {/* AI Decision */}
                <td className="py-3.5 text-xs font-bold font-sans">
                  <span className={cn(
                    row.aiDecision.startsWith('Increase') && 'text-emerald-400',
                    row.aiDecision.startsWith('Discount') && 'text-red-400',
                    row.aiDecision.startsWith('Maintain') && 'text-slate-400'
                  )}>
                    {row.aiDecision}
                  </span>
                </td>

                {/* Status badge */}
                <td className="py-3.5">
                  <span className={cn(
                    'px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider font-sans border inline-block select-none',
                    row.status === 'Applied' && 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                    row.status === 'Pending' && 'bg-blue-500/10 text-[#0250C5] border-blue-500/20'
                  )}>
                    {row.status}
                  </span>
                </td>

                {/* Action button triggers */}
                <td className="py-3.5">
                  <div className="flex items-center justify-center gap-1.5">
                    <button className="p-1.5 text-slate-400 hover:text-white hover:bg-white/5 rounded transition-colors cursor-pointer" title="Edit row">
                      <Edit className="w-3.5 h-3.5" />
                    </button>
                    <button className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded transition-colors cursor-pointer" title="Delete row">
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

    </div>
  );
}
