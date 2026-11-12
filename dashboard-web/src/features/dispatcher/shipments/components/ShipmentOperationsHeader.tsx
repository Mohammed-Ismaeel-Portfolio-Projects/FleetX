import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { RefreshCw, Calendar, Clock, Package, AlertTriangle, CheckCircle } from 'lucide-react';
import { cn } from '../../../../lib/utils';

interface QuickStat {
  label: string;
  value: number;
  icon: any;
  color: string;
  bgColor: string;
}

const quickStats: QuickStat[] = [
  {
    label: "Today's Shipments",
    value: 284,
    icon: Package,
    color: 'text-blue-400',
    bgColor: 'bg-blue-500/10 border-blue-500/20',
  },
  {
    label: 'Pending Assignments',
    value: 18,
    icon: Clock,
    color: 'text-amber-400',
    bgColor: 'bg-amber-500/10 border-amber-500/20',
  },
  {
    label: 'Delayed Shipments',
    value: 3,
    icon: AlertTriangle,
    color: 'text-red-400',
    bgColor: 'bg-red-500/10 border-red-500/20',
  },
  {
    label: 'High Priority',
    value: 12,
    icon: AlertTriangle,
    color: 'text-orange-400',
    bgColor: 'bg-orange-500/10 border-orange-500/20',
  },
  {
    label: 'Completed Today',
    value: 92,
    icon: CheckCircle,
    color: 'text-emerald-400',
    bgColor: 'bg-emerald-500/10 border-emerald-500/20',
  },
];

function StatCard({ stat }: { stat: QuickStat }) {
  const Icon = stat.icon;
  
  return (
    <motion.div
      whileHover={{ y: -2 }}
      className={cn(
        'flex items-center gap-3 px-4 py-3 rounded-xl border',
        stat.bgColor
      )}
    >
      <div className={cn('w-10 h-10 rounded-lg flex items-center justify-center border', stat.bgColor)}>
        <Icon className={cn('w-5 h-5', stat.color)} />
      </div>
      <div>
        <p className="text-2xl font-bold text-white">{stat.value}</p>
        <p className="text-[10px] text-slate-400 uppercase tracking-wider">{stat.label}</p>
      </div>
    </motion.div>
  );
}

export default function ShipmentOperationsHeader() {
  const [currentTime, setCurrentTime] = useState(new Date());
  const [isRefreshing, setIsRefreshing] = useState(false);

  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const handleRefresh = () => {
    setIsRefreshing(true);
    setTimeout(() => setIsRefreshing(false), 1000);
  };

  const formatTime = (date: Date) => {
    return date.toLocaleTimeString('en-US', { 
      hour: '2-digit', 
      minute: '2-digit',
      hour12: true 
    });
  };

  const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', { 
      weekday: 'long', 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    });
  };

  const getShift = () => {
    const hour = currentTime.getHours();
    if (hour >= 6 && hour < 14) return 'Morning Shift';
    if (hour >= 14 && hour < 22) return 'Afternoon Shift';
    return 'Night Shift';
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: -10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
      className="space-y-4"
    >
      {/* Top Bar */}
      <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div className="flex-1">
          <h1 className="text-2xl font-bold text-white font-sans tracking-tight">
            Shipment Operations Center
          </h1>
          <div className="flex items-center gap-4 mt-2">
            <div className="flex items-center gap-2">
              <Clock className="w-4 h-4 text-slate-500" />
              <span className="text-sm text-slate-400 font-medium">{getShift()}</span>
            </div>
            <div className="flex items-center gap-2">
              <Calendar className="w-4 h-4 text-slate-500" />
              <span className="text-sm text-slate-400 font-medium">{formatDate(currentTime)}</span>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-sm text-slate-400 font-medium">{formatTime(currentTime)}</span>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <div className="flex items-center gap-2 px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">
            <div className="relative">
              <div className="w-2.5 h-2.5 bg-emerald-500 rounded-full" />
              <div className="absolute inset-0 w-2.5 h-2.5 bg-emerald-500 rounded-full animate-ping" />
            </div>
            <span className="text-xs font-bold text-emerald-400 uppercase tracking-wider">Live</span>
          </div>
          
          <button
            onClick={handleRefresh}
            disabled={isRefreshing}
            className="flex items-center gap-2 px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <RefreshCw className={cn('w-4 h-4 text-slate-400', isRefreshing && 'animate-spin')} />
            <span className="text-xs font-bold text-slate-300 uppercase tracking-wider">Refresh</span>
          </button>
        </div>
      </div>

      {/* Quick Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        {quickStats.map((stat) => (
          <StatCard key={stat.label} stat={stat} />
        ))}
      </div>
    </motion.div>
  );
}
