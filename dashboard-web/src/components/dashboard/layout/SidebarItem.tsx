import { Link } from 'react-router-dom';
import type { LucideIcon } from 'lucide-react';
import { cn } from '../../../lib/utils';

interface SidebarItemProps {
  icon: LucideIcon;
  label: string;
  href: string;
  active: boolean;
  collapsed: boolean;
  onClick?: () => void;
}

export default function SidebarItem({
  icon: Icon,
  label,
  href,
  active,
  collapsed,
  onClick,
}: SidebarItemProps) {
  return (
    <Link
      to={href}
      onClick={onClick}
      className={cn(
        'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all duration-200 select-none outline-none relative group cursor-pointer text-sm font-medium',
        active
          ? 'bg-[#0250C5]/10 text-white border border-[#0250C5]/20 shadow-sm shadow-[#0250C5]/5'
          : 'text-slate-400 hover:text-white hover:bg-white/5 border border-transparent',
        collapsed ? 'justify-center px-0 w-10 h-10' : 'w-full'
      )}
      title={collapsed ? label : undefined}
      aria-label={label}
    >
      {/* Icon */}
      <Icon className={cn('w-5 h-5 flex-shrink-0 transition-transform duration-200 group-hover:scale-105', active ? 'text-[#0250C5]' : 'text-slate-400 group-hover:text-white')} />

      {/* Label (hidden when collapsed) */}
      {!collapsed && (
        <span className="truncate font-sans font-medium">
          {label}
        </span>
      )}

      {/* Active blue indicator bar on left when expanded */}
      {active && !collapsed && (
        <div className="absolute left-0 top-1/4 bottom-1/4 w-1 bg-[#0250C5] rounded-r-md" />
      )}

      {/* Tooltip for collapsed mode */}
      {collapsed && (
        <div className="absolute left-full ml-3 px-2.5 py-1.5 bg-slate-900 border border-slate-800 text-white text-[11px] font-semibold rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap z-50 shadow-xl">
          {label}
        </div>
      )}
    </Link>
  );
}
