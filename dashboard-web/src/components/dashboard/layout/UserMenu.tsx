import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { User, Lock, LogOut } from 'lucide-react';
import { Link } from 'react-router-dom';

export default function UserMenu() {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <div className="relative">
      {/* Avatar Trigger button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="w-8 h-8 rounded-full bg-[#0250C5]/10 border border-[#0250C5]/20 flex items-center justify-center text-[#0250C5] font-bold text-xs cursor-pointer select-none focus:outline-none hover:scale-105 active:scale-95 transition-transform duration-200"
        aria-label="Toggle user menu"
      >
        JD
      </button>

      {/* Popover Dropdown Shield Backdrop */}
      {isOpen && (
        <div 
          className="fixed inset-0 z-40 cursor-default" 
          onClick={() => setIsOpen(false)} 
        />
      )}

      {/* Dropdown Menu Panel */}
      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, y: 10, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 10, scale: 0.95 }}
            transition={{ duration: 0.2, ease: [0.16, 1, 0.3, 1] }}
            className="absolute right-0 mt-3 w-56 bg-[#121724] border border-white/5 rounded-2xl shadow-2xl z-50 overflow-hidden text-left"
          >
            {/* Header info */}
            <div className="px-5 py-4 border-b border-white/5 flex flex-col">
              <span className="text-xs font-bold text-slate-200 font-sans">
                John Doe
              </span>
              <span className="text-[10px] text-slate-500 font-sans mt-0.5">
                john@fleetx.com
              </span>
            </div>

            {/* Menu Options List */}
            <div className="p-1.5 flex flex-col gap-0.5">
              <Link
                to="/admin/security"
                onClick={() => setIsOpen(false)}
                className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-400 hover:text-white hover:bg-white/5 rounded-xl transition-all duration-200"
              >
                <User className="w-4 h-4 text-slate-500" />
                <span>My Profile</span>
              </Link>
              <Link
                to="/admin/security"
                onClick={() => setIsOpen(false)}
                className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-400 hover:text-white hover:bg-white/5 rounded-xl transition-all duration-200"
              >
                <Lock className="w-4 h-4 text-slate-500" />
                <span>Security</span>
              </Link>
            </div>

            {/* Logout Footer option */}
            <div className="p-1.5 bg-slate-900/40 border-t border-white/5">
              <button
                onClick={() => setIsOpen(false)}
                className="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-xl transition-all duration-200 cursor-pointer"
              >
                <LogOut className="w-4 h-4 text-red-500" />
                <span>Sign Out</span>
              </button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
