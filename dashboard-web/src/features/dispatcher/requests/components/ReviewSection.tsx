import type { ReactNode } from 'react';

interface ReviewSectionProps {
  title: string;
  subtitle?: string;
  children: ReactNode;
  className?: string;
}

export default function ReviewSection({ title, subtitle, children, className = '' }: ReviewSectionProps) {
  return (
    <section className={`rounded-2xl border border-white/5 bg-[#0E1320] p-5 ${className}`}>
      <div className="border-b border-white/5 pb-3">
        <h3 className="text-xs font-bold uppercase tracking-widest text-slate-500">{title}</h3>
        {subtitle ? <p className="mt-1 text-sm text-slate-400">{subtitle}</p> : null}
      </div>
      <div className="mt-4">{children}</div>
    </section>
  );
}

interface ReviewFieldProps {
  label: string;
  value?: ReactNode;
  hint?: string | null;
}

export function ReviewField({ label, value, hint }: ReviewFieldProps) {
  return (
    <div>
      <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{label}</p>
      <p className="mt-1 text-sm font-semibold text-slate-100">{value ?? 'Unavailable'}</p>
      {hint ? <p className="mt-0.5 text-xs text-slate-500">{hint}</p> : null}
    </div>
  );
}
