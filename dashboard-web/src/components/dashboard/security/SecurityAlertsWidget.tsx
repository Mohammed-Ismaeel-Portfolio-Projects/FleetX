import { ShieldAlert } from 'lucide-react';

interface SecurityAlert {
  id: string;
  category: string;
  time: string;
  title: string;
  description: string;
}

const activeAlerts: SecurityAlert[] = [
  {
    id: 'sec-1',
    category: 'UNAUTHORIZED ACCESS',
    time: '2m ago',
    title: 'Driver-ID #8822 login from Moscow, RU',
    description: 'Reported: Los Angeles, CA. Account auto-locked as a mitigation precaution.',
  },
  {
    id: 'sec-2',
    category: 'FAILED LOGINS',
    time: '15m ago',
    title: "5 failed attempts on 'admin_root'",
    description: 'Admin portal login failure. IP 112.5.4.1 blocked temporarily.',
  },
];

export default function SecurityAlertsWidget() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between h-[340px] shadow-md select-none text-left">
      
      {/* Header and badge */}
      <div className="flex justify-between items-center mb-5 flex-shrink-0">
        <div className="flex items-center gap-2">
          <ShieldAlert className="w-4.5 h-4.5 text-red-500" />
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Security Alerts
          </h3>
        </div>
        
        <span className="text-[8.5px] font-bold text-red-500 bg-red-500/10 border border-red-500/20 px-2 py-0.5 rounded-full uppercase tracking-wider animate-pulse">
          Critical
        </span>
      </div>

      {/* Incidents card list */}
      <div className="flex flex-col gap-4 overflow-y-auto pr-1 flex-grow scrollbar-none">
        {activeAlerts.map((alert) => (
          <div 
            key={alert.id}
            className="bg-[#1C0F16] border border-red-500/20 hover:border-red-500/35 rounded-xl p-4 flex flex-col gap-1.5 transition-all duration-150"
          >
            {/* Header tags */}
            <div className="flex justify-between items-center">
              <span className="text-[8px] font-extrabold text-red-400 uppercase tracking-widest font-mono">
                {alert.category}
              </span>
              <span className="text-[8.5px] font-bold text-slate-500 font-mono">
                {alert.time}
              </span>
            </div>

            {/* Title */}
            <h4 className="text-xs font-bold text-white font-sans tracking-tight leading-tight">
              {alert.title}
            </h4>

            {/* Description */}
            <p className="text-red-200/60 text-[10px] font-normal leading-relaxed">
              {alert.description}
            </p>
          </div>
        ))}
      </div>

    </div>
  );
}
