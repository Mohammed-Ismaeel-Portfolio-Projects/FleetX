import { motion } from 'framer-motion';
import { CheckCircle, Clock, Package, Truck, AlertCircle, Circle } from 'lucide-react';

interface TimelineItem {
  time: string;
  title: string;
  desc: string;
}

interface ShipmentTimelineProps {
  timeline: TimelineItem[];
}

function getTimelineIcon(title: string) {
  if (title.includes('Created') || title.includes('Order')) return Package;
  if (title.includes('Assigned') || title.includes('Driver')) return Truck;
  if (title.includes('Departed') || title.includes('Loaded')) return Truck;
  if (title.includes('In Transit') || title.includes('Checkpoint')) return Clock;
  if (title.includes('Delivered')) return CheckCircle;
  if (title.includes('Incident') || title.includes('Delayed')) return AlertCircle;
  return Circle;
}

function getTimelineColor(title: string) {
  if (title.includes('Created') || title.includes('Order')) return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
  if (title.includes('Assigned') || title.includes('Driver')) return 'text-purple-400 bg-purple-500/10 border-purple-500/20';
  if (title.includes('Departed') || title.includes('Loaded')) return 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';
  if (title.includes('In Transit') || title.includes('Checkpoint')) return 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20';
  if (title.includes('Delivered')) return 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';
  if (title.includes('Incident') || title.includes('Delayed')) return 'text-red-400 bg-red-500/10 border-red-500/20';
  return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
}

export default function ShipmentTimeline({ timeline }: ShipmentTimelineProps) {
  if (!timeline.length) {
    return (
      <p className="text-sm text-slate-400">
        Unavailable — no tracking_logs for this shipment
      </p>
    );
  }

  return (
    <div className="space-y-4">
      {timeline.map((item, index) => {
        const Icon = getTimelineIcon(item.title);
        const colorClass = getTimelineColor(item.title);
        const isLast = index === timeline.length - 1;

        return (
          <motion.div
            key={index}
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: index * 0.1 }}
            className="flex gap-4"
          >
            {/* Icon */}
            <div className={`flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center border ${colorClass}`}>
              <Icon className="w-5 h-5" />
            </div>

            {/* Content */}
            <div className="flex-1 pb-4">
              <div className="flex items-start justify-between mb-1">
                <h4 className="text-sm font-semibold text-white">{item.title}</h4>
                <span className="text-xs text-slate-500 font-mono">{item.time}</span>
              </div>
              <p className="text-xs text-slate-400 leading-relaxed">{item.desc}</p>
            </div>

            {/* Connector Line */}
            {!isLast && (
              <div className="absolute left-[29px] top-10 w-0.5 h-full bg-gradient-to-b from-white/10 to-transparent" />
            )}
          </motion.div>
        );
      })}
    </div>
  );
}
