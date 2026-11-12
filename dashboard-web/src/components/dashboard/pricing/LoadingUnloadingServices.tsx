import { useState } from 'react';
import { Edit2, Save, X, Plus, Package, Truck, Wrench } from 'lucide-react';

interface LoadingUnloadingService {
  id: string;
  name: string;
  category: 'loading' | 'unloading' | 'equipment';
  pricingType: 'flat' | 'hourly';
  price: number;
  status: 'active' | 'inactive';
}

interface LoadingUnloadingServicesProps {
  services: LoadingUnloadingService[];
  onUpdateService: (id: string, updates: Partial<LoadingUnloadingService>) => void;
  onCreateService: () => void;
}

export default function LoadingUnloadingServices({ 
  services, 
  onUpdateService, 
  onCreateService 
}: LoadingUnloadingServicesProps) {
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

  const getCategoryIcon = (category: LoadingUnloadingService['category']) => {
    switch (category) {
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

  const getCategoryColor = (category: LoadingUnloadingService['category']) => {
    switch (category) {
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
        <div className="flex items-center gap-2">
          <Package className="h-4 w-4 text-cyan-400" />
          <h3 className="text-sm font-semibold text-white">Loading & Unloading Services</h3>
        </div>
        <button
          type="button"
          onClick={onCreateService}
          className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
        >
          <Plus className="h-3 w-3" />
          Add Service
        </button>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr className="border-b border-white/5">
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Service Name
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Category
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Pricing Type
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Price
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Status
              </th>
              <th className="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            {services.map((service) => {
              const Icon = getCategoryIcon(service.category);
              const categoryColor = getCategoryColor(service.category);

              return (
                <tr key={service.id} className="border-b border-white/5 hover:bg-white/[0.02]">
                  {editingId === service.id ? (
                    <>
                      <td className="px-3 py-2">
                        <input
                          type="text"
                          value={editForm.name || ''}
                          onChange={(e) => handleChange('name', e.target.value)}
                          className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <select
                          value={editForm.category || 'loading'}
                          onChange={(e) => handleChange('category', e.target.value)}
                          className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                        >
                          <option value="loading">Loading</option>
                          <option value="unloading">Unloading</option>
                          <option value="equipment">Equipment</option>
                        </select>
                      </td>
                      <td className="px-3 py-2">
                        <select
                          value={editForm.pricingType || 'flat'}
                          onChange={(e) => handleChange('pricingType', e.target.value)}
                          className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                        >
                          <option value="flat">Flat</option>
                          <option value="hourly">Hourly</option>
                        </select>
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="number"
                          step="0.01"
                          value={editForm.price || 0}
                          onChange={(e) => handleChange('price', parseFloat(e.target.value))}
                          className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <select
                          value={editForm.status || 'active'}
                          onChange={(e) => handleChange('status', e.target.value)}
                          className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                        >
                          <option value="active">Active</option>
                          <option value="inactive">Inactive</option>
                        </select>
                      </td>
                      <td className="px-3 py-2 text-right">
                        <div className="flex justify-end gap-1">
                          <button
                            type="button"
                            onClick={handleSave}
                            className="rounded bg-green-500/10 p-1.5 text-green-400 transition-colors hover:bg-green-500/20"
                          >
                            <Save className="h-3 w-3" />
                          </button>
                          <button
                            type="button"
                            onClick={handleCancel}
                            className="rounded bg-red-500/10 p-1.5 text-red-400 transition-colors hover:bg-red-500/20"
                          >
                            <X className="h-3 w-3" />
                          </button>
                        </div>
                      </td>
                    </>
                  ) : (
                    <>
                      <td className="px-3 py-2">
                        <div className="flex items-center gap-2">
                          <div className={`rounded-lg border p-1 ${categoryColor}`}>
                            <Icon className="h-3 w-3" />
                          </div>
                          <span className="text-xs text-white">{service.name}</span>
                        </div>
                      </td>
                      <td className="px-3 py-2">
                        <span className="inline-flex rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-[9px] font-medium uppercase text-slate-400">
                          {service.category}
                        </span>
                      </td>
                      <td className="px-3 py-2">
                        <span className="text-xs text-slate-400 capitalize">{service.pricingType}</span>
                      </td>
                      <td className="px-3 py-2">
                        <span className="text-xs font-mono font-semibold text-cyan-400">
                          ${service.price.toFixed(2)}
                        </span>
                      </td>
                      <td className="px-3 py-2">
                        <span
                          className={`inline-flex rounded-full border px-2 py-0.5 text-[9px] font-medium uppercase capitalize ${
                            service.status === 'active'
                              ? 'border-green-500/20 bg-green-500/10 text-green-300'
                              : 'border-red-500/20 bg-red-500/10 text-red-300'
                          }`}
                        >
                          {service.status}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-right">
                        <button
                          type="button"
                          onClick={() => handleEdit(service)}
                          className="rounded bg-cyan-500/10 p-1.5 text-cyan-400 transition-colors hover:bg-cyan-500/20"
                        >
                          <Edit2 className="h-3 w-3" />
                        </button>
                      </td>
                    </>
                  )}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
