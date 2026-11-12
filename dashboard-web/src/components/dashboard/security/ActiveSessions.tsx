import { useState } from 'react';
import { Terminal, ShieldAlert, Monitor, Smartphone, Globe, Cloud } from 'lucide-react';

interface SessionItem {
  id: string;
  name: string;
  device: string;
  ip: string;
  location: string;
  icon: typeof Monitor;
  iconColor: string;
  bgColor: string;
}

const initialSessions: SessionItem[] = [
  {
    id: 'sess-1',
    name: 'admin_float_01',
    device: 'Win 11 • Chrome 122',
    ip: '192.168.***.***',
    location: 'Austin, TX (HQ)',
    icon: Monitor,
    iconColor: 'text-blue-400',
    bgColor: 'bg-blue-500/10 border-blue-500/20',
  },
  {
    id: 'sess-2',
    name: 'driver_app_42',
    device: 'iOS 17 • FleetX Mobile',
    ip: '73.48.***.***',
    location: 'New York, NY',
    icon: Smartphone,
    iconColor: 'text-cyan-400',
    bgColor: 'bg-cyan-500/10 border-cyan-500/20',
  },
  {
    id: 'sess-3',
    name: 'dispatch_pad_09',
    device: 'Android 14 • Chrome',
    ip: '98.202.***.***',
    location: 'Seattle, WA',
    icon: Globe,
    iconColor: 'text-emerald-400',
    bgColor: 'bg-emerald-500/10 border-emerald-500/20',
  },
  {
    id: 'sess-4',
    name: 'api_service_bot',
    device: 'Linux • Axios Client',
    ip: '18.11.***.***',
    location: 'AWS Region',
    icon: Cloud,
    iconColor: 'text-[#0250C5]',
    bgColor: 'bg-blue-500/10 border-blue-500/20',
  },
];

export default function ActiveSessions() {
  const [sessions, setSessions] = useState<SessionItem[]>(initialSessions);

  const handleTerminate = (id: string) => {
    setSessions(sessions.filter((s) => s.id !== id));
  };

  const handleKillAll = () => {
    setSessions([]);
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col shadow-md select-none w-full text-left">
      
      {/* Header and terminate actions */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 flex-shrink-0">
        <div className="flex items-center gap-2">
          <Terminal className="w-4.5 h-4.5 text-[#0250C5]" />
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Active Terminal Sessions
          </h3>
          <span className="text-[10px] text-slate-500 font-sans ml-1">
            • {sessions.length} Connected
          </span>
        </div>

        {sessions.length > 0 && (
          <button 
            onClick={handleKillAll}
            className="px-4 py-2 border border-red-500/20 hover:bg-red-500/5 active:scale-[0.98] transition-all text-red-400 hover:text-red-300 text-[10px] font-bold rounded-lg uppercase tracking-wider cursor-pointer select-none"
          >
            Kill All External Sessions
          </button>
        )}
      </div>

      {/* Grid stack of session cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        {sessions.map((sess) => (
          <div 
            key={sess.id}
            className="bg-white/2 border border-white/5 hover:border-white/10 rounded-xl p-4.5 flex flex-col justify-between min-h-[140px] relative overflow-hidden transition-all duration-200"
          >
            {/* Top row (Icon + Name) */}
            <div className="flex items-start gap-3.5 mb-3">
              <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 border ${sess.bgColor}`}>
                <sess.icon className={`w-4 h-4 ${sess.iconColor}`} />
              </div>

              <div className="flex flex-col">
                <span className="text-xs font-bold text-slate-200 font-sans">
                  {sess.name}
                </span>
                <span className="text-[10px] text-slate-500 font-sans mt-0.5 leading-none">
                  {sess.device}
                </span>
              </div>
            </div>

            {/* Bottom details & terminate button */}
            <div className="flex justify-between items-end gap-4 mt-auto">
              <div className="flex flex-col gap-1 text-[10px] font-sans">
                <div className="flex gap-1.5 text-slate-500">
                  <span>IP:</span>
                  <span className="text-slate-300 font-mono">{sess.ip}</span>
                </div>
                <div className="flex gap-1.5 text-slate-500">
                  <span>Loc:</span>
                  <span className="text-slate-300 font-medium">{sess.location}</span>
                </div>
              </div>

              <button 
                onClick={() => handleTerminate(sess.id)}
                className="text-[9px] font-bold text-red-400 hover:text-red-300 uppercase tracking-widest font-sans cursor-pointer transition-colors"
                title="Terminate session"
              >
                Terminate
              </button>
            </div>
          </div>
        ))}

        {sessions.length === 0 && (
          <div className="col-span-full py-8 border border-dashed border-white/5 rounded-xl flex flex-col items-center justify-center text-center">
            <ShieldAlert className="w-8 h-8 text-slate-600 mb-2" />
            <span className="text-xs text-slate-500 font-sans">No active external terminal connections found.</span>
          </div>
        )}
      </div>

    </div>
  );
}
