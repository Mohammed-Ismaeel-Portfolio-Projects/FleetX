import { useState } from 'react';
import Sidebar from './Sidebar';
import Topbar from './Topbar';
import MobileDrawer from './MobileDrawer';

interface AdminLayoutProps {
  children: React.ReactNode;
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  const [collapsed, setCollapsed] = useState(false);
  const [isMobileOpen, setIsMobileOpen] = useState(false);

  return (
    <div className="flex h-screen w-full bg-[#0B0F19] text-white overflow-hidden font-sans">
      {/* 1. Left Sidebar Navigation (Hidden on mobile/tablet) */}
      <Sidebar 
        collapsed={collapsed} 
        onToggleCollapse={() => setCollapsed(!collapsed)} 
      />

      {/* 2. Right Side Layout Segment (Top Navigation Header + Scrollable content viewport) */}
      <div className="flex flex-col flex-grow min-w-0 h-full relative">
        {/* Sticky Header Topbar */}
        <Topbar onOpenMobile={() => setIsMobileOpen(true)} />

        {/* Scrollable Content Viewport Area */}
        <main className="flex-grow overflow-y-auto p-6 md:p-8 w-full">
          {/* Centered layout wrapper to limit maximum width on large viewports */}
          <div className="max-w-7xl mx-auto w-full text-left">
            {children}
          </div>
        </main>
      </div>

      {/* 3. Mobile Navigation Drawer (Activated on small viewports) */}
      <MobileDrawer 
        isOpen={isMobileOpen} 
        onClose={() => setIsMobileOpen(false)} 
      />
    </div>
  );
}
