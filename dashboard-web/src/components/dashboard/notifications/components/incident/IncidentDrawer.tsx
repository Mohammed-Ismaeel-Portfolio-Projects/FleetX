import { motion, AnimatePresence } from 'framer-motion';
import { notificationCenterService } from '../../../../../features/dispatcher/notifications/services/notification-center.service';
import IncidentHeader from './IncidentHeader';
import IncidentSummary from './IncidentSummary';
import IncidentAssets from './IncidentAssets';
import IncidentAIAnalysis from './IncidentAIAnalysis';
import IncidentBusinessImpact from './IncidentBusinessImpact';
import IncidentLifecycle from './IncidentLifecycle';
import IncidentAssignment from './IncidentAssignment';
import IncidentSLA from './IncidentSLA';
import IncidentPriorityMatrix from './IncidentPriorityMatrix';
import IncidentEscalationStatus from './IncidentEscalationStatus';
import IncidentResolutionSummary from './IncidentResolutionSummary';
import IncidentRecommendedActions from './IncidentRecommendedActions';
import IncidentTimeline from './IncidentTimeline';
import IncidentNotes from './IncidentNotes';
import IncidentAttachments from './IncidentAttachments';
import IncidentAuditTrail from './IncidentAuditTrail';
import IncidentFooter from './IncidentFooter';

interface IncidentDrawerProps {
  isOpen: boolean;
  alertId: string | null;
  onClose: () => void;
}

export default function IncidentDrawer({ isOpen, alertId, onClose }: IncidentDrawerProps) {
  if (!alertId) return null;

  const incident = notificationCenterService.getIncidentDetails(alertId);
  const lifecycle = notificationCenterService.getIncidentLifecycle();
  const assignment = notificationCenterService.getIncidentAssignment();
  const sla = notificationCenterService.getIncidentSLA();
  const priorityMatrix = notificationCenterService.getIncidentPriorityMatrix();
  const escalation = notificationCenterService.getIncidentEscalationStatus();
  const resolution = notificationCenterService.getIncidentResolutionSummary();

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/50 backdrop-blur-sm z-40"
          />

          {/* Drawer */}
          <motion.div
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ duration: 0.2, ease: [0.16, 1, 0.3, 1] }}
            className="fixed right-0 top-0 h-full w-full sm:w-[460px] lg:w-[540px] bg-[#0E1320] border-l border-white/10 z-50 shadow-2xl overflow-hidden"
          >
            <div className="h-full flex flex-col">
              {/* Scrollable Content */}
              <div className="flex-1 overflow-y-auto p-6 space-y-6">
                <IncidentHeader incident={incident} onClose={onClose} />
                <IncidentLifecycle lifecycle={lifecycle} />
                <IncidentSLA sla={sla} />
                <IncidentAssignment assignment={assignment} />
                <IncidentPriorityMatrix matrix={priorityMatrix} />
                <IncidentEscalationStatus escalation={escalation} />
                <IncidentSummary incident={incident} />
                <IncidentAssets incident={incident} />
                <IncidentAIAnalysis incident={incident} />
                <IncidentBusinessImpact incident={incident} />
                <IncidentRecommendedActions />
                <IncidentTimeline timeline={incident.timeline} />
                <IncidentNotes notes={incident.notes} />
                <IncidentAttachments attachments={incident.attachments} />
                <IncidentAuditTrail auditTrail={incident.auditTrail} />
                <IncidentResolutionSummary resolution={resolution} />
              </div>

              {/* Footer */}
              <div className="p-6 border-t border-white/10 bg-[#0E1320]">
                <IncidentFooter onClose={onClose} />
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
