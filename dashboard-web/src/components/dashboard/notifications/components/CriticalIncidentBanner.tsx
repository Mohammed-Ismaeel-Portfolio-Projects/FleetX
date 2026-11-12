import { motion } from 'framer-motion';
import { notificationCenterService } from '../../../../features/dispatcher/notifications/services/notification-center.service';
import CriticalIncidentStatus from './CriticalIncidentStatus';
import CriticalIncidentDetails from './CriticalIncidentDetails';
import CriticalIncidentActions from './CriticalIncidentActions';

export default function CriticalIncidentBanner() {
  const incident = notificationCenterService.getCriticalIncident();

  const borderClass = incident.exists
    ? 'border-l-4 border-l-red-500'
    : 'border-l-4 border-l-green-500';

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className={`rounded-xl border border-white/5 bg-[#0E1320] p-6 shadow-sm ${borderClass}`}
    >
      <div className="flex flex-col gap-4">
        {/* Status Header */}
        <CriticalIncidentStatus incident={incident} />

        {/* Details Section */}
        <CriticalIncidentDetails incident={incident} />

        {/* Actions Section (only if incident exists) */}
        {incident.exists && <CriticalIncidentActions incident={incident} />}
      </div>
    </motion.div>
  );
}
