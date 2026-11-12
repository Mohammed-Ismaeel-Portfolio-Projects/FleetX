import { RotateCcw } from 'lucide-react';
import SearchInput from './SearchInput';

interface FiltersBarProps {
  searchQuery: string;
  countryFilter: string;
  cityFilter: string;
  typeFilter?: string;
  statusFilter: 'all' | 'active' | 'inactive';
  countries: string[];
  cities: string[];
  onSearchChange: (value: string) => void;
  onCountryChange: (value: string) => void;
  onCityChange: (value: string) => void;
  onTypeChange?: (value: string) => void;
  onStatusChange: (value: 'all' | 'active' | 'inactive') => void;
  onReset: () => void;
}

const selectClass = 'w-full cursor-pointer appearance-none rounded-xl border border-white/5 bg-[#0A0E17] px-4 py-2.5 text-sm text-white outline-none transition-all focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/20';

export default function FiltersBar({
  searchQuery,
  countryFilter,
  cityFilter,
  typeFilter,
  statusFilter,
  countries,
  cities,
  onSearchChange,
  onCountryChange,
  onCityChange,
  onTypeChange,
  onStatusChange,
  onReset,
}: FiltersBarProps) {
  return (
    <div className="flex flex-col gap-4 rounded-2xl border border-white/5 bg-[#0E1320] p-4">
      <div className="flex flex-col gap-4 xl:flex-row">
        <SearchInput value={searchQuery} onChange={onSearchChange} />

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:w-[720px] xl:grid-cols-[1fr_1fr_1fr_1fr_auto]">
          <select
            value={countryFilter}
            onChange={(event) => onCountryChange(event.target.value)}
            className={selectClass}
            aria-label="Filter by country"
          >
            <option value="">All Countries</option>
            {countries.map((country) => (
              <option key={country} value={country}>{country}</option>
            ))}
          </select>

          <select
            value={cityFilter}
            onChange={(event) => onCityChange(event.target.value)}
            className={selectClass}
            aria-label="Filter by city"
          >
            <option value="">All Cities</option>
            {cities.map((city) => (
              <option key={city} value={city}>{city}</option>
            ))}
          </select>

          {onTypeChange && (
            <select
              value={typeFilter || ''}
              onChange={(event) => onTypeChange(event.target.value)}
              className={selectClass}
              aria-label="Filter by company type"
            >
              <option value="">All Types</option>
              <option value="client">Client Companies</option>
              <option value="partner_carrier">Partner Carriers</option>
              <option value="hybrid">Hybrid</option>
              <option value="internal">Internal</option>
            </select>
          )}

          <select
            value={statusFilter}
            onChange={(event) => onStatusChange(event.target.value as 'all' | 'active' | 'inactive')}
            className={selectClass}
            aria-label="Filter by company status"
          >
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>

          <button
            type="button"
            onClick={onReset}
            className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-bold text-slate-300 transition-colors hover:bg-white/5 hover:text-white cursor-pointer"
          >
            <RotateCcw className="h-4 w-4" />
            Reset
          </button>
        </div>
      </div>
    </div>
  );
}
