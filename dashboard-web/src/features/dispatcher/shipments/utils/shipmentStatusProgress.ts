/** Lifecycle ordinal progress derived from official ShipmentSeeder status names. */
export const SHIPMENT_STATUS_PROGRESS: Record<string, number> = {
  'Draft': 0,
  'Pending Review': 8,
  'Approved': 15,
  'Waiting Assignment': 20,
  'Assigned': 30,
  'Ready For Pickup': 40,
  'Picked Up': 50,
  'In Transit': 65,
  'At Customs': 75,
  'Out For Delivery': 85,
  'Delivered': 95,
  'Completed': 100,
  'Cancelled': 0,
};

export function getShipmentStatusProgress(status: string): number | null {
  if (!(status in SHIPMENT_STATUS_PROGRESS)) {
    return null;
  }
  return SHIPMENT_STATUS_PROGRESS[status];
}
