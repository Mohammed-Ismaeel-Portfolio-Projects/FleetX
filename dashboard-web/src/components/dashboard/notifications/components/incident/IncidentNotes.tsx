import { motion } from 'framer-motion';
import { MessageSquare, Plus } from 'lucide-react';
import type { IncidentNote } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentNotesProps {
  notes: IncidentNote[];
  onAddNote?: () => void;
}

export default function IncidentNotes({ notes }: IncidentNotesProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <MessageSquare className="h-4 w-4 text-slate-500" />
          <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Notes
          </h3>
        </div>
          <button
            type="button"
            disabled
            title="Adding notes is unavailable"
            className="flex items-center gap-1 px-2 py-1 rounded-md border border-white/10 bg-white/5 text-[10px] text-slate-500 cursor-not-allowed"
          >
            <Plus className="h-3 w-3" />
            <span>Add Note</span>
          </button>
      </div>

      <div className="space-y-3">
        {notes.map((note) => (
          <div key={note.id} className="rounded-lg border border-white/5 bg-white/[0.02] p-3">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-semibold text-white">{note.author}</span>
              <span className="text-[10px] text-slate-500 font-mono">{note.timestamp}</span>
            </div>
            <p className="text-xs text-slate-300 leading-relaxed">{note.content}</p>
          </div>
        ))}

        {notes.length === 0 && (
          <div className="text-center py-4">
            <p className="text-xs text-slate-500">No notes yet</p>
          </div>
        )}
      </div>
    </motion.div>
  );
}
