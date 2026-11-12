/**
 * Realtime Connection Interface
 * 
 * Abstract interface for realtime connection management.
 * This is a transport abstraction layer - it knows nothing about vehicles or tracking.
 * Concrete implementations will use Laravel Echo, Socket.io, or other WebSocket libraries.
 */

/**
 * Connection status type
 */
export type ConnectionStatus = 
  | 'disconnected'
  | 'connecting'
  | 'connected'
  | 'reconnecting'
  | 'error';

/**
 * Connection status constants
 */
export const ConnectionStatus = {
  DISCONNECTED: 'disconnected' as const,
  CONNECTING: 'connecting' as const,
  CONNECTED: 'connected' as const,
  RECONNECTING: 'reconnecting' as const,
  ERROR: 'error' as const,
} as const;

/**
 * Connection state interface
 */
export interface ConnectionState {
  status: ConnectionStatus;
  lastConnectedAt: Date | null;
  lastDisconnectedAt: Date | null;
  reconnectAttempts: number;
  error: Error | null;
}

/**
 * Event callback type
 */
export type EventCallback<TData = unknown> = (data: TData) => void;

/**
 * Event registration options
 */
export interface EventRegistrationOptions {
  /**
   * Whether to listen for the event only once
   */
  once?: boolean;
}

/**
 * Realtime connection interface
 * 
 * This interface defines the contract for any realtime connection implementation.
 * It is intentionally generic and domain-agnostic - it only deals with connection
 * lifecycle and event transport, not business logic.
 */
export interface IRealtimeConnection {
  /**
   * Current connection state
   */
  readonly state: ConnectionState;

  /**
   * Connect to the realtime server
   * 
   * @returns Promise that resolves when connected
   * @throws Error if connection fails
   */
  connect(): Promise<void>;

  /**
   * Disconnect from the realtime server
   * 
   * @returns Promise that resolves when disconnected
   */
  disconnect(): Promise<void>;

  /**
   * Reconnect to the realtime server
   * 
   * @returns Promise that resolves when reconnected
   * @throws Error if reconnection fails
   */
  reconnect(): Promise<void>;

  /**
   * Register an event listener for a specific channel
   * 
   * @param channel - The channel name
   * @param event - The event name
   * @param callback - The callback to execute when the event is received
   * @param options - Optional registration options
   * @returns A function to unregister the event listener
   */
  on<TData = unknown>(
    channel: string,
    event: string,
    callback: EventCallback<TData>,
    options?: EventRegistrationOptions
  ): () => void;

  /**
   * Unregister an event listener
   * 
   * @param channel - The channel name
   * @param event - The event name
   * @param callback - The callback to unregister
   */
  off(channel: string, event: string, callback: EventCallback): void;

  /**
   * Subscribe to a channel
   * 
   * @param channel - The channel name
   * @returns Promise that resolves when subscribed
   * @throws Error if subscription fails
   */
  subscribe(channel: string): Promise<void>;

  /**
   * Unsubscribe from a channel
   * 
   * @param channel - The channel name
   * @returns Promise that resolves when unsubscribed
   */
  unsubscribe(channel: string): Promise<void>;

  /**
   * Check if connected to the realtime server
   */
  isConnected(): boolean;

  /**
   * Check if subscribed to a specific channel
   * 
   * @param channel - The channel name
   */
  isSubscribed(channel: string): boolean;
}
