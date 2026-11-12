import { Inbox } from 'lucide-react';
import { PanelCard } from './PanelCard';

interface EmptyStateProps {
  title: string;
  description: string;
  icon?: React.ReactNode;
}

export function EmptyState({ title, description, icon }: EmptyStateProps) {
  return (
    <PanelCard compact className="flex min-h-[220px] flex-col items-center justify-center text-center">
      <div className="mb-4 rounded-full border border-white/10 bg-white/5 p-4 text-slate-400">
        {icon ?? <Inbox className="h-6 w-6" />}
      </div>
      <h3 className="text-lg font-semibold text-white">{title}</h3>
      <p className="mt-2 max-w-md text-sm text-slate-400">{description}</p>
    </PanelCard>
  );
}
