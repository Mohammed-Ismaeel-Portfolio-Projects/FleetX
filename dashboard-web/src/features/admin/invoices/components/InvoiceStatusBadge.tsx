import { cn } from '../../../../lib/utils';
import type { MonthlyInvoiceStatus } from '../types/invoice.types';
import { formatInvoiceStatusLabel } from '../utils/invoiceFormatters';

interface InvoiceStatusBadgeProps {
  status: MonthlyInvoiceStatus;
  className?: string;
}

const statusStyles: Record<MonthlyInvoiceStatus, string> = {
  draft: 'bg-amber-500/10 text-amber-300 border-amber-500/20',
  finalized: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
  void: 'bg-slate-500/10 text-slate-300 border-slate-500/30',
  paid: 'bg-cyan-500/10 text-cyan-300 border-cyan-500/20',
};

export default function InvoiceStatusBadge({ status, className }: InvoiceStatusBadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-widest',
        statusStyles[status],
        className,
      )}
    >
      {formatInvoiceStatusLabel(status)}
    </span>
  );
}
