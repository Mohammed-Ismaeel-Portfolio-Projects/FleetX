import { motion } from 'framer-motion';
import type { LucideIcon } from 'lucide-react';
import { cn } from '../../../lib/utils';

interface RoleSummaryCardProps {
  role: string;
  badge: string;
  badgeStyle: string;
  description: string;
  usersCount: string;
  icon: LucideIcon;
}

export default function RoleSummaryCard({
  role,
  badge,
  badgeStyle,
  description,
  usersCount,
  icon: Icon,
}: RoleSummaryCardProps) {
  return (
    <motion.div
      whileHover={{ y: -4, borderColor: 'rgba(255, 255, 255, 0.1)', backgroundColor: '#121829' }}
      transition={{ duration: 0.25, ease: [0.16, 1, 0.3, 1] as const }}
      className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between select-none shadow-md cursor-pointer min-h-[160px] text-left relative overflow-hidden group"
    >
      {/* Top Header Row (Icon + Role Badge) */}
      <div className="flex justify-between items-center gap-4 mb-4">
        <div className="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center border border-blue-500/20 text-[#0250C5] group-hover:text-cyan-400 group-hover:border-cyan-500/25 transition-colors duration-200">
          <Icon className="w-4.5 h-4.5" />
        </div>

        {/* Status Pill Badge */}
        <span className={cn(
          'px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider border font-sans',
          badgeStyle
        )}>
          {badge}
        </span>
      </div>

      {/* Role Title & Description */}
      <div className="flex-grow flex flex-col mb-4">
        <h4 className="text-xs font-extrabold text-slate-200 font-sans tracking-tight mb-1">
          {role}
        </h4>
        <p className="text-slate-500 text-[10px] font-normal leading-normal">
          {description}
        </p>
      </div>

      {/* Bottom Metrics Row (Users Count + Edit text button) */}
      <div className="flex items-center justify-between mt-auto">
        <span className="text-xs font-bold text-white font-mono leading-none">
          {usersCount} Users
        </span>

        <span className="text-[10px] font-bold text-slate-400 hover:text-white uppercase tracking-wider font-sans transition-colors">
          Edit
        </span>
      </div>

    </motion.div>
  );
}
