import { Sparkles, AlertCircle, CheckCircle, Info } from 'lucide-react';

interface Recommendation {
  id: string;
  type: 'warning' | 'suggestion' | 'info';
  message: string;
  action?: string;
  impact?: string;
}

interface AIRecommendationProps {
  recommendations: Recommendation[];
}

export default function AIRecommendation({ recommendations }: AIRecommendationProps) {
  const getRecommendationIcon = (type: Recommendation['type']) => {
    switch (type) {
      case 'warning':
        return AlertCircle;
      case 'suggestion':
        return Sparkles;
      case 'info':
        return Info;
      default:
        return Info;
    }
  };

  const getRecommendationColor = (type: Recommendation['type']) => {
    switch (type) {
      case 'warning':
        return 'text-orange-400 bg-orange-500/10 border-orange-500/20';
      case 'suggestion':
        return 'text-purple-400 bg-purple-500/10 border-purple-500/20';
      case 'info':
        return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
      default:
        return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
    }
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center gap-2">
        <Sparkles className="h-4 w-4 text-cyan-400" />
        <h3 className="text-sm font-semibold text-white">AI Recommendations</h3>
      </div>

      <div className="space-y-3">
        {recommendations.map((rec) => {
          const Icon = getRecommendationIcon(rec.type);
          const colorClass = getRecommendationColor(rec.type);

          return (
            <div
              key={rec.id}
              className={`rounded-lg border ${colorClass} p-3`}
            >
              <div className="flex items-start gap-3">
                <div className={`mt-0.5 rounded-lg border border-white/10 bg-white/5 p-1.5 ${colorClass}`}>
                  <Icon className="h-3 w-3" />
                </div>
                <div className="flex-1">
                  <div className="text-xs text-white">{rec.message}</div>
                  {rec.action && (
                    <div className="mt-1 text-[10px] text-slate-400">
                      <span className="font-semibold text-slate-300">Action:</span> {rec.action}
                    </div>
                  )}
                  {rec.impact && (
                    <div className="mt-1 text-[10px] text-slate-400">
                      <span className="font-semibold text-slate-300">Impact:</span> {rec.impact}
                    </div>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      <div className="mt-4 rounded-lg border border-white/5 bg-[#0A0E17] p-3">
        <div className="flex items-center gap-2">
          <CheckCircle className="h-3 w-3 text-green-400" />
          <div className="text-[10px] text-slate-400">
            AI recommendations are based on market analysis, historical data, and pricing policies. Apply recommendations to optimize pricing strategy.
          </div>
        </div>
      </div>
    </div>
  );
}
