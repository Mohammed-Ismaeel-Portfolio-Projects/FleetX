import { TrendingUp, TrendingDown, DollarSign, Fuel, User, Wrench } from 'lucide-react';

interface ProfitData {
  estimatedRevenue: number;
  estimatedOperatingCost: number;
  estimatedProfit: number;
  profitMargin: number;
  fuelCost: number;
  driverCost: number;
  equipmentCost: number;
}

interface ProfitEstimatorProps {
  data: ProfitData;
}

export default function ProfitEstimator({ data }: ProfitEstimatorProps) {
  const kpiCards = [
    {
      label: 'Estimated Revenue',
      value: data.estimatedRevenue,
      icon: DollarSign,
      color: 'text-cyan-400',
      bgColor: 'bg-cyan-500/10',
      borderColor: 'border-cyan-500/20',
    },
    {
      label: 'Estimated Operating Cost',
      value: data.estimatedOperatingCost,
      icon: TrendingDown,
      color: 'text-red-400',
      bgColor: 'bg-red-500/10',
      borderColor: 'border-red-500/20',
    },
    {
      label: 'Estimated Profit',
      value: data.estimatedProfit,
      icon: TrendingUp,
      color: 'text-green-400',
      bgColor: 'bg-green-500/10',
      borderColor: 'border-green-500/20',
    },
    {
      label: 'Profit Margin',
      value: data.profitMargin,
      icon: TrendingUp,
      color: 'text-purple-400',
      bgColor: 'bg-purple-500/10',
      borderColor: 'border-purple-500/20',
      isPercentage: true,
    },
  ];

  const costBreakdown = [
    {
      label: 'Fuel Cost',
      value: data.fuelCost,
      icon: Fuel,
      color: 'text-orange-400',
    },
    {
      label: 'Driver Cost',
      value: data.driverCost,
      icon: User,
      color: 'text-blue-400',
    },
    {
      label: 'Equipment Cost',
      value: data.equipmentCost,
      icon: Wrench,
      color: 'text-green-400',
    },
  ];

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center gap-2">
        <TrendingUp className="h-4 w-4 text-cyan-400" />
        <h3 className="text-sm font-semibold text-white">Profit Estimation</h3>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        {kpiCards.map((card) => {
          const Icon = card.icon;
          return (
            <div
              key={card.label}
              className={`rounded-lg border ${card.borderColor} ${card.bgColor} p-3`}
            >
              <div className="mb-2">
                <Icon className={`h-4 w-4 ${card.color}`} />
              </div>
              <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                {card.label}
              </div>
              <div className={`text-lg font-mono font-bold ${card.color}`}>
                {card.isPercentage ? `${card.value.toFixed(1)}%` : `$${card.value.toFixed(2)}`}
              </div>
            </div>
          );
        })}
      </div>

      {/* Cost Breakdown */}
      <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
        <div className="mb-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
          Operating Cost Breakdown
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          {costBreakdown.map((item) => {
            const Icon = item.icon;
            return (
              <div key={item.label} className="flex items-center gap-3">
                <div className={`rounded-lg border border-white/10 bg-white/5 p-2 ${item.color}`}>
                  <Icon className="h-4 w-4" />
                </div>
                <div className="flex-1">
                  <div className="text-[10px] text-slate-400">{item.label}</div>
                  <div className={`text-sm font-mono font-semibold ${item.color}`}>
                    ${item.value.toFixed(2)}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
