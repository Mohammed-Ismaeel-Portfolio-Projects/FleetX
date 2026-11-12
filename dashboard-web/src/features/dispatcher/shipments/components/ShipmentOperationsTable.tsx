import { motion } from 'framer-motion';
import { Package } from 'lucide-react';
import type { Shipment } from '../types/shipment.types';
import ShipmentRow from './ShipmentRow';
import { TableSkeleton } from './ui/SkeletonLoader';

interface ShipmentOperationsTableProps {
  shipments: Shipment[];
  isLoading?: boolean;
  onRowClick?: (shipment: Shipment) => void;
  pagination?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
  onPageChange?: (page: number) => void;
}

export default function ShipmentOperationsTable({
  shipments,
  isLoading = false,
  onRowClick,
  pagination,
  onPageChange,
}: ShipmentOperationsTableProps) {
  if (isLoading) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-6 shadow-lg">
        <TableSkeleton rows={5} />
      </div>
    );
  }

  if (shipments.length === 0) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-12 shadow-lg flex flex-col items-center justify-center">
        <div className="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center mb-4">
          <Package className="w-8 h-8 text-slate-500" />
        </div>
        <h3 className="text-lg font-bold text-white mb-2">No Shipments Found</h3>
        <p className="text-sm text-slate-400 text-center max-w-md">
          No shipments match your current filters. Try adjusting your search or filter criteria.
        </p>
      </div>
    );
  }

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.05,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 10 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.3, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="space-y-4"
    >
      {/* Table Header */}
      <div className="flex items-center justify-between px-2">
        <h3 className="text-sm font-bold text-slate-500 uppercase tracking-wider">
          {shipments.length} Shipments
        </h3>
      </div>

      {/* Shipment Rows */}
      <div className="space-y-3">
        {shipments.map((shipment) => (
          <motion.div key={shipment.id} variants={itemVariants}>
            <ShipmentRow
              shipment={shipment}
              onClick={() => onRowClick?.(shipment)}
            />
          </motion.div>
        ))}
      </div>

      {/* Pagination */}
      {pagination && (
        <div className="flex items-center justify-between pt-4 border-t border-white/5">
          <p className="text-xs text-slate-500">
            Showing {shipments.length} of {pagination.total} shipments
          </p>
          <div className="flex items-center gap-2">
            <button 
              onClick={() => onPageChange?.(pagination.current_page - 1)}
              disabled={pagination.current_page <= 1}
              className="px-3 py-1.5 text-xs font-semibold text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Previous
            </button>
            <button 
              onClick={() => onPageChange?.(pagination.current_page + 1)}
              disabled={pagination.current_page >= pagination.last_page}
              className="px-3 py-1.5 text-xs font-semibold text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </motion.div>
  );
}
