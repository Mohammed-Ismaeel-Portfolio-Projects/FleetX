import { motion } from 'framer-motion';
import { cn } from '../../../../../lib/utils';

interface SkeletonProps {
  className?: string;
  variant?: 'text' | 'circular' | 'rectangular' | 'rounded';
  width?: string | number;
  height?: string | number;
  animation?: 'pulse' | 'wave' | 'none';
}

const animationVariants = {
  pulse: {
    animate: {
      opacity: [0.4, 1, 0.4],
      transition: {
        duration: 1.5,
        repeat: Infinity,
        ease: 'easeInOut',
      },
    },
  },
  wave: {
    animate: {
      x: ['-100%', '100%'],
    },
    transition: {
      duration: 1.5,
      repeat: Infinity,
      ease: 'linear',
    },
  },
  none: {},
};

export function Skeleton({ 
  className, 
  variant = 'rectangular', 
  width, 
  height, 
  animation = 'pulse' 
}: SkeletonProps) {
  const variantStyles = {
    text: 'h-4 rounded',
    circular: 'rounded-full',
    rectangular: 'rounded',
    rounded: 'rounded-lg',
  };

  const animationStyle = animation === 'pulse' ? animationVariants.pulse : animationVariants.none;

  return (
    <motion.div
      {...animationStyle}
      className={cn(
        'bg-white/5',
        variantStyles[variant],
        className
      )}
      style={{ width, height }}
    />
  );
}

export function ShipmentRowSkeleton() {
  return (
    <div className="flex items-center gap-4 p-4 border border-white/5 rounded-xl bg-[#0E1320]">
      <Skeleton variant="rectangular" width={100} height={20} />
      <Skeleton variant="text" width={120} />
      <Skeleton variant="text" width={100} />
      <Skeleton variant="text" width={100} />
      <Skeleton variant="rectangular" width={80} height={24} />
      <Skeleton variant="rectangular" width={80} height={24} />
      <Skeleton variant="text" width={80} />
      <Skeleton variant="text" width={60} />
      <div className="flex-1">
        <Skeleton variant="rectangular" height={6} />
      </div>
      <Skeleton variant="circular" width={32} height={32} />
    </div>
  );
}

export function KPICardSkeleton() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5">
      <div className="flex justify-between items-start mb-4">
        <Skeleton variant="rounded" width={48} height={48} />
        <Skeleton variant="text" width={60} />
      </div>
      <Skeleton variant="text" width={80} height={32} className="mb-2" />
      <Skeleton variant="text" width={100} className="mb-3" />
      <Skeleton variant="rectangular" height={6} />
    </div>
  );
}

export function ShipmentDetailsSkeleton() {
  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4 mb-6">
        <Skeleton variant="circular" width={64} height={64} />
        <div className="flex-1 space-y-2">
          <Skeleton variant="text" width={200} height={24} />
          <Skeleton variant="text" width={150} />
        </div>
      </div>
      
      <div className="grid grid-cols-2 gap-4">
        <Skeleton variant="rectangular" height={80} />
        <Skeleton variant="rectangular" height={80} />
      </div>
      
      <div className="space-y-3">
        <Skeleton variant="text" width={150} />
        <Skeleton variant="rectangular" height={60} />
        <Skeleton variant="rectangular" height={60} />
        <Skeleton variant="rectangular" height={60} />
      </div>
    </div>
  );
}

export function TableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="space-y-2">
      {/* Header */}
      <div className="flex items-center gap-4 p-4 border-b border-white/5">
        <Skeleton variant="text" width={100} />
        <Skeleton variant="text" width={120} />
        <Skeleton variant="text" width={100} />
        <Skeleton variant="text" width={100} />
        <Skeleton variant="text" width={80} />
        <Skeleton variant="text" width={80} />
        <Skeleton variant="text" width={80} />
        <Skeleton variant="text" width={60} />
        <Skeleton variant="text" width={100} />
        <Skeleton variant="text" width={100} />
      </div>
      
      {/* Rows */}
      {Array.from({ length: rows }).map((_, i) => (
        <ShipmentRowSkeleton key={i} />
      ))}
    </div>
  );
}
