import { memo } from 'react';
import type { RouteMonitoringData } from '../../../../features/admin/tracking-dashboard/services/route/route-monitoring.service';
import RouteStatusBadge from './RouteStatusBadge';
import type { DispatcherRouteStatus } from './route-status.types';

interface RouteMonitoringCardProps {
  data: RouteMonitoringData;
  isVisible: boolean;
  opacity?: number;
}

function SectionLabel({ children }: { children: string }) {
  return (
    <div
      style={{
        fontSize: 9,
        fontWeight: 700,
        letterSpacing: '0.08em',
        textTransform: 'uppercase',
        color: '#64748b',
        marginBottom: 8,
      }}
    >
      {children}
    </div>
  );
}

function MetaRow({ label, value, accent }: { label: string; value: string; accent?: string }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, marginBottom: 6 }}>
      <span style={{ fontSize: 10, color: '#94a3b8' }}>{label}</span>
      <span
        style={{
          fontSize: 11,
          color: accent || '#f8fafc',
          fontWeight: 600,
          textAlign: 'right',
          maxWidth: '62%',
          wordBreak: 'break-word',
        }}
      >
        {value}
      </span>
    </div>
  );
}

function RouteMonitoringCardComponent({
  data,
  isVisible,
  opacity = 1,
}: RouteMonitoringCardProps) {
  if (!isVisible || !data.routeData) return null;

  const { routeData, progressPercent, eta } = data;
  const status = (data.routeStatus || 'ON_ROUTE') as DispatcherRouteStatus;
  const coords = `${data.currentPosition.latitude.toFixed(5)}, ${data.currentPosition.longitude.toFixed(5)}`;
  const gpsAge = data.lastGpsUpdate
    ? new Date(data.lastGpsUpdate).toLocaleTimeString()
    : '—';

  return (
    <div
      style={{
        position: 'absolute',
        top: 12,
        left: 12,
        zIndex: 1000,
        opacity,
        transition: 'opacity 0.35s ease-out',
        pointerEvents: 'none',
      }}
    >
      <div
        style={{
          background: 'linear-gradient(165deg, rgba(14,19,32,0.97), rgba(10,16,32,0.96))',
          border: '1px solid rgba(6, 182, 212, 0.22)',
          borderRadius: 14,
          padding: 14,
          width: 300,
          maxWidth: 'min(300px, calc(100vw - 28px))',
          boxShadow: '0 12px 40px rgba(0, 0, 0, 0.45)',
          backdropFilter: 'blur(14px)',
          color: '#e2e8f0',
        }}
      >
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            gap: 8,
            marginBottom: 12,
            paddingBottom: 10,
            borderBottom: '1px solid rgba(255,255,255,0.08)',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <span
              style={{
                width: 8,
                height: 8,
                borderRadius: '50%',
                background: '#06b6d4',
                boxShadow: '0 0 10px rgba(6,182,212,0.55)',
              }}
            />
            <span
              style={{
                fontSize: 11,
                fontWeight: 700,
                color: '#67e8f9',
                letterSpacing: '0.04em',
                textTransform: 'uppercase',
              }}
            >
              Operations Monitor
            </span>
          </div>
          <RouteStatusBadge status={status} compact />
        </div>

        <SectionLabel>Shipment</SectionLabel>
        <MetaRow label="Shipment" value={data.shipmentNumber} accent="#67e8f9" />
        <MetaRow label="Origin" value={data.originCity} />
        <MetaRow label="Destination" value={data.destinationCity} />
        <MetaRow label="Assigned Truck" value={data.assignedTruck || '—'} />
        <MetaRow label="Driver" value={data.driverName || '—'} />

        <div
          style={{
            height: 1,
            background: 'rgba(255,255,255,0.08)',
            margin: '12px 0',
          }}
        />

        <SectionLabel>Route</SectionLabel>
        <div style={{ marginBottom: 10 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
            <span style={{ fontSize: 10, color: '#94a3b8' }}>Progress</span>
            <span style={{ fontSize: 11, color: '#67e8f9', fontWeight: 700 }}>{progressPercent}%</span>
          </div>
          <div
            style={{
              height: 5,
              background: 'rgba(255,255,255,0.08)',
              borderRadius: 999,
              overflow: 'hidden',
            }}
          >
            <div
              style={{
                height: '100%',
                width: `${progressPercent}%`,
                background:
                  status === 'DEVIATED'
                    ? 'linear-gradient(90deg, #ef4444, #f97316)'
                    : 'linear-gradient(90deg, #22c55e, #06b6d4)',
                borderRadius: 999,
                transition: 'width 0.4s ease-out, background 0.3s ease',
              }}
            />
          </div>
        </div>

        <div
          style={{
            display: 'grid',
            gridTemplateColumns: '1fr 1fr',
            gap: 8,
            marginBottom: 4,
          }}
        >
          <div>
            <div style={{ fontSize: 9, color: '#64748b', marginBottom: 2 }}>Remaining</div>
            <div style={{ fontSize: 12, fontWeight: 700, color: '#fff' }}>
              {routeData.remainingDistance.toFixed(1)} km
            </div>
          </div>
          <div>
            <div style={{ fontSize: 9, color: '#64748b', marginBottom: 2 }}>ETA</div>
            <div style={{ fontSize: 12, fontWeight: 700, color: '#67e8f9' }}>{eta}</div>
          </div>
          <div>
            <div style={{ fontSize: 9, color: '#64748b', marginBottom: 2 }}>Speed</div>
            <div style={{ fontSize: 12, fontWeight: 700, color: '#4ade80' }}>
              {data.currentSpeed.toFixed(0)} km/h
            </div>
          </div>
          <div>
            <div style={{ fontSize: 9, color: '#64748b', marginBottom: 2 }}>Status</div>
            <div style={{ fontSize: 11, fontWeight: 600, color: '#e2e8f0' }}>
              {data.movementStatus || '—'}
            </div>
          </div>
        </div>

        <div
          style={{
            height: 1,
            background: 'rgba(255,255,255,0.08)',
            margin: '12px 0',
          }}
        />

        <SectionLabel>Telemetry</SectionLabel>
        <MetaRow label="Last GPS" value={gpsAge} />
        <MetaRow label="Coordinates" value={coords} />
        {(data.deviationDistanceMeters != null && data.deviationDistanceMeters > 0) ||
        status === 'DEVIATED' ||
        status === 'REROUTING' ? (
          <MetaRow
            label="Deviation"
            value={`${Math.round(data.deviationDistanceMeters || 0)} m`}
            accent="#fca5a5"
          />
        ) : null}

        {data.isRerouting ? (
          <div
            style={{
              marginTop: 10,
              padding: '8px 10px',
              borderRadius: 8,
              background: 'rgba(245, 158, 11, 0.12)',
              border: '1px solid rgba(245, 158, 11, 0.3)',
              fontSize: 10,
              color: '#fde68a',
              fontWeight: 600,
            }}
          >
            Calculating new truck-compatible route…
          </div>
        ) : null}
      </div>
    </div>
  );
}

export default memo(RouteMonitoringCardComponent);
