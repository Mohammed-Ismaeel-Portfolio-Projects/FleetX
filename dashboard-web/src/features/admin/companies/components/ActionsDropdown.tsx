import { useEffect, useRef, useState } from 'react';
import { Edit3, Eye, MoreHorizontal, RotateCcw, Trash2 } from 'lucide-react';
import type { Company } from '../types/company.types';

interface ActionsDropdownProps {
  company: Company;
  disabled?: boolean;
  onView: (company: Company) => void;
  onEdit: (company: Company) => void;
  onDelete: (company: Company) => void;
  onRestore: (company: Company) => void;
}

export default function ActionsDropdown({
  company,
  disabled,
  onView,
  onEdit,
  onDelete,
  onRestore,
}: ActionsDropdownProps) {
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const isDeleted = Boolean(company.deleted_at);

  useEffect(() => {
    const handlePointerDown = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handlePointerDown);
    return () => document.removeEventListener('mousedown', handlePointerDown);
  }, []);

  const actionClass = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-300 transition-colors hover:bg-white/5 hover:text-white';

  return (
    <div ref={menuRef} className="relative flex justify-end">
      <button
        type="button"
        onClick={() => setIsOpen((current) => !current)}
        disabled={disabled}
        className="flex h-8 w-8 items-center justify-center rounded-lg border border-white/5 text-slate-400 transition-colors hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
        aria-label={`Open actions for ${company.company_name}`}
        aria-haspopup="menu"
        aria-expanded={isOpen}
      >
        <MoreHorizontal className="h-4 w-4" />
      </button>

      {isOpen && (
        <div className="absolute right-0 top-9 z-30 w-40 rounded-xl border border-white/10 bg-[#0A0E17] p-1.5 shadow-2xl" role="menu">
          <button type="button" className={actionClass} onClick={() => { setIsOpen(false); onView(company); }}>
            <Eye className="h-4 w-4" />
            View
          </button>
          {!isDeleted && (
            <button type="button" className={actionClass} onClick={() => { setIsOpen(false); onEdit(company); }}>
              <Edit3 className="h-4 w-4" />
              Edit
            </button>
          )}
          {isDeleted ? (
            <button type="button" className={actionClass} onClick={() => { setIsOpen(false); onRestore(company); }}>
              <RotateCcw className="h-4 w-4" />
              Restore
            </button>
          ) : (
            <button type="button" className={`${actionClass} text-red-300 hover:text-red-200`} onClick={() => { setIsOpen(false); onDelete(company); }}>
              <Trash2 className="h-4 w-4" />
              Delete
            </button>
          )}
        </div>
      )}
    </div>
  );
}
