import { Sparkles } from 'lucide-react';
import { cn } from '../../../lib/utils';

interface AiDetectionCardProps {
  title: string;
  confidence: string;
  confidenceLabel?: string;
  description: string;
  suggestedAction: string;
  buttonText: string;
  onAction?: () => void;
  badgeStyle?: string;
}

export default function AiDetectionCard({
  title,
  confidence,
  confidenceLabel = 'Confidence',
  description,
  suggestedAction,
  buttonText,
  onAction,
  badgeStyle,
}: AiDetectionCardProps) {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between min-h-[260px] shadow-md select-none text-left relative overflow-hidden group">
      
      {/* Top Header Row (AI Icon + Confidence Badge) */}
      <div className="flex justify-between items-center gap-4 mb-4 flex-shrink-0">
        <div className="w-7 h-7 rounded-lg bg-cyan-500/10 flex items-center justify-center border border-cyan-500/20 text-cyan-400">
          <Sparkles className="w-4 h-4" />
        </div>

        {/* Confidence Badge */}
        <span className={cn(
          'px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider border font-sans',
          badgeStyle || 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20'
        )}>
          {confidence} {confidenceLabel}
        </span>
      </div>

      {/* Title & Description Stack */}
      <div className="flex-grow flex flex-col mb-4">
        <h4 className="text-xs font-bold text-slate-200 font-sans tracking-tight mb-2">
          {title}
        </h4>
        <p className="text-slate-400 text-[11px] font-normal leading-relaxed">
          {description}
        </p>
      </div>

      {/* Suggested Action Callout Box matching visual reference */}
      <div className="bg-blue-500/5 border border-blue-500/10 rounded-xl p-3.5 flex flex-col gap-1 mb-4">
        <span className="text-[7.5px] font-bold text-slate-500 tracking-wider uppercase font-sans">
          suggested action
        </span>
        <span className="text-[11px] font-bold text-cyan-400 font-sans leading-snug">
          {suggestedAction}
        </span>
      </div>

      {/* Execute Action Button */}
      <button
        onClick={onAction}
        className="w-full py-2.5 bg-white/5 border border-white/5 hover:bg-[#0250C5] hover:border-[#0250C5] hover:text-white active:scale-[0.98] transition-all text-slate-300 text-[10px] font-bold rounded-lg uppercase tracking-wider cursor-pointer select-none text-center font-sans"
      >
        {buttonText}
      </button>

    </div>
  );
}
