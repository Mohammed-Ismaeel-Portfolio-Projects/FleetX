import { useState } from 'react';
import { Edit2, Save, X, Plus, Settings, Wrench } from 'lucide-react';

interface Equipment {
  id: string;
  name: string;
  price: number;
  pricingType: 'flat' | 'daily' | 'hourly';
  status: 'active' | 'inactive';
  description: string;
}

interface EquipmentPricingProps {
  equipment: Equipment[];
  onUpdateEquipment: (id: string, updates: Partial<Equipment>) => void;
  onCreateEquipment: () => void;
}

export default function EquipmentPricing({ 
  equipment, 
  onUpdateEquipment, 
  onCreateEquipment 
}: EquipmentPricingProps) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<Partial<Equipment>>({});

  const handleEdit = (item: Equipment) => {
    setEditingId(item.id);
    setEditForm(item);
  };

  const handleCancel = () => {
    setEditingId(null);
    setEditForm({});
  };

  const handleSave = () => {
    if (editingId && editForm) {
      onUpdateEquipment(editingId, editForm);
      setEditingId(null);
      setEditForm({});
    }
  };

  const handleChange = (field: keyof Equipment, value: any) => {
    setEditForm(prev => ({ ...prev, [field]: value }));
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Wrench className="h-4 w-4 text-cyan-400" />
          <h3 className="text-sm font-semibold text-white">Equipment Pricing</h3>
        </div>
        <button
          type="button"
          onClick={onCreateEquipment}
          className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
        >
          <Plus className="h-3 w-3" />
          Add Equipment
        </button>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {equipment.map((item) => (
          <div
            key={item.id}
            className={`rounded-lg border border-white/5 bg-[#0A0E17] p-4 transition-all ${
              !item.status ? 'opacity-50' : ''
            }`}
          >
            {editingId === item.id ? (
              <div className="space-y-3">
                <div>
                  <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                    Equipment Name
                  </label>
                  <input
                    type="text"
                    value={editForm.name || ''}
                    onChange={(e) => handleChange('name', e.target.value)}
                    className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                  />
                </div>
                <div className="grid grid-cols-2 gap-2">
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
                      Pricing Type
                    </label>
                    <select
                      value={editForm.pricingType || 'flat'}
                      onChange={(e) => handleChange('pricingType', e.target.value)}
                      className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                    >
                      <option value="flat">Flat</option>
                      <option value="daily">Daily</option>
                      <option value="hourly">Hourly</option>
                    </select>
                  </div>
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
                  <div className="flex items-center gap-2">
                    <div className="rounded-lg border border-purple-500/20 bg-purple-500/10 p-1.5 text-purple-400">
                      <Settings className="h-4 w-4" />
                    </div>
                    <span className="text-xs font-semibold text-white">{item.name}</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => handleEdit(item)}
                    className="rounded bg-cyan-500/10 p-1.5 text-cyan-400 transition-colors hover:bg-cyan-500/20"
                  >
                    <Edit2 className="h-3 w-3" />
                  </button>
                </div>
                <div className="text-[10px] text-slate-400">{item.description}</div>
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-sm font-mono font-semibold text-cyan-400">
                      ${item.price.toFixed(2)}
                    </div>
                    <div className="text-[9px] text-slate-400 capitalize">{item.pricingType}</div>
                  </div>
                  <div className="flex items-center gap-2">
                    <div
                      className={`h-2 w-2 rounded-full ${
                        item.status === 'active' ? 'bg-green-400' : 'bg-slate-500'
                      }`}
                    />
                    <span className="text-[9px] text-slate-400 capitalize">{item.status}</span>
                  </div>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
