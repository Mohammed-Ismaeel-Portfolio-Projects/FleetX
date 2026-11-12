import { Building2, ChevronDown, ChevronUp, Inbox, ServerCrash } from 'lucide-react';
import ActionsDropdown from './ActionsDropdown';
import Pagination from './Pagination';
import StatusSwitch from './StatusSwitch';
import type { Company, CompanyCollection } from '../types/company.types';

interface CompaniesTableProps {
  companies: Company[];
  isLoading: boolean;
  error: string | null;
  pagination: CompanyCollection['meta'];
  sortField: string;
  sortDirection: 'asc' | 'desc';
  statusLoadingIds: Set<string>;
  actionDisabled: boolean;
  hasFilters: boolean;
  onSort: (field: string) => void;
  onRetry: () => void;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  onView: (company: Company) => void;
  onEdit: (company: Company) => void;
  onDelete: (company: Company) => void;
  onRestore: (company: Company) => void;
  onToggleStatus: (company: Company, isActive: boolean) => void;
}

const columns = [
  { label: 'Logo', className: 'w-[74px]' },
  { label: 'Company', sort: 'company_name', className: 'min-w-[220px]' },
  { label: 'Code', sort: 'company_code', className: 'min-w-[120px]' },
  { label: 'Country', sort: 'country', className: 'min-w-[110px]' },
  { label: 'City', sort: 'city', className: 'min-w-[130px]' },
  { label: 'Email', className: 'min-w-[220px]' },
  { label: 'Phone', className: 'min-w-[150px]' },
  { label: 'Status Badge', className: 'min-w-[150px]' },
  { label: 'Created Date', sort: 'created_at', className: 'min-w-[140px]' },
  { label: 'Actions', className: 'w-[90px] text-right' },
];

function SkeletonRows() {
  return (
    <>
      {Array.from({ length: 8 }).map((_, index) => (
        <tr key={index} className="border-b border-white/5">
          {columns.map((column) => (
            <td key={column.label} className="px-4 py-4">
              <div className="h-4 animate-pulse rounded bg-white/5" />
            </td>
          ))}
        </tr>
      ))}
    </>
  );
}

function SortIcon({ active, direction }: { active: boolean; direction: 'asc' | 'desc' }) {
  if (!active) {
    return <ChevronDown className="h-3 w-3 opacity-30" />;
  }

  return direction === 'asc' ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />;
}

export default function CompaniesTable({
  companies,
  isLoading,
  error,
  pagination,
  sortField,
  sortDirection,
  statusLoadingIds,
  actionDisabled,
  hasFilters,
  onSort,
  onRetry,
  onPageChange,
  onPerPageChange,
  onView,
  onEdit,
  onDelete,
  onRestore,
  onToggleStatus,
}: CompaniesTableProps) {
  if (error) {
    return (
      <div className="rounded-2xl border border-red-500/20 bg-[#0E1320] p-12 text-center shadow-md">
        <ServerCrash className="mx-auto h-12 w-12 text-red-300" />
        <h3 className="mt-4 text-sm font-bold text-white">Companies could not be loaded</h3>
        <p className="mx-auto mt-2 max-w-md text-xs leading-5 text-slate-500">{error}</p>
        <button
          type="button"
          onClick={onRetry}
          className="mt-5 rounded-xl bg-[#0250C5] px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-[#0244a5]"
        >
          Retry
        </button>
      </div>
    );
  }

  if (!isLoading && companies.length === 0) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-12 text-center shadow-md">
        <Inbox className="mx-auto h-12 w-12 text-slate-600" />
        <h3 className="mt-4 text-sm font-bold text-white">No companies found</h3>
        <p className="mt-2 text-xs text-slate-500">
          {hasFilters ? 'Try adjusting your search or filters.' : 'Create the first company to begin building the directory.'}
        </p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-white/5 bg-[#0E1320] shadow-md">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[1280px] border-collapse">
          <thead className="sticky top-0 z-10 bg-[#0A0E17]">
            <tr className="border-b border-white/5">
              {columns.map((column) => (
                <th key={column.label} className={`px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-500 ${column.className}`}>
                  {column.sort ? (
                    <button
                      type="button"
                      onClick={() => onSort(column.sort!)}
                      className="inline-flex items-center gap-1.5 transition-colors hover:text-white"
                    >
                      {column.label}
                      <SortIcon active={sortField === column.sort} direction={sortDirection} />
                    </button>
                  ) : (
                    column.label
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-white/5">
            {isLoading ? (
              <SkeletonRows />
            ) : (
              companies.map((company) => (
                <tr key={company.id} className="transition-colors hover:bg-white/[0.02]">
                  <td className="px-4 py-3">
                    <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-[#090D16]">
                      {company.logo_path ? (
                        <img src={company.logo_path} alt="" className="h-full w-full object-cover" />
                      ) : (
                        <Building2 className="h-5 w-5 text-slate-500" />
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex min-w-0 flex-col">
                      <span className="truncate text-sm font-bold text-white">{company.company_name}</span>
                      <span className="truncate text-xs text-slate-500">{company.legal_name}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <span className="font-mono text-xs font-bold text-slate-300">{company.company_code}</span>
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-300">{company.country}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{company.city}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{company.primary_email}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{company.primary_phone || 'Not provided'}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <span className={`inline-flex rounded-full border px-2 py-1 text-[11px] font-bold ${
                        company.deleted_at
                          ? 'border-red-500/20 bg-red-500/10 text-red-300'
                          : company.is_active
                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                            : 'border-slate-500/20 bg-slate-500/10 text-slate-400'
                      }`}>
                        {company.deleted_at ? 'Deleted' : company.is_active ? 'Active' : 'Inactive'}
                      </span>
                      {!company.deleted_at && (
                        <StatusSwitch
                          isActive={company.is_active}
                          isLoading={statusLoadingIds.has(company.id)}
                          disabled={actionDisabled}
                          onToggle={(nextValue) => onToggleStatus(company, nextValue)}
                        />
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-300">
                    {new Date(company.created_at).toLocaleDateString()}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <ActionsDropdown
                      company={company}
                      disabled={actionDisabled}
                      onView={onView}
                      onEdit={onEdit}
                      onDelete={onDelete}
                      onRestore={onRestore}
                    />
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <Pagination
        pagination={pagination}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
      />
    </div>
  );
}
