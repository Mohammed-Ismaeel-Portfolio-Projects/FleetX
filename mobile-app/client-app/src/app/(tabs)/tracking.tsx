import { Redirect } from 'expo-router';

/**
 * C3.2 — Tracking is shipment-centric, not a primary tab.
 * Former `/(tabs)/tracking` deep links redirect to Shipments.
 * Progress: Shipments → detail → /shipment-tracking/[id].
 */
export default function TrackingTabRedirect() {
  return <Redirect href="/(tabs)/shipments" />;
}
