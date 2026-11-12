import { memo } from 'react';
import { ROUTE_STATUS_META, type DispatcherRouteStatus } from './route-status.types';

interface RouteStatusBadgeProps {
  status: DispatcherRouteStatus;
  compact?: boolean;
}

function RouteStatusBadgeComponent({ status, compact = false }: RouteStatusBadgeProps) {
  const meta = ROUTE_STATUS_META[status] ?? ROUTE_STATUS_META.ON_ROUTE;

  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: compact ? 5 : 7,
        padding: compact ? '3px 8px' : '5px 10px',
        borderRadius: 999,
        background: meta.bg,
        border: `1px solid ${meta.border}`,
        color: meta.color,
        fontSize: compact ? 9 : 10,
        fontWeight: 700,
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        transition: 'background 0.25s ease, border-color 0.25s ease, color 0.25s ease',
      }}
    >
      <span
        style={{
          width: compact ? 6 : 7,
          height: compact ? 6 : 7,
          borderRadius: '50%',
          background: meta.dot,
          boxShadow: `0 0 8px ${meta.dot}`,
          animation:
            status === 'DEVIATED' || status === 'REROUTING'
              ? 'routeStatusPulse 1.2s ease-in-out infinite'
              : undefined,
        }}
      />
      {meta.label}
      <style>{`
        @keyframes routeStatusPulse {
          0%, 100% { opacity: 1; transform: scale(1); }
          50% { opacity: 0.45; transform: scale(0.85); }
        }
      `}</style>
    </span>
  );
}

export default memo(RouteStatusBadgeComponent);
