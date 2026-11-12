import { useLocation } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  LayoutDashboard,
  Building2,
  Truck,
  Box,
  User,
  Package,
  Navigation,
  DollarSign,
  FileText,
  BarChart3,
  Bell,
  Shield,
  ChevronLeft,
  ChevronRight,
  LogOut
} from 'lucide-react';
import { cn } from '../../../lib/utils';
import SidebarItem from './SidebarItem';
import logoImg from '../../../assets/logo/logo.png';
import { useAuth } from '../../../core/auth';

interface SidebarProps {
  collapsed: boolean;
  onToggleCollapse: () => void;
  isMobile?: boolean;
  onCloseMobile?: () => void;
}

const navItems = [
  { icon: LayoutDashboard, label: 'Dashboard', href: '/admin' },
  { icon: Building2, label: 'Companies', href: '/admin/companies' },
  { icon: Shield, label: 'Partner Companies', href: '/admin/partner-companies' },
  { icon: Truck, label: 'Fleet Management', href: '/admin/fleet' },
  { icon: Box, label: 'Trailers', href: '/admin/trailers' },
  { icon: User, label: 'Drivers', href: '/admin/drivers' },
  { icon: Package, label: 'Shipments', href: '/admin/shipments' },
  { icon: Navigation, label: 'Tracking', href: '/admin/tracking' },
  { icon: DollarSign, label: 'Pricing', href: '/admin/pricing' },
  { icon: FileText, label: 'Monthly Invoices', href: '/admin/monthly-invoices' },
  { icon: BarChart3, label: 'Analytics', href: '/admin/analytics' },
  { icon: Bell, label: 'Notifications', href: '/admin/notifications' },
  { icon: Shield, label: 'Security', href: '/admin/security' },
];

export default function Sidebar({
  collapsed,
  onToggleCollapse,
  isMobile = false,
  onCloseMobile,
}: SidebarProps) {
  const location = useLocation();
  const { user, logout } = useAuth();

  return (
    <motion.aside
      animate={{ width: collapsed ? 76 : 240 }}
      transition={{ duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
      className={cn(
        'flex flex-col h-full bg-[#0E1320] border-r border-white/5 py-6 px-3 relative z-30 select-none flex-shrink-0',
        isMobile ? 'w-[240px] border-r-0' : 'hidden md:flex'
      )}
    >
      {/* Top Brand Logo */}
      <div className={cn('flex items-center gap-3 px-3 mb-8 h-10', collapsed ? 'justify-center px-0' : '')}>
        <img 
          src={logoImg} 
          alt="FleetX Logo" 
          className="w-8 h-8 rounded-lg object-cover border border-white/10"
        />
        {!collapsed && (
          <div className="flex flex-col text-left">
            <span className="font-extrabold text-sm tracking-tight text-white font-sans">
              Fleet<span className="text-[#0250C5]">X</span>
            </span>
            <span className="text-[9px] font-bold text-slate-500 uppercase tracking-widest font-sans">
              Precision Logistics
            </span>
          </div>
        )}
      </div>

      {/* Main Nav Items List */}
      <nav className="flex-grow flex flex-col gap-1 overflow-y-auto px-1 scrollbar-none">
        {navItems.map((item) => {
          // Exact match for '/' admin, otherwise prefix match
          const isActive = item.href === '/admin' 
            ? location.pathname === '/admin' || location.pathname === '/admin/'
            : item.href === '/admin/companies'
              ? location.pathname === '/admin/companies' || location.pathname.startsWith('/admin/companies/')
              : location.pathname.startsWith(item.href);

          return (
            <SidebarItem
              key={item.label}
              icon={item.icon}
              label={item.label}
              href={item.href}
              active={isActive}
              collapsed={collapsed}
              onClick={isMobile ? onCloseMobile : undefined}
            />
          );
        })}
      </nav>

      {/* Bottom User shortcut */}
      <div className={cn('pt-4 border-t border-white/5 flex flex-col gap-4 mt-auto', collapsed ? 'items-center' : 'px-2')}>
        <div className="flex items-center gap-3 w-full">
          <div className="w-9 h-9 rounded-full bg-[#0250C5]/10 border border-[#0250C5]/20 flex items-center justify-center flex-shrink-0 text-[#0250C5] font-bold text-sm">
            {user?.name?.split(' ').map(n => n[0]).join('').toUpperCase() || 'JD'}
          </div>
          {!collapsed && (
            <div className="flex flex-col text-left truncate flex-grow">
              <span className="text-xs font-bold text-slate-200 font-sans truncate">
                {user?.name || 'John Doe'}
              </span>
              <span className="text-[9.5px] font-semibold text-slate-500 font-sans truncate">
                {user?.role || 'admin'}
              </span>
            </div>
          )}
          {!collapsed && (
            <button
              onClick={() => {
                void logout();
              }}
              className="p-1.5 text-slate-500 hover:text-red-400 transition-colors rounded-lg hover:bg-red-500/10 cursor-pointer"
              title="Sign Out"
              aria-label="Sign Out"
            >
              <LogOut className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>

      {/* Collapse Trigger chevron button (hidden on mobile) */}
      {!isMobile && (
        <button
          onClick={onToggleCollapse}
          className="absolute -right-3.5 top-[22px] w-7 h-7 rounded-full bg-[#0E1320] border border-white/10 flex items-center justify-center text-slate-400 hover:text-white shadow-lg cursor-pointer hover:bg-slate-800 transition-colors active:scale-95"
          title={collapsed ? 'Expand Sidebar' : 'Collapse Sidebar'}
          aria-label={collapsed ? 'Expand Sidebar' : 'Collapse Sidebar'}
        >
          {collapsed ? <ChevronRight className="w-4.5 h-4.5" /> : <ChevronLeft className="w-4.5 h-4.5" />}
        </button>
      )}
    </motion.aside>
  );
}
