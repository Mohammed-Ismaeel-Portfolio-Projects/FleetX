import { useEffect, useMemo, useState } from 'react';
import { Building2, Plus } from 'lucide-react';
import CompaniesTable from '../components/CompaniesTable';
import CompanyDetailsDrawer from '../components/CompanyDetailsDrawer';
import CompanyModal from '../components/CompanyModal';
import DeleteDialog from '../components/DeleteDialog';
import FiltersBar from '../components/FiltersBar';
import { companyToFormData, emptyCompanyForm } from '../components/CompanyForm';
import { useCompanies } from '../hooks/useCompanies';
import { CompanyApiError, companiesService } from '../services/companies.service';
import type {
  Company,
  CompanyFormData,
  CompanyPayload,
  CompanyValidationErrors,
} from '../types/company.types';
import { useToast } from '../../../../shared/components/toast';

type ModalMode = 'create' | 'edit';
type ConfirmMode = 'delete' | 'restore';

export default function CompaniesPage() {
  const {
    companies,
    isLoading,
    error,
    pagination,
    updateParams,
    refresh,
    replaceCompany,
  } = useCompanies({ page: 1, per_page: 15, sort_by: 'created_at', sort_direction: 'desc' });
  const toast = useToast();

  const [searchQuery, setSearchQuery] = useState('');
  const [countryFilter, setCountryFilter] = useState('');
  const [cityFilter, setCityFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all');
  const [sortField, setSortField] = useState('created_at');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
  const [modalMode, setModalMode] = useState<ModalMode>('create');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedCompany, setSelectedCompany] = useState<Company | null>(null);
  const [formData, setFormData] = useState<CompanyFormData>(emptyCompanyForm);
  const [formErrors, setFormErrors] = useState<CompanyValidationErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [drawerCompany, setDrawerCompany] = useState<Company | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [isDrawerLoading, setIsDrawerLoading] = useState(false);
  const [confirmMode, setConfirmMode] = useState<ConfirmMode>('delete');
  const [confirmCompany, setConfirmCompany] = useState<Company | null>(null);
  const [isConfirmSubmitting, setIsConfirmSubmitting] = useState(false);
  const [statusLoadingIds, setStatusLoadingIds] = useState<Set<string>>(new Set());

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      updateParams({ search: searchQuery || undefined, page: 1 });
    }, 500);

    return () => window.clearTimeout(timeoutId);
  }, [searchQuery, updateParams]);

  const uniqueCountries = useMemo(() => (
    Array.from(new Set(companies.map((company) => company.country).filter(Boolean))).sort()
  ), [companies]);

  const uniqueCities = useMemo(() => (
    Array.from(new Set(companies.map((company) => company.city).filter(Boolean))).sort()
  ), [companies]);

  const hasFilters = Boolean(searchQuery || countryFilter || cityFilter || statusFilter !== 'all');
  const actionDisabled = isSubmitting || isConfirmSubmitting;

  const openCreateModal = () => {
    setModalMode('create');
    setSelectedCompany(null);
    setFormData(emptyCompanyForm);
    setFormErrors({});
    setIsModalOpen(true);
  };

  const openEditModal = (company: Company) => {
    setModalMode('edit');
    setSelectedCompany(company);
    setFormData(companyToFormData(company));
    setFormErrors({});
    setIsModalOpen(true);
  };

  const closeModal = () => {
    if (isSubmitting) {
      return;
    }

    setIsModalOpen(false);
    setSelectedCompany(null);
    setFormErrors({});
  };

  const handleSubmit = async (payload: CompanyPayload) => {
    setIsSubmitting(true);
    setFormErrors({});

    try {
      if (modalMode === 'create') {
        await companiesService.createCompany(payload);
        toast.success('Company created', 'The company was added to the directory.');
      } else if (selectedCompany) {
        await companiesService.updateCompany(selectedCompany.id, payload);
        toast.success('Company updated', 'The company profile was saved.');
      }

      setIsModalOpen(false);
      setSelectedCompany(null);
      refresh();
    } catch (err) {
      if (err instanceof CompanyApiError) {
        setFormErrors(err.validationErrors);
        toast.error('Request failed', err.message);
      } else {
        toast.error('Request failed', 'Unable to save company.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleView = async (company: Company) => {
    setIsDrawerOpen(true);
    setIsDrawerLoading(true);
    setDrawerCompany(company);

    try {
      const details = await companiesService.getCompanyById(company.id);
      setDrawerCompany(details);
    } catch (err) {
      toast.error('Details unavailable', err instanceof Error ? err.message : 'Unable to load company details.');
    } finally {
      setIsDrawerLoading(false);
    }
  };

  const requestDelete = (company: Company) => {
    setConfirmMode('delete');
    setConfirmCompany(company);
  };

  const requestRestore = (company: Company) => {
    setConfirmMode('restore');
    setConfirmCompany(company);
  };

  const closeConfirmDialog = () => {
    if (isConfirmSubmitting) {
      return;
    }

    setConfirmCompany(null);
  };

  const handleConfirm = async () => {
    if (!confirmCompany) {
      return;
    }

    setIsConfirmSubmitting(true);
    try {
      if (confirmMode === 'delete') {
        await companiesService.deleteCompany(confirmCompany.id);
        toast.warning('Company deleted', 'This company was soft deleted.');
      } else {
        await companiesService.restoreCompany(confirmCompany.id);
        toast.success('Company restored', 'The company is available again.');
      }

      setConfirmCompany(null);
      refresh();
    } catch (err) {
      toast.error('Request failed', err instanceof Error ? err.message : 'Unable to complete this action.');
    } finally {
      setIsConfirmSubmitting(false);
    }
  };

  const handleToggleStatus = async (company: Company, nextValue: boolean) => {
    setStatusLoadingIds((current) => new Set(current).add(company.id));
    replaceCompany(company.id, { is_active: nextValue });

    try {
      await companiesService.toggleCompanyStatus(company.id, nextValue);
      toast.info(nextValue ? 'Company activated' : 'Company deactivated');
    } catch (err) {
      replaceCompany(company.id, { is_active: company.is_active });
      toast.error('Status rolled back', err instanceof Error ? err.message : 'Unable to update company status.');
    } finally {
      setStatusLoadingIds((current) => {
        const next = new Set(current);
        next.delete(company.id);
        return next;
      });
    }
  };

  const handleCountryChange = (value: string) => {
    setCountryFilter(value);
    updateParams({ country: value || undefined, page: 1 });
  };

  const handleCityChange = (value: string) => {
    setCityFilter(value);
    updateParams({ city: value || undefined, page: 1 });
  };

  const handleTypeChange = (value: string) => {
    setTypeFilter(value);
    updateParams({ company_type: value || undefined, page: 1 });
  };

  const handleStatusChange = (value: 'all' | 'active' | 'inactive') => {
    setStatusFilter(value);
    updateParams({ is_active: value === 'all' ? undefined : value === 'active', page: 1 });
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    setCountryFilter('');
    setCityFilter('');
    setTypeFilter('');
    setStatusFilter('all');
    updateParams({ search: undefined, country: undefined, city: undefined, company_type: undefined, is_active: undefined, page: 1 });
  };

  const handleSort = (field: string) => {
    const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
    setSortField(field);
    setSortDirection(direction);
    updateParams({ sort_by: field, sort_direction: direction, page: 1 });
  };

  return (
    <div className="flex w-full select-none flex-col gap-6 text-left font-sans">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div className="flex flex-col gap-1.5">
          <h1 className="text-2xl font-bold tracking-tight text-white">Companies Directory</h1>
          <p className="text-xs font-normal text-slate-500">
            Manage client companies, view contact information, and monitor account status.
          </p>
        </div>

        <button
          type="button"
          onClick={openCreateModal}
          className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0250C5] px-4 py-2.5 text-sm font-bold text-white shadow-md transition-colors hover:bg-[#0244a5]"
        >
          <Plus className="h-4 w-4" />
          Create Company
        </button>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-4">
          <div className="flex items-center gap-3">
            <span className="rounded-xl border border-[#0250C5]/20 bg-[#0250C5]/10 p-2 text-[#00E5FF]">
              <Building2 className="h-5 w-5" />
            </span>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Total Results</p>
              <p className="mt-1 font-mono text-2xl font-bold text-white">{pagination.total}</p>
            </div>
          </div>
        </div>
      </div>

      <FiltersBar
        searchQuery={searchQuery}
        countryFilter={countryFilter}
        cityFilter={cityFilter}
        typeFilter={typeFilter}
        statusFilter={statusFilter}
        countries={uniqueCountries}
        cities={uniqueCities}
        onSearchChange={setSearchQuery}
        onCountryChange={handleCountryChange}
        onCityChange={handleCityChange}
        onTypeChange={handleTypeChange}
        onStatusChange={handleStatusChange}
        onReset={handleResetFilters}
      />

      <CompaniesTable
        companies={companies}
        isLoading={isLoading}
        error={error}
        pagination={pagination}
        sortField={sortField}
        sortDirection={sortDirection}
        statusLoadingIds={statusLoadingIds}
        actionDisabled={actionDisabled}
        hasFilters={hasFilters}
        onSort={handleSort}
        onRetry={refresh}
        onPageChange={(page) => updateParams({ page })}
        onPerPageChange={(perPage) => updateParams({ per_page: perPage, page: 1 })}
        onView={handleView}
        onEdit={openEditModal}
        onDelete={requestDelete}
        onRestore={requestRestore}
        onToggleStatus={handleToggleStatus}
      />

      <CompanyModal
        isOpen={isModalOpen}
        title={modalMode === 'create' ? 'Create Company' : 'Edit Company'}
        description={modalMode === 'create'
          ? 'Add a new company profile with business, contact, and localization details.'
          : 'Update editable company profile fields.'}
        formData={formData}
        errors={formErrors}
        isSubmitting={isSubmitting}
        submitLabel={modalMode === 'create' ? 'Create Company' : 'Save Changes'}
        onChange={setFormData}
        onSubmit={handleSubmit}
        onClose={closeModal}
      />

      <CompanyDetailsDrawer
        company={drawerCompany}
        isOpen={isDrawerOpen}
        isLoading={isDrawerLoading}
        onClose={() => setIsDrawerOpen(false)}
      />

      <DeleteDialog
        isOpen={Boolean(confirmCompany)}
        title={confirmMode === 'delete' ? 'Delete company?' : 'Restore company?'}
        message={confirmMode === 'delete'
          ? 'This company will be soft deleted.'
          : 'This company will be restored and returned to the directory.'}
        confirmLabel={confirmMode === 'delete' ? 'Delete' : 'Restore'}
        tone={confirmMode === 'delete' ? 'danger' : 'restore'}
        isSubmitting={isConfirmSubmitting}
        onCancel={closeConfirmDialog}
        onConfirm={handleConfirm}
      />
    </div>
  );
}
