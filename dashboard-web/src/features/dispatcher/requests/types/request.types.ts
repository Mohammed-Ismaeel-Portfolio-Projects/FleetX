/** Canonical ShipmentOrder status names (type = shipment_order). */
export const SHIPMENT_ORDER_STATUSES = [
  'draft',
  'submitted',
  'under_review',
  'changes_requested',
  'approved',
  'rejected',
  'cancelled',
  'converted',
] as const;

export type ShipmentOrderStatusName = (typeof SHIPMENT_ORDER_STATUSES)[number] | string;

export interface ShipmentRequestCustomer {
  id: string;
  companyName: string;
  companyCode: string;
}

export interface ShipmentRequestPriceSummary {
  currency: string;
  total: number;
  calculatedAt: string | null;
}

export interface ShipmentRequestPriceSnapshot {
  currency: string;
  total: number;
  calculatedAt: string | null;
  distanceKm: number | null;
  warnings: string[];
}

export interface ShipmentRequestListItem {
  id: string;
  requestNumber: string;
  referenceNumber: string | null;
  customer: ShipmentRequestCustomer | null;
  statusId: number | string;
  status: ShipmentOrderStatusName;
  submissionRevision: number | null;
  cargoType: string | null;
  cargoDescription: string | null;
  cargoWeight: string | number | null;
  packageCount: number | null;
  trailerType: string | null;
  pickupCity: string | null;
  pickupCountry: string | null;
  deliveryCity: string | null;
  deliveryCountry: string | null;
  routeLabel: string;
  requestedPickupDate: string | null;
  requestedDeliveryDate: string | null;
  priority: string | null;
  priceSummary: ShipmentRequestPriceSummary | null;
  convertedShipmentId?: string | null;
  createdAt: string | null;
  updatedAt: string | null;
}

export interface ShipmentRequestStatusHistoryItem {
  id: string | number;
  fromStatus: string | null;
  toStatus: string | null;
  changedBy: { id: string; name: string; email: string } | null;
  reason: string | null;
  createdAt: string | null;
}

export interface ShipmentOrderPlannedRoute {
  status: 'AVAILABLE' | 'UNAVAILABLE' | string;
  distanceMeters: number | null;
  distanceKm: number | null;
  durationSeconds: number | null;
  durationFormatted: string | null;
  geometry: [number, number][] | null;
  calculatedAt: string | null;
}

export interface ShipmentRequestDetail extends ShipmentRequestListItem {
  submittedBy: { id: string; name: string; email: string } | null;
  cargoVolume: string | number | null;
  hazardousCargo: boolean;
  temperatureControlled: boolean;
  pickupCompany: string | null;
  pickupContact: string | null;
  pickupPhone: string | null;
  pickupAddress: string | null;
  pickupLatitude: number | null;
  pickupLongitude: number | null;
  deliveryCompany: string | null;
  deliveryContact: string | null;
  deliveryPhone: string | null;
  deliveryAddress: string | null;
  deliveryLatitude: number | null;
  deliveryLongitude: number | null;
  specialInstructions: string | null;
  clientNotes: string | null;
  dispatcherNotes: string | null;
  convertedShipmentId: string | null;
  convertedShipment: {
    id: string;
    shipmentNumber: string;
    status: string | null;
  } | null;
  priceSnapshot: ShipmentRequestPriceSnapshot | null;
  plannedRoute?: ShipmentOrderPlannedRoute | null;
  statusHistory: ShipmentRequestStatusHistoryItem[];
}

export interface ShipmentRequestQueryParams {
  search?: string;
  status?: string;
  status_id?: number | string;
  customer_company_id?: string;
  exclude_converted?: boolean;
  requested_pickup_date_from?: string;
  requested_pickup_date_to?: string;
  page?: number;
  per_page?: number;
}

export interface ShipmentRequestListMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from?: number | null;
  to?: number | null;
}
