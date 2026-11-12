import { useState, useEffect, useTransition } from 'react';
import { motion } from 'framer-motion';
import {
  ShieldCheck,
  Plus,
  Search,
  RefreshCw,
  Building2,
  Phone,
  Mail,
  MapPin,
  CheckCircle2,
  XCircle,
  X,
  Eye,
  Edit2,
  Trash2,
  ChevronLeft,
  ChevronRight,
  AlertCircle,
  Truck
} from 'lucide-react';
import { companiesService } from '../services/companies.service';
import type { Company, CompanyCollection, CompanyFormData, CompanyPayload } from '../types/company.types';
import CompanyForm, { companyToFormData, emptyCompanyForm } from '../components/CompanyForm';
import CompanyModal from '../components/CompanyModal';
import PartnerCompanyDetailsDrawer from '../components/PartnerCompanyDetailsDrawer';

export default function PartnerCompaniesPage() {
  const [, startTransition] = useTransition();

  // Page state
  const [data, setData] = useState<CompanyCollection | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Filters state
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all');
  const [page, setPage] = useState(1);
  const perPage = 15;

  // Selected company for details drawer
  const [selectedPartner, setSelectedPartner] = useState<Company | null>(null);

  // Modal State (Create / Edit)
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingPartner, setEditingPartner] = useState<Company | null>(null);
  const [formData, setFormData] = useState<CompanyFormData>(emptyCompanyForm);
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formAlert, setFormAlert] = useState<string | null>(null);

  // Fetch partner companies
  const fetchPartnerCompanies = async (showRefreshSpinner = false) => {
    if (showRefreshSpinner) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await companiesService.getCompanies({
        company_type: 'partner_carrier',
        search: search.trim() || undefined,
        is_active: statusFilter === 'all' ? undefined : statusFilter === 'active',
        page,
        per_page: perPage,
        sort_by: 'created_at',
        sort_direction: 'desc',
      });
      setData(response);
    } catch (err: any) {
      setError(err?.message || 'Failed to load partner companies');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  };

  useEffect(() => {
    void fetchPartnerCompanies();
  }, [page, statusFilter]);

  // Debounced search trigger
  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    void fetchPartnerCompanies();
  };

  // Open Create Modal
  const handleOpenCreateModal = () => {
    setEditingPartner(null);
    setFormData({
      ...emptyCompanyForm,
      company_type: 'partner_carrier',
    });
    setFormErrors({});
    setFormAlert(null);
    setIsModalOpen(true);
  };

  // Open Edit Modal
  const handleOpenEditModal = (partner: Company) => {
    setEditingPartner(partner);
    setFormData(companyToFormData(partner));
    setFormErrors({});
    setFormAlert(null);
    setIsModalOpen(true);
  };

  // Save Partner (Create or Update)
  const handleFormSubmit = async (payload: CompanyPayload) => {
    setIsSubmitting(true);
    setFormErrors({});
    setFormAlert(null);

    // Enforce company_type = partner_carrier for creation in Partner Companies page
    const partnerPayload = {
      ...payload,
      company_type: payload.company_type || 'partner_carrier',
    };

    try {
      if (editingPartner) {
        await companiesService.updateCompany(editingPartner.id, partnerPayload);
      } else {
        await companiesService.createCompany(partnerPayload);
      }

      setIsModalOpen(false);
      void fetchPartnerCompanies();
    } catch (err: any) {
      if (err?.validationErrors && Object.keys(err.validationErrors).length > 0) {
        setFormErrors(err.validationErrors);
      } else {
        setFormAlert(err?.message || 'Failed to save partner company.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  // Toggle Active/Inactive Status
  const handleToggleStatus = async (partner: Company) => {
    try {
      await companiesService.toggleCompanyStatus(partner.id, !partner.is_active);
      if (selectedPartner?.id === partner.id) {
        setSelectedPartner({ ...partner, is_active: !partner.is_active });
      }
      void fetchPartnerCompanies(true);
    } catch (err: any) {
      alert(err?.message || 'Failed to toggle company status.');
    }
  };

  // Soft Delete Company
  const handleDeleteCompany = async (partner: Company) => {
    if (!window.confirm(`Are you sure you want to soft-delete "${partner.company_name}"?`)) return;

    try {
      await companiesService.deleteCompany(partner.id);
      if (selectedPartner?.id === partner.id) {
        setSelectedPartner(null);
      }
      void fetchPartnerCompanies(true);
    } catch (err: any) {
      alert(err?.message || 'Failed to delete partner company.');
    }
  };

  const companiesList = data?.data || [];
  const meta = data?.meta;

  return (
    <div className="space-y-6 pb-12">
      {/* Header Section */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0250C5]/10 border border-[#0250C5]/30 text-[#0250C5]">
              <ShieldCheck className="h-5 w-5" />
            </div>
            <div>
              <h1 className="text-xl font-extrabold text-white tracking-tight">Partner Companies</h1>
              <p className="text-xs text-slate-400 font-medium">
                External carrier partners executing transport & managing fleet resources
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={() => fetchPartnerCompanies(true)}
            disabled={isRefreshing}
            className="flex items-center gap-2 rounded-xl border border-white/10 bg-[#090D16] px-3.5 py-2 text-xs font-bold text-slate-300 hover:bg-white/5 hover:text-white transition-colors cursor-pointer disabled:opacity-50"
            title="Refresh list"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
            Refresh
          </button>

          <button
            onClick={handleOpenCreateModal}
            className="flex items-center gap-2 rounded-xl bg-[#0250C5] px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-[#0250C5]/20 hover:bg-[#0244a5] transition-all cursor-pointer"
          >
            <Plus className="h-4 w-4" />
            Add Partner Carrier
          </button>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-4">
        <form onSubmit={handleSearchSubmit} className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1">
            <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search partner name, legal name, code, email..."
              className="w-full rounded-xl border border-white/5 bg-[#090D16] pl-10 pr-4 py-2 text-xs text-white placeholder-slate-500 outline-none focus:border-[#0250C5]/60 focus:ring-1 focus:ring-[#0250C5]"
            />
          </div>

          <div className="flex items-center gap-2">
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Status:</span>
            <div className="flex rounded-xl border border-white/5 bg-[#090D16] p-1 gap-1">
              {(['all', 'active', 'inactive'] as const).map((st) => (
                <button
                  key={st}
                  type="button"
                  onClick={() => {
                    setStatusFilter(st);
                    setPage(1);
                  }}
                  className={`rounded-lg px-3 py-1 text-xs font-bold capitalize transition-all cursor-pointer ${
                    statusFilter === st
                      ? 'bg-[#0250C5] text-white shadow-sm'
                      : 'text-slate-400 hover:text-slate-200'
                  }`}
                >
                  {st}
                </button>
              ))}
            </div>
          </div>
        </form>
      </div>

      {/* Error state */}
      {error && (
        <div className="rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-rose-400 shrink-0" />
          <p className="text-xs font-medium text-rose-300 flex-1">{error}</p>
          <button onClick={() => fetchPartnerCompanies()} className="text-xs font-bold text-rose-400 underline cursor-pointer">
            Retry
          </button>
        </div>
      )}

      {/* List Table / Loading / Empty */}
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] overflow-hidden shadow-xl">
        {isLoading ? (
          <div className="p-8 text-center space-y-3">
            <RefreshCw className="mx-auto h-7 w-7 animate-spin text-[#0250C5]" />
            <p className="text-xs font-medium text-slate-400">Loading partner companies...</p>
          </div>
        ) : companiesList.length === 0 ? (
          <div className="p-12 text-center space-y-3">
            <Building2 className="mx-auto h-10 w-10 text-slate-600" />
            <h3 className="text-sm font-bold text-slate-300">No Partner Companies Found</h3>
            <p className="text-xs text-slate-500 max-w-sm mx-auto">
              There are no partner carrier companies matching your criteria. Click "Add Partner Carrier" to create one.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-[#090D16] border-b border-white/5 text-[10px] uppercase tracking-wider font-bold text-slate-400">
                <tr>
                  <th className="px-5 py-3.5">Partner Carrier</th>
                  <th className="px-5 py-3.5">Code</th>
                  <th className="px-5 py-3.5">Contact Details</th>
                  <th className="px-5 py-3.5">Location</th>
                  <th className="px-5 py-3.5">Status</th>
                  <th className="px-5 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5 text-slate-300">
                {companiesList.map((partner) => (
                  <tr key={partner.id} className="hover:bg-white/[0.02] transition-colors group">
                    {/* Partner Carrier Name & Type */}
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-3">
                        <div className="h-9 w-9 rounded-xl bg-[#0250C5]/10 border border-[#0250C5]/20 flex items-center justify-center text-[#0250C5] font-bold text-sm">
                          {partner.company_name.substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                          <span className="font-bold text-white block text-sm group-hover:text-[#0250C5] transition-colors">
                            {partner.company_name}
                          </span>
                          <span className="text-[10px] font-semibold text-slate-400 block">
                            {partner.legal_name}
                          </span>
                        </div>
                      </div>
                    </td>

                    {/* Company Code & Badge */}
                    <td className="px-5 py-4">
                      <div className="space-y-1">
                        <span className="font-mono text-xs font-bold text-slate-200 block">
                          {partner.company_code}
                        </span>
                        <span className="inline-flex items-center rounded-md bg-[#0250C5]/10 border border-[#0250C5]/20 px-2 py-0.5 text-[9px] font-bold text-[#0250C5] uppercase">
                          {partner.company_type || 'PARTNER_CARRIER'}
                        </span>
                      </div>
                    </td>

                    {/* Contact Email & Phone */}
                    <td className="px-5 py-4">
                      <div className="space-y-1">
                        <div className="flex items-center gap-1.5 text-slate-300">
                          <Mail className="h-3.5 w-3.5 text-slate-500" />
                          <span>{partner.primary_email}</span>
                        </div>
                        {partner.primary_phone && (
                          <div className="flex items-center gap-1.5 text-slate-400 text-[11px]">
                            <Phone className="h-3 w-3 text-slate-500" />
                            <span>{partner.primary_phone}</span>
                          </div>
                        )}
                      </div>
                    </td>

                    {/* Location */}
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-1.5 text-slate-300">
                        <MapPin className="h-3.5 w-3.5 text-emerald-400" />
                        <span>{partner.city}, {partner.country}</span>
                      </div>
                    </td>

                    {/* Status */}
                    <td className="px-5 py-4">
                      <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ${
                        partner.is_active
                          ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400'
                          : 'bg-rose-500/10 border border-rose-500/20 text-rose-400'
                      }`}>
                        {partner.is_active ? <CheckCircle2 className="h-3 w-3" /> : <XCircle className="h-3 w-3" />}
                        {partner.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>

                    {/* Actions */}
                    <td className="px-5 py-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => setSelectedPartner(partner)}
                          className="flex items-center gap-1 rounded-lg border border-white/10 bg-[#090D16] px-2.5 py-1.5 text-xs font-bold text-slate-300 hover:border-[#0250C5]/40 hover:text-[#0250C5] transition-all cursor-pointer"
                          title="View Fleet & Details"
                        >
                          <Eye className="h-3.5 w-3.5" />
                          Fleet
                        </button>

                        <button
                          onClick={() => handleOpenEditModal(partner)}
                          className="p-1.5 rounded-lg border border-white/5 bg-[#090D16] text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                          title="Edit Partner"
                        >
                          <Edit2 className="h-3.5 w-3.5" />
                        </button>

                        <button
                          onClick={() => handleToggleStatus(partner)}
                          className={`p-1.5 rounded-lg border border-white/5 bg-[#090D16] transition-colors cursor-pointer ${
                            partner.is_active ? 'text-emerald-400 hover:text-amber-400' : 'text-slate-400 hover:text-emerald-400'
                          }`}
                          title={partner.is_active ? 'Deactivate' : 'Activate'}
                        >
                          {partner.is_active ? <XCircle className="h-3.5 w-3.5" /> : <CheckCircle2 className="h-3.5 w-3.5" />}
                        </button>

                        <button
                          onClick={() => handleDeleteCompany(partner)}
                          className="p-1.5 rounded-lg border border-white/5 bg-[#090D16] text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors cursor-pointer"
                          title="Delete Partner"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination Footer */}
        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-white/5 px-5 py-3.5 bg-[#090D16]">
            <span className="text-xs text-slate-400">
              Showing page <strong className="text-white">{meta.current_page}</strong> of <strong className="text-white">{meta.last_page}</strong> ({meta.total} total partners)
            </span>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setPage((p) => Math.max(p - 1, 1))}
                disabled={meta.current_page <= 1}
                className="p-1.5 rounded-lg border border-white/10 text-slate-300 hover:bg-white/5 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <button
                onClick={() => setPage((p) => Math.min(p + 1, meta.last_page))}
                disabled={meta.current_page >= meta.last_page}
                className="p-1.5 rounded-lg border border-white/10 text-slate-300 hover:bg-white/5 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
              >
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Partner Details Drawer */}
      <PartnerCompanyDetailsDrawer
        company={selectedPartner}
        isOpen={!!selectedPartner}
        onClose={() => setSelectedPartner(null)}
        onStatusToggle={handleToggleStatus}
      />

      {/* Create / Edit Company Modal */}
      <CompanyModal
        isOpen={isModalOpen}
        title={editingPartner ? 'Edit Partner Company' : 'Add New Partner Carrier'}
        description={
          editingPartner
            ? 'Update partner carrier profile, legal identification, and contact information.'
            : 'Register a new external partner carrier company to manage fleet resources and transport assignments.'
        }
        icon={ShieldCheck}
        formData={formData}
        errors={formErrors}
        formAlert={formAlert}
        isSubmitting={isSubmitting}
        submitLabel={editingPartner ? 'Save Partner Changes' : 'Create Partner Carrier'}
        onChange={setFormData}
        onSubmit={handleFormSubmit}
        onClose={() => setIsModalOpen(false)}
      />
    </div>
  );
}
