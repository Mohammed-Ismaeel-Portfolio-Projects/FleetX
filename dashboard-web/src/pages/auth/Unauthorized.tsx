import { useNavigate } from 'react-router-dom';
import { ShieldAlert, ChevronLeft } from 'lucide-react';
import { useAuth } from '../../core/auth';

export default function Unauthorized() {
  const navigate = useNavigate();
  const { user } = useAuth();

  const handleReturn = () => {
    if (!user) {
      navigate('/login');
    } else if (user.role === 'admin') {
      navigate('/admin');
    } else if (user.role === 'dispatcher') {
      navigate('/dispatcher/shipments');
    }
  };

  return (
    <div className="min-h-screen w-full bg-[#0B0F19] text-white flex items-center justify-center font-sans p-6 select-none relative overflow-hidden">
      
      {/* Background glow */}
      <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[300px] h-[300px] bg-red-500/10 rounded-full blur-[80px] pointer-events-none" />

      {/* Alert Card */}
      <div className="bg-[#0E1320] border border-red-500/25 rounded-3xl p-8 max-w-sm w-full shadow-2xl relative z-10 text-center flex flex-col items-center">
        
        {/* Warning Icon */}
        <div className="w-12 h-12 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-500 mb-6 animate-pulse">
          <ShieldAlert className="w-6 h-6" />
        </div>

        {/* Info */}
        <h2 className="text-sm font-extrabold text-white font-sans tracking-tight mb-2 uppercase tracking-widest">
          Access Restriction
        </h2>
        <p className="text-slate-500 text-xs font-normal mb-8 font-sans leading-relaxed">
          Your identity level does not hold clearance credentials to enter this operational node.
        </p>

        {/* Back Button */}
        <button
          onClick={handleReturn}
          className="w-full py-3 bg-red-500/10 hover:bg-red-500/15 border border-red-500/30 active:scale-[0.98] transition-all text-red-400 text-xs font-bold rounded-xl flex items-center justify-center gap-1.5 cursor-pointer select-none"
        >
          <ChevronLeft className="w-4 h-4" />
          <span>Return to Dashboard</span>
        </button>

      </div>

    </div>
  );
}
