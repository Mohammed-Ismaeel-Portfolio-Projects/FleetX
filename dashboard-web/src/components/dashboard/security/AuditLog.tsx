import { ShieldAlert, Filter, Download } from 'lucide-react';
import { cn } from '../../../lib/utils';

interface AuditItem {
  id: string;
  time: string;
  user: string;
  action: 'PERMISSION_MOD' | 'BLOCK_IP' | 'DATA_EXPORT';
  description: string;
}

const auditLogs: AuditItem[] = [
  {
    id: 'aud-1',
    time: '2026-06-29 10:22:45',
    user: 'ADMIN: Sarah',
    action: 'PERMISSION_MOD',
    description: "Elevated 'Driver #401' to 'Senior Driver' status.",
  },
  {
    id: 'aud-2',
    time: '2026-06-29 10:05:12',
    user: 'SYSTEM_AI',
    action: 'BLOCK_IP',
    description: 'Auto-blocked IP 112.5.4.1 following failed MFA streak.',
  },
  {
    id: 'aud-3',
    time: '2026-06-29 09:48:15',
    user: 'DISPATCH: Mark',
    action: 'DATA_EXPORT',
    description: 'Bulk export of active routes for Q4 planning.',
  },
];

export default function AuditLog() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col shadow-md select-none w-full text-left">
      
      {/* Header and filter actions */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 flex-shrink-0">
        <div className="flex items-center gap-2">
          <ShieldAlert className="w-4.5 h-4.5 text-[#0250C5]" />
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Immutable Audit Log
          </h3>
        </div>

        {/* Buttons filters */}
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <button className="px-3 py-1.5 border border-white/5 bg-white/2 hover:bg-white/5 active:scale-[0.98] transition-all text-slate-300 text-[10px] font-bold rounded-lg uppercase tracking-wider flex items-center gap-1.5 cursor-pointer">
            <Filter className="w-3.5 h-3.5" />
            <span>Filter</span>
          </button>
          
          <button className="px-3.5 py-1.5 bg-[#00E5FF]/10 border border-[#00E5FF]/20 hover:bg-[#00E5FF]/15 active:scale-[0.98] transition-all text-[#00E5FF] text-[10px] font-bold rounded-lg uppercase tracking-wider flex items-center gap-1.5 cursor-pointer select-none">
            <Download className="w-3.5 h-3.5" />
            <span>Export CSV</span>
          </button>
        </div>
      </div>

      {/* Audit Log Rows list */}
      <div className="flex flex-col gap-3 w-full">
        {auditLogs.map((log) => (
          <div 
            key={log.id}
            className="bg-white/2 border border-white/5 rounded-xl p-4.5 flex flex-col md:flex-row justify-between items-stretch md:items-center gap-4 hover:bg-white/4 transition-colors duration-150 relative overflow-hidden"
          >
            {/* Timestamp and User */}
            <div className="flex flex-col md:flex-row md:items-center gap-4 md:gap-8 flex-grow">
              <span className="text-[10px] text-slate-500 font-mono font-bold leading-none max-w-[120px] shrink-0">
                {log.time}
              </span>
              
              <span className="text-xs font-bold text-slate-200 font-sans tracking-tight shrink-0 max-w-[150px]">
                {log.user}
              </span>

              {/* Action Badge */}
              <span className={cn(
                'px-2 py-0.5 rounded text-[8.5px] font-bold uppercase tracking-wider border font-sans w-fit leading-none',
                log.action === 'PERMISSION_MOD' && 'bg-blue-500/10 text-[#0250C5] border-blue-500/20',
                log.action === 'BLOCK_IP' && 'bg-red-500/10 text-red-400 border-red-500/20',
                log.action === 'DATA_EXPORT' && 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20'
              )}>
                {log.action}
              </span>

              {/* Description summary */}
              <p className="text-slate-400 text-[11.5px] leading-relaxed font-sans font-medium flex-grow">
                {log.description}
              </p>
            </div>

            {/* Right Action link */}
            <button className="text-[10px] font-bold text-slate-400 hover:text-white uppercase tracking-wider font-sans cursor-pointer transition-colors text-left md:text-right shrink-0">
              View Preview
            </button>
          </div>
        ))}
      </div>

    </div>
  );
}
