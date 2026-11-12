import { memo, useState } from 'react';
import { Edit2, Save, X, Plus, Package, Truck, Wrench } from 'lucide-react';
import type { LoadingUnloadingService } from '../types/pricing.types';

interface LoadingUnloadingServicesProps {
  services: LoadingUnloadingService[];
  onUpdateService: (id: string, updates: Partial<LoadingUnloadingService>) => void;
}

function LoadingUnloadingServicesComponent({ services, onUpdateService }: LoadingUnloadingServicesProps) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<Partial<LoadingUnloadingService>>({});

  const handleEdit = (service: LoadingUnloadingService) => {
    setEditingId(service.id);
    setEditForm(service);
  };

  const handleCancel = () => {
    setEditingId(null);
    setEditForm({});
  };

  const handleSave = () => {
    if (editingId && editForm) {
      onUpdateService(editingId, editForm);
      setEditingId(null);
      setEditForm({});
    }
  };

  const handleChange = (field: keyof LoadingUnloadingService, value: any) => {
    setEditForm(prev => ({ ...prev, [field]: value }));
  };

  const getServiceIcon = (type: LoadingUnloadingService['serviceType']) => {
    switch (type) {
      case 'loading':
        return Truck;
      case 'unloading':
        return Package;
      case 'equipment':
        return Wrench;
      default:
        return Package;
    }
  };

  const getServiceTypeColor = (type: LoadingUnloadingService['serviceType']) => {
    switch (type) {
      case 'loading':
        return 'text-green-400 bg-green-500/10 border-green-500/20';
      case 'unloading':
        return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
      case 'equipment':
        return 'text-purple-400 bg-purple-500/10 border-purple-500/20';
      default:
        return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
    }
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-white">Loading & Unloading Services</h3>
        <button
          type="button"
          className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
        >
          <Plus className="h-3 w-3" />
          Add Service
        </button>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {services.map((service) => {
          const Icon = getServiceIcon(service.serviceType);
          const typeColor = getServiceTypeColor(service.serviceType);

          return (
            <div
              key={service.id}
              className={`rounded-lg border border-white/5 bg-[#0A0E17] p-4 transition-all ${
                !service.enabled ? 'opacity-50' : ''
              }`}
            >
              {editingId === service.id ? (
                <div className="space-y-3">
                  <div>
                    <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                      Service Name
                    </label>
                    <input
                      type="text"
                      value={editForm.name || ''}
                      onChange={(e) => handleChange('name', e.target.value)}
                      className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                      Price
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      value={editForm.price || 0}
                      onChange={(e) => handleChange('price', parseFloat(e.target.value))}
                      className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                      Description
                    </label>
                    <textarea
                      value={editForm.description || ''}
                      onChange={(e) => handleChange('description', e.target.value)}
                      rows={2}
                      className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50 resize-none"
                    />
                  </div>
                  <div className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      id={`enabled-${service.id}`}
                      checked={editForm.enabled || false}
                      onChange={(e) => handleChange('enabled', e.target.checked)}
                      className="rounded border-white/10 bg-[#0E1320] text-cyan-500 focus:ring-cyan-500/50"
                    />
                    <label htmlFor={`enabled-${service.id}`} className="text-xs text-slate-400">
                      Enabled
                    </label>
                  </div>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={handleSave}
                      className="flex-1 rounded bg-green-500/10 px-3 py-1.5 text-xs font-medium text-green-400 transition-colors hover:bg-green-500/20"
                    >
                      <Save className="mr-1 inline h-3 w-3" />
                      Save
                    </button>
                    <button
                      type="button"
                      onClick={handleCancel}
                      className="flex-1 rounded bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/20"
                    >
                      <X className="mr-1 inline h-3 w-3" />
                      Cancel
                    </button>
                  </div>
                </div>
              ) : (
                <div className="space-y-2">
                  <div className="flex items-start justify-between">
                    <div className={`rounded-lg border p-1.5 ${typeColor}`}>
                      <Icon className="h-4 w-4" />
                    </div>
                    <button
                      type="button"
                      onClick={() => handleEdit(service)}
                      className="rounded bg-cyan-500/10 p-1.5 text-cyan-400 transition-colors hover:bg-cyan-500/20"
                    >
                      <Edit2 className="h-3 w-3" />
                    </button>
                  </div>
                  <div>
                    <div className="text-xs font-semibold text-white">{service.name}</div>
                    <div className="mt-1 text-[10px] text-slate-400">{service.description}</div>
                  </div>
                  <div className="flex items-center justify-between">
                    <div className="text-lg font-mono font-semibold text-cyan-400">
                      ${service.price.toFixed(2)}
                    </div>
                    <div className="flex items-center gap-2">
                      <div
                        className={`h-2 w-2 rounded-full ${
                          service.enabled ? 'bg-green-400' : 'bg-slate-500'
                        }`}
                      />
                      <span className="text-[10px] text-slate-400">
                        {service.enabled ? 'Active' : 'Disabled'}
                      </span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

export default memo(LoadingUnloadingServicesComponent);
