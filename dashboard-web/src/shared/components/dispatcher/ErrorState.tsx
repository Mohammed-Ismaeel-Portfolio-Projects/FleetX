import { AlertTriangle } from 'lucide-react';
import { PanelCard } from './PanelCard';

interface ErrorStateProps {
  title?: string;
  description?: string;
}

export function ErrorState({ title = 'Unable to load dispatcher data', description = 'Please refresh or try again in a moment.' }: ErrorStateProps) {
  return (
    <PanelCard compact className="flex min-h-[220px] flex-col items-center justify-center text-center">
      <div className="mb-4 rounded-full border border-rose-400/20 bg-rose-500/10 p-4 text-rose-300">
        <AlertTriangle className="h-6 w-6" />
      </div>
      <h3 className="text-lg font-semibold text-white">{title}</h3>
      <p className="mt-2 max-w-md text-sm text-slate-400">{description}</p>
    </PanelCard>
  );
}
