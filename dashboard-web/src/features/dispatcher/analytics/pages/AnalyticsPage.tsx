import { DispatcherSurface } from '../../../../shared/components/dispatcher/DispatcherSurface';
import { PageHeader } from '../../../../shared/components/dispatcher/PageHeader';
import { OperationalAnalyticsCenter } from '../components/OperationalAnalyticsCenter';

/**
 * Shared Operational Analytics surface for Dispatcher and Admin shells.
 * Route guards: `/dispatcher/analytics` (dispatcher), `/admin/analytics` (admin).
 * Data: same auth:api KPI endpoints for both roles. Navigation is role-scoped.
 */
export default function AnalyticsPage() {
  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      <PageHeader
        title="Operational Analytics"
        subtitle="Current operational snapshot, delivery performance, and domain capacity breakdowns."
        badge="OPS KPIs"
        showStreaming={false}
      />
      <OperationalAnalyticsCenter />
    </DispatcherSurface>
  );
}
