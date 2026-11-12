import { useState } from 'react';
import { Navigate, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../core/auth';
import { authService } from '../../core/services/auth.service';
import { mapDashboardRole } from '../../core/auth/sessionLifecycle';
import { Sparkles, Eye, EyeOff, LogIn } from 'lucide-react';
import logoImg from '../../assets/logo/logo.png';

export default function Login() {
  const { login, user, isLoading: isSessionLoading } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    // Basic validation
    if (!email.trim() || !password.trim()) {
      setError('Please enter both email and password.');
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const response = await authService.login({ email, password });

      const role = mapDashboardRole(response.user.role.name);
      if (!role) {
        setError('This account cannot access the operations dashboard.');
        return;
      }

      login(
        {
          id: response.user.id,
          name: response.user.name,
          role,
        },
        response.access_token
      );

      const from = location.state?.from?.pathname || (role === 'admin' ? '/admin' : '/dispatcher/shipments');
      navigate(from, { replace: true });
    } catch (err: any) {
      if (err.response?.status === 401 || err.response?.status === 422) {
        setError('Invalid email or password.');
      } else {
        setError('An unexpected error occurred. Please try again.');
      }
      console.error('Login error:', err);
    } finally {
      setIsLoading(false);
    }
  };

  if (isSessionLoading) {
    return (
      <div className="flex h-screen w-full bg-[#0B0F19] text-white items-center justify-center font-sans">
        <div className="flex flex-col items-center gap-3">
          <span className="w-8 h-8 rounded-full border-2 border-t-transparent border-[#0250C5] animate-spin" />
          <span className="text-xs text-slate-500 font-bold uppercase tracking-widest font-sans">
            Authenticating Session...
          </span>
        </div>
      </div>
    );
  }

  if (user) {
    const home = user.role === 'admin' ? '/admin' : '/dispatcher/shipments';
    return <Navigate to={home} replace />;
  }

  return (
    <div className="min-h-screen w-full bg-[#0B0F19] text-white flex items-center justify-center font-sans p-6 select-none relative overflow-hidden">
      
      {/* Background radial glow */}
      <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] bg-[#0250C5]/10 rounded-full blur-[100px] pointer-events-none" />

      {/* Login Card */}
      <div className="bg-[#0E1320] border border-white/5 rounded-3xl p-8 max-w-md w-full shadow-2xl relative z-10 flex flex-col">
        
        {/* Brand Logo & Header */}
        <div className="flex flex-col items-center text-center mb-8">
          <div className="flex items-center gap-3 mb-4">
            <img 
              src={logoImg} 
              alt="FleetX Logo" 
              className="w-12 h-12 rounded-xl object-cover border border-white/10"
            />
            <div className="flex flex-col text-left">
              <span className="font-extrabold text-2xl tracking-tight text-white font-sans">
                Fleet<span className="text-[#0250C5]">X</span>
              </span>
              <span className="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-sans">
                Precision Logistics
              </span>
            </div>
          </div>
          <h2 className="text-sm font-bold text-white tracking-tight uppercase tracking-widest mt-2">
            Mission Portal Login
          </h2>
          <p className="text-slate-400 text-xs font-normal mt-2">
            Enter your credentials to access the node.
          </p>
        </div>

        {/* Error Message */}
        {error && (
          <div className="w-full p-3 mb-6 bg-red-500/10 border border-red-500/20 rounded-xl flex items-center">
            <p className="text-red-400 text-xs font-medium">{error}</p>
          </div>
        )}

        {/* Login Form */}
        <form onSubmit={handleSubmit} className="flex flex-col gap-5 w-full">
          
          {/* Email Field */}
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-semibold text-slate-400 ml-1">Email Address</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="admin@fleetx.com"
              disabled={isLoading}
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/50 transition-all disabled:opacity-50"
              required
            />
          </div>

          {/* Password Field */}
          <div className="flex flex-col gap-1.5">
            <div className="flex items-center justify-between ml-1">
              <label className="text-xs font-semibold text-slate-400">Password</label>
              <button 
                type="button" 
                className="text-[11px] text-[#00A5D6] hover:text-[#00E5FF] transition-colors"
                onClick={(e) => e.preventDefault()}
              >
                Forgot Password?
              </button>
            </div>
            <div className="relative">
              <input
                type={showPassword ? "text" : "password"}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                disabled={isLoading}
                className="w-full bg-white/5 border border-white/10 rounded-xl pl-4 pr-12 py-3.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/50 transition-all disabled:opacity-50"
                required
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
                disabled={isLoading}
              >
                {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

          {/* Remember Me */}
          <div className="flex items-center gap-2 mt-1 mb-2">
            <input
              type="checkbox"
              id="rememberMe"
              checked={rememberMe}
              onChange={(e) => setRememberMe(e.target.checked)}
              disabled={isLoading}
              className="w-3.5 h-3.5 rounded-sm border-white/10 bg-white/5 checked:bg-[#0250C5] checked:border-[#0250C5] focus:ring-offset-0 focus:ring-1 focus:ring-[#0250C5]/50 cursor-pointer disabled:opacity-50"
            />
            <label htmlFor="rememberMe" className="text-xs text-slate-400 cursor-pointer hover:text-slate-300 transition-colors">
              Remember me on this device
            </label>
          </div>

          {/* Submit Button */}
          <button
            type="submit"
            disabled={isLoading}
            className="w-full py-3.5 mt-2 bg-gradient-to-r from-[#0250C5] to-[#00A5D6] hover:brightness-105 active:scale-[0.98] transition-all text-white text-xs font-bold rounded-xl flex items-center justify-center gap-2.5 cursor-pointer shadow-md select-none disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
          >
            {isLoading ? (
              <>
                <span className="w-4 h-4 rounded-full border-2 border-t-transparent border-white animate-spin" />
                <span>Authenticating...</span>
              </>
            ) : (
              <>
                <LogIn className="w-4 h-4" />
                <span>Secure Login</span>
              </>
            )}
          </button>
        </form>

        {/* Footer Sparks tag */}
        <div className="flex items-center justify-center gap-1.5 mt-8 opacity-45">
          <Sparkles className="w-3.5 h-3.5 text-[#00E5FF]" />
          <span className="text-[9px] font-bold tracking-widest uppercase font-mono">
            Autonomous Routing Node
          </span>
        </div>

      </div>
    </div>
  );
}
