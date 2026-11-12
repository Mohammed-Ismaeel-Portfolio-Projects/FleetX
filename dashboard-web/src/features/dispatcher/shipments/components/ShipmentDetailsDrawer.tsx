import { motion, AnimatePresence } from 'framer-motion';
import { Link } from 'react-router-dom';
import { X, MapPin, User, Package, Clock, FileText } from 'lucide-react';
import type { Shipment } from '../types/shipment.types';
import StatusBadge from './ui/StatusBadge';
import PriorityBadge from './ui/PriorityBadge';
import ProgressIndicator from './ui/ProgressIndicator';
import ShipmentTimeline from './ShipmentTimeline';
import { getShipmentStatusProgress } from '../utils/shipmentStatusProgress';
import { DeliveryEvidenceConsole } from '../../../admin/shipments/components/DeliveryEvidenceConsole';

interface ShipmentDetailsDrawerProps {
  shipment: Shipment | null;
  isOpen: boolean;
  onClose: () => void;
}

export default function ShipmentDetailsDrawer({
  shipment,
  isOpen,
  onClose,
}: ShipmentDetailsDrawerProps) {
  if (!shipment) return null;

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50"
          />

          <motion.div
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ type: 'spring', damping: 30, stiffness: 300 }}
            className="fixed right-0 top-0 h-full w-full max-w-2xl bg-[#0E1320] border-l border-white/10 shadow-2xl z-50 overflow-y-auto"
          >
            <div className="flex flex-col h-full">
              <div className="flex items-center justify-between p-6 border-b border-white/5 sticky top-0 bg-[#0E1320] z-10">
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#0250C5] to-[#00E5FF] flex items-center justify-center">
                    <Package className="w-6 h-6 text-white" />
                  </div>
                  <div>
                    <h2 className="text-xl font-bold text-white font-sans">{shipment.shipmentNumber}</h2>
                    <p className="text-sm text-slate-400">{shipment.customer}</p>
                  </div>
                </div>
                <button
                  onClick={onClose}
                  className="p-2 hover:bg-white/5 rounded-lg transition-colors"
                  aria-label="Close shipment details"
                >
                  <X className="w-5 h-5 text-slate-400" />
                </button>
              </div>

              <div className="flex-1 overflow-y-auto p-6 space-y-6">
                <div className="flex items-center gap-4">
                  <StatusBadge status={shipment.status} size="lg" />
                  <PriorityBadge priority={shipment.priority} size="lg" />
                  {shipment.collaborationType === 'outbound_partner' ? (
                    <span className="rounded-lg border border-amber-400/30 bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-300">
                      Outbound Partner
                    </span>
                  ) : shipment.collaborationType === 'inbound_partner' ? (
                    <span className="rounded-lg border border-purple-400/30 bg-purple-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-purple-300">
                      Inbound Partner
                    </span>
                  ) : null}
                </div>

                {shipment.sourceRequest || shipment.shipmentOrderId ? (
                  <div className="bg-cyan-500/10 border border-cyan-400/20 rounded-xl p-4 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <FileText className="w-5 h-5 text-cyan-400" />
                      <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider text-cyan-300">Originating Request</p>
                        <p className="text-sm font-semibold text-white">
                          {shipment.sourceRequest?.requestNumber || 'Linked Request History'}
                        </p>
                      </div>
                    </div>
                    <Link
                      to={`/dispatcher/requests/${shipment.sourceRequest?.id || shipment.shipmentOrderId}`}
                      className="rounded-lg border border-cyan-400/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:bg-cyan-500/20 transition"
                    >
                      View Request History
                    </Link>
                  </div>
                ) : null}

                <div className="bg-white/[0.02] border border-white/5 rounded-xl p-5">
                  <h3 className="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <MapPin className="w-4 h-4" />
                    Route Information
                  </h3>
                  <div className="space-y-3">
                    <div className="flex items-start gap-3">
                      <div className="w-3 h-3 rounded-full bg-emerald-400 mt-1.5 flex-shrink-0" />
                      <div>
                        <p className="text-xs text-slate-500 uppercase tracking-wider mb-1">Pickup</p>
                        <p className="text-sm font-semibold text-white">{shipment.origin}</p>
                      </div>
                    </div>
                    <div className="ml-1.5 border-l-2 border-dashed border-white/10 h-4" />
                    <div className="flex items-start gap-3">
                      <div className="w-3 h-3 rounded-full bg-[#00E5FF] mt-1.5 flex-shrink-0" />
                      <div>
                        <p className="text-xs text-slate-500 uppercase tracking-wider mb-1">Delivery</p>
                        <p className="text-sm font-semibold text-white">{shipment.destination}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="bg-white/[0.02] border border-white/5 rounded-xl p-5">
                  <h3 className="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <User className="w-4 h-4" />
                    Assignment Information
                  </h3>
                  {shipment.driver || shipment.truck || shipment.trailer ? (
                    <div className="space-y-2">
                      <div className="flex items-center justify-between">
                        <span className="text-xs text-slate-400">Driver</span>
                        <span className="text-sm font-semibold text-white">{shipment.driver || 'Unavailable'}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-xs text-slate-400">Vehicle</span>
                        <span className="text-sm font-semibold text-white">{shipment.truck || 'Unavailable'}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-xs text-slate-400">Trailer</span>
                        <span className="text-sm font-semibold text-white">{shipment.trailer || 'Unavailable'}</span>
                      </div>
                    </div>
                  ) : (
                    <div className="text-center py-6">
                      <User className="w-12 h-12 text-slate-600 mx-auto mb-3" />
                      <p className="text-sm text-slate-400">Unavailable — no active assignment</p>
                    </div>
                  )}
                </div>

                <div className="bg-white/[0.02] border border-white/5 rounded-xl p-5">
                  <h3 className="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <Clock className="w-4 h-4" />
                    Lifecycle Progress
                  </h3>
                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <span className="text-xs text-slate-500">Status</span>
                      <span className="text-sm font-bold text-white">{shipment.status}</span>
                    </div>
                    {(() => {
                      const progress = getShipmentStatusProgress(shipment.status);
                      if (progress === null) {
                        return (
                          <p className="text-xs text-slate-500">Progress unavailable for this status</p>
                        );
                      }
                      return (
                        <>
                          <div className="flex items-center justify-between">
                            <span className="text-xs text-slate-500">Lifecycle progress</span>
                            <span className="text-sm font-bold text-white">{progress}%</span>
                          </div>
                          <ProgressIndicator
                            progress={progress}
                            size="md"
                            color="emerald"
                          />
                        </>
                      );
                    })()}
                    <div className="flex items-center justify-between mt-2">
                      <span className="text-xs text-slate-500">Planned Delivery</span>
                      <span className="text-sm font-bold text-white font-mono">
                        {shipment.plannedDeliveryDate || 'Unavailable'}
                      </span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-xs text-slate-500">Actual Pickup</span>
                      <span className="text-sm font-bold text-white font-mono">
                        {shipment.actualPickupDate || 'Unavailable'}
                      </span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-xs text-slate-500">Actual Delivery</span>
                      <span className="text-sm font-bold text-white font-mono">
                        {shipment.actualDeliveryDate || 'Unavailable'}
                      </span>
                    </div>
                  </div>
                </div>

                <div className="bg-white/[0.02] border border-white/5 rounded-xl p-5">
                  <h3 className="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <Clock className="w-4 h-4" />
                    Activity Timeline
                  </h3>
                  <ShipmentTimeline timeline={shipment.timeline} />
                </div>

                <div className="bg-white/[0.02] border border-white/5 rounded-xl p-5">
                  <h3 className="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <FileText className="w-4 h-4" />
                    Notes
                  </h3>
                  <p className="text-sm text-slate-300 whitespace-pre-wrap">
                    {shipment.notes || 'Unavailable'}
                  </p>
                </div>

                <DeliveryEvidenceConsole shipmentId={shipment.id} />
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
