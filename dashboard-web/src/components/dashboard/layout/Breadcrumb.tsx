import { useLocation, Link } from 'react-router-dom';
import { ChevronRight, Home } from 'lucide-react';

// Helpers to format routes to readable breadcrumb labels
const routeLabels: Record<string, string> = {
  admin: 'Admin',
  fleet: 'Fleet Management',
  drivers: 'Drivers',
  shipments: 'Shipments',
  tracking: 'Tracking',
  logs: 'Tracking Logs',
  pricing: 'Pricing',
  'monthly-invoices': 'Monthly Invoices',
  analytics: 'Analytics',
  notifications: 'Notifications',
  security: 'Security',
};

export default function Breadcrumb() {
  const location = useLocation();
  const pathnames = location.pathname.split('/').filter((x) => x);

  return (
    <nav className="flex items-center gap-2 text-xs font-medium text-slate-500 select-none font-sans">
      {/* Root Home icon link */}
      <Link 
        to="/" 
        className="flex items-center gap-1.5 hover:text-slate-200 transition-colors duration-200"
        title="Go to Landing Page"
      >
        <Home className="w-3.5 h-3.5" />
        <span className="hidden sm:inline">FleetX</span>
      </Link>

      {pathnames.map((value, index) => {
        const to = `/${pathnames.slice(0, index + 1).join('/')}`;
        const isLast = index === pathnames.length - 1;
        
        // Match path or capitalize slug
        const label = routeLabels[value] || value.charAt(0).toUpperCase() + value.slice(1);

        return (
          <div key={to} className="flex items-center gap-2">
            <ChevronRight className="w-3 h-3 text-slate-600 flex-shrink-0" />
            {isLast ? (
              <span className="text-slate-200 font-semibold truncate max-w-[120px] sm:max-w-none">
                {label}
              </span>
            ) : (
              <Link
                to={to}
                className="hover:text-slate-200 transition-colors duration-200 truncate max-w-[100px] sm:max-w-none"
              >
                {label}
              </Link>
            )}
          </div>
        );
      })}
    </nav>
  );
}
