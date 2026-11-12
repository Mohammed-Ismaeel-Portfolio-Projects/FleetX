import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Sparkles, Check, X, ShieldAlert } from 'lucide-react';
import { cn } from '../../../lib/utils';

interface PricingRecommendation {
  id: string;
  route: string;
  badge: string;
  badgeStyle: string;
  description: string;
  confidence: string;
  impact: string;
}

const initialRecommendations: PricingRecommendation[] = [
  {
    id: 'rec-1',
    route: 'East-Coast Corridor Congestion',
    badge: 'Dynamic Adjustment',
    badgeStyle: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
    description: 'Demand spike detected in NJ/NY hubs. Suggesting +12% base rate increase for next 6 hours.',
    confidence: '96%',
    impact: '+$12.4k',
  },
  {
    id: 'rec-2',
    route: 'Southwest Surplus Capacity',
    badge: 'Revenue Protection',
    badgeStyle: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
    description: 'Low volume in TX/AZ corridors. 5% discount recommendation to secure high-priority freight.',
    confidence: '88%',
    impact: '+$8.2k',
  },
  {
    id: 'rec-3',
    route: 'Chicago Distribution Shortage',
    badge: 'Surge Pricing',
    badgeStyle: 'bg-red-500/10 text-red-400 border-red-500/20',
    description: 'Capacity shortfall on Midwest lanes. AI recommends +8% surcharge to manage backlog.',
    confidence: '92%',
    impact: '+$15.6k',
  },
];

export default function PricingEngineWidget() {
  const [recs, setRecs] = useState<PricingRecommendation[]>(initialRecommendations);
  const [activeTab, setActiveTab] = useState<'live' | 'history'>('live');

  const handleApprove = (id: string) => {
    setRecs(recs.filter((r) => r.id !== id));
  };

  const handleReject = (id: string) => {
    setRecs(recs.filter((r) => r.id !== id));
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col h-[440px] shadow-md select-none text-left">
      
      {/* Header Panel */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 flex-shrink-0">
        <div className="flex items-center gap-2.5">
          <div className="w-6 h-6 rounded-md bg-[#00E5FF]/10 flex items-center justify-center border border-[#00E5FF]/20 text-[#00E5FF]">
            <Sparkles className="w-3.5 h-3.5" />
          </div>
          <div className="flex flex-col text-left">
            <h3 className="text-sm font-bold text-white font-sans tracking-tight">
              AI Pricing Engine
            </h3>
            <span className="text-[9px] text-slate-500 uppercase tracking-widest font-sans font-bold">
              Active Intelligence Monitoring
            </span>
          </div>
        </div>

        {/* Right Tab controls */}
        <div className="flex items-center bg-white/5 border border-white/5 rounded-lg p-0.5">
          <button
            onClick={() => setActiveTab('live')}
            className={cn(
              'px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-wider transition-all duration-200 cursor-pointer select-none font-sans',
              activeTab === 'live' ? 'bg-[#0250C5] text-white' : 'text-slate-400 hover:text-white'
            )}
          >
            Live Analytics
          </button>
          <button
            onClick={() => setActiveTab('history')}
            className={cn(
              'px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-wider transition-all duration-200 cursor-pointer select-none font-sans',
              activeTab === 'history' ? 'bg-[#0250C5] text-white' : 'text-slate-400 hover:text-white'
            )}
          >
            History
          </button>
        </div>
      </div>

      {/* Main Recs List */}
      <div className="flex-grow overflow-y-auto pr-1 flex flex-col gap-4 scrollbar-none">
        <AnimatePresence initial={false}>
          {recs.map((rec) => (
            <motion.div
              key={rec.id}
              initial={{ opacity: 0, height: 0, y: 15 }}
              animate={{ opacity: 1, height: 'auto', y: 0 }}
              exit={{ opacity: 0, height: 0, scale: 0.95 }}
              transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
              className="bg-white/2 border border-white/5 rounded-xl p-4 flex flex-col justify-between hover:bg-white/4 transition-colors duration-150 overflow-hidden"
            >
              {/* Header Title & Badges Row */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-2.5">
                <h4 className="text-xs font-bold text-slate-200 font-sans tracking-tight">
                  {rec.route}
                </h4>
                
                {/* AI Adjustment Badge */}
                <span className={cn(
                  'px-2 py-0.5 rounded text-[8.5px] font-bold uppercase tracking-wider border inline-block select-none font-sans self-start sm:self-auto',
                  rec.badgeStyle
                )}>
                  {rec.badge}
                </span>
              </div>

              {/* Description */}
              <p className="text-slate-400 text-[11px] font-normal leading-relaxed mb-4">
                {rec.description}
              </p>

              {/* Status details & buttons row */}
              <div className="flex flex-wrap items-center justify-between gap-4 mt-auto">
                <div className="flex gap-5">
                  {/* Confidence */}
                  <div className="flex flex-col">
                    <span className="text-[7.5px] font-bold text-slate-500 tracking-wider uppercase font-sans">
                      confidence
                    </span>
                    <span className="text-xs font-bold text-white font-mono mt-0.5">
                      {rec.confidence}
                    </span>
                  </div>
                  {/* Est Impact */}
                  <div className="flex flex-col">
                    <span className="text-[7.5px] font-bold text-slate-500 tracking-wider uppercase font-sans">
                      est. impact
                    </span>
                    <span className="text-xs font-bold text-emerald-400 font-sans mt-0.5">
                      {rec.impact}
                    </span>
                  </div>
                </div>

                {/* Actions Approve / Reject */}
                <div className="flex items-center gap-2">
                  <button 
                    onClick={() => handleReject(rec.id)}
                    className="p-2 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors border border-white/5 cursor-pointer"
                    title="Reject"
                    aria-label="Reject recommendation"
                  >
                    <X className="w-4 h-4" />
                  </button>
                  <button 
                    onClick={() => handleApprove(rec.id)}
                    className="px-4 py-2 bg-[#0250C5] hover:bg-[#0244a5] active:scale-[0.98] transition-all text-white text-[10px] font-bold rounded-lg uppercase tracking-wider flex items-center gap-1.5 cursor-pointer select-none"
                  >
                    <Check className="w-3.5 h-3.5" />
                    <span>Approve</span>
                  </button>
                </div>
              </div>
            </motion.div>
          ))}

          {recs.length === 0 && (
            <motion.div 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="flex-grow flex flex-col items-center justify-center text-center p-6 border border-dashed border-white/5 rounded-xl"
            >
              <ShieldAlert className="w-8 h-8 text-slate-600 mb-2" />
              <span className="text-xs text-slate-500 font-sans">No recommendations pending approval</span>
            </motion.div>
          )}
        </AnimatePresence>
      </div>

    </div>
  );
}
