import { motion } from 'framer-motion';
import { DispatcherSurface } from '../../../../shared/components/dispatcher/DispatcherSurface';
import { PageHeader } from '../../../../shared/components/dispatcher/PageHeader';
import { useNotifications } from '../hooks/useNotifications';
import IncidentKPIs from '../components/operations/IncidentKPIs';
import IncidentFilters from '../components/operations/IncidentFilters';
import IncidentQueue from '../components/operations/IncidentQueue';
import IncidentDetailsPanel from '../components/operations/IncidentDetailsPanel';
import StatefulPanel from '../components/operations/StatefulPanel';

export default function NotificationsPage() {
  const {
    incidents,
    selectedIncident,
    selectedIncidentId,
    setSelectedIncidentId,
    metrics,
    isLoading,
    viewState,
    refreshIncidents,
    searchQuery,
    setSearchQuery,
    typeFilter,
    setTypeFilter,
    statusIdFilter,
    setStatusIdFilter,
    knownTypes,
    statusOptions,
    resetFilters,
    pagination,
    onPageChange,
    canResolveSelected,
    resolvedStatusId,
    isResolving,
    resolveError,
    resolveSelectedIncident,
    loadError,
    retryLoad,
  } = useNotifications();

  const itemVariants = {
    hidden: { opacity: 0, y: 12 },
    visible: { opacity: 1, y: 0, transition: { duration: 0.45, ease: [0.16, 1, 0.3, 1] as const } },
  };

  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      <motion.div variants={itemVariants} initial="hidden" animate="visible" className="w-full">
        <PageHeader
          title="Operations Center"
          subtitle="Monitor operational issues requiring dispatcher attention."
          badge="ALERTS"
          showStreaming={false}
          onRefresh={refreshIncidents}
        />
      </motion.div>

      <motion.div variants={itemVariants} initial="hidden" animate="visible" className="w-full">
        <IncidentKPIs metrics={metrics} />
      </motion.div>

      <motion.div variants={itemVariants} initial="hidden" animate="visible" className="w-full">
        <IncidentFilters
          searchQuery={searchQuery}
          onSearchChange={setSearchQuery}
          typeFilter={typeFilter}
          onTypeChange={setTypeFilter}
          statusIdFilter={statusIdFilter}
          onStatusIdChange={setStatusIdFilter}
          knownTypes={knownTypes}
          statusOptions={statusOptions}
          onReset={resetFilters}
        />
      </motion.div>

      <motion.div
        variants={itemVariants}
        initial="hidden"
        animate="visible"
        className="grid grid-cols-1 xl:grid-cols-12 gap-6"
      >
        <div className="xl:col-span-5">
          <StatefulPanel
            state={isLoading ? 'loading' : viewState}
            errorMessage={loadError}
            onRetry={() => void retryLoad()}
          >
            <IncidentQueue
              incidents={incidents}
              selectedIncidentId={selectedIncidentId}
              onSelect={setSelectedIncidentId}
              pagination={pagination}
              onPageChange={onPageChange}
            />
          </StatefulPanel>
        </div>
        <div className="xl:col-span-7">
          <StatefulPanel
            state={isLoading ? 'loading' : viewState}
            errorMessage={loadError}
            onRetry={() => void retryLoad()}
          >
            <IncidentDetailsPanel
              incident={selectedIncident}
              canResolve={canResolveSelected}
              isResolving={isResolving}
              resolveError={resolveError}
              resolvedStatusIdAvailable={resolvedStatusId != null}
              onResolve={() => void resolveSelectedIncident()}
            />
          </StatefulPanel>
        </div>
      </motion.div>
    </DispatcherSurface>
  );
}
