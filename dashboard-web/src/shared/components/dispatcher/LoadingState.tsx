import { PanelCard } from './PanelCard';

interface LoadingStateProps {
  title?: string;
  description?: string;
}

export function LoadingState({ title = 'Loading dispatcher data', description = 'Preparing the latest operational context.' }: LoadingStateProps) {
  return (
    <PanelCard compact className="flex min-h-[220px] flex-col items-center justify-center text-center">
      <div className="mb-4 h-10 w-10 animate-spin rounded-full border-4 border-cyan-400/30 border-t-cyan-300" />
      <h3 className="text-lg font-semibold text-white">{title}</h3>
      <p className="mt-2 text-sm text-slate-400">{description}</p>
    </PanelCard>
  );
}
