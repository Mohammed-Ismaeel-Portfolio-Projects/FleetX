/** Client-safe shipment request types (C3.3 list + C3.4 workflow). */

export interface ClientShipmentRequestStatus {
  id: number;
  name: string;
  type: string;
}

/** List row — matches ClientShipmentRequestListResource. */
export interface ClientLinkedShipment {
  id: string;
  shipmentNumber: string | null;
  status: ClientShipmentRequestStatus | null;
}

export interface ClientShipmentRequest {
  id: string;
  requestNumber: string | null;
  referenceNumber: string | null;
  statusId: number | null;
  status: ClientShipmentRequestStatus | null;
  cargoType: string | null;
  cargoDescription: string | null;
  packageCount: number | null;
  pickupCity: string | null;
  pickupCountry: string | null;
  pickupCompany: string | null;
  deliveryCity: string | null;
  deliveryCountry: string | null;
  deliveryCompany: string | null;
  requestedPickupDate: string | null;
  requestedDeliveryDate: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  linkedShipment: ClientLinkedShipment | null;
}

/** Allowlisted customer-safe review reason from Client show (C5.3). */
export interface ClientReviewFeedback {
  message: string;
}

/** Detail — matches ClientShipmentRequestShowResource. */
export interface ClientShipmentRequestDetails extends ClientShipmentRequest {
  submissionRevision: number | null;
  cargoWeight: number | null;
  cargoVolume: number | null;
  /** Authoritative TrailerType enum value when set. */
  trailerType: string | null;
  hazardousCargo: boolean;
  temperatureControlled: boolean;
  pickupContact: string | null;
  pickupPhone: string | null;
  pickupAddress: string | null;
  deliveryContact: string | null;
  deliveryPhone: string | null;
  deliveryAddress: string | null;
  pickupLatitude: number | null;
  pickupLongitude: number | null;
  deliveryLatitude: number | null;
  deliveryLongitude: number | null;
  specialInstructions: string | null;
  clientNotes: string | null;
  /** From status-history reason only — never dispatcher_notes. */
  reviewFeedback: ClientReviewFeedback | null;
}

export interface ClientShipmentRequestListMeta {
  currentPage: number;
  perPage: number;
  total: number;
  lastPage: number;
  from: number | null;
  to: number | null;
}

export interface ClientShipmentRequestListResult {
  requests: ClientShipmentRequest[];
  meta: ClientShipmentRequestListMeta;
}

export interface ListClientShipmentRequestsParams {
  page?: number;
  per_page?: number;
  /** Exact shipment_order status.name — Client list filter only (C5.4). */
  status?: string;
}

/** Writable fields allowed by Create/Update ClientShipmentRequestRequest. */
export interface ClientShipmentRequestWritePayload {
  reference_number?: string | null;
  cargo_type?: string | null;
  cargo_description?: string | null;
  trailer_type?: string | null;
  cargo_weight?: number | null;
  cargo_volume?: number | null;
  package_count?: number | null;
  hazardous_cargo?: boolean;
  temperature_controlled?: boolean;
  pickup_company?: string | null;
  pickup_contact?: string | null;
  pickup_phone?: string | null;
  pickup_address?: string | null;
  pickup_city?: string | null;
  pickup_country?: string | null;
  pickup_latitude?: number | null;
  pickup_longitude?: number | null;
  delivery_company?: string | null;
  delivery_contact?: string | null;
  delivery_phone?: string | null;
  delivery_address?: string | null;
  delivery_city?: string | null;
  delivery_country?: string | null;
  delivery_latitude?: number | null;
  delivery_longitude?: number | null;
  requested_pickup_date?: string | null;
  requested_delivery_date?: string | null;
  special_instructions?: string | null;
  client_notes?: string | null;
}
