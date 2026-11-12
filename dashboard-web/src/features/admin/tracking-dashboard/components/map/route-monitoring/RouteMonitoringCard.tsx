import { memo } from 'react';
import type { RouteMonitoringData } from '../../../services/route/route-monitoring.service';

interface RouteMonitoringCardProps {
  data: RouteMonitoringData;
  isVisible: boolean;
  opacity?: number;
}

function RouteMonitoringCardComponent({
  data,
  isVisible,
  opacity = 1,
}: RouteMonitoringCardProps) {
  if (!isVisible || !data.routeData) return null;

  const { routeData, progressPercent, eta } = data;

  return (
    <div
      style={{
        position: 'absolute',
        top: '12px',
        left: '12px',
        zIndex: 1000,
        opacity,
        transition: 'opacity 0.3s ease-out',
        pointerEvents: 'none',
      }}
    >
      <div
        style={{
          background: 'rgba(14, 19, 32, 0.96)',
          border: '1px solid rgba(6, 182, 212, 0.25)',
          borderRadius: '10px',
          padding: '14px',
          minWidth: '240px',
          maxWidth: '280px',
          boxShadow: '0 2px 12px rgba(0, 0, 0, 0.3)',
          backdropFilter: 'blur(10px)',
        }}
      >
        {/* Header */}
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            marginBottom: '10px',
            paddingBottom: '10px',
            borderBottom: '1px solid rgba(255, 255, 255, 0.08)',
          }}
        >
          <div
            style={{
              width: '6px',
              height: '6px',
              borderRadius: '50%',
              background: '#06b6d4',
              boxShadow: '0 0 6px rgba(6, 182, 212, 0.4)',
            }}
          />
          <span
            style={{
              fontSize: '11px',
              fontWeight: '600',
              color: '#06b6d4',
              letterSpacing: '0.3px',
              textTransform: 'uppercase',
            }}
          >
            Route Monitoring
          </span>
        </div>

        {/* Shipment Number */}
        <div style={{ marginBottom: '10px' }}>
          <div style={{ fontSize: '8px', color: '#64748b', marginBottom: '1px' }}>
            Shipment Number
          </div>
          <div style={{ fontSize: '11px', color: '#fff', fontWeight: '600', fontFamily: 'Courier New, monospace' }}>
            {data.shipmentNumber}
          </div>
        </div>

        {/* Origin -> Destination */}
        <div style={{ marginBottom: '10px' }}>
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '6px',
              marginBottom: '3px',
            }}
          >
            <div
              style={{
                width: '14px',
                height: '14px',
                borderRadius: '3px',
                background: '#22c55e',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '9px',
                color: '#0E1320',
                fontWeight: '700',
              }}
            >
              A
            </div>
            <span style={{ fontSize: '10px', color: '#cbd5e1', fontWeight: '500' }}>
              {data.originCity}
            </span>
          </div>

          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '6px',
              marginLeft: '7px',
              marginBottom: '3px',
            }}
          >
            <div
              style={{
                width: '2px',
                height: '10px',
                background: 'linear-gradient(to bottom, #22c55e, #ef4444)',
                borderRadius: '1px',
              }}
            />
          </div>

          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '6px',
            }}
          >
            <div
              style={{
                width: '14px',
                height: '14px',
                borderRadius: '3px',
                background: '#ef4444',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '9px',
                color: '#0E1320',
                fontWeight: '700',
              }}
            >
              B
            </div>
            <span style={{ fontSize: '10px', color: '#cbd5e1', fontWeight: '500' }}>
              {data.destinationCity}
            </span>
          </div>
        </div>

        {/* Progress Bar */}
        <div style={{ marginBottom: '10px' }}>
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              marginBottom: '3px',
            }}
          >
            <span style={{ fontSize: '9px', color: '#64748b', fontWeight: '500' }}>
              Progress
            </span>
            <span style={{ fontSize: '9px', color: '#06b6d4', fontWeight: '600' }}>
              {progressPercent}%
            </span>
          </div>
          <div
            style={{
              height: '3px',
              background: 'rgba(255, 255, 255, 0.08)',
              borderRadius: '2px',
              overflow: 'hidden',
            }}
          >
            <div
              style={{
                height: '100%',
                width: `${progressPercent}%`,
                background: 'linear-gradient(90deg, #22c55e, #06b6d4)',
                borderRadius: '2px',
                transition: 'width 0.3s ease-out',
              }}
            />
          </div>
        </div>

        {/* Stats Grid */}
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: '1fr 1fr',
            gap: '6px',
          }}
        >
          <div>
            <div style={{ fontSize: '8px', color: '#64748b', marginBottom: '1px' }}>
              Distance Completed
            </div>
            <div style={{ fontSize: '10px', color: '#fff', fontWeight: '600' }}>
              {routeData.completedDistance.toFixed(1)} km
            </div>
          </div>
          <div>
            <div style={{ fontSize: '8px', color: '#64748b', marginBottom: '1px' }}>
              Distance Remaining
            </div>
            <div style={{ fontSize: '10px', color: '#fff', fontWeight: '600' }}>
              {routeData.remainingDistance.toFixed(1)} km
            </div>
          </div>
          <div>
            <div style={{ fontSize: '8px', color: '#64748b', marginBottom: '1px' }}>
              Total Distance
            </div>
            <div style={{ fontSize: '10px', color: '#fff', fontWeight: '600' }}>
              {routeData.totalDistance.toFixed(1)} km
            </div>
          </div>
          <div>
            <div style={{ fontSize: '8px', color: '#64748b', marginBottom: '1px' }}>
              ETA
            </div>
            <div style={{ fontSize: '10px', color: '#06b6d4', fontWeight: '600' }}>
              {eta}
            </div>
          </div>
        </div>

        {/* Speed */}
        <div
          style={{
            marginTop: '10px',
            paddingTop: '10px',
            borderTop: '1px solid rgba(255, 255, 255, 0.08)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
          }}
        >
          <div>
            <span style={{ fontSize: '9px', color: '#64748b', fontWeight: '500' }}>
              Current Speed
            </span>
            <span
              style={{
                fontSize: '13px',
                color: '#22c55e',
                fontWeight: '700',
                fontFamily: 'Courier New, monospace',
                marginLeft: '6px',
              }}
            >
              {data.currentSpeed.toFixed(0)} km/h
            </span>
          </div>
          <div>
            <span style={{ fontSize: '9px', color: '#64748b', fontWeight: '500' }}>
              Avg Speed
            </span>
            <span
              style={{
                fontSize: '13px',
                color: '#fff',
                fontWeight: '700',
                fontFamily: 'Courier New, monospace',
                marginLeft: '6px',
              }}
            >
              {data.averageSpeed.toFixed(0)} km/h
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}

export default memo(RouteMonitoringCardComponent);
