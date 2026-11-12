import type { Driver, DriverKPIs } from '../../drivers/types/driver-operations.types';
import type { ActivityItem } from '../../dashboard/services/dashboard.service';
import type { Issue } from '../../dashboard/services/issues.service';

/** Partial-failure errors by source — never invent zeros for failed sources. */
export type CommunicationSectionErrors = {
  drivers: string | null;
  driverKpis: string | null;
  dashboard: string | null;
  issues: string | null;
  activity: string | null;
};

export type CommunicationContextMetric = {
  id: string;
  label: string;
  value: string;
  detail: string;
  href: string | null;
};

export type CommunicationViewModel = {
  /** Client clock when a successful aggregate load applied. */
  lastUpdated: string | null;
  metrics: CommunicationContextMetric[];
  drivers: Driver[];
  driverTotal: number | null;
  driverKpis: DriverKPIs | null;
  issues: Issue[];
  activity: ActivityItem[];
  sectionErrors: CommunicationSectionErrors;
};
