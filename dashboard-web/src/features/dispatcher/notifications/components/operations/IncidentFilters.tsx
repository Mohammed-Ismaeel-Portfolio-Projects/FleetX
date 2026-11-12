import { Filter, RotateCcw, Search } from 'lucide-react';

interface StatusOption {
  id: number;
  name: string;
}

interface IncidentFiltersProps {
  searchQuery: string;
  onSearchChange: (value: string) => void;
  typeFilter: string;
  onTypeChange: (value: string) => void;
  statusIdFilter: number | null;
  onStatusIdChange: (value: number | null) => void;
  knownTypes: string[];
  statusOptions: StatusOption[];
  onReset: () => void;
}

export default function IncidentFilters({
  searchQuery,
  onSearchChange,
  typeFilter,
  onTypeChange,
  statusIdFilter,
  onStatusIdChange,
  knownTypes,
  statusOptions,
  onReset,
}: IncidentFiltersProps) {
  return (
    <section className="rounded-3xl border border-slate-800/80 bg-slate-950/70 p-4 shadow-lg shadow-black/10">
      <div className="flex flex-col gap-3 xl:flex-row xl:items-end">
        <label className="flex-1 text-sm text-slate-300">
          <span className="mb-2 flex items-center gap-2 text-slate-400">
            <Search className="h-4 w-4" /> Search (this page)
          </span>
          <input
            value={searchQuery}
            onChange={(event) => onSearchChange(event.target.value)}
            className="w-full rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none"
            placeholder="Client-side search over this loaded page"
          />
        </label>

        <label className="min-w-[180px] text-sm text-slate-300">
          <span className="mb-2 flex items-center gap-2 text-slate-400">
            <Filter className="h-4 w-4" /> Type
          </span>
          <select
            value={typeFilter}
            onChange={(event) => onTypeChange(event.target.value)}
            className="w-full rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 text-sm text-white"
          >
            <option value="">All types</option>
            {knownTypes.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
        </label>

        <label className="min-w-[180px] text-sm text-slate-300">
          <span className="mb-2 flex items-center gap-2 text-slate-400">
            <Filter className="h-4 w-4" /> Status
          </span>
          <select
            value={statusIdFilter == null ? '' : String(statusIdFilter)}
            onChange={(event) => {
              const raw = event.target.value;
              onStatusIdChange(raw === '' ? null : Number(raw));
            }}
            className="w-full rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 text-sm text-white"
          >
            <option value="">All statuses</option>
            {statusOptions.map((option) => (
              <option key={option.id} value={String(option.id)}>
                {option.name}
              </option>
            ))}
          </select>
        </label>

        <button
          type="button"
          onClick={onReset}
          className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-700 bg-slate-900/80 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-slate-500"
        >
          <RotateCcw className="h-4 w-4" />
          Reset
        </button>
      </div>
    </section>
  );
}
