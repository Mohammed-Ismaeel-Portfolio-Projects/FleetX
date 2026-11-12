import { useEffect, useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Building2, X } from 'lucide-react';
import type { Company } from '../types/company.types';

interface CompanyDetailsDrawerProps {
  company: Company | null;
  isOpen: boolean;
  isLoading: boolean;
  onClose: () => void;
}

function valueOf(value: string | number | boolean | null | undefined) {
  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }

  return value || 'Not provided';
}

function DetailSection({ title, rows }: { title: string; rows: Array<[string, string | number | boolean | null | undefined]> }) {
  return (
    <section className="rounded-2xl border border-white/5 bg-[#090D16] p-4">
      <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">{title}</h3>
      <dl className="mt-4 grid grid-cols-1 gap-3">
        {rows.map(([label, value]) => (
          <div key={label} className="flex items-start justify-between gap-4">
            <dt className="text-xs text-slate-500">{label}</dt>
            <dd className="max-w-[62%] text-right text-xs font-semibold text-slate-200">{valueOf(value)}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export default function CompanyDetailsDrawer({ company, isOpen, isLoading, onClose }: CompanyDetailsDrawerProps) {
  const closeRef = useRef<HTMLButtonElement>(null);

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
        <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Company details">
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
            className="absolute right-0 top-0 flex h-full w-full max-w-xl flex-col border-l border-white/10 bg-[#0E1320] shadow-2xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-white/5 px-6 py-5">
              <div className="flex items-start gap-3">
                <span className="rounded-xl border border-[#0250C5]/20 bg-[#0250C5]/10 p-2 text-[#00E5FF]">
                  <Building2 className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="text-lg font-bold text-white">Company Details</h2>
                  <p className="mt-1 text-xs text-slate-500">{company?.company_name || 'Loading company profile'}</p>
                </div>
              </div>
              <button
                ref={closeRef}
                type="button"
                onClick={onClose}
                className="rounded-xl p-2 text-slate-500 transition-colors hover:bg-white/5 hover:text-white"
                aria-label="Close company details"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6">
              {isLoading ? (
                <div className="flex h-64 items-center justify-center">
                  <span className="h-8 w-8 rounded-full border-2 border-[#0250C5] border-t-transparent animate-spin" />
                </div>
              ) : company ? (
                <div className="flex flex-col gap-4">
                  <DetailSection
                    title="General"
                    rows={[
                      ['Company Name', company.company_name],
                      ['Legal Name', company.legal_name],
                      ['Company Code', company.company_code],
                      ['Created Date', new Date(company.created_at).toLocaleDateString()],
                    ]}
                  />
                  <DetailSection
                    title="Business"
                    rows={[
                      ['Tax Number', company.tax_number],
                      ['VAT Number', company.vat_number],
                      ['Registration Number', company.business_registration_number],
                      ['Website', company.website],
                    ]}
                  />
                  <DetailSection
                    title="Contact"
                    rows={[
                      ['Email', company.primary_email],
                      ['Phone', company.primary_phone],
                    ]}
                  />
                  <DetailSection
                    title="Address"
                    rows={[
                      ['Country', company.country],
                      ['State', company.state_province],
                      ['City', company.city],
                      ['Address', company.address_line_1],
                      ['Postal Code', company.postal_code],
                    ]}
                  />
                  <DetailSection
                    title="Localization"
                    rows={[
                      ['Currency', company.currency_code],
                      ['Language', company.language_code],
                      ['Timezone', company.timezone],
                    ]}
                  />
                  <DetailSection
                    title="Status"
                    rows={[
                      ['Status ID', company.status_id],
                      ['Active', company.is_active],
                      ['Deleted', Boolean(company.deleted_at)],
                    ]}
                  />
                  <section className="rounded-2xl border border-white/5 bg-[#090D16] p-4">
                    <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Notes</h3>
                    <p className="mt-4 text-sm leading-6 text-slate-300">{valueOf(company.notes)}</p>
                  </section>
                </div>
              ) : (
                <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-5 text-sm text-red-200">
                  Company details could not be loaded.
                </div>
              )}
            </div>
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  );
}
