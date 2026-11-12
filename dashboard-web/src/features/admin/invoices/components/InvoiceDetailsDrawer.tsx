import { useEffect, useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { CreditCard, FileText } from 'lucide-react';
import type { MonthlyInvoiceDetail } from '../types/invoice.types';
import InvoiceStatusBadge from './InvoiceStatusBadge';
import {
  formatBillingPeriod,
  formatDateTime,
  formatInvoiceMoney,
} from '../utils/invoiceFormatters';

interface InvoiceDetailsDrawerProps {
  invoice: MonthlyInvoiceDetail | null;
  isOpen: boolean;
  isLoading: boolean;
  isPdfLoading: boolean;
  isLifecycleSubmitting: boolean;
  isCheckoutLoading?: boolean;
  onClose: () => void;
  onFinalize: () => void;
  onVoid: () => void;
  onOpenPdf: () => void;
  onPayWithStripe?: () => void;
}

function DetailSection({ title, rows }: { title: string; rows: Array<[string, string | number | null]> }) {
  return (
    <section className="rounded-2xl border border-white/5 bg-[#090D16] p-4">
      <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">{title}</h3>
      <dl className="mt-4 grid grid-cols-1 gap-3">
        {rows.map(([label, value]) => (
          <div key={label} className="flex items-start justify-between gap-4">
            <dt className="text-xs text-slate-500">{label}</dt>
            <dd className="max-w-[68%] text-right text-xs font-semibold text-slate-200">{value ?? '—'}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export default function InvoiceDetailsDrawer({
  invoice,
  isOpen,
  isLoading,
  isPdfLoading,
  isLifecycleSubmitting,
  isCheckoutLoading = false,
  onClose,
  onFinalize,
  onVoid,
  onOpenPdf,
  onPayWithStripe,
}: InvoiceDetailsDrawerProps) {
  const closeRef = useRef<HTMLButtonElement>(null);
  const showFinalize = invoice?.status === 'draft';
  const showVoid = invoice?.status === 'draft' || invoice?.status === 'finalized';
  const isVoid = invoice?.status === 'void';

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    closeRef.current?.focus();
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Monthly invoice details">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"
            onClick={onClose}
          />
          <motion.aside
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ duration: 0.24 }}
            className="absolute right-0 top-0 flex h-full w-full max-w-3xl flex-col border-l border-white/10 bg-[#0E1320] shadow-2xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-white/5 px-6 py-5">
              <div className="flex items-start gap-3">
                <span className="rounded-xl border border-secondary/20 bg-secondary/10 p-2 text-accent">
                  <FileText className="h-5 w-5" />
                </span>
                <div>
                  <div className="flex items-center gap-2">
                    <h2 className="text-lg font-bold text-white">Monthly Invoice</h2>
                    {invoice && <InvoiceStatusBadge status={invoice.status} />}
                  </div>
                  <p className="mt-1 text-xs text-slate-500">
                    {invoice ? `Invoice #${invoice.id}` : 'Loading invoice details'}
                  </p>
                </div>
              </div>
              <button
                ref={closeRef}
                type="button"
                onClick={onClose}
                className="rounded-xl p-2 text-slate-500 transition-colors hover:bg-white/5 hover:text-white"
                aria-label="Close invoice details"
              >
                ×
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6">
              {isLoading ? (
                <div className="flex h-64 items-center justify-center">
                  <span className="h-8 w-8 rounded-full border-2 border-[#0250C5] border-t-transparent animate-spin" />
                </div>
              ) : invoice ? (
                <div className="space-y-4">
                  {isVoid && (
                    <div className="rounded-2xl border border-slate-500/30 bg-slate-500/10 px-4 py-3">
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-200">VOID</p>
                      <p className="mt-1 text-xs text-slate-400">
                        This invoice is void and no longer valid for billing. Persisted amounts remain preserved for audit history.
                      </p>
                    </div>
                  )}

                  {invoice.status === 'paid' && (
                    <div className="rounded-2xl border border-cyan-500/30 bg-cyan-500/10 px-4 py-3">
                      <p className="text-xs font-bold uppercase tracking-widest text-cyan-200">PAID</p>
                      <p className="mt-1 text-xs text-cyan-300">
                        This invoice has been successfully paid via Stripe Checkout Test integration.
                      </p>
                    </div>
                  )}

                  <DetailSection
                    title="Invoice Header"
                    rows={[
                      ['Invoice ID', invoice.id],
                      ['Company', invoice.company?.company_name ?? '—'],
                      ['Company Code', invoice.company?.company_code ?? '—'],
                      ['Billing Period', formatBillingPeriod(invoice.billing_period)],
                      ['Currency', invoice.currency],
                      ['Status', invoice.status.toUpperCase()],
                      ['Subtotal', formatInvoiceMoney(invoice.subtotal, invoice.currency)],
                      ['Total', formatInvoiceMoney(invoice.total, invoice.currency)],
                      ['Issued At', formatDateTime(invoice.issued_at)],
                      ['Paid At', formatDateTime(invoice.paid_at)],
                      ['Created At', formatDateTime(invoice.created_at)],
                    ]}
                  />

                  <section className="rounded-2xl border border-white/5 bg-[#090D16] p-4">
                    <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Line Items</h3>
                    {!invoice.line_items || invoice.line_items.length === 0 ? (
                      <p className="mt-4 text-xs text-slate-500">No line items returned for this invoice.</p>
                    ) : (
                      <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[760px] border-collapse text-xs">
                          <thead>
                            <tr className="border-b border-white/5 text-left text-[10px] uppercase tracking-widest text-slate-500">
                              <th className="pb-2 pr-3">Line #</th>
                              <th className="pb-2 pr-3">Shipment</th>
                              <th className="pb-2 pr-3">Description</th>
                              <th className="pb-2 pr-3">Qty</th>
                              <th className="pb-2 pr-3">Unit Price</th>
                              <th className="pb-2 pr-3">Line Total</th>
                              <th className="pb-2">Audit Snapshot</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-white/5">
                            {invoice.line_items.map((item) => (
                              <tr key={item.id}>
                                <td className="py-3 pr-3 font-mono text-slate-300">{item.id}</td>
                                <td className="py-3 pr-3 text-slate-300">{item.shipment_reference || '—'}</td>
                                <td className="py-3 pr-3 text-slate-300">{item.description || '—'}</td>
                                <td className="py-3 pr-3 text-slate-300">{item.quantity}</td>
                                <td className="py-3 pr-3 font-mono text-slate-200">{formatInvoiceMoney(item.unit_price, item.currency)}</td>
                                <td className="py-3 pr-3 font-mono text-slate-200">{formatInvoiceMoney(item.line_total, item.currency)}</td>
                                <td className="py-3 text-slate-400">
                                  {item.final_price_breakdown ? (
                                    <div className="space-y-1">
                                      <div>Breakdown #{item.final_price_breakdown.id}</div>
                                      <div>Status: {item.final_price_breakdown.status ?? '—'}</div>
                                      <div>Calculated: {formatDateTime(item.final_price_breakdown.calculated_at)}</div>
                                    </div>
                                  ) : (
                                    '—'
                                  )}
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    )}
                  </section>
                </div>
              ) : (
                <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-5 text-sm text-red-200">
                  Invoice details could not be loaded.
                </div>
              )}
            </div>

            {invoice && (
              <div className="flex flex-wrap items-center justify-end gap-3 border-t border-white/5 px-6 py-4">
                <button
                  type="button"
                  onClick={onOpenPdf}
                  disabled={isPdfLoading || isLifecycleSubmitting}
                  className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-bold text-slate-200 transition-colors hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {isPdfLoading ? 'Opening PDF…' : 'Preview PDF'}
                </button>
                {invoice.status === 'finalized' && onPayWithStripe && (
                  <button
                    type="button"
                    onClick={onPayWithStripe}
                    disabled={isCheckoutLoading || isLifecycleSubmitting || isPdfLoading}
                    className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg transition-all hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <CreditCard className="h-4 w-4" />
                    {isCheckoutLoading ? 'Preparing Checkout…' : 'Pay with Stripe (Test)'}
                  </button>
                )}
                {showVoid && (
                  <button
                    type="button"
                    onClick={onVoid}
                    disabled={isLifecycleSubmitting || isPdfLoading || isCheckoutLoading}
                    className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm font-bold text-red-200 transition-colors hover:bg-red-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    Void Invoice
                  </button>
                )}
                {showFinalize && (
                  <button
                    type="button"
                    onClick={onFinalize}
                    disabled={isLifecycleSubmitting || isPdfLoading || isCheckoutLoading}
                    className="rounded-xl bg-secondary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-secondary/80 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    Finalize Invoice
                  </button>
                )}
              </div>
            )}
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  );
}
