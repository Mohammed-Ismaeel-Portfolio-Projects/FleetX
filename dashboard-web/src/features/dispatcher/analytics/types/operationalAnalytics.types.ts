export interface OperationalAnalyticsKpi {
  id: string;
  label: string;
  value: string;
  /** Honest unit/definition note — never a trend or target. */
  detail?: string;
}

export interface FleetPerformanceMetric {
  label: string;
  /** Pass-through display from fleet-kpis, or Unavailable. */
  value: string;
}

export interface DriverAvailabilityMetric {
  label: string;
  /** Pass-through display from drivers/kpis, or Unavailable. */
  value: string;
}

export interface ShipmentStatusMetric {
  label: string;
  /** Pass-through display from shipments/kpis, or Unavailable. */
  value: string;
}

/** Per-source load errors — null means that source succeeded. */
export interface OperationalAnalyticsSectionErrors {
  dashboard: string | null;
  fleet: string | null;
  shipments: string | null;
  drivers: string | null;
  performance: string | null;
}

export interface OperationalAnalyticsViewModel {
  header: {
    lastUpdated: string;
  };
  kpis: OperationalAnalyticsKpi[];
  fleet: FleetPerformanceMetric[];
  drivers: DriverAvailabilityMetric[];
  shipments: ShipmentStatusMetric[];
  sectionErrors: OperationalAnalyticsSectionErrors;
}
