import { motion } from 'framer-motion';
import { cn } from '../../../lib/utils';

interface DispatcherSurfaceProps {
  children: React.ReactNode;
  className?: string;
  animate?: boolean;
}

export function DispatcherSurface({ children, className, animate = true }: DispatcherSurfaceProps) {
  const content = (
    <div className={cn('flex w-full flex-col gap-6 text-left font-sans select-none', className)}>{children}</div>
  );

  if (!animate) return content;

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45, ease: [0.16, 1, 0.3, 1] as const }}
    >
      {content}
    </motion.div>
  );
}
