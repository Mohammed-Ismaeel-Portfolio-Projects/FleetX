import { useEffect, useMemo, useState } from 'react';
import { FileText } from 'lucide-react';
import { useToast } from '../../../../shared/components/toast';
import { useCompanies } from '../../companies/hooks/useCompanies';
import { useMonthlyInvoices } from '../hooks/useMonthlyInvoices';
import {
  monthlyInvoicesService,
  MonthlyInvoiceApiError,
} from '../services/monthlyInvoices.service';
import MonthlyInvoicesTable from '../components/MonthlyInvoicesTable';
import InvoiceDetailsDrawer from '../components/InvoiceDetailsDrawer';
import InvoiceLifecycleDialog from '../components/InvoiceLifecycleDialog';
import type { MonthlyInvoiceDetail, MonthlyInvoiceListItem, MonthlyInvoiceQueryParams } from '../types/invoice.types';

const defaultParams: MonthlyInvoiceQueryParams = {
  page: 1,
  per_page: 15,
  sort_by: 'created_at',
  sort_direction: 'desc',
};

type LifecycleMode = 'finalize' | 'void';

export default function MonthlyInvoicesPage() {
  const toast = useToast();
  const {
    invoices,
    isLoading,
    error,
    pagination,
    updateParams,
    refresh,
  } = useMonthlyInvoices(defaultParams);
  const { companies } = useCompanies({ page: 1, per_page: 100 });

  const [companyFilter, setCompanyFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [currencyFilter, setCurrencyFilter] = useState('');
  const [billingStartFilter, setBillingStartFilter] = useState('');
  const [billingEndFilter, setBillingEndFilter] = useState('');
  const [sortField, setSortField] = useState<NonNullable<MonthlyInvoiceQueryParams['sort_by']>>('created_at');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

  const [drawerInvoice, setDrawerInvoice] = useState<MonthlyInvoiceDetail | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [isDrawerLoading, setIsDrawerLoading] = useState(false);
  const [isPdfLoading, setIsPdfLoading] = useState(false);
  const [isLifecycleSubmitting, setIsLifecycleSubmitting] = useState(false);
  const [isCheckoutLoading, setIsCheckoutLoading] = useState(false);
  const [lifecycleMode, setLifecycleMode] = useState<LifecycleMode | null>(null);

  useEffect(() => {
    updateParams({
      company_id: companyFilter || undefined,
      status: (statusFilter || undefined) as MonthlyInvoiceQueryParams['status'],
      currency: currencyFilter ? currencyFilter.toUpperCase() : undefined,
      billing_period_start: billingStartFilter || undefined,
      billing_period_end: billingEndFilter || undefined,
      page: 1,
    });
  }, [billingEndFilter, billingStartFilter, companyFilter, currencyFilter, statusFilter, updateParams]);

  useEffect(() => {
    const searchParams = new URLSearchParams(window.location.search);
    const paymentResult = searchParams.get('payment');
    const invoiceParam = searchParams.get('invoice');

    if (!paymentResult || !invoiceParam) {
      return;
    }

    const invoiceId = Number(invoiceParam);
    if (paymentResult === 'success' && invoiceId) {
      void (async () => {
        try {
          const statusResult = await monthlyInvoicesService.getPaymentStatus(invoiceId);
          if (statusResult.status === 'paid') {
            toast.success('Payment Confirmed', `Invoice #${invoiceId} has been successfully paid.`);
          } else {
            toast.info('Payment Processing', `Invoice #${invoiceId} payment is being verified by Stripe.`);
          }
          refresh();
        } catch {
          toast.success('Payment Received', `Invoice #${invoiceId} payment flow completed.`);
        }
      })();
    } else if (paymentResult === 'cancelled' && invoiceId) {
      toast.info('Payment Cancelled', `Checkout session for Invoice #${invoiceId} was cancelled.`);
    }

    window.history.replaceState({}, document.title, window.location.pathname);
  }, [refresh, toast]);

  const hasFilters = Boolean(
    companyFilter || statusFilter || currencyFilter || billingStartFilter || billingEndFilter,
  );

  const companyOptions = useMemo(
    () => companies.map((company) => ({ value: company.id, label: company.company_name })),
    [companies],
  );

  const handleView = async (invoice: MonthlyInvoiceListItem) => {
    setIsDrawerOpen(true);
    setIsDrawerLoading(true);
    setDrawerInvoice(null);

    try {
      const details = await monthlyInvoicesService.getMonthlyInvoice(invoice.id);
      setDrawerInvoice(details);
    } catch (err) {
      toast.error('Details unavailable', err instanceof Error ? err.message : 'Unable to load invoice details.');
    } finally {
      setIsDrawerLoading(false);
    }
  };

  const handlePayWithStripe = async () => {
    if (!drawerInvoice) {
      return;
    }

    setIsCheckoutLoading(true);
    try {
      const checkoutRes = await monthlyInvoicesService.createCheckoutSession(drawerInvoice.id);
      if (checkoutRes.checkout_url) {
        window.location.href = checkoutRes.checkout_url;
      } else {
        throw new Error('No checkout URL returned from server.');
      }
    } catch (err) {
      const message = err instanceof MonthlyInvoiceApiError ? err.message : 'Failed to create Stripe Checkout session.';
      toast.error('Checkout Creation Failed', message);
      setIsCheckoutLoading(false);
    }
  };

  const handleSort = (field: NonNullable<MonthlyInvoiceQueryParams['sort_by']>) => {
    const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
    setSortField(field);
    setSortDirection(direction);
    updateParams({ sort_by: field, sort_direction: direction, page: 1 });
  };

  const handleResetFilters = () => {
    setCompanyFilter('');
    setStatusFilter('');
    setCurrencyFilter('');
    setBillingStartFilter('');
    setBillingEndFilter('');
    updateParams({
      company_id: undefined,
      status: undefined,
      currency: undefined,
      billing_period_start: undefined,
      billing_period_end: undefined,
      page: 1,
      sort_by: sortField,
      sort_direction: sortDirection,
    });
  };

  const handleOpenPdf = async () => {
    if (!drawerInvoice) {
      return;
    }

    setIsPdfLoading(true);
    try {
      await monthlyInvoicesService.openMonthlyInvoicePdf(drawerInvoice.id);
    } catch (err) {
      toast.error('PDF unavailable', err instanceof MonthlyInvoiceApiError ? err.message : 'Unable to open invoice PDF.');
    } finally {
      setIsPdfLoading(false);
    }
  };

  const handleLifecycleConfirm = async () => {
    if (!drawerInvoice || !lifecycleMode) {
      return;
    }

    setIsLifecycleSubmitting(true);
    try {
      const updated = lifecycleMode === 'finalize'
        ? await monthlyInvoicesService.finalizeMonthlyInvoice(drawerInvoice.id)
        : await monthlyInvoicesService.voidMonthlyInvoice(drawerInvoice.id);

      setDrawerInvoice(updated);
      setLifecycleMode(null);
      refresh();
      toast.success(
        lifecycleMode === 'finalize' ? 'Invoice finalized' : 'Invoice voided',
        lifecycleMode === 'finalize'
          ? 'The invoice is now finalized and financially immutable.'
          : 'The invoice is now void and preserved for billing history.',
      );
    } catch (err) {
      const message = err instanceof MonthlyInvoiceApiError ? err.message : 'Unable to complete invoice lifecycle action.';
      const code = err instanceof MonthlyInvoiceApiError ? err.errors?.code : undefined;
      toast.error('Lifecycle action failed', code ? `${message} (${code})` : message);
    } finally {
      setIsLifecycleSubmitting(false);
    }
  };

  const lifecycleCopy = lifecycleMode === 'finalize'
    ? {
        title: 'Finalize invoice?',
        message: [
          'This invoice will become FINALIZED.',
          'Billing values will become immutable.',
          'issued_at will be assigned by the server.',
          'This action cannot be reverted to draft.',
        ].join('\n'),
        confirmLabel: 'Finalize Invoice',
        tone: 'primary' as const,
      }
    : {
        title: 'Void invoice?',
        message: drawerInvoice?.status === 'finalized'
          ? [
              'This finalized invoice will become VOID.',
              'Existing billing snapshot and issued_at remain preserved.',
              'The invoice will no longer be valid for billing.',
            ].join('\n')
          : [
              'This draft invoice will become VOID.',
              'It will not become finalized.',
              'issued_at will remain null.',
            ].join('\n'),
        confirmLabel: 'Void Invoice',
        tone: 'danger' as const,
      };

  return (
    <div className="flex w-full flex-col gap-6 font-sans text-left select-none">
      <div className="flex flex-col gap-1.5">
        <div className="flex items-center gap-2">
          <FileText className="h-5 w-5 text-cyan-400" />
          <h1 className="text-2xl font-bold tracking-tight text-white">Monthly Invoices</h1>
        </div>
        <p className="text-xs font-normal text-slate-500">
          Commercial billing governance — inspect persisted invoice snapshots and manage lifecycle actions only.
        </p>
      </div>

      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6 shadow-md">
        <div className="mb-4 flex flex-col gap-4">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-white">Invoice Register</h2>
            {hasFilters && (
              <button
                type="button"
                onClick={handleResetFilters}
                className="text-xs text-cyan-400 hover:text-cyan-300"
              >
                Reset Filters
              </button>
            )}
          </div>

          <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
            <select
              value={companyFilter}
              onChange={(event) => setCompanyFilter(event.target.value)}
              className="rounded-xl border border-white/10 bg-[#090D16] px-3 py-2.5 text-sm text-white outline-none"
            >
              <option value="">All companies</option>
              {companyOptions.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
            <select
              value={statusFilter}
              onChange={(event) => setStatusFilter(event.target.value)}
              className="rounded-xl border border-white/10 bg-[#090D16] px-3 py-2.5 text-sm text-white outline-none"
            >
              <option value="">All statuses</option>
              <option value="draft">DRAFT</option>
              <option value="finalized">FINALIZED</option>
              <option value="void">VOID</option>
            </select>
            <input
              type="text"
              value={currencyFilter}
              onChange={(event) => setCurrencyFilter(event.target.value.toUpperCase())}
              maxLength={3}
              placeholder="Currency (USD)"
              className="rounded-xl border border-white/10 bg-[#090D16] px-3 py-2.5 text-sm text-white outline-none placeholder:text-slate-500"
            />
            <input
              type="date"
              value={billingStartFilter}
              onChange={(event) => setBillingStartFilter(event.target.value)}
              className="rounded-xl border border-white/10 bg-[#090D16] px-3 py-2.5 text-sm text-white outline-none"
            />
            <input
              type="date"
              value={billingEndFilter}
              onChange={(event) => setBillingEndFilter(event.target.value)}
              className="rounded-xl border border-white/10 bg-[#090D16] px-3 py-2.5 text-sm text-white outline-none"
            />
          </div>
        </div>

        <MonthlyInvoicesTable
          invoices={invoices}
          isLoading={isLoading}
          error={error}
          pagination={pagination}
          sortField={sortField}
          sortDirection={sortDirection}
          hasFilters={hasFilters}
          onSort={handleSort}
          onRetry={refresh}
          onPageChange={(page) => updateParams({ page })}
          onPerPageChange={(perPage) => updateParams({ per_page: perPage, page: 1 })}
          onView={handleView}
        />
      </div>

      <InvoiceDetailsDrawer
        invoice={drawerInvoice}
        isOpen={isDrawerOpen}
        isLoading={isDrawerLoading}
        isPdfLoading={isPdfLoading}
        isLifecycleSubmitting={isLifecycleSubmitting}
        isCheckoutLoading={isCheckoutLoading}
        onClose={() => {
          if (!isLifecycleSubmitting && !isCheckoutLoading) {
            setIsDrawerOpen(false);
            setLifecycleMode(null);
          }
        }}
        onFinalize={() => setLifecycleMode('finalize')}
        onVoid={() => setLifecycleMode('void')}
        onOpenPdf={handleOpenPdf}
        onPayWithStripe={handlePayWithStripe}
      />

      <InvoiceLifecycleDialog
        isOpen={Boolean(lifecycleMode)}
        title={lifecycleCopy.title}
        message={lifecycleCopy.message}
        confirmLabel={lifecycleCopy.confirmLabel}
        tone={lifecycleCopy.tone}
        isSubmitting={isLifecycleSubmitting}
        onCancel={() => !isLifecycleSubmitting && setLifecycleMode(null)}
        onConfirm={handleLifecycleConfirm}
      />
    </div>
  );
}
