/**
 * Driver assignment history types (D11) — mirror Driver history/summary resources.
 */

export type DriverHistoryOutcome = {
  key: string;
  label: string;
};

export type DriverHistoryPlace = {
  company: string | null;
  city: string | null;
  country: string | null;
  address?: string | null;
};

export type DriverHistoryListItem = {
  id: number;
  assignmentCode: string | null;
  status: { name: string; key: string | null } | null;
  outcome: DriverHistoryOutcome;
  executionPhase: string | null;
  shipmentNumber: string | null;
  pickup: DriverHistoryPlace;
  delivery: DriverHistoryPlace;
  plannedPickupDate: string | null;
  plannedDeliveryDate: string | null;
  assignedAt: string | null;
  endedAt: string | null;
};

export type DriverHistoryPage = {
  items: DriverHistoryListItem[];
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
};

export type DriverAssignmentSummary = {
  id: number;
  assignmentCode: string | null;
  status: { name: string; key: string | null } | null;
  outcome: DriverHistoryOutcome;
  executionPhase: string | null;
  assignedAt: string | null;
  endedAt: string | null;
  shipment: {
    id: string;
    shipmentNumber: string | null;
    referenceNumber: string | null;
    status: string | null;
    pickup: DriverHistoryPlace;
    delivery: DriverHistoryPlace;
    plannedPickupDate: string | null;
    plannedDeliveryDate: string | null;
  } | null;
  vehicle: { plateNumber: string | null; fleetNumber: string | null } | null;
  trailer: { trailerCode: string | null; plateNumber: string | null } | null;
  verification: {
    pickup: { verified: boolean; verifiedAt: string | null };
    delivery: { verified: boolean; verifiedAt: string | null };
  };
  proof: {
    exists: boolean;
    confirmedAt: string | null;
    types: string[];
  };
  exceptions: {
    type: string;
    label: string;
    description: string | null;
    status: string | null;
    createdAt: string | null;
  }[];
  safety: {
    speedAlertCount: number;
    harshBrakingCount: number;
    suddenStopCount: number;
    motionAlertCount: number;
  };
};
