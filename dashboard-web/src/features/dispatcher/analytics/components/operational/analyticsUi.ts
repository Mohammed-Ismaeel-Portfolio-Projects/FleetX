import { dispatcherDesignTokens } from '../../../../../shared/constants/dispatcherDesignSystem';

/** Shared Analytics presentation tokens — UI only (no data semantics). */
export const analyticsUi = {
  panel:
    'rounded-[24px] border border-white/10 bg-[#0E1320] shadow-[0_16px_48px_rgba(0,0,0,0.2)]',
  panelQuiet:
    'rounded-[24px] border border-white/[0.07] bg-[#0B101A]/90 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]',
  kpiCard:
    'relative overflow-hidden rounded-[22px] border border-white/10 bg-[#0E1320] p-5 shadow-[0_12px_40px_rgba(0,0,0,0.18)] h-full min-h-[132px] flex flex-col',
  kpiCardFeatured:
    'relative overflow-hidden rounded-[24px] border border-emerald-400/25 bg-[#0E1320] p-6 md:p-7 shadow-[0_18px_52px_rgba(0,0,0,0.24)] min-h-[156px] flex flex-col',
  metricTile:
    'rounded-2xl border border-white/[0.08] bg-[#111827]/80 px-4 py-3.5 min-h-[88px] flex flex-col justify-center',
  sectionTitle: 'text-base font-semibold tracking-tight text-white',
  sectionDescription: 'mt-1.5 max-w-3xl text-sm leading-relaxed text-slate-500',
  domainTitle: 'text-sm font-semibold uppercase tracking-[0.16em] text-slate-100',
  drillLink:
    'inline-flex shrink-0 items-center rounded-xl border border-cyan-400/25 bg-cyan-500/[0.07] px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-cyan-300/90 transition hover:border-cyan-400/45 hover:bg-cyan-500/15 hover:text-cyan-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/40',
  value: 'font-semibold tabular-nums tracking-tight text-white',
  valueMuted: 'font-semibold tabular-nums tracking-tight text-slate-500',
  label: 'text-[13px] font-medium text-slate-400',
  detail: 'mt-2 line-clamp-2 text-xs leading-relaxed text-slate-500',
  bannerWarn:
    'rounded-2xl border border-amber-500/25 bg-amber-500/[0.08] px-4 py-3 text-sm leading-relaxed text-amber-100',
  bannerError:
    'rounded-[24px] border border-red-500/25 bg-red-500/[0.08] p-5 text-sm leading-relaxed text-red-200',
  buttonPrimary: dispatcherDesignTokens.buttonPrimary,
} as const;

/** Metric-identity accents (not value thresholds). */
export type AnalyticsAccent = 'risk' | 'warn' | 'active' | 'success' | 'neutral';

export const analyticsAccent: Record<
  AnalyticsAccent,
  { card: string; bar: string; value: string; label: string; chip: string; tint: string }
> = {
  risk: {
    card: 'border-rose-400/25',
    bar: 'bg-rose-400',
    value: 'text-rose-50',
    label: 'text-rose-200/75',
    chip: 'border-rose-400/25 bg-rose-500/15 text-rose-200',
    tint: 'bg-rose-500/[0.06]',
  },
  warn: {
    card: 'border-amber-400/25',
    bar: 'bg-amber-400',
    value: 'text-amber-50',
    label: 'text-amber-200/75',
    chip: 'border-amber-400/25 bg-amber-500/15 text-amber-200',
    tint: 'bg-amber-500/[0.06]',
  },
  active: {
    card: 'border-cyan-400/25',
    bar: 'bg-cyan-400',
    value: 'text-cyan-50',
    label: 'text-cyan-200/75',
    chip: 'border-cyan-400/25 bg-cyan-500/15 text-cyan-200',
    tint: 'bg-cyan-500/[0.06]',
  },
  success: {
    card: 'border-emerald-400/25',
    bar: 'bg-emerald-400',
    value: 'text-emerald-50',
    label: 'text-emerald-200/75',
    chip: 'border-emerald-400/25 bg-emerald-500/15 text-emerald-200',
    tint: 'bg-emerald-500/[0.06]',
  },
  neutral: {
    card: 'border-white/10',
    bar: 'bg-slate-500',
    value: 'text-white',
    label: 'text-slate-400',
    chip: 'border-white/10 bg-white/5 text-slate-300',
    tint: 'bg-white/[0.02]',
  },
};

/** Domain panel identity (Fleet / Shipments / Drivers). */
export const domainAccent = {
  fleet: {
    panel: 'border-cyan-400/20',
    iconWrap: 'rounded-xl border border-cyan-400/25 bg-cyan-500/10 p-2 text-cyan-300',
    title: 'text-cyan-100',
  },
  shipments: {
    panel: 'border-sky-400/20',
    iconWrap: 'rounded-xl border border-sky-400/25 bg-sky-500/10 p-2 text-sky-300',
    title: 'text-sky-100',
  },
  drivers: {
    panel: 'border-violet-400/20',
    iconWrap: 'rounded-xl border border-violet-400/25 bg-violet-500/10 p-2 text-violet-300',
    title: 'text-violet-100',
  },
} as const;
