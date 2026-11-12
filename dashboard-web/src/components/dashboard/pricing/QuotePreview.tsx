import { MapPin, Package, Calendar, User, FileText, Truck } from 'lucide-react';

interface QuoteData {
  origin: string;
  destination: string;
  shipmentSummary: string;
  estimatedCost: number;
  taxes: number;
  total: number;
  validity: string;
  preparedBy: string;
}

interface QuotePreviewProps {
  data: QuoteData;
}

export default function QuotePreview({ data }: QuotePreviewProps) {
  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center gap-2">
        <FileText className="h-4 w-4 text-cyan-400" />
        <h3 className="text-sm font-semibold text-white">Quote Preview</h3>
      </div>

      {/* FleetX Branding */}
      <div className="mb-4 rounded-lg border border-cyan-500/30 bg-cyan-500/5 p-3">
        <div className="flex items-center gap-3">
          <div className="rounded-lg border border-cyan-500/20 bg-cyan-500/10 p-2">
            <Truck className="h-5 w-5 text-cyan-400" />
          </div>
          <div>
            <div className="text-sm font-bold text-white">FleetX Logistics</div>
            <div className="text-[10px] text-slate-400">Enterprise Freight Solutions</div>
          </div>
        </div>
      </div>

      {/* Quote Details */}
      <div className="space-y-3">
        {/* Origin & Destination */}
        <div className="grid grid-cols-2 gap-3">
          <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
            <div className="mb-1 flex items-center gap-2">
              <MapPin className="h-3 w-3 text-green-400" />
              <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Origin
              </div>
            </div>
            <div className="text-xs text-white">{data.origin}</div>
          </div>
          <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
            <div className="mb-1 flex items-center gap-2">
              <MapPin className="h-3 w-3 text-red-400" />
              <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Destination
              </div>
            </div>
            <div className="text-xs text-white">{data.destination}</div>
          </div>
        </div>

        {/* Shipment Summary */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
          <div className="mb-1 flex items-center gap-2">
            <Package className="h-3 w-3 text-cyan-400" />
            <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Shipment Summary
            </div>
          </div>
          <div className="text-xs text-white">{data.shipmentSummary}</div>
        </div>

        {/* Cost Breakdown */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
          <div className="space-y-2">
            <div className="flex justify-between">
              <span className="text-xs text-slate-400">Estimated Cost</span>
              <span className="text-xs font-mono text-white">${data.estimatedCost.toFixed(2)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-slate-400">Taxes</span>
              <span className="text-xs font-mono text-white">${data.taxes.toFixed(2)}</span>
            </div>
            <div className="flex justify-between border-t border-white/10 pt-2">
              <span className="text-xs font-semibold text-white">Total</span>
              <span className="text-sm font-mono font-bold text-cyan-400">${data.total.toFixed(2)}</span>
            </div>
          </div>
        </div>

        {/* Validity & Prepared By */}
        <div className="grid grid-cols-2 gap-3">
          <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
            <div className="mb-1 flex items-center gap-2">
              <Calendar className="h-3 w-3 text-purple-400" />
              <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Validity
              </div>
            </div>
            <div className="text-xs text-white">{data.validity}</div>
          </div>
          <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
            <div className="mb-1 flex items-center gap-2">
              <User className="h-3 w-3 text-blue-400" />
              <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Prepared By
              </div>
            </div>
            <div className="text-xs text-white">{data.preparedBy}</div>
          </div>
        </div>
      </div>
    </div>
  );
}
