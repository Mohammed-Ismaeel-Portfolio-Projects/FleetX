import { memo, useState } from 'react';
import { Layers, Eye, EyeOff } from 'lucide-react';

interface LayerControlProps {
  onLayerChange: (layers: Record<string, boolean>) => void;
}

const defaultLayers = {
  vehicles: true,
  assignments: false,
  shipments: false,
  routes: false,
  geofences: false,
  clusters: false,
};

const layerConfig = {
  vehicles: { label: 'Vehicles', icon: '🚛', color: 'text-cyan-400' },
  assignments: { label: 'Assignments', icon: '📋', color: 'text-green-400' },
  shipments: { label: 'Shipments', icon: '📦', color: 'text-yellow-400' },
  routes: { label: 'Routes', icon: '🛤️', color: 'text-purple-400' },
  geofences: { label: 'Geofences', icon: '📍', color: 'text-orange-400' },
  clusters: { label: 'Clusters', icon: '🔵', color: 'text-blue-400' },
};

function LayerControlComponent({ onLayerChange }: LayerControlProps) {
  const [layers, setLayers] = useState<Record<string, boolean>>(defaultLayers);
  const [isOpen, setIsOpen] = useState(false);

  const toggleLayer = (layerId: string) => {
    const newLayers = {
      ...layers,
      [layerId]: !layers[layerId],
    };
    setLayers(newLayers);
    onLayerChange(newLayers);
  };

  return (
    <div className="absolute right-4 bottom-24 z-[1000]">
      <div className="relative">
        <button
          type="button"
          onClick={() => setIsOpen(!isOpen)}
          className="flex items-center gap-2 rounded-xl border border-white/10 bg-[#0A0E17]/90 backdrop-blur-sm px-4 py-2.5 text-sm font-medium text-slate-300 transition-all hover:bg-white/5 hover:text-white shadow-lg"
        >
          <Layers className="h-4 w-4" />
          Layers
        </button>

        {isOpen && (
          <div className="absolute bottom-full right-0 mb-2 w-56 rounded-xl border border-white/10 bg-[#0E1320]/95 backdrop-blur-sm shadow-2xl overflow-hidden">
            <div className="p-2 space-y-1">
              {Object.entries(layerConfig).map(([id, config]) => (
                <button
                  key={id}
                  type="button"
                  onClick={() => toggleLayer(id)}
                  className="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/5 transition-colors text-left"
                >
                  <span className="text-lg">{config.icon}</span>
                  <span className="flex-1 text-sm text-white">{config.label}</span>
                  {layers[id] ? (
                    <Eye className={`h-4 w-4 ${config.color}`} />
                  ) : (
                    <EyeOff className="h-4 w-4 text-slate-500" />
                  )}
                </button>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default memo(LayerControlComponent);
