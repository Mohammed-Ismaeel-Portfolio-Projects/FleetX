/**
 * Dispatcher Shipment Operational Detail (C2.9-C2.7 / Step 6).
 * Extends the existing /dispatcher/shipments/:id page — read-only workspace.
 * Controlled refresh when Engine-enrolled (reuses list-style visibility poll).
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import type { Shipment } from '../types/shipment.types';
import { shipmentsService } from '../services/shipments.service';
import ShipmentDetailsPanel from '../components/ShipmentDetailsPanel';
import { DispatcherSurface } from '../../../../shared/components/dispatcher/DispatcherSurface';
import { PageHeader } from '../../../../shared/components/dispatcher/PageHeader';
import { LoadingState } from '../../../../shared/components/dispatcher/LoadingState';
import { ErrorState } from '../../../../shared/components/dispatcher/ErrorState';
import { EmptyState } from '../../../../shared/components/dispatcher/EmptyState';

const ENGINE_REFRESH_MS = 15_000;

export default function ShipmentDetailsPage() {
  const { id } = useParams<{ id: string }>();
  const [shipment, setShipment] = useState<Shipment | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const visibleRef = useRef(typeof document === 'undefined' ? true : document.visibilityState === 'visible');

  const load = useCallback(async (opts?: { silent?: boolean }) => {
    if (!id) {
      setShipment(null);
      setIsLoading(false);
      setError(null);
      return;
    }

    if (!opts?.silent) {
      setIsLoading(true);
    }
    setError(null);

    try {
      const data = await shipmentsService.getShipmentById(id);
      setShipment(data || null);
    } catch (err) {
      if (!opts?.silent) {
        setShipment(null);
      }
      setError(err instanceof Error ? err.message : 'Failed to load shipment');
    } finally {
      if (!opts?.silent) {
        setIsLoading(false);
      }
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    const onVisibility = () => {
      visibleRef.current = document.visibilityState === 'visible';
    };
    document.addEventListener('visibilitychange', onVisibility);
    return () => document.removeEventListener('visibilitychange', onVisibility);
  }, []);

  useEffect(() => {
    const eligible = Boolean(shipment?.engineOps?.simulationEligible);
    if (!id || !eligible) return;

    const timer = window.setInterval(() => {
      if (!visibleRef.current) return;
      void load({ silent: true });
    }, ENGINE_REFRESH_MS);

    return () => window.clearInterval(timer);
  }, [id, shipment?.engineOps?.simulationEligible, load]);

  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      <PageHeader
        title="Shipment Operations"
        subtitle="Operational workspace for a converted or existing shipment."
        badge="Ops"
        showStreaming={Boolean(shipment?.engineOps?.simulationEligible)}
        onRefresh={() => void load()}
        actions={
          <Link
            to="/dispatcher/shipments"
            className="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
          >
            <ArrowLeft className="h-4 w-4" /> Back to Shipments
          </Link>
        }
      />

      {isLoading ? (
        <LoadingState
          title="Loading shipment operations"
          description="Fetching shipment detail for the dispatcher workspace."
        />
      ) : error ? (
        <div className="flex flex-col gap-3">
          <ErrorState title="Unable to load shipment" description={error} />
          <button
            type="button"
            onClick={() => void load()}
            className="self-start rounded-2xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200"
          >
            Retry
          </button>
        </div>
      ) : !shipment ? (
        <EmptyState title="Shipment not found" description="This shipment could not be located." />
      ) : (
        <ShipmentDetailsPanel
          shipment={shipment}
          onEngineEnrolled={() => load({ silent: true })}
        />
      )}
    </DispatcherSurface>
  );
}
