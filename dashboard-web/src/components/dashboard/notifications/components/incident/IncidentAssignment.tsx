import { motion } from 'framer-motion';
import { User, Users, Building2, Clock, ArrowRight } from 'lucide-react';
import type { IncidentAssignment } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentAssignmentProps {
  assignment: IncidentAssignment;
}

export default function IncidentAssignment({ assignment }: IncidentAssignmentProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-4">
        Assignment
      </h3>

      <div className="space-y-3 mb-4">
        <div className="flex items-center gap-3">
          <User className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Dispatcher</span>
            <p className="text-sm font-semibold text-white">{assignment.assignedDispatcher}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Building2 className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Manager</span>
            <p className="text-sm font-semibold text-white">{assignment.assignedManager}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Users className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Team</span>
            <p className="text-sm font-semibold text-white">{assignment.assignedTeam}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Clock className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Assigned</span>
            <p className="text-sm font-mono text-slate-300">{assignment.assignmentTime}</p>
          </div>
        </div>
      </div>

      <div className="pt-3 border-t border-white/5">
        <span className="text-[10px] text-slate-500 uppercase tracking-wider">Current Owner</span>
        <p className="text-sm font-semibold text-white mt-1">{assignment.currentOwner}</p>
      </div>

      {assignment.transferHistory.length > 0 && (
        <div className="pt-3 border-t border-white/5 mt-3">
          <span className="text-[10px] text-slate-500 uppercase tracking-wider">Transfer History</span>
          <div className="mt-2 space-y-2">
            {assignment.transferHistory.map((transfer) => (
              <div key={transfer.id} className="flex items-center gap-2 text-xs">
                <span className="text-slate-400">{transfer.from}</span>
                <ArrowRight className="h-3 w-3 text-slate-500" />
                <span className="text-white font-semibold">{transfer.to}</span>
                <span className="text-slate-500 font-mono">{transfer.timestamp}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </motion.div>
  );
}
