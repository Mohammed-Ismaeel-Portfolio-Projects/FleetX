import { motion } from 'framer-motion';
import { FileImage, MapPin, Wrench, FileText, Download } from 'lucide-react';
import type { IncidentAttachment } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentAttachmentsProps {
  attachments: IncidentAttachment[];
}

export default function IncidentAttachments({ attachments }: IncidentAttachmentsProps) {
  const getAttachmentIcon = (type: string) => {
    const icons = {
      photo: FileImage,
      gps: MapPin,
      diagnostic: Wrench,
      log: FileText,
    };
    return icons[type as keyof typeof icons] || FileText;
  };

  const getAttachmentColor = (type: string) => {
    const colors = {
      photo: 'text-cyan-400',
      gps: 'text-green-400',
      diagnostic: 'text-orange-400',
      log: 'text-slate-400',
    };
    return colors[type as keyof typeof colors] || colors.log;
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-4">
        Attachments
      </h3>

      <div className="space-y-2">
        {attachments.map((attachment) => {
          const Icon = getAttachmentIcon(attachment.type);
          const colorClass = getAttachmentColor(attachment.type);
          return (
            <div
              key={attachment.id}
              className="flex items-center gap-3 p-3 rounded-lg border border-white/5 bg-white/[0.02] hover:bg-white/[0.04] transition-all cursor-pointer"
            >
              <Icon className={`h-4 w-4 ${colorClass}`} />
              <div className="flex-1">
                <p className="text-xs font-semibold text-white">{attachment.name}</p>
                <p className="text-[10px] text-slate-500 font-mono">{attachment.timestamp}</p>
              </div>
              <button className="p-2 rounded-md border border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 transition-all">
                <Download className="h-4 w-4" />
              </button>
            </div>
          );
        })}

        {attachments.length === 0 && (
          <div className="text-center py-4">
            <p className="text-xs text-slate-500">No attachments</p>
          </div>
        )}
      </div>
    </motion.div>
  );
}
