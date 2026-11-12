import { ChevronDown, ChevronUp, DollarSign } from 'lucide-react';
import { useState } from 'react';

interface CostItem {
  id: string;
  name: string;
  calculation: string;
  amount: number;
  category: 'base' | 'distance' | 'weight' | 'equipment' | 'services' | 'waiting' | 'border' | 'insurance' | 'extra' | 'discount';
}

interface CostBreakdownProps {
  costs: CostItem[];
  total: number;
}

export default function CostBreakdown({ costs, total }: CostBreakdownProps) {
  const [isExpanded, setIsExpanded] = useState(true);

  const getCategoryColor = (category: CostItem['category']) => {
    switch (category) {
      case 'base':
        return 'text-cyan-400';
      case 'distance':
        return 'text-blue-400';
      case 'weight':
        return 'text-purple-400';
      case 'equipment':
        return 'text-green-400';
      case 'services':
        return 'text-yellow-400';
      case 'waiting':
        return 'text-orange-400';
      case 'border':
        return 'text-pink-400';
      case 'insurance':
        return 'text-teal-400';
      case 'extra':
        return 'text-red-400';
      case 'discount':
        return 'text-green-400';
      default:
        return 'text-slate-400';
    }
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <DollarSign className="h-4 w-4 text-cyan-400" />
          <h3 className="text-sm font-semibold text-white">Live Cost Breakdown</h3>
        </div>
        <button
          type="button"
          onClick={() => setIsExpanded(!isExpanded)}
          className="rounded bg-white/5 p-1.5 text-slate-400 transition-colors hover:bg-white/10"
        >
          {isExpanded ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
        </button>
      </div>

      {isExpanded && (
        <div className="space-y-2">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-white/5">
                  <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Rule Name
                  </th>
                  <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Calculation
                  </th>
                  <th className="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Amount
                  </th>
                </tr>
              </thead>
              <tbody>
                {costs.map((cost) => (
                  <tr key={cost.id} className="border-b border-white/5 hover:bg-white/[0.02]">
                    <td className="px-3 py-2">
                      <span className="text-xs text-white">{cost.name}</span>
                    </td>
                    <td className="px-3 py-2">
                      <span className="text-[10px] text-slate-400">{cost.calculation}</span>
                    </td>
                    <td className="px-3 py-2 text-right">
                      <span className={`text-xs font-mono font-semibold ${getCategoryColor(cost.category)}`}>
                        {cost.category === 'discount' ? '-' : ''}
                        ${Math.abs(cost.amount).toFixed(2)}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Total */}
          <div className="mt-4 rounded-lg border border-cyan-500/30 bg-cyan-500/5 p-4">
            <div className="flex items-center justify-between">
              <div>
                <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                  Estimated Shipment Price
                </div>
              </div>
              <div className="text-2xl font-mono font-bold text-cyan-400">
                ${total.toFixed(2)}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
