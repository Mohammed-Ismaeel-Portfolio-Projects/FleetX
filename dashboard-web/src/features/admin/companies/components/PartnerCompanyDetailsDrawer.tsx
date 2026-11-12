import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  X,
  Building2,
  Truck,
  User,
  Box,
  Package,
  Mail,
  Phone,
  Globe,
  MapPin,
  Calendar,
  ShieldCheck,
  CheckCircle2,
  XCircle,
  RefreshCw,
  ExternalLink,
  Layers
} from 'lucide-react';
import { axiosClient } from '../../../../core/api';
import type { Company } from '../types/company.types';

interface PartnerCompanyDetailsDrawerProps {
  company: Company | null;
  isOpen: boolean;
  onClose: () => void;
  onStatusToggle?: (company: Company) => void;
}

type TabType = 'overview' | 'vehicles' | 'drivers' | 'trailers' | 'assignments';

interface VehicleItem {
  id: string;
  operational_code: string;
  plate_number: string;
  fleet_number?: string;
  manufacturer?: string;
  model?: string;
  status?: { name: string };
  is_active: boolean;
}

interface DriverItem {
  id: string;
  employee_number: string;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  license_class?: string;
  status?: { name: string };
  is_active: boolean;
}

interface TrailerItem {
  id: string;
  trailer_code: string;
  plate_number?: string;
  trailer_type?: string;
  status?: { name: string };
  is_active: boolean;
}

interface ShipmentItem {
  id: string;
  shipment_number: string;
  reference_number?: string;
  status?: { name: string };
  priority?: string;
  planned_pickup_date?: string;
  planned_delivery_date?: string;
  customer_company?: { company_name: string };
}

export default function PartnerCompanyDetailsDrawer({
  company,
  isOpen,
  onClose,
  onStatusToggle,
}: PartnerCompanyDetailsDrawerProps) {
  const [activeTab, setActiveTab] = useState<TabType>('overview');

  // Partner Fleet Resources State
  const [vehicles, setVehicles] = useState<VehicleItem[]>([]);
  const [drivers, setDrivers] = useState<DriverItem[]>([]);
  const [trailers, setTrailers] = useState<TrailerItem[]>([]);
  const [assignments, setAssignments] = useState<ShipmentItem[]>([]);

  const [loadingFleet, setLoadingFleet] = useState<boolean>(false);
  const [fleetError, setFleetError] = useState<string | null>(null);

  useEffect(() => {
    if (!isOpen || !company?.id) return;

    setActiveTab('overview');
    loadPartnerFleetData(company.id);
  }, [isOpen, company?.id]);

  const loadPartnerFleetData = async (companyId: string) => {
    setLoadingFleet(true);
    setFleetError(null);

    try {
      const [vehiclesRes, driversRes, trailersRes, shipmentsRes] = await Promise.allSettled([
        axiosClient.get(`/vehicles`, { params: { company_id: companyId, per_page: 50 } }),
        axiosClient.get(`/drivers`, { params: { company_id: companyId, per_page: 50 } }),
        axiosClient.get(`/trailers`, { params: { company_id: companyId, per_page: 50 } }),
        axiosClient.get(`/shipments`, { params: { executing_company_id: companyId, per_page: 50 } }),
      ]);

      if (vehiclesRes.status === 'fulfilled') {
        const data = vehiclesRes.value.data?.data?.data || vehiclesRes.value.data?.data || [];
        setVehicles(Array.isArray(data) ? data : []);
      }
      if (driversRes.status === 'fulfilled') {
        const data = driversRes.value.data?.data?.data || driversRes.value.data?.data || [];
        setDrivers(Array.isArray(data) ? data : []);
      }
      if (trailersRes.status === 'fulfilled') {
        const data = trailersRes.value.data?.data?.data || trailersRes.value.data?.data || [];
        setTrailers(Array.isArray(data) ? data : []);
      }
      if (shipmentsRes.status === 'fulfilled') {
        const data = shipmentsRes.value.data?.data?.data || shipmentsRes.value.data?.data || [];
        setAssignments(Array.isArray(data) ? data : []);
      }
    } catch (err) {
      setFleetError('Failed to load partner fleet resources.');
    } finally {
      setLoadingFleet(false);
    }
  };

  if (!isOpen || !company) return null;

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex justify-end overflow-hidden bg-black/60 backdrop-blur-sm">
        {/* Overlay dismiss click */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          onClick={onClose}
          className="absolute inset-0 cursor-pointer"
        />

        {/* Slide-over panel */}
        <motion.div
          initial={{ x: '100%' }}
          animate={{ x: 0 }}
          exit={{ x: '100%' }}
          transition={{ type: 'spring', damping: 25, stiffness: 200 }}
          className="relative z-10 flex h-full w-full max-w-2xl flex-col bg-[#0E1320] border-l border-white/10 text-white shadow-2xl"
        >
          {/* Header */}
          <div className="flex items-center justify-between border-b border-white/10 px-6 py-5 bg-[#090D16]">
            <div className="flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#0250C5]/10 border border-[#0250C5]/30 text-[#0250C5]">
                <ShieldCheck className="h-6 w-6" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="text-lg font-bold text-white tracking-tight">{company.company_name}</h2>
                  <span className="inline-flex items-center rounded-md bg-[#0250C5]/15 border border-[#0250C5]/30 px-2 py-0.5 text-[10px] font-bold text-[#0250C5] uppercase">
                    {company.company_type || 'PARTNER_CARRIER'}
                  </span>
                </div>
                <p className="text-xs font-medium text-slate-400 font-mono mt-0.5">
                  Code: <span className="text-slate-200">{company.company_code}</span> • {company.city}, {company.country}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-3">
              {onStatusToggle && (
                <button
                  onClick={() => onStatusToggle(company)}
                  className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all cursor-pointer ${
                    company.is_active
                      ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20'
                      : 'bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500/20'
                  }`}
                >
                  {company.is_active ? <CheckCircle2 className="h-3.5 w-3.5" /> : <XCircle className="h-3.5 w-3.5" />}
                  {company.is_active ? 'Active' : 'Inactive'}
                </button>
              )}

              <button
                onClick={onClose}
                className="rounded-xl border border-white/10 p-2 text-slate-400 hover:bg-white/5 hover:text-white transition-colors cursor-pointer"
                aria-label="Close drawer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
          </div>

          {/* Navigation Tabs */}
          <div className="flex border-b border-white/10 px-6 bg-[#090D16]/50 gap-1 overflow-x-auto scrollbar-none">
            <button
              onClick={() => setActiveTab('overview')}
              className={`flex items-center gap-2 border-b-2 py-3 px-3 text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'overview'
                  ? 'border-[#0250C5] text-white bg-white/5'
                  : 'border-transparent text-slate-400 hover:text-slate-200'
              }`}
            >
              <Building2 className="h-4 w-4 text-[#0250C5]" />
              Overview
            </button>

            <button
              onClick={() => setActiveTab('vehicles')}
              className={`flex items-center gap-2 border-b-2 py-3 px-3 text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'vehicles'
                  ? 'border-[#0250C5] text-white bg-white/5'
                  : 'border-transparent text-slate-400 hover:text-slate-200'
              }`}
            >
              <Truck className="h-4 w-4 text-emerald-400" />
              Vehicles
              <span className="rounded-full bg-emerald-500/20 text-emerald-300 px-1.5 py-0.2 text-[10px]">
                {vehicles.length}
              </span>
            </button>

            <button
              onClick={() => setActiveTab('drivers')}
              className={`flex items-center gap-2 border-b-2 py-3 px-3 text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'drivers'
                  ? 'border-[#0250C5] text-white bg-white/5'
                  : 'border-transparent text-slate-400 hover:text-slate-200'
              }`}
            >
              <User className="h-4 w-4 text-sky-400" />
              Drivers
              <span className="rounded-full bg-sky-500/20 text-sky-300 px-1.5 py-0.2 text-[10px]">
                {drivers.length}
              </span>
            </button>

            <button
              onClick={() => setActiveTab('trailers')}
              className={`flex items-center gap-2 border-b-2 py-3 px-3 text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'trailers'
                  ? 'border-[#0250C5] text-white bg-white/5'
                  : 'border-transparent text-slate-400 hover:text-slate-200'
              }`}
            >
              <Box className="h-4 w-4 text-amber-400" />
              Trailers
              <span className="rounded-full bg-amber-500/20 text-amber-300 px-1.5 py-0.2 text-[10px]">
                {trailers.length}
              </span>
            </button>

            <button
              onClick={() => setActiveTab('assignments')}
              className={`flex items-center gap-2 border-b-2 py-3 px-3 text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'assignments'
                  ? 'border-[#0250C5] text-white bg-white/5'
                  : 'border-transparent text-slate-400 hover:text-slate-200'
              }`}
            >
              <Package className="h-4 w-4 text-purple-400" />
              Assignments
              <span className="rounded-full bg-purple-500/20 text-purple-300 px-1.5 py-0.2 text-[10px]">
                {assignments.length}
              </span>
            </button>
          </div>

          {/* Drawer Body Content */}
          <div className="flex-1 overflow-y-auto p-6 scrollbar-thin scrollbar-thumb-white/10">
            {loadingFleet && activeTab !== 'overview' ? (
              <div className="flex flex-col items-center justify-center py-16 gap-3">
                <RefreshCw className="h-6 w-6 animate-spin text-[#0250C5]" />
                <p className="text-xs text-slate-400 font-medium">Loading partner fleet resources...</p>
              </div>
            ) : fleetError && activeTab !== 'overview' ? (
              <div className="rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-center">
                <p className="text-xs font-medium text-rose-300">{fleetError}</p>
                <button
                  onClick={() => loadPartnerFleetData(company.id)}
                  className="mt-2 text-xs font-bold text-rose-400 underline cursor-pointer"
                >
                  Retry
                </button>
              </div>
            ) : (
              <>
                {/* TAB 1: OVERVIEW */}
                {activeTab === 'overview' && (
                  <div className="space-y-6">
                    {/* Fleet Stats Banner */}
                    <div className="grid grid-cols-4 gap-3">
                      <div className="rounded-xl border border-white/5 bg-[#090D16] p-3.5 flex flex-col items-center justify-center">
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Vehicles</span>
                        <span className="text-xl font-extrabold text-white mt-1">{vehicles.length}</span>
                      </div>
                      <div className="rounded-xl border border-white/5 bg-[#090D16] p-3.5 flex flex-col items-center justify-center">
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Drivers</span>
                        <span className="text-xl font-extrabold text-white mt-1">{drivers.length}</span>
                      </div>
                      <div className="rounded-xl border border-white/5 bg-[#090D16] p-3.5 flex flex-col items-center justify-center">
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Trailers</span>
                        <span className="text-xl font-extrabold text-white mt-1">{trailers.length}</span>
                      </div>
                      <div className="rounded-xl border border-white/5 bg-[#090D16] p-3.5 flex flex-col items-center justify-center">
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Assignments</span>
                        <span className="text-xl font-extrabold text-white mt-1">{assignments.length}</span>
                      </div>
                    </div>

                    {/* Company Legal & Contact Details */}
                    <div className="rounded-2xl border border-white/5 bg-[#090D16] p-5 space-y-4">
                      <h3 className="text-xs font-bold uppercase tracking-widest text-slate-400 flex items-center gap-2">
                        <Building2 className="h-4 w-4 text-[#0250C5]" />
                        Legal & Contact Information
                      </h3>

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div>
                          <span className="text-slate-500 block">Legal Name</span>
                          <span className="font-semibold text-slate-200 mt-0.5 block">{company.legal_name || 'N/A'}</span>
                        </div>

                        <div>
                          <span className="text-slate-500 block">Primary Email</span>
                          <span className="font-semibold text-slate-200 mt-0.5 flex items-center gap-1.5">
                            <Mail className="h-3.5 w-3.5 text-slate-400" />
                            {company.primary_email}
                          </span>
                        </div>

                        <div>
                          <span className="text-slate-500 block">Phone</span>
                          <span className="font-semibold text-slate-200 mt-0.5 flex items-center gap-1.5">
                            <Phone className="h-3.5 w-3.5 text-slate-400" />
                            {company.primary_phone || 'N/A'}
                          </span>
                        </div>

                        <div>
                          <span className="text-slate-500 block">Website</span>
                          {company.website ? (
                            <a
                              href={company.website}
                              target="_blank"
                              rel="noreferrer"
                              className="font-semibold text-[#0250C5] hover:underline mt-0.5 inline-flex items-center gap-1"
                            >
                              <Globe className="h-3.5 w-3.5" />
                              {company.website}
                              <ExternalLink className="h-3 w-3" />
                            </a>
                          ) : (
                            <span className="font-semibold text-slate-400 mt-0.5 block">N/A</span>
                          )}
                        </div>

                        <div>
                          <span className="text-slate-500 block">Tax / VAT Number</span>
                          <span className="font-semibold text-slate-200 font-mono mt-0.5 block">
                            {company.vat_number || company.tax_number || 'N/A'}
                          </span>
                        </div>

                        <div>
                          <span className="text-slate-500 block">Business Reg. Number</span>
                          <span className="font-semibold text-slate-200 font-mono mt-0.5 block">
                            {company.business_registration_number || 'N/A'}
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Address & Location */}
                    <div className="rounded-2xl border border-white/5 bg-[#090D16] p-5 space-y-4">
                      <h3 className="text-xs font-bold uppercase tracking-widest text-slate-400 flex items-center gap-2">
                        <MapPin className="h-4 w-4 text-emerald-400" />
                        Location & Address
                      </h3>

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div className="md:col-span-2">
                          <span className="text-slate-500 block">Address</span>
                          <span className="font-semibold text-slate-200 mt-0.5 block">
                            {company.address_line_1} {company.address_line_2 ? `, ${company.address_line_2}` : ''}
                          </span>
                        </div>

                        <div>
                          <span className="text-slate-500 block">City / State</span>
                          <span className="font-semibold text-slate-200 mt-0.5 block">
                            {company.city}{company.state_province ? `, ${company.state_province}` : ''}
                          </span>
                        </div>

                        <div>
                          <span className="text-slate-500 block">Country / Postal Code</span>
                          <span className="font-semibold text-slate-200 mt-0.5 block">
                            {company.country} - {company.postal_code}
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Operational Notes */}
                    {company.notes && (
                      <div className="rounded-2xl border border-white/5 bg-[#090D16] p-5 space-y-2">
                        <h3 className="text-xs font-bold uppercase tracking-widest text-slate-400 flex items-center gap-2">
                          <Layers className="h-4 w-4 text-amber-400" />
                          Partner Notes
                        </h3>
                        <p className="text-xs text-slate-300 leading-relaxed italic">{company.notes}</p>
                      </div>
                    )}
                  </div>
                )}

                {/* TAB 2: VEHICLES */}
                {activeTab === 'vehicles' && (
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <h3 className="text-xs font-bold uppercase tracking-widest text-slate-400">
                        Assigned Partner Vehicles ({vehicles.length})
                      </h3>
                    </div>

                    {vehicles.length === 0 ? (
                      <div className="rounded-2xl border border-dashed border-white/10 p-10 text-center">
                        <Truck className="mx-auto h-8 w-8 text-slate-600 mb-2" />
                        <p className="text-sm font-semibold text-slate-300">No vehicles assigned</p>
                        <p className="text-xs text-slate-500 mt-1">This partner company does not have registered vehicles yet.</p>
                      </div>
                    ) : (
                      <div className="space-y-2.5">
                        {vehicles.map((v) => (
                          <div key={v.id} className="rounded-xl border border-white/5 bg-[#090D16] p-3.5 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                              <div className="h-9 w-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                                <Truck className="h-4.5 w-4.5" />
                              </div>
                              <div>
                                <span className="text-xs font-bold text-white block">{v.operational_code}</span>
                                <span className="text-[11px] text-slate-400 font-mono block">
                                  Plate: {v.plate_number} {v.fleet_number ? `• Fleet #${v.fleet_number}` : ''}
                                </span>
                              </div>
                            </div>
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                              v.is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-500/10 text-slate-400'
                            }`}>
                              {v.status?.name || (v.is_active ? 'Active' : 'Inactive')}
                            </span>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                )}

                {/* TAB 3: DRIVERS */}
                {activeTab === 'drivers' && (
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <h3 className="text-xs font-bold uppercase tracking-widest text-slate-400">
                        Employed Partner Drivers ({drivers.length})
                      </h3>
                    </div>

                    {drivers.length === 0 ? (
                      <div className="rounded-2xl border border-dashed border-white/10 p-10 text-center">
                        <User className="mx-auto h-8 w-8 text-slate-600 mb-2" />
                        <p className="text-sm font-semibold text-slate-300">No drivers assigned</p>
                        <p className="text-xs text-slate-500 mt-1">This partner company does not have employed drivers registered yet.</p>
                      </div>
                    ) : (
                      <div className="space-y-2.5">
                        {drivers.map((d) => (
                          <div key={d.id} className="rounded-xl border border-white/5 bg-[#090D16] p-3.5 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                              <div className="h-9 w-9 rounded-lg bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400">
                                <User className="h-4.5 w-4.5" />
                              </div>
                              <div>
                                <span className="text-xs font-bold text-white block">{d.first_name} {d.last_name}</span>
                                <span className="text-[11px] text-slate-400 font-mono block">
                                  Emp #{d.employee_number} {d.license_class ? `• Class ${d.license_class}` : ''} • {d.phone || d.email}
                                </span>
                              </div>
                            </div>
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                              d.is_active ? 'bg-sky-500/10 text-sky-400' : 'bg-slate-500/10 text-slate-400'
                            }`}>
                              {d.status?.name || (d.is_active ? 'Active' : 'Offline')}
                            </span>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                )}

                {/* TAB 4: TRAILERS */}
                {activeTab === 'trailers' && (
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <h3 className="text-xs font-bold uppercase tracking-widest text-slate-400">
                        Owned Partner Trailers ({trailers.length})
                      </h3>
                    </div>

                    {trailers.length === 0 ? (
                      <div className="rounded-2xl border border-dashed border-white/10 p-10 text-center">
                        <Box className="mx-auto h-8 w-8 text-slate-600 mb-2" />
                        <p className="text-sm font-semibold text-slate-300">No trailers assigned</p>
                        <p className="text-xs text-slate-500 mt-1">This partner company does not have owned trailers registered yet.</p>
                      </div>
                    ) : (
                      <div className="space-y-2.5">
                        {trailers.map((t) => (
                          <div key={t.id} className="rounded-xl border border-white/5 bg-[#090D16] p-3.5 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                              <div className="h-9 w-9 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                                <Box className="h-4.5 w-4.5" />
                              </div>
                              <div>
                                <span className="text-xs font-bold text-white block">{t.trailer_code}</span>
                                <span className="text-[11px] text-slate-400 font-mono block">
                                  {t.trailer_type || 'Standard Trailer'} {t.plate_number ? `• Plate: ${t.plate_number}` : ''}
                                </span>
                              </div>
                            </div>
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                              t.is_active ? 'bg-amber-500/10 text-amber-400' : 'bg-slate-500/10 text-slate-400'
                            }`}>
                              {t.status?.name || (t.is_active ? 'Active' : 'Inactive')}
                            </span>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                )}

                {/* TAB 5: ASSIGNMENTS */}
                {activeTab === 'assignments' && (
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <h3 className="text-xs font-bold uppercase tracking-widest text-slate-400">
                        Executed Carrier Assignments ({assignments.length})
                      </h3>
                    </div>

                    {assignments.length === 0 ? (
                      <div className="rounded-2xl border border-dashed border-white/10 p-10 text-center">
                        <Package className="mx-auto h-8 w-8 text-slate-600 mb-2" />
                        <p className="text-sm font-semibold text-slate-300">No active assignments</p>
                        <p className="text-xs text-slate-500 mt-1">This partner company is not currently executing active shipments.</p>
                      </div>
                    ) : (
                      <div className="space-y-2.5">
                        {assignments.map((s) => (
                          <div key={s.id} className="rounded-xl border border-white/5 bg-[#090D16] p-3.5 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                              <div className="h-9 w-9 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400">
                                <Package className="h-4.5 w-4.5" />
                              </div>
                              <div>
                                <span className="text-xs font-bold text-white block">{s.shipment_number}</span>
                                <span className="text-[11px] text-slate-400 block">
                                  Customer: <span className="text-slate-200">{s.customer_company?.company_name || 'B2B Client'}</span>
                                </span>
                              </div>
                            </div>
                            <span className="px-2.5 py-1 rounded-lg bg-purple-500/10 text-purple-300 border border-purple-500/20 text-xs font-bold">
                              {s.status?.name || 'In Progress'}
                            </span>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </>
            )}
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  );
}
