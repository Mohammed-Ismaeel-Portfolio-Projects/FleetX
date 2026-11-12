import { memo } from 'react';
import { DollarSign, TrendingUp, Settings, Tag, Clock, Activity } from 'lucide-react';
import type { PricingStats } from '../types/pricing.types';

interface PricingOverviewProps {
  stats: PricingStats;
}

interface StatCard {
  label: string;
  value: string | number;
  icon: any;
  color: string;
  bgColor: string;
  borderColor: string;
}

function PricingOverviewComponent({ stats }: PricingOverviewProps) {
  const cards: StatCard[] = [
    {
      label: 'Total Pricing Rules',
      value: stats.totalPricingRules,
      icon: Settings,
      color: 'text-cyan-400',
      bgColor: 'bg-cyan-500/10',
      borderColor: 'border-cyan-500/20',
    },
    {
      label: 'Base Rates',
      value: stats.baseRates,
      icon: DollarSign,
      color: 'text-green-400',
      bgColor: 'bg-green-500/10',
      borderColor: 'border-green-500/20',
    },
    {
      label: 'Extra Charge Rules',
      value: stats.extraChargeRules,
      icon: Tag,
      color: 'text-yellow-400',
      bgColor: 'bg-yellow-500/10',
      borderColor: 'border-yellow-500/20',
    },
    {
      label: 'Discount Rules',
      value: stats.discountRules,
      icon: TrendingUp,
      color: 'text-purple-400',
      bgColor: 'bg-purple-500/10',
      borderColor: 'border-purple-500/20',
    },
    {
      label: 'Avg Shipment Cost',
      value: `$${stats.averageShipmentCost.toLocaleString()}`,
      icon: Activity,
      color: 'text-orange-400',
      bgColor: 'bg-orange-500/10',
      borderColor: 'border-orange-500/20',
    },
    {
      label: 'Last Updated',
      value: new Date(stats.lastUpdated).toLocaleDateString(),
      icon: Clock,
      color: 'text-slate-400',
      bgColor: 'bg-slate-500/10',
      borderColor: 'border-slate-500/20',
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
      {cards.map((card) => (
        <div
          key={card.label}
          className={`rounded-xl border ${card.borderColor} ${card.bgColor} bg-[#0E1320] p-4 shadow-sm transition-all duration-200 hover:border-white/10 hover:shadow-md`}
        >
          <div className="flex items-start gap-3">
            <div className={`rounded-lg ${card.bgColor} p-2`}>
              <card.icon className={`h-4 w-4 ${card.color}`} />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                {card.label}
              </p>
              <p className={`mt-1 font-mono text-lg font-bold ${card.color} transition-all duration-200`}>
                {card.value}
              </p>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

export default memo(PricingOverviewComponent);
