import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { CompanyCollection } from '../types/company.types';

interface PaginationProps {
  pagination: CompanyCollection['meta'];
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
}

function getPageNumbers(currentPage: number, lastPage: number) {
  const start = Math.max(1, currentPage - 2);
  const end = Math.min(lastPage, currentPage + 2);
  return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

export default function Pagination({ pagination, onPageChange, onPerPageChange }: PaginationProps) {
  const pages = getPageNumbers(pagination.current_page, pagination.last_page);

  return (
    <div className="flex flex-col gap-4 border-t border-white/5 bg-[#0A0E17] px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
      <div className="text-xs text-slate-500">
        Showing <span className="font-mono font-bold text-slate-300">{pagination.from || 0}</span> to{' '}
        <span className="font-mono font-bold text-slate-300">{pagination.to || 0}</span> of{' '}
        <span className="font-mono font-bold text-[#00E5FF]">{pagination.total}</span> results
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <label className="flex items-center gap-2 text-xs text-slate-500">
          Rows
          <select
            value={pagination.per_page}
            onChange={(event) => onPerPageChange(Number(event.target.value))}
            className="rounded-lg border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs font-bold text-white outline-none"
            aria-label="Rows per page"
          >
            {[10, 15, 25, 50].map((value) => (
              <option key={value} value={value}>{value}</option>
            ))}
          </select>
        </label>

        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={() => onPageChange(pagination.current_page - 1)}
            disabled={pagination.current_page === 1}
            className="flex h-8 w-8 items-center justify-center rounded-lg border border-white/5 text-slate-400 transition-colors hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
            aria-label="Previous page"
          >
            <ChevronLeft className="h-4 w-4" />
          </button>

          {pages.map((page) => (
            <button
              key={page}
              type="button"
              onClick={() => onPageChange(page)}
              className={`h-8 min-w-8 rounded-lg px-2 text-xs font-bold transition-colors ${
                page === pagination.current_page
                  ? 'bg-[#0250C5] text-white'
                  : 'border border-white/5 text-slate-400 hover:bg-white/5 hover:text-white'
              }`}
              aria-label={`Page ${page}`}
              aria-current={page === pagination.current_page ? 'page' : undefined}
            >
              {page}
            </button>
          ))}

          <button
            type="button"
            onClick={() => onPageChange(pagination.current_page + 1)}
            disabled={pagination.current_page === pagination.last_page}
            className="flex h-8 w-8 items-center justify-center rounded-lg border border-white/5 text-slate-400 transition-colors hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
            aria-label="Next page"
          >
            <ChevronRight className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
