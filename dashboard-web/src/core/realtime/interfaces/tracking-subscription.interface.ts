/**
 * Tracking Subscription Interface
 * 
 * Domain-specific interfaces for tracking subscriptions.
 * These interfaces define the contract for tracking-related realtime operations.
 */

/**
 * Tracking update message from backend
 * Matches the broadcast payload from TrackingStateBroadcasted event
 */
export interface TrackingUpdateMessage {
  vehicle_id: string;
  shipment_id: string | null;
  assignment_id: string | null;
  location: {
    latitude: number;
    longitude: number;
  };
  telemetry: {
    heading: number;
    speed: number;
    accuracy: number;
    signal_quality: number;
  };
  status: {
    movement_status: string;
    online_status: string;
  };
  timestamp: string;
}

/**
 * Route deviation broadcast payload (tracking.route.deviation)
 */
export interface RouteDeviationUpdateMessage {
  vehicle_id: string;
  shipment_id: string;
  assignment_id: string | null;
  deviation_distance: number;
  location: {
    latitude: number;
    longitude: number;
  };
  timestamp: string;
}

/**
 * Route updated broadcast payload (tracking.route.updated)
 */
export interface RouteUpdatedMessage {
  shipment_id: string;
  vehicle_id: string | null;
  assignment_id: string | null;
  route?: {
    id?: string;
    origin?: { latitude: number; longitude: number };
    destination?: { latitude: number; longitude: number };
    distance?: unknown;
    duration?: unknown;
    eta?: string;
    geometry?: {
      encoded?: string;
      coordinates?: Array<[number, number] | { latitude: number; longitude: number }>;
    };
    coordinates?: Array<[number, number] | { latitude: number; longitude: number }>;
  } | null;
  metadata?: {
    is_reroute?: boolean;
    broadcast_at?: string;
    created_at?: string;
    [key: string]: unknown;
  };
}

/**
 * Tracking update callback type
 */
export type TrackingUpdateCallback = (update: TrackingUpdateMessage) => void;

/**
 * Route deviation callback type
 */
export type RouteDeviationCallback = (update: RouteDeviationUpdateMessage) => void;

/**
 * Route updated callback type
 */
export type RouteUpdatedCallback = (update: RouteUpdatedMessage) => void;

/**
 * Subscription type enumeration
 */
export type SubscriptionType =
  | 'vehicle'
  | 'shipment'
  | 'fleet';

/**
 * Subscription state interface
 */
export interface SubscriptionState {
  type: SubscriptionType;
  identifier: string | null;
  channel: string;
  subscribed: boolean;
  subscribedAt: Date | null;
  lastUpdateAt: Date | null;
}

/**
 * Subscription options
 */
export interface SubscriptionOptions {
  onUpdate: TrackingUpdateCallback;
  onRouteDeviation?: RouteDeviationCallback;
  onRouteUpdated?: RouteUpdatedCallback;
  onSubscribed?: () => void;
  onError?: (error: Error) => void;
  onUnsubscribed?: () => void;
}

/**
 * Tracking subscription manager interface
 * 
 * This interface defines the contract for managing tracking-specific subscriptions.
 * It abstracts away the channel naming and event routing logic.
 */
export interface ITrackingSubscriptionManager {
  /**
   * Current subscription state
   */
  readonly currentSubscription: SubscriptionState | null;

  /**
   * Subscribe to vehicle-specific tracking updates
   * 
   * @param vehicleId - The vehicle ID
   * @param options - Subscription options
   * @returns Promise that resolves when subscribed
   */
  subscribeVehicle(vehicleId: string, options: SubscriptionOptions): Promise<void>;

  /**
   * Subscribe to shipment-specific tracking updates
   * 
   * @param shipmentId - The shipment ID
   * @param options - Subscription options
   * @returns Promise that resolves when subscribed
   */
  subscribeShipment(shipmentId: string, options: SubscriptionOptions): Promise<void>;

  /**
   * Subscribe to fleet-wide tracking updates
   * 
   * @param options - Subscription options
   * @returns Promise that resolves when subscribed
   */
  subscribeFleet(options: SubscriptionOptions): Promise<void>;

  /**
   * Unsubscribe from current subscription
   * 
   * @returns Promise that resolves when unsubscribed
   */
  unsubscribe(): Promise<void>;

  /**
   * Check if currently subscribed
   */
  isSubscribed(): boolean;

  /**
   * Get the current subscription type
   */
  getSubscriptionType(): SubscriptionType | null;
}
