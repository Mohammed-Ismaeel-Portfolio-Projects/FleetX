import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { AlertTriangle, Building2, Package, Truck } from 'lucide-react';
import {
  dashboardService,
  DashboardApiError,
  type DashboardKPI,
} from '../../../features/dispatcher/dashboard/services/dashboard.service';
import { companiesService, CompanyApiError } from '../../../features/admin/companies/services/companies.service';
import KpiCard from './KpiCard';

function formatCount(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return 'Unavailable';
  }
  return value.toLocaleString();
}

export default function KpiGrid() {
  const [kpi, setKpi] = useState<DashboardKPI | null>(null);
  const [companyTotal, setCompanyTotal] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setIsLoading(true);
      setError(null);

      try {
        const [kpiData, companiesData] = await Promise.all([
          dashboardService.getKPI(),
          companiesService.getCompanies({ page: 1, per_page: 1 }),
        ]);

        if (cancelled) return;
        setKpi(kpiData);
        setCompanyTotal(companiesData.meta?.total ?? null);
      } catch (err) {
        if (cancelled) return;
        setKpi(null);
        setCompanyTotal(null);
        const message = err instanceof DashboardApiError || err instanceof CompanyApiError
          ? err.message
          : 'Failed to load dashboard KPIs';
        setError(message);
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 10 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.45, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 w-full">
        {Array.from({ length: 5 }).map((_, index) => (
          <div
            key={index}
            className="min-h-[120px] rounded-2xl border border-white/5 bg-[#0E1320] p-5 animate-pulse"
          >
            <div className="h-3 w-24 rounded bg-white/10" />
            <div className="mt-6 h-7 w-16 rounded bg-white/10" />
            <p className="mt-3 text-[10px] text-slate-500">Loading…</p>
          </div>
        ))}
      </div>
    );
  }

  if (error || !kpi) {
    return (
      <div className="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-5 text-sm text-amber-100">
        Platform KPIs are unavailable. {error ?? 'Dashboard KPI endpoint did not return data.'}
      </div>
    );
  }

  const delayedValue = formatCount(kpi.delayed_shipments);
  const delayedUnavailable = delayedValue === 'Unavailable';

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 w-full"
    >
      <motion.div variants={itemVariants}>
        <KpiCard
          icon={Building2}
          label="Registered Companies"
          value={formatCount(companyTotal)}
          subtitle="Tenant companies on the platform"
          status="neutral"
          iconColor="text-violet-400"
          iconBgColor="bg-violet-500/10 border-violet-500/20"
          href="/admin/companies"
        />
      </motion.div>

      <motion.div variants={itemVariants}>
        <KpiCard
          icon={Package}
          label="Active Shipments"
          value={formatCount(kpi.active_shipments)}
          subtitle="Not completed or cancelled"
          status="neutral"
          iconColor="text-blue-400"
          iconBgColor="bg-blue-500/10 border-blue-500/20"
          href="/admin/shipments"
        />
      </motion.div>

      <motion.div variants={itemVariants}>
        <KpiCard
          icon={Truck}
          label="Fleet In Transit"
          value={formatCount(kpi.vehicles_in_transit)}
          subtitle="Active fleet units on assignments"
          status="neutral"
          iconColor="text-emerald-400"
          iconBgColor="bg-emerald-500/10 border-emerald-500/20"
          href="/admin/tracking"
        />
      </motion.div>

      <motion.div variants={itemVariants}>
        <KpiCard
          icon={AlertTriangle}
          label="Critical Issues"
          value={formatCount(kpi.critical_alerts)}
          subtitle="Open critical or emergency issues"
          status="neutral"
          iconColor="text-orange-400"
          iconBgColor="bg-orange-500/10 border-orange-500/20"
          href="/admin/notifications"
        />
      </motion.div>

      <motion.div variants={itemVariants}>
        <KpiCard
          icon={AlertTriangle}
          label="Delayed Shipments"
          value={delayedValue}
          subtitle={
            delayedUnavailable
              ? 'No in-flight delay signal from the API'
              : 'Shipments flagged as delayed'
          }
          status="neutral"
          iconColor="text-slate-400"
          iconBgColor="bg-white/5 border-white/10"
        />
      </motion.div>
    </motion.div>
  );
}
