import { Wrench, AlertTriangle, CheckCircle, Clock, DollarSign, Calendar } from 'lucide-react';
import type { Maintenance } from '../types/fleet-operations.types';

interface MaintenancePanelProps {
  maintenance: Maintenance[];
}

export default function MaintenancePanel({ maintenance }: MaintenancePanelProps) {
  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'Critical':
        return 'bg-red-500/10 border-red-500/20 text-red-400';
      case 'High':
        return 'bg-amber-500/10 border-amber-500/20 text-amber-400';
      case 'Medium':
        return 'bg-blue-500/10 border-blue-500/20 text-blue-400';
      case 'Low':
        return 'bg-slate-500/10 border-slate-500/20 text-slate-400';
      default:
        return 'bg-slate-500/10 border-slate-500/20 text-slate-400';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'Overdue':
        return <AlertTriangle className="w-4 h-4 text-red-400" />;
      case 'Upcoming':
        return <Clock className="w-4 h-4 text-amber-400" />;
      case 'Completed':
        return <CheckCircle className="w-4 h-4 text-emerald-400" />;
      default:
        return <Clock className="w-4 h-4 text-slate-400" />;
    }
  };

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'Oil Change':
        return <Wrench className="w-4 h-4" />;
      case 'Tire Service':
        return <Wrench className="w-4 h-4" />;
      case 'Brake Service':
        return <Wrench className="w-4 h-4" />;
      case 'Engine Repair':
        return <AlertTriangle className="w-4 h-4" />;
      case 'Inspection':
        return <CheckCircle className="w-4 h-4" />;
      default:
        return <Wrench className="w-4 h-4" />;
    }
  };

  if (maintenance.length === 0) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg p-8 flex flex-col items-center justify-center h-full">
        <Wrench className="w-16 h-16 text-slate-600 mb-4" />
        <p className="text-sm text-slate-400">Maintenance data unavailable</p>
        <p className="text-xs text-slate-500 mt-2 text-center">No maintenance backend is connected to this workspace</p>
      </div>
    );
  }

  const overdueMaintenance = maintenance.filter(m => m.status === 'Overdue');
  const upcomingMaintenance = maintenance.filter(m => m.status === 'Upcoming');

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      {/* Header */}
      <div className="p-5 border-b border-white/5">
        <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
          <Wrench className="w-4 h-4 text-[#00E5FF]" />
          Maintenance Panel
        </h2>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto p-5 space-y-5">
        {/* Overdue Maintenance */}
        {overdueMaintenance.length > 0 && (
          <div className="bg-red-500/5 border border-red-500/10 rounded-xl p-4">
            <h3 className="text-xs font-bold text-red-400 uppercase tracking-wider mb-3 flex items-center gap-2">
              <AlertTriangle className="w-3.5 h-3.5" />
              Overdue ({overdueMaintenance.length})
            </h3>
            <div className="space-y-3">
              {overdueMaintenance.map((item) => (
                <div key={item.id} className="bg-white/[0.02] border border-white/5 rounded-lg p-3">
                  <div className="flex items-start justify-between mb-2">
                    <div className="flex items-center gap-2">
                      {getTypeIcon(item.type)}
                      <span className="text-sm font-semibold text-white">{item.type}</span>
                    </div>
                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${getPriorityColor(item.priority)}`}>
                      {item.priority}
                    </span>
                  </div>
                  <p className="text-xs text-slate-400 mb-2">{item.description}</p>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-1 text-xs text-red-400">
                      <Calendar className="w-3 h-3" />
                      <span>Due: {item.dueDate}</span>
                    </div>
                    {item.estimatedCost && (
                      <div className="flex items-center gap-1 text-xs text-slate-400">
                        <DollarSign className="w-3 h-3" />
                        <span>${item.estimatedCost}</span>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Upcoming Maintenance */}
        {upcomingMaintenance.length > 0 && (
          <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
            <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Clock className="w-3.5 h-3.5" />
              Upcoming ({upcomingMaintenance.length})
            </h3>
            <div className="space-y-3">
              {upcomingMaintenance.map((item) => (
                <div key={item.id} className="bg-white/[0.02] border border-white/5 rounded-lg p-3">
                  <div className="flex items-start justify-between mb-2">
                    <div className="flex items-center gap-2">
                      {getTypeIcon(item.type)}
                      <span className="text-sm font-semibold text-white">{item.type}</span>
                    </div>
                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${getPriorityColor(item.priority)}`}>
                      {item.priority}
                    </span>
                  </div>
                  <p className="text-xs text-slate-400 mb-2">{item.description}</p>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-1 text-xs text-slate-400">
                      <Calendar className="w-3 h-3" />
                      <span>Due: {item.dueDate}</span>
                    </div>
                    {item.estimatedCost && (
                      <div className="flex items-center gap-1 text-xs text-slate-400">
                        <DollarSign className="w-3 h-3" />
                        <span>${item.estimatedCost}</span>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Maintenance Summary */}
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Maintenance Summary</h3>
          <div className="grid grid-cols-2 gap-3">
            <div className="text-center p-3 bg-white/[0.02] rounded-lg">
              <p className="text-2xl font-bold text-red-400">{overdueMaintenance.length}</p>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider">Overdue</p>
            </div>
            <div className="text-center p-3 bg-white/[0.02] rounded-lg">
              <p className="text-2xl font-bold text-amber-400">{upcomingMaintenance.length}</p>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider">Upcoming</p>
            </div>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Quick Actions</h3>
          <div className="space-y-2">
            <button className="w-full px-4 py-2.5 bg-[#0250C5] hover:bg-[#0244a5] text-white text-xs font-bold rounded-lg transition-colors">
              Schedule Maintenance
            </button>
            <button className="w-full px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold rounded-lg transition-colors">
              View Maintenance History
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
