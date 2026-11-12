import { useState } from 'react';
import { Plus, Check } from 'lucide-react';
import { motion } from 'framer-motion';

interface PolicyItem {
  id: string;
  name: string;
  desc: string;
  active: boolean;
}

const initialPolicies: PolicyItem[] = [
  {
    id: 'pol-1',
    name: 'Session Timeout',
    desc: '60 Minutes',
    active: true,
  },
  {
    id: 'pol-2',
    name: 'API Rate Limit',
    desc: '10k requests/min',
    active: true,
  },
];

export default function PolicyEngine() {
  const [policies, setPolicies] = useState<PolicyItem[]>(initialPolicies);
  const [isDeploying, setIsDeploying] = useState(false);

  const togglePolicy = (id: string) => {
    setPolicies(policies.map((p) => (p.id === id ? { ...p, active: !p.active } : p)));
  };

  const handleDeploy = () => {
    setIsDeploying(true);
    setTimeout(() => setIsDeploying(false), 2000);
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between h-[360px] shadow-md select-none text-left">
      
      {/* Title Header */}
      <div className="flex justify-between items-center mb-5 flex-shrink-0">
        <h3 className="text-sm font-bold text-white font-sans tracking-tight">
          Policy Engine
        </h3>
        
        <span className="text-[8.5px] font-bold text-[#00E5FF] bg-[#00E5FF]/10 border border-[#00E5FF]/20 px-2 py-0.5 rounded-full uppercase tracking-wider font-mono">
          MFA Enforcements
        </span>
      </div>

      {/* Policies List Stack */}
      <div className="flex flex-col gap-4 overflow-y-auto pr-1 flex-grow scrollbar-none mb-4">
        {policies.map((policy) => (
          <div 
            key={policy.id}
            className="bg-white/2 border border-white/5 rounded-xl p-4 flex items-center justify-between gap-6"
          >
            {/* Left title and details */}
            <div className="flex flex-col">
              <span className="text-xs font-bold text-slate-200 font-sans">
                {policy.name}
              </span>
              <span className="text-[10px] text-slate-500 font-sans mt-0.5">
                {policy.desc}
              </span>
            </div>

            {/* Right Toggle */}
            <button
              onClick={() => togglePolicy(policy.id)}
              className={`w-8 h-4 rounded-full transition-colors flex items-center p-0.5 cursor-pointer ${
                policy.active ? 'bg-[#0250C5]' : 'bg-slate-800'
              }`}
              aria-label={`Toggle ${policy.name}`}
              aria-checked={policy.active}
              role="switch"
            >
              <motion.div 
                layout 
                className="w-3 h-3 rounded-full bg-white"
                animate={{ x: policy.active ? 14 : 0 }}
                transition={{ type: 'spring', stiffness: 500, damping: 30 }}
              />
            </button>
          </div>
        ))}
      </div>

      {/* Deploy Button */}
      <button
        onClick={handleDeploy}
        className="w-full py-3 bg-gradient-to-r from-[#0250C5] to-[#00A5D6] hover:brightness-105 active:scale-[0.98] transition-all duration-200 text-white text-xs font-bold rounded-xl flex items-center justify-center gap-2 cursor-pointer shadow-md select-none mt-auto"
      >
        {isDeploying ? (
          <>
            <Check className="w-4 h-4 text-emerald-400 animate-pulse" />
            <span>Policy Deployed</span>
          </>
        ) : (
          <>
            <Plus className="w-4 h-4" />
            <span>Deploy New Policy</span>
          </>
        )}
      </button>

    </div>
  );
}
