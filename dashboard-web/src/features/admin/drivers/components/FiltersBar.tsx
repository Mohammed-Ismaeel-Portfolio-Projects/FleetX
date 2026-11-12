import { RotateCcw, Search } from 'lucide-react';

interface SelectOption {
  value: string;
  label: string;
}

interface FiltersBarProps {
  searchQuery: string;
  nationalityFilter: string;
  licenseClassFilter: string;
  statusFilter: string;
  experienceFilter: string;
  activeFilter: 'all' | 'active' | 'inactive';
  licenseClassOptions: SelectOption[];
  statusOptions: SelectOption[];
  onSearchChange: (value: string) => void;
  onNationalityChange: (value: string) => void;
  onLicenseClassChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onExperienceChange: (value: string) => void;
  onActiveChange: (value: 'all' | 'active' | 'inactive') => void;
  onReset: () => void;
}

const selectClass = 'w-full cursor-pointer appearance-none rounded-xl border border-white/5 bg-[#0A0E17] px-4 py-2.5 text-sm text-white outline-none transition-all focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/20';

export default function FiltersBar({
  searchQuery,
  nationalityFilter,
  licenseClassFilter,
  statusFilter,
  experienceFilter,
  activeFilter,
  licenseClassOptions,
  statusOptions,
  onSearchChange,
  onNationalityChange,
  onLicenseClassChange,
  onStatusChange,
  onExperienceChange,
  onActiveChange,
  onReset,
}: FiltersBarProps) {
  return (
    <div className="flex flex-col gap-4 rounded-2xl border border-white/5 bg-[#0E1320] p-4">
      <div className="flex flex-col gap-4 xl:flex-row xl:items-start">
        <div className="flex w-full max-w-xl items-center gap-3.5">
          <div className="relative flex h-11 w-full items-center rounded-xl border border-white/5 bg-[#090D16] px-4">
            <Search className="mr-3 h-4 w-4 text-slate-500" />
            <input
              type="text"
              value={searchQuery}
              onChange={(event) => onSearchChange(event.target.value)}
              placeholder="Search employee number, name, email, license..."
              className="w-full bg-transparent text-xs text-white placeholder-slate-500 focus:outline-none font-sans"
              aria-label="Search drivers"
            />
          </div>
        </div>

        <div className="grid w-full grid-cols-1 gap-3 md:grid-cols-2 xl:w-auto xl:grid-cols-3 2xl:grid-cols-4">
          <select
            value={nationalityFilter}
            onChange={(event) => onNationalityChange(event.target.value)}
            className={selectClass}
            aria-label="Filter by nationality"
          >
            <option value="">All Nationalities</option>
          </select>

          <select
            value={licenseClassFilter}
            onChange={(event) => onLicenseClassChange(event.target.value)}
            className={selectClass}
            aria-label="Filter by license class"
          >
            <option value="">All License Classes</option>
            {licenseClassOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>

          <select
            value={statusFilter}
            onChange={(event) => onStatusChange(event.target.value)}
            className={selectClass}
            aria-label="Filter by status"
          >
            <option value="">All Statuses</option>
            {statusOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>

          <input
            type="text"
            value={experienceFilter}
            onChange={(event) => onExperienceChange(event.target.value)}
            placeholder="Min Experience (years)"
            className={selectClass}
            aria-label="Filter by minimum experience"
          />

          <select
            value={activeFilter}
            onChange={(event) => onActiveChange(event.target.value as 'all' | 'active' | 'inactive')}
            className={selectClass}
            aria-label="Filter by active state"
          >
            <option value="all">All States</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>

          <button
            type="button"
            onClick={onReset}
            className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-bold text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
          >
            <RotateCcw className="h-4 w-4" />
            Reset
          </button>
        </div>
      </div>
    </div>
  );
}
