import { RefreshCw, Truck, Radio } from 'lucide-react';

interface OperationalAction {
  id: string;
  icon: typeof RefreshCw;
  iconColor: string;
  bgColor: string;
  title: string;
  description: string;
  hoverColor: string;
}

const actions: OperationalAction[] = [
  {
    id: 'op-1',
    icon: RefreshCw,
    iconColor: 'text-cyan-400',
    bgColor: 'bg-cyan-500/10 border-cyan-500/20',
    title: 'Trigger Manual Reroute',
    description: 'Override current AI pathways',
    hoverColor: 'hover:border-cyan-500/30 hover:bg-cyan-500/5',
  },
  {
    id: 'op-2',
    icon: Truck,
    iconColor: 'text-blue-400',
    bgColor: 'bg-blue-500/10 border-blue-500/20',
    title: 'Activate Backup Fleet',
    description: 'Deploy standby units from Hub 8',
    hoverColor: 'hover:border-blue-500/30 hover:bg-blue-500/5',
  },
  {
    id: 'op-3',
    icon: Radio,
    iconColor: 'text-red-400',
    bgColor: 'bg-red-500/10 border-red-500/20',
    title: 'Broadcast SOS Alert',
    description: 'Signal all units in critical zone',
    hoverColor: 'hover:border-red-500/30 hover:bg-red-500/5',
  },
];

export default function OperationalResponseWidget() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between h-[340px] shadow-md select-none text-left">
      
      {/* Title Header */}
      <h3 className="text-sm font-bold text-white font-sans tracking-tight mb-5 flex-shrink-0">
        Operational Response
      </h3>

      {/* Button triggers container */}
      <div className="flex flex-col gap-3.5 flex-grow">
        {actions.map((act) => (
          <button
            key={act.id}
            className={`w-full p-3.5 rounded-xl border border-white/5 bg-white/2 cursor-pointer flex items-center gap-3.5 transition-all duration-200 ${act.hoverColor} active:scale-[0.98] outline-none text-left`}
            aria-label={act.title}
          >
            {/* Icon */}
            <div className={`w-8.5 h-8.5 rounded-lg flex items-center justify-center flex-shrink-0 border ${act.bgColor}`}>
              <act.icon className={`w-4 h-4 ${act.iconColor}`} />
            </div>

            {/* Description */}
            <div className="flex flex-col">
              <span className="text-xs font-bold text-slate-200 font-sans">
                {act.title}
              </span>
              <span className="text-[10px] text-slate-500 font-sans font-medium mt-0.5 leading-none">
                {act.description}
              </span>
            </div>
          </button>
        ))}
      </div>

    </div>
  );
}
