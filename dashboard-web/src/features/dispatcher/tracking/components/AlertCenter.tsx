import { useEffect, useState } from 'react';
import { AlertTriangle, Radio } from 'lucide-react';
import { realtimeConnectionService } from '../../../../core/realtime/services/realtime-connection.service';
import type { RouteDeviationUpdateMessage } from '../../../../core/realtime/interfaces/tracking-subscription.interface';

interface DeviationAlertItem {
  id: string;
  vehicleId: string;
  shipmentId: string;
  meters: number;
  timestamp: string;
}

/**
 * Lightweight alert feed for route deviation broadcasts.
 * Full alert management remains a future sprint.
 */
export default function AlertCenter() {
  const [items, setItems] = useState<DeviationAlertItem[]>([]);

  useEffect(() => {
    let unsubscribe: (() => void) | null = null;
    let cancelled = false;

    const boot = async () => {
      try {
        await realtimeConnectionService.connect();
        if (cancelled) return;
        await realtimeConnectionService.subscribe('tracking.fleet');
        unsubscribe = realtimeConnectionService.on<RouteDeviationUpdateMessage>(
          'tracking.fleet',
          'tracking.route.deviation',
          (message) => {
            setItems((current) =>
              [
                {
                  id: `${message.vehicle_id}-${message.timestamp}`,
                  vehicleId: message.vehicle_id,
                  shipmentId: message.shipment_id,
                  meters: Math.round(message.deviation_distance || 0),
                  timestamp: message.timestamp,
                },
                ...current,
              ].slice(0, 8)
            );
          }
        );
      } catch (error) {
        console.warn('[AlertCenter] realtime unavailable', error);
      }
    };

    void boot();

    return () => {
      cancelled = true;
      unsubscribe?.();
      void realtimeConnectionService.unsubscribe('tracking.fleet');
    };
  }, []);

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg">
      <div className="p-5 border-b border-white/5">
        <div className="flex items-center justify-between mb-1">
          <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 text-red-400" />
            Alert Center
          </h2>
          <span className="text-xs font-semibold text-cyan-300/90 bg-cyan-500/10 px-2 py-1 rounded-full inline-flex items-center gap-1">
            <Radio className="w-3 h-3" />
            Live
          </span>
        </div>
        <p className="text-[11px] text-slate-500">Route deviation stream</p>
      </div>

      <div className="p-5 max-h-[400px] overflow-y-auto">
        {items.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-10 text-center">
            <AlertTriangle className="w-12 h-12 text-slate-600 mb-3" />
            <p className="text-sm font-semibold text-slate-400 mb-1">No active deviations</p>
            <p className="text-xs text-slate-500 max-w-sm">
              When a truck leaves its GraphHopper route, alerts appear here and as toasts on the map.
            </p>
          </div>
        ) : (
          <ul className="space-y-3">
            {items.map((item) => (
              <li
                key={item.id}
                className="rounded-xl border border-red-500/20 bg-red-500/5 px-3 py-3"
              >
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="text-xs font-semibold text-red-300">Route deviation</p>
                    <p className="text-[11px] text-slate-400 mt-1">
                      Vehicle {item.vehicleId.slice(0, 8)}… · Shipment {item.shipmentId.slice(0, 8)}…
                    </p>
                  </div>
                  <span className="text-[11px] font-bold text-amber-300">{item.meters} m</span>
                </div>
                <p className="text-[10px] text-slate-500 mt-2">
                  {new Date(item.timestamp).toLocaleTimeString()}
                </p>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
