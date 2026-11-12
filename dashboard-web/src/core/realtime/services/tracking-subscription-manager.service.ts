/**
 * Tracking Subscription Manager
 *
 * Domain-specific subscription manager for tracking channels.
 */

import type {
  ITrackingSubscriptionManager,
  SubscriptionState,
  SubscriptionType,
  SubscriptionOptions,
  TrackingUpdateMessage,
  RouteDeviationUpdateMessage,
  RouteUpdatedMessage,
} from '../interfaces/tracking-subscription.interface';
import type { IRealtimeConnection } from '../interfaces/realtime-connection.interface';
import { realtimeConnectionService } from './realtime-connection.service';

export class TrackingSubscriptionManager implements ITrackingSubscriptionManager {
  private _currentSubscription: SubscriptionState | null = null;
  private _connection: IRealtimeConnection;
  private _unsubscribeCallbacks: Array<() => void> = [];

  constructor(connection: IRealtimeConnection) {
    this._connection = connection;
  }

  get currentSubscription(): SubscriptionState | null {
    return this._currentSubscription;
  }

  async subscribeVehicle(vehicleId: string, options: SubscriptionOptions): Promise<void> {
    await this._subscribe('vehicle', vehicleId, this.buildVehicleChannel(vehicleId), options);
  }

  async subscribeShipment(shipmentId: string, options: SubscriptionOptions): Promise<void> {
    await this._subscribe('shipment', shipmentId, this.buildShipmentChannel(shipmentId), options);
  }

  async subscribeFleet(options: SubscriptionOptions): Promise<void> {
    await this._subscribe('fleet', null, this.buildFleetChannel(), options);
  }

  async unsubscribe(): Promise<void> {
    if (!this._currentSubscription) {
      return;
    }

    for (const unsubscribe of this._unsubscribeCallbacks) {
      unsubscribe();
    }
    this._unsubscribeCallbacks = [];

    await this._connection.unsubscribe(this._currentSubscription.channel);
    this._currentSubscription = null;
  }

  isSubscribed(): boolean {
    return this._currentSubscription !== null && this._currentSubscription.subscribed;
  }

  getSubscriptionType(): SubscriptionType | null {
    return this._currentSubscription?.type || null;
  }

  private async _subscribe(
    type: SubscriptionType,
    identifier: string | null,
    channel: string,
    options: SubscriptionOptions
  ): Promise<void> {
    if (this._currentSubscription) {
      await this.unsubscribe();
    }

    try {
      await this._connection.subscribe(channel);

      const unsubUpdate = this._connection.on<TrackingUpdateMessage>(
        channel,
        '.tracking.state.updated',
        (data: TrackingUpdateMessage) => {
          options.onUpdate(data);
          if (this._currentSubscription) {
            this._currentSubscription.lastUpdateAt = new Date();
          }
        }
      );
      this._unsubscribeCallbacks.push(unsubUpdate);

      if (options.onRouteDeviation) {
        const unsubDeviation = this._connection.on<RouteDeviationUpdateMessage>(
          channel,
          '.tracking.route.deviation',
          (data: RouteDeviationUpdateMessage) => {
            options.onRouteDeviation?.(data);
            if (this._currentSubscription) {
              this._currentSubscription.lastUpdateAt = new Date();
            }
          }
        );
        this._unsubscribeCallbacks.push(unsubDeviation);
      }

      if (options.onRouteUpdated) {
        const unsubRouteUpdate = this._connection.on<RouteUpdatedMessage>(
          channel,
          '.tracking.route.updated',
          (data: RouteUpdatedMessage) => {
            options.onRouteUpdated?.(data);
            if (this._currentSubscription) {
              this._currentSubscription.lastUpdateAt = new Date();
            }
          }
        );
        this._unsubscribeCallbacks.push(unsubRouteUpdate);
      }

      this._currentSubscription = {
        type,
        identifier,
        channel,
        subscribed: true,
        subscribedAt: new Date(),
        lastUpdateAt: new Date(),
      };

      options.onSubscribed?.();
    } catch (error) {
      options.onError?.(error as Error);
      throw error;
    }
  }

  private buildVehicleChannel(vehicleId: string): string {
    return `tracking.vehicle.${vehicleId}`;
  }

  private buildShipmentChannel(shipmentId: string): string {
    return `tracking.shipment.${shipmentId}`;
  }

  private buildFleetChannel(): string {
    return 'tracking.fleet';
  }
}

export const trackingSubscriptionManager = new TrackingSubscriptionManager(realtimeConnectionService);
