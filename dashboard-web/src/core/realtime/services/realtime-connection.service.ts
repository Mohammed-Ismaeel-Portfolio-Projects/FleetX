/**
 * Realtime Connection Service
 * 
 * Transport abstraction layer for realtime connections.
 * This service knows nothing about vehicles or tracking - it only handles
 * connection lifecycle and event transport.
 * 
 * This implementation uses Laravel Echo for WebSocket communication.
 */

import type {
  IRealtimeConnection,
  ConnectionState,
  ConnectionStatus,
  EventCallback,
  EventRegistrationOptions,
} from '../interfaces/realtime-connection.interface';
import { destroyEcho, initializeEcho } from '../config/echo.config';

/**
 * Realtime Connection Service
 * 
 * Implements the realtime connection using Laravel Echo.
 * This service is domain-agnostic and only handles transport concerns.
 */
export class RealtimeConnectionService implements IRealtimeConnection {
  private _state: ConnectionState = {
    status: 'disconnected' as ConnectionStatus,
    lastConnectedAt: null,
    lastDisconnectedAt: null,
    reconnectAttempts: 0,
    error: null,
  };

  private _echo: any = null;
  private _subscriptions = new Map<string, any>();
  private _eventCallbacks = new Map<string, Map<string, Set<EventCallback>>>();
  private _reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  private _maxReconnectAttempts = 5;
  private _reconnectDelay = 3000; // 3 seconds
  private _connectWaiters: Array<{
    resolve: () => void;
    reject: (error: Error) => void;
  }> = [];
  private _handlersBound = false;

  /**
   * Current connection state
   */
  get state(): ConnectionState {
    return { ...this._state };
  }

  /**
   * Connect to the realtime server.
   * Resolves only after the underlying Pusher connection reports "connected".
   */
  async connect(): Promise<void> {
    if (this._state.status === 'connected' && this._echo) {
      return;
    }

    if (this._state.status === 'connecting') {
      return new Promise<void>((resolve, reject) => {
        this._connectWaiters.push({ resolve, reject });
      });
    }

    this.setState('connecting');

    try {
      this._echo = initializeEcho();
      this._bindConnectionHandlers();

      const pusherState = this._echo.connector?.pusher?.connection?.state;
      if (pusherState === 'connected') {
        this.setState('connected');
        this._reconnectAttempts = 0;
        return;
      }

      this._echo.connector.connect();

      await new Promise<void>((resolve, reject) => {
        const timeout = setTimeout(() => {
          reject(new Error('Realtime connection timed out'));
        }, 12_000);

        this._connectWaiters.push({
          resolve: () => {
            clearTimeout(timeout);
            resolve();
          },
          reject: (error) => {
            clearTimeout(timeout);
            reject(error);
          },
        });
      });
    } catch (error) {
      this.setState('error', error as Error);
      this._rejectWaiters(error as Error);
      this._scheduleReconnect();
      throw error;
    }
  }

  private _bindConnectionHandlers(): void {
    if (!this._echo || this._handlersBound) return;
    this._handlersBound = true;

    const connection = this._echo.connector.pusher.connection;

    connection.bind('connected', () => {
      this.setState('connected');
      this._reconnectAttempts = 0;
      this._resolveWaiters();
    });

    connection.bind('disconnected', () => {
      this.setState('disconnected');
      this._scheduleReconnect();
    });

    connection.bind('error', (error: Error) => {
      this.setState('error', error);
      this._rejectWaiters(error instanceof Error ? error : new Error('Realtime connection error'));
      this._scheduleReconnect();
    });

    connection.bind('unavailable', () => {
      const err = new Error('Connection unavailable');
      this.setState('error', err);
      this._rejectWaiters(err);
      this._scheduleReconnect();
    });
  }

  private _resolveWaiters(): void {
    const waiters = this._connectWaiters.splice(0);
    waiters.forEach((w) => w.resolve());
  }

  private _rejectWaiters(error: Error): void {
    const waiters = this._connectWaiters.splice(0);
    waiters.forEach((w) => w.reject(error));
  }

  /**
   * Disconnect from the realtime server
   */
  async disconnect(): Promise<void> {
    this._clearReconnectTimer();
    this._handlersBound = false;
    this._rejectWaiters(new Error('Disconnected'));

    if (this._echo) {
      destroyEcho();
      this._echo = null;
    }

    this._subscriptions.clear();
    this._eventCallbacks.clear();
    this.setState('disconnected');
  }

  /**
   * Reconnect to the realtime server
   */
  async reconnect(): Promise<void> {
    await this.disconnect();
    await this.connect();
  }

  /**
   * Register an event listener for a specific channel
   */
  on<TData = unknown>(
    channel: string,
    event: string,
    callback: EventCallback<TData>,
    options?: EventRegistrationOptions
  ): () => void {
    if (!this._echo) {
      console.warn('Echo not initialized. Event listener not registered.');
      return () => {};
    }

    // Store callback for cleanup
    if (!this._eventCallbacks.has(channel)) {
      this._eventCallbacks.set(channel, new Map());
    }
    const channelCallbacks = this._eventCallbacks.get(channel)!;
    
    if (!channelCallbacks.has(event)) {
      channelCallbacks.set(event, new Set());
    }
    const eventCallbacks = channelCallbacks.get(event)!;
    eventCallbacks.add(callback);

    // Register with Echo
    const echoChannel = this._echo.private(channel);
    
    if (options?.once) {
      echoChannel.listen(event, (data: TData) => {
        callback(data);
        this.off(channel, event, callback);
      });
    } else {
      echoChannel.listen(event, (data: TData) => {
        callback(data);
      });
    }

    // Return unsubscribe function
    return () => this.off(channel, event, callback);
  }

  /**
   * Unregister an event listener
   */
  off(channel: string, event: string, callback: EventCallback): void {
    if (!this._echo) {
      return;
    }

    // Remove from our tracking
    const channelCallbacks = this._eventCallbacks.get(channel);
    if (channelCallbacks) {
      const eventCallbacks = channelCallbacks.get(event);
      if (eventCallbacks) {
        eventCallbacks.delete(callback);
      }
    }

    // Stop listening via Echo
    const echoChannel = this._echo.private(channel);
    echoChannel.stopListening(event);
  }

  /**
   * Subscribe to a channel
   */
  async subscribe(channel: string): Promise<void> {
    if (!this._echo) {
      throw new Error('Echo not initialized. Call connect() first.');
    }

    if (this._subscriptions.has(channel)) {
      return; // Already subscribed
    }

    try {
      const echoChannel = this._echo.private(channel);
      
      // Wait for subscription to complete
      await new Promise<void>((resolve, reject) => {
        echoChannel.subscribe((error?: Error) => {
          if (error) {
            reject(error);
          } else {
            resolve();
          }
        });
      });

      this._subscriptions.set(channel, echoChannel);
    } catch (error) {
      throw new Error(`Failed to subscribe to channel ${channel}: ${error}`);
    }
  }

  /**
   * Unsubscribe from a channel
   */
  async unsubscribe(channel: string): Promise<void> {
    if (!this._echo) {
      return;
    }

    const echoChannel = this._subscriptions.get(channel);
    if (echoChannel) {
      echoChannel.unsubscribe();
      this._subscriptions.delete(channel);
    }

    // Clean up event callbacks for this channel
    this._eventCallbacks.delete(channel);
  }

  /**
   * Check if connected to the realtime server
   */
  isConnected(): boolean {
    return this._state.status === 'connected';
  }

  /**
   * Check if subscribed to a specific channel
   */
  isSubscribed(channel: string): boolean {
    return this._subscriptions.has(channel);
  }

  /**
   * Update connection state
   */
  private setState(status: ConnectionStatus, error: Error | null = null): void {
    this._state = {
      status,
      lastConnectedAt: status === 'connected' ? new Date() : this._state.lastConnectedAt,
      lastDisconnectedAt: status === 'disconnected' ? new Date() : this._state.lastDisconnectedAt,
      reconnectAttempts: status === 'reconnecting' ? this._state.reconnectAttempts + 1 : this._state.reconnectAttempts,
      error,
    };
  }

  /**
   * Schedule automatic reconnection
   */
  private _scheduleReconnect(): void {
    if (this._state.reconnectAttempts >= this._maxReconnectAttempts) {
      console.warn('Max reconnect attempts reached. Stopping reconnection.');
      return;
    }

    if (this._reconnectTimer) {
      clearTimeout(this._reconnectTimer);
    }

    this.setState('reconnecting');

    this._reconnectTimer = setTimeout(() => {
      void this.reconnect().catch((error: Error) => {
        // connect() already schedules the next attempt on failure
        console.warn('Realtime reconnect failed:', error.message);
      });
    }, this._reconnectDelay);
  }

  /**
   * Clear reconnect timer
   */
  private _clearReconnectTimer(): void {
    if (this._reconnectTimer) {
      clearTimeout(this._reconnectTimer);
      this._reconnectTimer = null;
    }
  }

  /**
   * Get reconnect attempts (for testing)
   */
  get _reconnectAttempts(): number {
    return this._state.reconnectAttempts;
  }

  /**
   * Set reconnect attempts (for testing)
   */
  set _reconnectAttempts(value: number) {
    this._state.reconnectAttempts = value;
  }
}

/**
 * Singleton instance
 */
export const realtimeConnectionService = new RealtimeConnectionService();
