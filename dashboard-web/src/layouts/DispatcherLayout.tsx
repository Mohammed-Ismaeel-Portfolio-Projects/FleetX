import { useState } from 'react';
import { useLocation, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { 
  LayoutDashboard, 
  Package, 
  Navigation, 
  LogOut, 
  ChevronLeft, 
  ChevronRight,
  Menu,
  Truck,
  Bell,
  BarChart2,
  Users,
  MessageSquareText,
  ClipboardList
} from 'lucide-react';
import { useAuth } from '../core/auth';
import { cn } from '../lib/utils';
import logoImg from '../assets/logo/logo.png';

interface DispatcherLayoutProps {
  children?: React.ReactNode;
}

export default function DispatcherLayout({ children }: DispatcherLayoutProps) {
  const [collapsed, setCollapsed] = useState(false);
  const [isMobileOpen, setIsMobileOpen] = useState(false);
  const location = useLocation();
  const navigate = useNavigate();
  const { user, logout } = useAuth();

  const handleLogout = () => {
    void logout().then(() => {
      navigate('/login', { replace: true });
    });
  };

  const navItems = [
    { icon: LayoutDashboard, label: 'Dashboard', href: '/dispatcher' },
    { icon: ClipboardList, label: 'Requests', href: '/dispatcher/requests' },
    { icon: Package, label: 'Shipments Center', href: '/dispatcher/shipments' },
    { icon: Navigation, label: 'Active Tracking', href: '/dispatcher/tracking' },
    { icon: Truck, label: 'Fleet Management', href: '/dispatcher/fleet' },
    { icon: Users, label: 'Driver Operations', href: '/dispatcher/drivers' },
    { icon: Bell, label: 'Alerts Center', href: '/dispatcher/notifications' },
    { icon: MessageSquareText, label: 'Communication Center', href: '/dispatcher/communications' },
    { icon: BarChart2, label: 'Analytics Center', href: '/dispatcher/analytics' },
  ];

  return (
    <div className="flex h-screen w-full bg-[#0B0F19] text-white overflow-hidden font-sans">
      
      {/* 1. Left Sidebar Navigation */}
      <motion.aside
        animate={{ width: collapsed ? 76 : 240 }}
        transition={{ duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
        className="hidden md:flex flex-col h-full bg-[#0E1320] border-r border-white/5 py-6 px-3 relative z-30 select-none flex-shrink-0"
      >
        {/* Brand logo */}
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
                Dispatcher Ops
              </span>
            </div>
          )}
        </div>

        {/* Navigation list */}
        <nav className="flex-grow flex flex-col gap-1 overflow-y-auto px-1 scrollbar-none">
          {navItems.map((item) => {
            const isActive = location.pathname === item.href || location.pathname.startsWith(item.href + '/');
            return (
              <Link
                key={item.label}
                to={item.href}
                className={cn(
                  'flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold font-sans uppercase tracking-wider transition-all duration-150 select-none cursor-pointer border border-transparent',
                  isActive 
                    ? 'bg-[#0250C5]/10 border-[#0250C5]/20 text-[#00E5FF]' 
                    : 'text-slate-400 hover:text-white hover:bg-white/2'
                )}
              >
                <item.icon className="w-4.5 h-4.5 flex-shrink-0" />
                {!collapsed && <span>{item.label}</span>}
              </Link>
            );
          })}
        </nav>

        {/* Footer actions */}
        <div className="mt-auto px-1">
          <button 
            onClick={handleLogout}
            className={cn(
              'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold font-sans uppercase tracking-wider text-red-400 hover:text-red-300 hover:bg-red-500/5 transition-all cursor-pointer border border-transparent',
              collapsed ? 'justify-center px-0' : ''
            )}
            title="Sign Out"
          >
            <LogOut className="w-4.5 h-4.5 flex-shrink-0" />
            {!collapsed && <span>Sign Out</span>}
          </button>
        </div>

        {/* Collapse toggle button */}
        <button 
          onClick={() => setCollapsed(!collapsed)}
          className="absolute -right-3 top-18 w-6 h-6 rounded-full border border-white/5 bg-[#0E1320] flex items-center justify-center text-slate-400 hover:text-white shadow-md cursor-pointer z-40"
        >
          {collapsed ? <ChevronRight className="w-3.5 h-3.5" /> : <ChevronLeft className="w-3.5 h-3.5" />}
        </button>
      </motion.aside>

      {/* 2. Main Right View Container */}
      <div className="flex flex-col flex-grow min-w-0 h-full relative">
        
        {/* Topbar header */}
        <header className="sticky top-0 bg-[#0E1320] border-b border-white/5 h-16 w-full flex items-center justify-between px-6 z-20">
          <button 
            onClick={() => setIsMobileOpen(true)}
            className="md:hidden p-2 text-slate-400 hover:text-white cursor-pointer"
            aria-label="Open mobile drawer"
          >
            <Menu className="w-5 h-5" />
          </button>

          <div className="flex items-center gap-2 text-left">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-widest font-mono">
              Dispatcher node
            </span>
          </div>

          <div className="flex items-center gap-4">
            <div className="flex flex-col text-right">
              <span className="text-xs font-bold text-white font-sans">{user?.name}</span>
              <span className="text-[9px] font-bold text-slate-500 uppercase tracking-widest font-sans">
                Dispatcher Level
              </span>
            </div>
          </div>
        </header>

        {/* Viewport Content */}
        <main className="flex-grow overflow-y-auto p-6 md:p-8 w-full">
          <div className="max-w-7xl mx-auto w-full text-left">
            {children}
          </div>
        </main>
      </div>

      {/* 3. Mobile Navigation Drawer */}
      {isMobileOpen && (
        <div className="fixed inset-0 z-50 md:hidden flex">
          {/* Backdrop mask */}
          <div 
            onClick={() => setIsMobileOpen(false)}
            className="absolute inset-0 bg-slate-950/80 backdrop-blur-xs"
          />

          {/* Drawer sidebar panel */}
          <div className="relative w-[240px] bg-[#0E1320] h-full flex flex-col p-6 z-10 border-r border-white/5">
            <div className="flex items-center gap-3 mb-8">
              <img 
                src={logoImg} 
                alt="Logo" 
                className="w-8 h-8 rounded-lg object-cover"
              />
              <span className="font-extrabold text-white text-sm">FleetX</span>
            </div>

            <nav className="flex flex-col gap-2">
              {navItems.map((item) => (
                <Link
                  key={item.label}
                  to={item.href}
                  onClick={() => setIsMobileOpen(false)}
                  className="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-white/2"
                >
                  <item.icon className="w-4.5 h-4.5" />
                  <span>{item.label}</span>
                </Link>
              ))}
            </nav>

            <button
              onClick={handleLogout}
              className="mt-auto flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-red-400 hover:bg-red-500/5 w-full cursor-pointer text-left"
            >
              <LogOut className="w-4.5 h-4.5" />
              <span>Sign Out</span>
            </button>
          </div>
        </div>
      )}

    </div>
  );
}
