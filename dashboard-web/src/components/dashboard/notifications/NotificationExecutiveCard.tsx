import { motion } from 'framer-motion';
import type { LucideIcon } from 'lucide-react';
import type { NotificationKPI } from '../../../features/dispatcher/notifications/services/notification-center.service';

interface NotificationExecutiveCardProps {
  kpi: NotificationKPI;
  icon: LucideIcon;
}

export default function NotificationExecutiveCard({ kpi, icon: Icon }: NotificationExecutiveCardProps) {
  const getColorClasses = (color: string) => {
    const colors = {
      red: 'border-red-500/30 bg-red-500/10 text-red-400',
      orange: 'border-orange-500/30 bg-orange-500/10 text-orange-400',
      blue: 'border-blue-500/30 bg-blue-500/10 text-blue-400',
      green: 'border-green-500/30 bg-green-500/10 text-green-400',
      purple: 'border-purple-500/30 bg-purple-500/10 text-purple-400',
    };
    return colors[color as keyof typeof colors] || colors.blue;
  };

  const getTrendIcon = (trend: string) => {
    switch (trend) {
      case 'up':
        return '↑';
      case 'down':
        return '↓';
      case 'stable':
        return '→';
      default:
        return '→';
    }
  };

  const getTrendColor = (trend: string) => {
    switch (trend) {
      case 'up':
        return 'text-green-400';
      case 'down':
        return 'text-red-400';
      case 'stable':
        return 'text-slate-400';
      default:
        return 'text-slate-400';
    }
  };

  const colorClasses = getColorClasses(kpi.color);
  const trendIcon = getTrendIcon(kpi.trend);
  const trendColor = getTrendColor(kpi.trend);

  // Calculate sparkline height percentages
  const maxSparkline = Math.max(...kpi.sparkline);
  const minSparkline = Math.min(...kpi.sparkline);
  const sparklineRange = maxSparkline - minSparkline || 1;

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      whileHover={{ scale: 1.01 }}
      className={`rounded-xl border ${colorClasses} bg-gradient-to-br from-white/5 to-white/[0.02] p-4 backdrop-blur-sm shadow-sm`}
    >
      <div className="mb-3 flex items-center gap-2">
        <Icon className="h-4 w-4" />
        <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
          {kpi.title}
        </span>
      </div>

      <div className="mb-2 text-2xl font-mono font-bold text-white">
        {kpi.primaryValue}
      </div>

      <div className="mb-3 text-[10px] text-slate-400">
        {kpi.secondaryLabel}
      </div>

      <div className="mb-3 flex items-center gap-2">
        <span className={`text-lg font-semibold ${trendColor}`}>
          {trendIcon}
        </span>
        <span className={`text-xs font-semibold ${trendColor}`}>
          {kpi.trendValue}%
        </span>
      </div>

      {/* Sparkline */}
      <div className="flex items-end gap-0.5 h-8">
        {kpi.sparkline.map((value, index) => {
          const height = ((value - minSparkline) / sparklineRange) * 100;
          return (
            <div
              key={index}
              className="flex-1 rounded-t bg-current opacity-60"
              style={{ height: `${Math.max(height, 10)}%` }}
            />
          );
        })}
      </div>
    </motion.div>
  );
}
