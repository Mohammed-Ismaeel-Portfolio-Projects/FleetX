/**
 * Dispatcher Request Review + Decision + Conversion (C2.9-C2.4 / C2.5 / C2.6).
 * Convert reuses existing POST /shipment-requests/{id}/convert. No Assignment/Tracking.
 */
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, ShieldCheck, ExternalLink } from 'lucide-react';
import { DispatcherSurface } from '../../../../shared/components/dispatcher/DispatcherSurface';
import { PageHeader } from '../../../../shared/components/dispatcher/PageHeader';
import { LoadingState } from '../../../../shared/components/dispatcher/LoadingState';
import { ErrorState } from '../../../../shared/components/dispatcher/ErrorState';
import { EmptyState } from '../../../../shared/components/dispatcher/EmptyState';
import { useShipmentRequestDetail } from '../hooks/useShipmentRequestDetail';
import RequestReviewSummary from '../components/RequestReviewSummary';
import RequestStatusHistoryPanel from '../components/RequestStatusHistoryPanel';
import RequestDecisionPanel from '../components/RequestDecisionPanel';
import RequestConversionPanel from '../components/RequestConversionPanel';
import ReviewSection, { ReviewField } from '../components/ReviewSection';
import RequestRouteMap from '../components/RequestRouteMap';
import RequestAssignmentWorkspace from '../components/RequestAssignmentWorkspace';

export default function RequestDetailsPage() {
  const { id } = useParams<{ id: string }>();
  const { request, setRequest, isLoading, error, refresh } = useShipmentRequestDetail(id);

  const isHistorical = Boolean(
    request &&
    ['rejected', 'cancelled', 'completed'].includes(request.status.toLowerCase())
  );

  const convertedShipmentId = request?.convertedShipment?.id || request?.convertedShipmentId;

  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      <PageHeader
        title="Request Operations"
        subtitle={isHistorical ? "Historical Audit Record — Read Only" : "Review completeness, decide, convert, and assign real drivers and fleet resources."}
        badge={isHistorical ? "Historical / Read-Only" : "Operations"}
        showStreaming={false}
        onRefresh={() => void refresh()}
        actions={
          <Link
            to="/dispatcher/requests"
            className="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
          >
            <ArrowLeft className="h-4 w-4" /> Back to Requests
          </Link>
        }
      />

      {isLoading ? (
        <LoadingState title="Loading request review" description="Fetching request detail for dispatcher review." />
      ) : error ? (
        <div className="flex flex-col gap-3">
          <ErrorState title="Unable to load request review" description={error} />
          <button
            type="button"
            onClick={() => void refresh()}
            className="self-start rounded-2xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200"
          >
            Retry
          </button>
        </div>
      ) : !request ? (
        <EmptyState title="Request not found" description="This shipment request could not be located." />
      ) : (
        <div className="flex flex-col gap-4">
          {isHistorical ? (
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-cyan-500/20 bg-cyan-500/10 p-4 text-cyan-200 shadow-md font-sans">
              <div className="flex items-start gap-3">
                <ShieldCheck className="h-5 w-5 shrink-0 text-cyan-400 mt-0.5" />
                <div>
                  <p className="text-xs font-bold uppercase tracking-wider text-cyan-300">Historical Audit Record — Read Only</p>
                  <p className="mt-1 text-xs text-slate-300">
                    This request record is historical and immutable. Submitted details and status history are permanently preserved for audit trail accuracy and cannot be modified.
                  </p>
                </div>
              </div>
              {convertedShipmentId ? (
                <Link
                  to={`/dispatcher/shipments/${convertedShipmentId}`}
                  className="inline-flex shrink-0 items-center gap-2 rounded-xl border border-cyan-400/30 bg-cyan-500/20 px-3.5 py-2 text-xs font-bold text-cyan-100 hover:bg-cyan-500/30 transition-all"
                >
                  <ExternalLink className="h-3.5 w-3.5" /> View Shipment
                </Link>
              ) : null}
            </div>
          ) : null}

          <RequestReviewSummary request={request} />

          <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <div className="xl:col-span-8 flex flex-col gap-4">
              <ReviewSection title="Request identity">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <ReviewField label="Request number" value={request.requestNumber} />
                  <ReviewField label="Reference" value={request.referenceNumber || 'No reference'} />
                  <ReviewField label="Submission revision" value={request.submissionRevision ?? '—'} />
                  <ReviewField label="Priority" value={request.priority || 'Unavailable'} />
                </div>
              </ReviewSection>

              <ReviewSection title="Customer" subtitle="Who requested the shipment.">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <ReviewField
                    label="Company"
                    value={request.customer?.companyName || 'Unavailable'}
                    hint={request.customer?.companyCode || null}
                  />
                  <ReviewField
                    label="Submitted by"
                    value={request.submittedBy?.name || 'Unavailable'}
                    hint={request.submittedBy?.email || null}
                  />
                </div>
              </ReviewSection>

              <ReviewSection title="Pickup" subtitle="Origin details from the request API.">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <ReviewField label="Company" value={request.pickupCompany || 'Unavailable'} />
                  <ReviewField label="Contact" value={request.pickupContact || 'Unavailable'} hint={request.pickupPhone} />
                  <ReviewField label="Address" value={request.pickupAddress || 'Unavailable'} />
                  <ReviewField
                    label="City / country"
                    value={[request.pickupCity, request.pickupCountry].filter(Boolean).join(', ') || 'Unavailable'}
                  />
                  {request.pickupLatitude != null && request.pickupLongitude != null ? (
                    <ReviewField
                      label="Coordinates"
                      value={`${request.pickupLatitude}, ${request.pickupLongitude}`}
                    />
                  ) : null}
                </div>
              </ReviewSection>

              <ReviewSection title="Delivery" subtitle="Destination details from the request API.">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <ReviewField label="Company" value={request.deliveryCompany || 'Unavailable'} />
                  <ReviewField label="Contact" value={request.deliveryContact || 'Unavailable'} hint={request.deliveryPhone} />
                  <ReviewField label="Address" value={request.deliveryAddress || 'Unavailable'} />
                  <ReviewField
                    label="City / country"
                    value={[request.deliveryCity, request.deliveryCountry].filter(Boolean).join(', ') || 'Unavailable'}
                  />
                  {request.deliveryLatitude != null && request.deliveryLongitude != null ? (
                    <ReviewField
                      label="Coordinates"
                      value={`${request.deliveryLatitude}, ${request.deliveryLongitude}`}
                    />
                  ) : null}
                </div>
              </ReviewSection>

              {(request.pickupLatitude != null || request.deliveryLatitude != null) ? (
                <ReviewSection title="Planned Route & Map" subtitle="Calculated road network route and stop coordinates.">
                  <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <ReviewField
                      label="Planned Distance"
                      value={
                        request.plannedRoute?.distanceKm != null
                          ? `${request.plannedRoute.distanceKm.toLocaleString()} km`
                          : request.priceSnapshot?.distanceKm != null
                          ? `${request.priceSnapshot.distanceKm.toLocaleString()} km`
                          : 'Unavailable'
                      }
                    />
                    <ReviewField
                      label="Estimated Duration"
                      value={request.plannedRoute?.durationFormatted || 'Unavailable'}
                    />
                    <ReviewField
                      label="Route Status"
                      value={
                        request.plannedRoute?.status === 'AVAILABLE'
                          ? 'Calculated & Persisted'
                          : request.plannedRoute?.status === 'UNAVAILABLE'
                          ? 'Temporarily Unavailable'
                          : 'Pending Calculation'
                      }
                    />
                  </div>
                  <RequestRouteMap request={request} />
                </ReviewSection>
              ) : null}

              <ReviewSection title="Cargo" subtitle="What needs to be transported.">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <ReviewField label="Cargo type" value={request.cargoType || 'Unavailable'} />
                  <ReviewField label="Description" value={request.cargoDescription || 'Unavailable'} />
                  <ReviewField label="Weight" value={request.cargoWeight ?? 'Unavailable'} />
                  <ReviewField label="Volume" value={request.cargoVolume ?? 'Unavailable'} />
                  <ReviewField label="Package count" value={request.packageCount ?? 'Unavailable'} />
                  <ReviewField label="Trailer type" value={request.trailerType || 'Unavailable'} />
                  <ReviewField label="Hazardous" value={request.hazardousCargo ? 'Yes' : 'No'} />
                  <ReviewField label="Temperature controlled" value={request.temperatureControlled ? 'Yes' : 'No'} />
                </div>
              </ReviewSection>

              <ReviewSection title="Schedule" subtitle="Requested timing from the client.">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <ReviewField label="Requested pickup" value={request.requestedPickupDate || 'Unavailable'} />
                  <ReviewField label="Requested delivery" value={request.requestedDeliveryDate || 'Unavailable'} />
                  <ReviewField label="Created at" value={request.createdAt || 'Unavailable'} />
                  <ReviewField label="Updated at" value={request.updatedAt || 'Unavailable'} />
                </div>
              </ReviewSection>

              <ReviewSection
                title="Pricing"
                subtitle="Persisted estimate from submission. This is not a final invoice."
              >
                {request.priceSnapshot || request.priceSummary ? (
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <ReviewField
                      label="Estimated total"
                      value={
                        request.priceSnapshot
                          ? `${request.priceSnapshot.total} ${request.priceSnapshot.currency}`
                          : `${request.priceSummary?.total} ${request.priceSummary?.currency}`
                      }
                    />
                    <ReviewField
                      label="Calculated at"
                      value={
                        request.priceSnapshot?.calculatedAt ||
                        request.priceSummary?.calculatedAt ||
                        'Unavailable'
                      }
                    />
                    {request.priceSnapshot?.distanceKm != null ? (
                      <ReviewField label="Distance (km)" value={request.priceSnapshot.distanceKm} />
                    ) : null}
                    {request.priceSnapshot?.warnings?.length ? (
                      <ReviewField label="Warnings" value={request.priceSnapshot.warnings.join(' · ')} />
                    ) : null}
                  </div>
                ) : (
                  <p className="text-sm text-slate-400">No persisted price snapshot is available for this request.</p>
                )}
              </ReviewSection>

              {(request.specialInstructions || request.clientNotes || request.dispatcherNotes) && (
                <ReviewSection title="Notes">
                  <div className="grid grid-cols-1 gap-4">
                    {request.specialInstructions ? (
                      <ReviewField label="Special instructions" value={request.specialInstructions} />
                    ) : null}
                    {request.clientNotes ? (
                      <ReviewField label="Client notes" value={request.clientNotes} />
                    ) : null}
                    {request.dispatcherNotes ? (
                      <ReviewField label="Dispatcher notes" value={request.dispatcherNotes} />
                    ) : null}
                  </div>
                </ReviewSection>
              )}
            </div>

            <aside className="xl:col-span-4 flex flex-col gap-4">
              <ReviewSection title="Operational metadata">
                <div className="space-y-3 text-sm">
                  <ReviewField label="Route summary" value={request.routeLabel} />
                  <ReviewField
                    label="Converted shipment"
                    value={request.convertedShipment?.shipmentNumber || (request.convertedShipmentId ? `Shipment ${request.convertedShipmentId}` : 'None')}
                    hint={request.convertedShipment?.status || null}
                  />
                  {request.convertedShipmentId ? (
                    <ReviewField label="Converted shipment id" value={request.convertedShipmentId} />
                  ) : null}
                </div>
              </ReviewSection>

              <RequestStatusHistoryPanel history={request.statusHistory} />

              <RequestDecisionPanel
                request={request}
                onDecided={(next) => {
                  setRequest(next);
                  void refresh({ silent: true });
                }}
              />

              <RequestConversionPanel
                request={request}
                onConverted={(next) => {
                  setRequest(next);
                  void refresh({ silent: true });
                }}
              />

              <RequestAssignmentWorkspace
                request={request}
                onAssigned={() => void refresh({ silent: true })}
              />
            </aside>
          </div>
        </div>
      )}
    </DispatcherSurface>
  );
}
