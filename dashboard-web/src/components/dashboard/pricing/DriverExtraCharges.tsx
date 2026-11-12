import { useState } from 'react';
import { Edit2, Save, X, Plus, User, Clock } from 'lucide-react';

interface DriverCharge {
  id: string;
  name: string;
  price: number;
  chargeType: 'flat' | 'percentage';
  status: 'active' | 'inactive';
  description: string;
}

interface DriverExtraChargesProps {
  charges: DriverCharge[];
  onUpdateCharge: (id: string, updates: Partial<DriverCharge>) => void;
  onCreateCharge: () => void;
}

export default function DriverExtraCharges({ 
  charges, 
  onUpdateCharge, 
  onCreateCharge 
}: DriverExtraChargesProps) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<Partial<DriverCharge>>({});

  const handleEdit = (charge: DriverCharge) => {
    setEditingId(charge.id);
    setEditForm(charge);
  };

  const handleCancel = () => {
    setEditingId(null);
    setEditForm({});
  };

  const handleSave = () => {
    if (editingId && editForm) {
      onUpdateCharge(editingId, editForm);
      setEditingId(null);
      setEditForm({});
    }
  };

  const handleChange = (field: keyof DriverCharge, value: any) => {
    setEditForm(prev => ({ ...prev, [field]: value }));
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <User className="h-4 w-4 text-cyan-400" />
          <h3 className="text-sm font-semibold text-white">Driver Extra Charges</h3>
        </div>
        <button
          type="button"
          onClick={onCreateCharge}
          className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
        >
          <Plus className="h-3 w-3" />
          Add Charge
        </button>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr className="border-b border-white/5">
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Charge Name
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Price
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Type
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Description
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
            {charges.map((charge) => (
              <tr key={charge.id} className={`border-b border-white/5 hover:bg-white/[0.02] ${charge.status === 'inactive' ? 'opacity-50' : ''}`}>
                {editingId === charge.id ? (
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
                        value={editForm.chargeType || 'flat'}
                        onChange={(e) => handleChange('chargeType', e.target.value)}
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      >
                        <option value="flat">Flat</option>
                        <option value="percentage">Percentage</option>
                      </select>
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="text"
                        value={editForm.description || ''}
                        onChange={(e) => handleChange('description', e.target.value)}
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
                        <div className="rounded-lg border border-blue-500/20 bg-blue-500/10 p-1 text-blue-400">
                          <Clock className="h-3 w-3" />
                        </div>
                        <span className="text-xs text-white">{charge.name}</span>
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      <span className="text-xs font-mono font-semibold text-cyan-400">
                        {charge.chargeType === 'flat' ? `$${charge.price.toFixed(2)}` : `${charge.price}%`}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <span
                        className={`inline-flex rounded-full border px-1.5 py-0.5 text-[8px] font-medium uppercase ${
                          charge.chargeType === 'flat'
                            ? 'border-cyan-500/20 bg-cyan-500/10 text-cyan-300'
                            : 'border-purple-500/20 bg-purple-500/10 text-purple-300'
                        }`}
                      >
                        {charge.chargeType}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <span className="text-xs text-slate-400">{charge.description}</span>
                    </td>
                    <td className="px-3 py-2">
                      <span
                        className={`inline-flex rounded-full border px-2 py-0.5 text-[9px] font-medium uppercase capitalize ${
                          charge.status === 'active'
                            ? 'border-green-500/20 bg-green-500/10 text-green-300'
                            : 'border-red-500/20 bg-red-500/10 text-red-300'
                        }`}
                      >
                        {charge.status}
                      </span>
                    </td>
                    <td className="px-3 py-2 text-right">
                      <button
                        type="button"
                        onClick={() => handleEdit(charge)}
                        className="rounded bg-cyan-500/10 p-1.5 text-cyan-400 transition-colors hover:bg-cyan-500/20"
                      >
                        <Edit2 className="h-3 w-3" />
                      </button>
                    </td>
                  </>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
