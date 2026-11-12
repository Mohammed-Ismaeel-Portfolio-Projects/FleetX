import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';
import type { LucideIcon } from 'lucide-react';
import { cn } from '../../../lib/utils';

interface KpiCardProps {
  icon: LucideIcon;
  label: string;
  value: string;
  subtitle?: string;
  status: 'positive' | 'negative' | 'neutral';
  statusColor?: string;
  iconBgColor?: string;
  iconColor?: string;
  href?: string;
}

function KpiCardBody({
  icon: Icon,
  label,
  value,
  subtitle,
  status,
  statusColor,
  iconBgColor,
  iconColor,
}: Omit<KpiCardProps, 'href'>) {
  return (
    <>
      <div className="flex justify-between items-start gap-4">
        <span className="text-[9px] font-bold text-slate-500 tracking-wider uppercase font-sans">
          {label}
        </span>
        <div className={cn(
          'w-7 h-7 rounded-lg flex items-center justify-center border transition-all duration-300',
          iconBgColor || 'bg-white/5 border-white/5 group-hover:border-white/10'
        )}>
          <Icon className={cn('w-3.5 h-3.5', iconColor || 'text-slate-400 group-hover:text-white')} />
        </div>
      </div>

      <div className="mt-3.5">
        <span className={cn(
          'text-2xl font-extrabold tracking-tight font-sans block leading-none',
          value === 'Unavailable' ? 'text-slate-400' : 'text-white'
        )}>
          {value}
        </span>
      </div>

      {subtitle ? (
        <div className="mt-2.5 flex items-center gap-1.5">
          <span className={cn(
            'text-[10px] font-bold font-sans tracking-tight leading-none',
            value === 'Unavailable' && 'text-slate-500',
            value !== 'Unavailable' && status === 'positive' && (statusColor || 'text-emerald-400'),
            value !== 'Unavailable' && status === 'negative' && (statusColor || 'text-red-400'),
            value !== 'Unavailable' && status === 'neutral' && (statusColor || 'text-slate-400')
          )}>
            {subtitle}
          </span>
        </div>
      ) : null}
    </>
  );
}

export default function KpiCard(props: KpiCardProps) {
  const className = cn(
    'bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between select-none shadow-md relative overflow-hidden group min-h-[120px] text-left',
    props.href && 'cursor-pointer hover:border-white/10 transition-colors'
  );

  if (props.href) {
    return (
      <motion.div whileHover={{ y: -4 }} transition={{ duration: 0.25, ease: [0.16, 1, 0.3, 1] as const }}>
        <Link to={props.href} className={className} aria-label={`Open ${props.label}`}>
          <KpiCardBody {...props} />
        </Link>
      </motion.div>
    );
  }

  return (
    <motion.div
      whileHover={{ y: -4, borderColor: 'rgba(255, 255, 255, 0.1)', backgroundColor: '#121829' }}
      transition={{ duration: 0.25, ease: [0.16, 1, 0.3, 1] as const }}
      className={className}
    >
      <KpiCardBody {...props} />
    </motion.div>
  );
}
