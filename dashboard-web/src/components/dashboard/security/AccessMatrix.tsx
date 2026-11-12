import { useState } from 'react';
import { Shield, Lock, Check } from 'lucide-react';
import { cn } from '../../../lib/utils';

interface PermissionRow {
  name: string;
  email: string;
  avatar: string;
  fleet: boolean;
  financials: boolean | 'locked';
  staffing: boolean | 'locked';
  overrides: boolean | 'locked';
}

const initialRows: PermissionRow[] = [
  {
    name: 'John Doe',
    email: 'j.doe@fleetx.ai',
    avatar: 'JD',
    fleet: true,
    financials: true,
    staffing: 'locked',
    overrides: 'locked',
  },
  {
    name: 'Sarah Admin',
    email: 's.admin@fleetx.ai',
    avatar: 'SA',
    fleet: true,
    financials: true,
    staffing: true,
    overrides: true,
  },
  {
    name: 'Bill Tracker',
    email: 'b.tracker@fleetx.ai',
    avatar: 'BT',
    fleet: true,
    financials: 'locked',
    staffing: 'locked',
    overrides: 'locked',
  },
];

export default function AccessMatrix() {
  const [rows, setRows] = useState<PermissionRow[]>(initialRows);

  const togglePermission = (rowIndex: number, field: 'fleet' | 'financials' | 'staffing' | 'overrides') => {
    const updated = [...rows];
    const val = updated[rowIndex][field];
    if (val !== 'locked') {
      updated[rowIndex][field] = !val as any;
      setRows(updated);
    }
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col shadow-md select-none w-full text-left">
      
      {/* Title & Legend row */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 flex-shrink-0">
        <div className="flex items-center gap-2">
          <Shield className="w-4.5 h-4.5 text-[#0250C5]" />
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Access Control Matrix
          </h3>
        </div>

        {/* Custom Legend */}
        <div className="flex items-center gap-4 text-[8.5px] font-bold text-slate-500 uppercase tracking-widest font-mono">
          <div className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded bg-blue-500/10 border border-blue-500/20 block" />
            <span>Read</span>
          </div>
          <div className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded bg-cyan-500/10 border border-[#00E5FF]/20 block" />
            <span>Write</span>
          </div>
          <div className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded bg-[#0250C5] block" />
            <span>Admin</span>
          </div>
        </div>
      </div>

      {/* Overflow table grid */}
      <div className="overflow-x-auto w-full scrollbar-none">
        <table className="w-full min-w-[700px] border-collapse">
          <thead>
            <tr className="border-b border-white/5 pb-3">
              <th className="text-[9px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[28%]">
                User Identity
              </th>
              <th className="text-[9px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[18%] text-center">
                Fleet Assets
              </th>
              <th className="text-[9px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[18%] text-center">
                Financials
              </th>
              <th className="text-[9px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[18%] text-center">
                Staffing
              </th>
              <th className="text-[9px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[18%] text-center">
                Overrides
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/2">
            {rows.map((row, rIdx) => (
              <tr 
                key={row.email}
                className="hover:bg-white/2 transition-colors duration-150 cursor-pointer"
              >
                {/* User avatar and email info */}
                <td className="py-3.5 flex items-center gap-3">
                  <div className="w-8 h-8 rounded-full bg-slate-800 border border-white/5 flex items-center justify-center text-[10px] font-bold text-slate-200">
                    {row.avatar}
                  </div>
                  <div className="flex flex-col">
                    <span className="text-xs font-bold text-slate-200 font-sans">
                      {row.name}
                    </span>
                    <span className="text-[10px] text-slate-500 font-sans mt-0.5">
                      {row.email}
                    </span>
                  </div>
                </td>

                {/* Fleet Checkbox */}
                <td className="py-3.5 text-center">
                  <button 
                    onClick={() => togglePermission(rIdx, 'fleet')}
                    className="inline-flex justify-center items-center cursor-pointer"
                    aria-label={`Toggle fleet assets for ${row.name}`}
                  >
                    <div className={cn(
                      'w-4.5 h-4.5 rounded border flex items-center justify-center transition-all',
                      row.fleet 
                        ? 'bg-[#0250C5] border-[#0250C5] text-white' 
                        : 'border-white/10 hover:border-white/20 bg-white/2'
                    )}>
                      {row.fleet && <Check className="w-3.5 h-3.5" />}
                    </div>
                  </button>
                </td>

                {/* Financials Checkbox */}
                <td className="py-3.5 text-center">
                  {row.financials === 'locked' ? (
                    <div className="inline-flex justify-center items-center text-slate-600">
                      <Lock className="w-3.5 h-3.5" />
                    </div>
                  ) : (
                    <button 
                      onClick={() => togglePermission(rIdx, 'financials')}
                      className="inline-flex justify-center items-center cursor-pointer"
                      aria-label={`Toggle financials for ${row.name}`}
                    >
                      <div className={cn(
                        'w-4.5 h-4.5 rounded border flex items-center justify-center transition-all',
                        row.financials 
                          ? 'bg-[#0250C5] border-[#0250C5] text-white' 
                          : 'border-white/10 hover:border-white/20 bg-white/2'
                      )}>
                        {row.financials && <Check className="w-3.5 h-3.5" />}
                      </div>
                    </button>
                  )}
                </td>

                {/* Staffing Checkbox */}
                <td className="py-3.5 text-center">
                  {row.staffing === 'locked' ? (
                    <div className="inline-flex justify-center items-center text-slate-600">
                      <Lock className="w-3.5 h-3.5" />
                    </div>
                  ) : (
                    <button 
                      onClick={() => togglePermission(rIdx, 'staffing')}
                      className="inline-flex justify-center items-center cursor-pointer"
                      aria-label={`Toggle staffing for ${row.name}`}
                    >
                      <div className={cn(
                        'w-4.5 h-4.5 rounded border flex items-center justify-center transition-all',
                        row.staffing === true 
                          ? 'bg-[#0250C5] border-[#0250C5] text-white' 
                          : 'border-white/10 hover:border-white/20 bg-white/2'
                      )}>
                        {row.staffing === true && <Check className="w-3.5 h-3.5" />}
                      </div>
                    </button>
                  )}
                </td>

                {/* Overrides Checkbox */}
                <td className="py-3.5 text-center">
                  {row.overrides === 'locked' ? (
                    <div className="inline-flex justify-center items-center text-slate-600">
                      <Lock className="w-3.5 h-3.5" />
                    </div>
                  ) : (
                    <button 
                      onClick={() => togglePermission(rIdx, 'overrides')}
                      className="inline-flex justify-center items-center cursor-pointer"
                      aria-label={`Toggle overrides for ${row.name}`}
                    >
                      <div className={cn(
                        'w-4.5 h-4.5 rounded border flex items-center justify-center transition-all',
                        row.overrides === true 
                          ? 'bg-[#0250C5] border-[#0250C5] text-white' 
                          : 'border-white/10 hover:border-white/20 bg-white/2'
                      )}>
                        {row.overrides === true && <Check className="w-3.5 h-3.5" />}
                      </div>
                    </button>
                  )}
                </td>

              </tr>
            ))}
          </tbody>
        </table>
      </div>

    </div>
  );
}
