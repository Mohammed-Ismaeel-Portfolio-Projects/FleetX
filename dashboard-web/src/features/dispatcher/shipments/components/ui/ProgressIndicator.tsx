import { motion } from 'framer-motion';
import { cn } from '../../../../../lib/utils';

interface ProgressIndicatorProps {
  progress: number; // 0-100
  size?: 'sm' | 'md' | 'lg';
  showLabel?: boolean;
  color?: 'emerald' | 'blue' | 'amber' | 'red' | 'purple';
  animated?: boolean;
}

const colorConfig = {
  emerald: 'bg-emerald-400',
  blue: 'bg-blue-400',
  amber: 'bg-amber-400',
  red: 'bg-red-400',
  purple: 'bg-purple-400',
};

const sizeConfig = {
  sm: {
    height: 'h-1',
    label: 'text-[9px]',
  },
  md: {
    height: 'h-1.5',
    label: 'text-[10px]',
  },
  lg: {
    height: 'h-2',
    label: 'text-xs',
  },
};

export default function ProgressIndicator({ 
  progress, 
  size = 'md', 
  showLabel = false, 
  color = 'emerald',
  animated = true 
}: ProgressIndicatorProps) {
  const sizeStyles = sizeConfig[size];
  const barColor = colorConfig[color];

  return (
    <div className="w-full">
      <div className="flex items-center justify-between mb-1">
        {showLabel && (
          <span className={cn('font-semibold text-slate-400', sizeStyles.label)}>
            {progress}%
          </span>
        )}
      </div>
      <div className={cn('w-full bg-white/5 rounded-full overflow-hidden', sizeStyles.height)}>
        <motion.div
          initial={{ width: 0 }}
          animate={{ width: `${progress}%` }}
          transition={{ duration: animated ? 0.8 : 0, ease: [0.16, 1, 0.3, 1] }}
          className={cn('h-full rounded-full', barColor)}
        />
      </div>
    </div>
  );
}
