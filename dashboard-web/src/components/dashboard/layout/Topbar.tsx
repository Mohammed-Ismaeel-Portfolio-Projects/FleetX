import { Menu } from 'lucide-react';
import Breadcrumb from './Breadcrumb';
import Search from './Search';
import Notifications from './Notifications';
import UserMenu from './UserMenu';

interface TopbarProps {
  onOpenMobile: () => void;
}

export default function Topbar({ onOpenMobile }: TopbarProps) {
  return (
    <header className="h-16 flex items-center justify-between px-6 bg-[#0B0F19]/80 backdrop-blur-md border-b border-white/5 sticky top-0 z-20 w-full">
      {/* Left side actions (Hamburger + Path segments) */}
      <div className="flex items-center gap-3">
        {/* Mobile Hamburger menu trigger */}
        <button
          onClick={onOpenMobile}
          className="md:hidden p-2 text-slate-400 hover:text-white rounded-lg hover:bg-white/5 cursor-pointer"
          title="Open Drawer"
          aria-label="Open Drawer"
        >
          <Menu className="w-5 h-5" />
        </button>

        {/* Dynamic Breadcrumbs */}
        <Breadcrumb />
      </div>

      {/* Right side actions (Search + Actions list) */}
      <div className="flex items-center gap-4">
        {/* Global Search Bar */}
        <Search />

        {/* Separator line */}
        <div className="h-4 w-px bg-white/5 hidden sm:block" />

        {/* Action icons */}
        <div className="flex items-center gap-2">
          {/* System Notifications Bell */}
          <Notifications />
        </div>

        {/* Vertical divider */}
        <div className="h-7 w-px bg-white/5" />

        {/* Profile Dropdown avatar */}
        <UserMenu />
      </div>
    </header>
  );
}
