import { memo, useMemo } from 'react';
import { RefreshCw, Truck, MapPin, ZoomIn } from 'lucide-react';

interface LiveStatusFooterProps {
  vehicles: any[];
  visibleCount: number;
  zoom: number;
  selectedVehicle: any | null;
  lastRefresh: Date;
  onRefresh: () => void;
}

function LiveStatusFooterComponent({
  vehicles,
  visibleCount,
  zoom,
  selectedVehicle,
  lastRefresh,
  onRefresh,
}: LiveStatusFooterProps) {
  const timeSinceRefresh = useMemo(() => {
    const seconds = Math.floor((Date.now() - lastRefresh.getTime()) / 1000);
    if (seconds < 60) return `${seconds}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    return `${Math.floor(seconds / 3600)}h ago`;
  }, [lastRefresh]);

  return (
    <div className="absolute bottom-4 left-4 right-4 z-[1000]">
      <div className="flex items-center justify-between rounded-xl border border-white/10 bg-[#0A0E17]/90 backdrop-blur-sm px-4 py-2.5 shadow-lg">
        <div className="flex items-center gap-6">
          {/* Last Refresh */}
          <div className="flex items-center gap-2">
            <RefreshCw className="h-4 w-4 text-cyan-400" />
            <span className="text-xs text-slate-400">
              Last refresh: <span className="text-white font-medium">{timeSinceRefresh}</span>
            </span>
            <button
              type="button"
              onClick={onRefresh}
              className="text-xs text-cyan-400 hover:text-cyan-300 transition-colors"
            >
              Refresh
            </button>
          </div>

          {/* Vehicles Visible */}
          <div className="flex items-center gap-2">
            <Truck className="h-4 w-4 text-cyan-400" />
            <span className="text-xs text-slate-400">
              Visible: <span className="text-white font-medium">{visibleCount}</span> / <span className="text-white">{vehicles.length}</span>
            </span>
          </div>

          {/* Zoom Level */}
          <div className="flex items-center gap-2">
            <ZoomIn className="h-4 w-4 text-cyan-400" />
            <span className="text-xs text-slate-400">
              Zoom: <span className="text-white font-medium">{zoom.toFixed(1)}</span>
            </span>
          </div>
        </div>

        {/* Selected Vehicle */}
        {selectedVehicle && (
          <div className="flex items-center gap-2">
            <MapPin className="h-4 w-4 text-cyan-400" />
            <span className="text-xs text-slate-400">
              Selected: <span className="text-white font-medium">{selectedVehicle.fleetNumber}</span>
            </span>
          </div>
        )}
      </div>
    </div>
  );
}

export default memo(LiveStatusFooterComponent);
