import { useCallback, useEffect, useRef, useState } from 'react';
import { Search, X, Check, ChevronDown, ChevronUp } from 'lucide-react';

interface ResourceItem {
  id: string;
  label: string;
  subtitle: string | null;
  badge: string | null;
  badgeTone: 'success' | 'warning' | 'danger' | 'neutral';
  disabled: boolean;
  disabledReason: string | null;
  isPrimary?: boolean;
}

interface ResourceSelectorProps {
  testId: string;
  title: string;
  placeholder: string;
  selectedId: string | null;
  items: ResourceItem[];
  loading: boolean;
  error: string | null;
  onSearch: (query: string) => void;
  onSelect: (id: string | null) => void;
  onRetry: () => void;
  required?: boolean;
  hasCategories?: boolean;
  primaryHeader?: string;
  secondaryHeader?: string;
  noMatchingMessage?: string;
  emptyStateMessage?: string;
  subtitleExtra?: React.ReactNode;
}

const badgeStyles: Record<string, string> = {
  success: 'border-emerald-400/20 bg-emerald-500/10 text-emerald-300',
  warning: 'border-amber-400/20 bg-amber-500/10 text-amber-200',
  danger: 'border-rose-400/20 bg-rose-500/10 text-rose-200',
  neutral: 'border-white/10 bg-white/5 text-slate-300',
};

export default function ResourceSelector({
  testId,
  title,
  placeholder,
  selectedId,
  items,
  loading,
  error,
  onSearch,
  onSelect,
  onRetry,
  required = false,
  hasCategories = false,
  primaryHeader = 'Recommended / Matching',
  secondaryHeader = 'Other eligible',
  noMatchingMessage = 'No matching candidates currently available.',
  emptyStateMessage = 'No eligible items currently available.',
  subtitleExtra,
}: ResourceSelectorProps) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [showSecondary, setShowSecondary] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const containerRef = useRef<HTMLDivElement>(null);

  const selected = items.find((i) => i.id === selectedId) ?? null;

  const handleQueryChange = useCallback(
    (value: string) => {
      setQuery(value);
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => onSearch(value), 280);
    },
    [onSearch]
  );

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const primaryItems = hasCategories ? items.filter((i) => i.isPrimary) : items;
  const secondaryItems = hasCategories ? items.filter((i) => !i.isPrimary) : [];

  return (
    <div ref={containerRef} data-testid={testId} className="space-y-2">
      <div className="flex items-center justify-between">
        <label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
          {title}
          {required && <span className="ml-0.5 text-rose-400">*</span>}
        </label>
        {selected ? (
          <button
            type="button"
            onClick={() => onSelect(null)}
            className="text-[10px] font-semibold text-slate-400 hover:text-slate-200"
          >
            Clear
          </button>
        ) : null}
      </div>

      {subtitleExtra}

      {selected && !open ? (
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="flex w-full items-center gap-3 rounded-xl border border-emerald-400/20 bg-emerald-500/5 px-3 py-3 text-left transition hover:bg-emerald-500/10"
        >
          <Check className="h-4 w-4 shrink-0 text-emerald-400" />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold text-slate-100">{selected.label}</p>
            {selected.subtitle ? (
              <p className="truncate text-xs text-slate-400">{selected.subtitle}</p>
            ) : null}
          </div>
          {selected.badge ? (
            <span
              className={`shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider ${badgeStyles[selected.badgeTone]}`}
            >
              {selected.badge}
            </span>
          ) : null}
        </button>
      ) : (
        <>
          <div className="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2">
            <Search className="h-4 w-4 shrink-0 text-slate-500" />
            <input
              data-testid={`${testId}-search`}
              type="text"
              value={query}
              onChange={(e) => handleQueryChange(e.target.value)}
              onFocus={() => setOpen(true)}
              placeholder={placeholder}
              className="min-w-0 flex-1 bg-transparent text-sm text-slate-200 outline-none placeholder:text-slate-500"
            />
            {query ? (
              <button
                type="button"
                onClick={() => {
                  setQuery('');
                  onSearch('');
                }}
                className="text-slate-500 hover:text-slate-300"
              >
                <X className="h-3.5 w-3.5" />
              </button>
            ) : null}
          </div>

          {open ? (
            <div className="max-h-72 overflow-y-auto rounded-xl border border-white/10 bg-[#0A0F1A]">
              {loading ? (
                <p className="px-3 py-4 text-center text-xs text-slate-500">Loading…</p>
              ) : error ? (
                <div className="px-3 py-4 text-center">
                  <p className="text-xs text-rose-300">{error}</p>
                  <button
                    type="button"
                    onClick={onRetry}
                    className="mt-2 text-xs font-semibold text-cyan-300 hover:text-cyan-200"
                  >
                    Retry
                  </button>
                </div>
              ) : items.length === 0 ? (
                <p className="px-3 py-4 text-center text-xs text-slate-400">{emptyStateMessage}</p>
              ) : !hasCategories ? (
                items.map((item) => renderResourceOption(item, selectedId, testId, onSelect, setOpen, setQuery))
              ) : (
                <div className="divide-y divide-white/5">
                  {/* Primary / Matching Candidates */}
                  <div>
                    <div className="bg-cyan-500/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-cyan-300">
                      {primaryHeader} ({primaryItems.length})
                    </div>
                    {primaryItems.length === 0 ? (
                      <p className="px-3 py-3 text-xs italic text-slate-400">{noMatchingMessage}</p>
                    ) : (
                      primaryItems.map((item) => renderResourceOption(item, selectedId, testId, onSelect, setOpen, setQuery))
                    )}
                  </div>

                  {/* Secondary / More Eligible Candidates */}
                  {secondaryItems.length > 0 ? (
                    <div>
                      {!query && !showSecondary ? (
                        <button
                          type="button"
                          onClick={() => setShowSecondary(true)}
                          className="flex w-full items-center justify-between bg-white/[0.02] px-3 py-2 text-left text-xs font-semibold text-cyan-400 hover:bg-white/5"
                        >
                          <span>+ More eligible trailers ({secondaryItems.length})</span>
                          <ChevronDown className="h-3.5 w-3.5 text-cyan-400" />
                        </button>
                      ) : (
                        <div>
                          <div className="flex items-center justify-between bg-amber-500/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-amber-300">
                            <span>{secondaryHeader} ({secondaryItems.length})</span>
                            {!query ? (
                              <button
                                type="button"
                                onClick={() => setShowSecondary(false)}
                                className="text-[10px] text-amber-200 hover:underline flex items-center gap-0.5"
                              >
                                Hide <ChevronUp className="h-3 w-3" />
                              </button>
                            ) : null}
                          </div>
                          {secondaryItems.map((item) => renderResourceOption(item, selectedId, testId, onSelect, setOpen, setQuery))}
                        </div>
                      )}
                    </div>
                  ) : null}
                </div>
              )}
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}

function renderResourceOption(
  item: ResourceItem,
  selectedId: string | null,
  testId: string,
  onSelect: (id: string | null) => void,
  setOpen: (open: boolean) => void,
  setQuery: (q: string) => void
) {
  return (
    <button
      key={item.id}
      type="button"
      disabled={item.disabled}
      data-testid={`${testId}-option-${item.id}`}
      onClick={() => {
        if (!item.disabled) {
          onSelect(item.id);
          setOpen(false);
          setQuery('');
        }
      }}
      className={`flex w-full items-center gap-3 border-b border-white/5 px-3 py-2.5 text-left transition last:border-b-0 ${
        item.disabled
          ? 'cursor-not-allowed opacity-40'
          : item.id === selectedId
            ? 'bg-cyan-500/10'
            : 'hover:bg-white/5'
      }`}
    >
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold text-slate-100">{item.label}</p>
        {item.subtitle ? (
          <p className="truncate text-xs text-slate-400">{item.subtitle}</p>
        ) : null}
        {item.disabled && item.disabledReason ? (
          <p className="text-[10px] text-rose-300/70">{item.disabledReason}</p>
        ) : null}
      </div>
      {item.badge ? (
        <span
          className={`shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider ${badgeStyles[item.badgeTone]}`}
        >
          {item.badge}
        </span>
      ) : null}
      {item.id === selectedId ? (
        <Check className="h-4 w-4 shrink-0 text-cyan-400" />
      ) : null}
    </button>
  );
}

export type { ResourceItem };

