import { Search as SearchIcon } from 'lucide-react';

export default function Search() {
  return (
    <div className="relative w-full max-w-[280px] hidden sm:block">
      {/* Search Icon */}
      <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" />

      {/* Input Field */}
      <input
        type="text"
        placeholder="Search shipments, vehicles..."
        className="w-full pl-9 pr-12 py-1.5 bg-white/5 hover:bg-white/10 border border-white/5 rounded-full text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-[#0250C5] focus:border-[#0250C5] focus:bg-slate-900/60 transition-all duration-200"
      />

      {/* Keyboard Shortcut Indicator */}
      <div className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-0.5 px-1.5 py-0.5 rounded border border-white/5 bg-white/5 text-[9px] font-bold text-slate-500 font-mono select-none">
        <span>⌘</span>
        <span>K</span>
      </div>
    </div>
  );
}
