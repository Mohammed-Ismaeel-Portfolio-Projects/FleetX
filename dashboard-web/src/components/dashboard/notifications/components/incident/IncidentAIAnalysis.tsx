import { motion } from 'framer-motion';
import { Brain } from 'lucide-react';
import type { IncidentDetails } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentAIAnalysisProps {
  incident: IncidentDetails;
}

export default function IncidentAIAnalysis({ incident }: IncidentAIAnalysisProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-purple-500/20 bg-purple-500/5 p-4 space-y-4"
    >
      <div className="flex items-center gap-2">
        <Brain className="h-4 w-4 text-purple-400" />
        <h3 className="text-[10px] font-semibold uppercase tracking-wider text-purple-400">
          AI Analysis
        </h3>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <span className="text-[10px] text-slate-500 uppercase tracking-wider">Confidence</span>
          <p className="text-sm font-mono font-semibold text-purple-400">{incident.aiConfidence}</p>
        </div>
        <div>
          <span className="text-[10px] text-slate-500 uppercase tracking-wider">Prediction</span>
          <p className="text-sm text-slate-300">{incident.aiPrediction}</p>
        </div>
      </div>

      <div className="space-y-3">
        <div>
          <span className="text-[10px] text-slate-500 uppercase tracking-wider">Root Cause Analysis</span>
          <p className="text-sm text-slate-300 leading-relaxed mt-1">
            {incident.aiRootCauseAnalysis}
          </p>
        </div>

        <div>
          <span className="text-[10px] text-slate-500 uppercase tracking-wider">Expected Outcome</span>
          <p className="text-sm text-slate-300 leading-relaxed mt-1">
            {incident.aiExpectedOutcome}
          </p>
        </div>

        <div>
          <span className="text-[10px] text-slate-500 uppercase tracking-wider">Suggested Resolution</span>
          <p className="text-sm text-slate-300 leading-relaxed mt-1">
            {incident.aiSuggestedResolution}
          </p>
        </div>
      </div>
    </motion.div>
  );
}
