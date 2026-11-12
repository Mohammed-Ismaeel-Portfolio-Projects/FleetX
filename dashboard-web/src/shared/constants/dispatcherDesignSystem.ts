export const dispatcherDesignTokens = {
  surface: 'rounded-[24px] border border-white/10 bg-[#0E1320] shadow-[0_20px_60px_rgba(0,0,0,0.22)]',
  surfaceElevated: 'rounded-[28px] border border-white/10 bg-[#0A1020]/95 shadow-[0_24px_80px_rgba(0,0,0,0.32)]',
  input: 'rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 outline-none placeholder:text-slate-500 focus:border-cyan-400/40 focus:ring-1 focus:ring-cyan-400/20',
  buttonPrimary: 'inline-flex items-center justify-center gap-2 rounded-2xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-3 text-sm font-semibold text-cyan-200 transition hover:bg-cyan-500/20',
  buttonSecondary: 'inline-flex items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 transition hover:bg-white/10',
  sectionGap: 'gap-6',
  pagePadding: 'p-6 md:p-8',
} as const;
