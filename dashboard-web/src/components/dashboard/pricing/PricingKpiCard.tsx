import { motion } from 'framer-motion';
import { cn } from '../../../lib/utils';

interface PricingKpiCardProps {
  label: string;
  value: string;
  trend: string;
  status: 'positive' | 'negative' | 'neutral';
  sparklinePoints: number[];
}

export default function PricingKpiCard({
  label,
  value,
  trend,
  status,
  sparklinePoints,
}: PricingKpiCardProps) {
  // Find min/max for simple scaling inside the sparkline
  const min = Math.min(...sparklinePoints);
  const max = Math.max(...sparklinePoints);
  const range = max - min || 1;

  // Map values to coordinates
  const svgWidth = 80;
  const svgHeight = 22;
  const pointsString = sparklinePoints
    .map((val, index) => {
      const x = (index / (sparklinePoints.length - 1)) * svgWidth;
      const y = svgHeight - 2 - ((val - min) / range) * (svgHeight - 4);
      return `${x},${y}`;
    })
    .join(' ');

  return (
    <motion.div
      whileHover={{ y: -4, borderColor: 'rgba(255, 255, 255, 0.1)', backgroundColor: '#121829' }}
      transition={{ duration: 0.25, ease: [0.16, 1, 0.3, 1] as const }}
      className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between select-none shadow-md cursor-pointer relative overflow-hidden group min-h-[120px] text-left"
    >
      {/* Label Title */}
      <div className="flex justify-between items-start gap-4">
        <span className="text-[9px] font-bold text-slate-500 tracking-wider uppercase font-sans">
          {label}
        </span>
      </div>

      {/* Main Value & Sparkline Row */}
      <div className="flex items-end justify-between gap-4 mt-3">
        {/* Metric */}
        <span className="text-2xl font-extrabold text-white tracking-tight font-sans block leading-none">
          {value}
        </span>

        {/* Sparkline Graph */}
        <div className="w-[80px] h-[22px] flex items-end opacity-75 group-hover:opacity-100 transition-opacity duration-200">
          <svg className="w-full h-full" viewBox={`0 0 ${svgWidth} ${svgHeight}`}>
            <polyline
              fill="none"
              stroke={
                status === 'positive'
                  ? '#10b981' // emerald-500
                  : status === 'negative'
                  ? '#ef4444' // red-500
                  : '#3b82f6' // blue-500
              }
              strokeWidth="1.8"
              points={pointsString}
            />
          </svg>
        </div>
      </div>

      {/* Trend details */}
      <div className="mt-3 flex items-center gap-1.5">
        <span className={cn(
          'text-[10px] font-bold font-sans tracking-tight leading-none',
          status === 'positive' && 'text-emerald-400',
          status === 'negative' && 'text-red-400',
          status === 'neutral' && 'text-[#0250C5]'
        )}>
          {trend}
        </span>
      </div>
    </motion.div>
  );
}
