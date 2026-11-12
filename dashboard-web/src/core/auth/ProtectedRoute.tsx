import type { ReactNode } from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from './useAuth';

interface ProtectedRouteProps {
  allowedRoles?: ('admin' | 'dispatcher')[];
  allowedCompanyTypes?: string[];
  children?: ReactNode;
}

export default function ProtectedRoute({ allowedRoles, allowedCompanyTypes, children }: ProtectedRouteProps) {
  const { user, isLoading, sessionError, retrySession } = useAuth();

  if (isLoading) {
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

  if (sessionError) {
    return (
      <div className="flex h-screen w-full bg-[#0B0F19] text-white items-center justify-center font-sans p-6">
        <div className="max-w-md w-full bg-[#0E1320] border border-white/10 rounded-2xl p-6 flex flex-col gap-4 text-center">
          <h1 className="text-sm font-bold uppercase tracking-widest text-white">Session verification unavailable</h1>
          <p className="text-sm text-slate-400">{sessionError}</p>
          <button
            type="button"
            onClick={() => {
              void retrySession();
            }}
            className="rounded-xl bg-[#0250C5] px-4 py-3 text-xs font-bold uppercase tracking-widest text-white"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles && !allowedRoles.includes(user.role)) {
    return <Navigate to="/unauthorized" replace />;
  }

  if (allowedCompanyTypes && user.companyType && !allowedCompanyTypes.includes(user.companyType)) {
    return <Navigate to="/unauthorized" replace />;
  }

  return children ? <>{children}</> : <Outlet />;
}
