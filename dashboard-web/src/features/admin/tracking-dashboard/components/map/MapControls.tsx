import { memo, useCallback, useState, useEffect } from 'react';
import { useMap } from 'react-leaflet';
import { ZoomIn, ZoomOut, Crosshair, Layers, Maximize, Minimize, Compass, Map } from 'lucide-react';

interface MapControlsProps {
  onFitBounds?: () => void;
}

function MapControlsComponent({ onFitBounds }: MapControlsProps) {
  const map = useMap();
  const [layer, setLayer] = useState<'street' | 'satellite'>('street');
  const [isFullscreen, setIsFullscreen] = useState(false);

  useEffect(() => {
    const handleFullscreenChange = () => {
      const isFull = Boolean(document.fullscreenElement);
      setIsFullscreen(isFull);
      setTimeout(() => {
        map.invalidateSize();
      }, 100);
    };

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('mozfullscreenchange', handleFullscreenChange);

    return () => {
      document.removeEventListener('fullscreenchange', handleFullscreenChange);
      document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
      document.removeEventListener('mozfullscreenchange', handleFullscreenChange);
    };
  }, [map]);

  const handleZoomIn = useCallback(() => {
    map.zoomIn();
  }, [map]);

  const handleZoomOut = useCallback(() => {
    map.zoomOut();
  }, [map]);

  const handleCurrentLocation = useCallback(() => {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const { latitude, longitude } = position.coords;
          map.flyTo([latitude, longitude], 15, { duration: 1 });
        },
        (error) => {
          console.error('Error getting current location:', error);
        }
      );
    }
  }, [map]);

  const handleResetView = useCallback(() => {
    map.setView([40, -100], 4, { duration: 1 });
  }, [map]);

  const handleFitBounds = useCallback(() => {
    onFitBounds?.();
  }, [onFitBounds]);

  const handleFullscreen = useCallback(() => {
    const mapContainer = map.getContainer();
    const workspaceContainer =
      mapContainer.closest('.tracking-workspace') ||
      mapContainer.closest('.relative') ||
      mapContainer;

    if (document.fullscreenElement) {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      } else if ((document as any).webkitExitFullscreen) {
        (document as any).webkitExitFullscreen();
      } else if ((document as any).msExitFullscreen) {
        (document as any).msExitFullscreen();
      }
    } else {
      if (workspaceContainer.requestFullscreen) {
        workspaceContainer.requestFullscreen();
      } else if ((workspaceContainer as any).webkitRequestFullscreen) {
        (workspaceContainer as any).webkitRequestFullscreen();
      } else if ((workspaceContainer as any).msRequestFullscreen) {
        (workspaceContainer as any).msRequestFullscreen();
      }
    }
  }, [map]);

  const handleCompass = useCallback(() => {
    map.setBearing(0);
  }, [map]);

  const handleLayerSwitch = useCallback(() => {
    setLayer((prev) => (prev === 'street' ? 'satellite' : 'street'));
  }, []);

  return (
    <div className="absolute right-4 top-4 z-[1000] flex flex-col gap-2">
      {/* Zoom Controls */}
      <div className="flex flex-col gap-2 rounded-xl border border-white/10 bg-[#0E1320] p-2 shadow-lg">
        <button
          type="button"
          onClick={handleZoomIn}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-[#0A0E17] text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
          title="Zoom In"
        >
          <ZoomIn className="h-4 w-4" />
        </button>
        <button
          type="button"
          onClick={handleZoomOut}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-[#0A0E17] text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
          title="Zoom Out"
        >
          <ZoomOut className="h-4 w-4" />
        </button>
      </div>

      {/* Navigation Controls */}
      <div className="flex flex-col gap-2 rounded-xl border border-white/10 bg-[#0E1320] p-2 shadow-lg">
        <button
          type="button"
          onClick={handleCurrentLocation}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-[#0A0E17] text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
          title="Current Location"
        >
          <Crosshair className="h-4 w-4" />
        </button>
        <button
          type="button"
          onClick={handleCompass}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-[#0A0E17] text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
          title="Reset Compass"
        >
          <Compass className="h-4 w-4" />
        </button>
        <button
          type="button"
          onClick={handleFitBounds}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-[#0A0E17] text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
          title="Fit Fleet"
        >
          <Layers className="h-4 w-4" />
        </button>
        <button
          type="button"
          onClick={handleResetView}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-[#0A0E17] text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
          title="Reset View"
        >
          <Map className="h-4 w-4" />
        </button>
      </div>

      {/* View Controls */}
      <div className="flex flex-col gap-2 rounded-xl border border-white/10 bg-[#0E1320] p-2 shadow-lg">
        <button
          type="button"
          onClick={handleLayerSwitch}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-[#0A0E17] text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
          title={`Switch to ${layer === 'street' ? 'Satellite' : 'Street'} Layer`}
        >
          <Layers className="h-4 w-4" />
        </button>
        <button
          type="button"
          onClick={handleFullscreen}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-[#0A0E17] text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
          title={isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'}
        >
          {isFullscreen ? <Minimize className="h-4 w-4" /> : <Maximize className="h-4 w-4" />}
        </button>
      </div>
    </div>
  );
}

export default memo(MapControlsComponent);
