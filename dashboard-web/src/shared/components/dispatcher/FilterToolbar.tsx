import { Search, RotateCcw } from 'lucide-react';
import { cn } from '../../../lib/utils';
import { dispatcherDesignTokens } from '../../constants/dispatcherDesignSystem';

interface FilterToolbarProps {
  children?: React.ReactNode;
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  onReset?: () => void;
  className?: string;
}

export function FilterToolbar({ children, searchValue, onSearchChange, onReset, className }: FilterToolbarProps) {
  return (
    <div className={cn(dispatcherDesignTokens.surface, 'p-4', className)}>
      <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div className="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
          <label className={cn('flex min-w-[220px] items-center gap-2', dispatcherDesignTokens.input, 'text-slate-400')}>
            <Search className="h-4 w-4" />
            <input
              value={searchValue ?? ''}
              onChange={(event) => onSearchChange?.(event.target.value)}
              placeholder="Search operations"
              className="w-full bg-transparent text-sm text-white outline-none"
              aria-label="Search operations"
            />
          </label>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {children}
          {onReset ? (
            <button onClick={onReset} className={cn(dispatcherDesignTokens.buttonSecondary)} aria-label="Reset filters">
              <RotateCcw className="h-4 w-4" /> Reset
            </button>
          ) : null}
        </div>
      </div>
    </div>
  );
}
