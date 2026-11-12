import { motion, AnimatePresence } from 'framer-motion';
import { X } from 'lucide-react';
import Sidebar from './Sidebar';

interface MobileDrawerProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function MobileDrawer({ isOpen, onClose }: MobileDrawerProps) {
  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-50 md:hidden flex">
          {/* Backdrop Blur Overlay */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 0.6 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.3 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"
          />

          {/* Drawer Navigation Pane */}
          <motion.div
            initial={{ x: -240 }}
            animate={{ x: 0 }}
            exit={{ x: -240 }}
            transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
            className="relative flex flex-col h-full w-[240px] bg-[#0E1320] shadow-2xl z-50"
          >
            {/* Close Trigger Button */}
            <button
              onClick={onClose}
              className="absolute right-4 top-4 p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-white/5 cursor-pointer"
              title="Close Drawer"
              aria-label="Close Drawer"
            >
              <X className="w-5 h-5" />
            </button>

            {/* Sidebar content */}
            <div className="h-full w-full pt-10">
              <Sidebar
                collapsed={false}
                onToggleCollapse={() => {}}
                isMobile={true}
                onCloseMobile={onClose}
              />
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  );
}
