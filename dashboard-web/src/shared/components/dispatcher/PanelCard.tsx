import { motion } from 'framer-motion';
import { cn } from '../../../lib/utils';
import { dispatcherDesignTokens } from '../../constants/dispatcherDesignSystem';

interface PanelCardProps {
  children: React.ReactNode;
  className?: string;
  compact?: boolean;
  hover?: boolean;
}

export function PanelCard({ children, className, compact = false, hover = true }: PanelCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45, ease: [0.16, 1, 0.3, 1] as const }}
      whileHover={hover ? { y: -4, scale: 1.01 } : undefined}
      className={cn(dispatcherDesignTokens.surface, compact ? 'p-4' : 'p-6', className)}
    >
      {children}
    </motion.div>
  );
}
