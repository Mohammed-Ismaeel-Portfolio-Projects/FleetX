import { motion } from 'framer-motion';
import { AlertTriangle, Activity, CheckCircle, Clock, Brain, Server, Target, Shield } from 'lucide-react';
import { notificationCenterService } from '../../../features/dispatcher/notifications/services/notification-center.service';
import NotificationExecutiveCard from './NotificationExecutiveCard';

export default function NotificationExecutiveKPIs() {
  const kpis = notificationCenterService.getExecutiveKPIs();

  const iconMap = {
    'critical-alerts': AlertTriangle,
    'open-incidents': Activity,
    'resolved-today': CheckCircle,
    'avg-response-time': Clock,
    'ai-prediction-accuracy': Brain,
    'system-availability': Server,
    'avg-resolution-time': Target,
    'false-positive-rate': Shield,
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
    >
      {kpis.map((kpi) => {
        const Icon = iconMap[kpi.id as keyof typeof iconMap] || Activity;
        return (
          <NotificationExecutiveCard key={kpi.id} kpi={kpi} icon={Icon} />
        );
      })}
    </motion.div>
  );
}
