/**
 * Mobile Echo / Reverb adapter — same Pusher protocol + channel contract as Dashboard M5/M6.
 * M8: single AppState owner, one active conversation subscription, honest disconnect status.
 */

import Echo from 'laravel-echo';
import { AppState, type AppStateStatus, Platform } from 'react-native';
import { AppConfig } from '../../lib/config';
import { getAccessToken } from '../../lib/auth/tokenStorage';
import { mapApiMessage } from './messagingApi';
import type { Message, RealtimeStatus } from './types';

// Use core pusher-js (works on Expo web + RN). Avoid pusher-js/react-native
// which requires @react-native-community/netinfo at Metro resolve time.
// eslint-disable-next-line @typescript-eslint/no-require-imports
const Pusher = require('pusher-js');

export interface MessageCreatedPayload {
  id?: string;
  conversation_id?: string;
  sender_user_id?: number;
  body?: string;
  sender?: { id: number; name: string; email: string } | null;
  created_at?: string | null;
  updated_at?: string | null;
  conversation_updated_at?: string | null;
}

export const MESSAGE_CREATED_EVENT = '.message.created';

export function isMessagingRealtimeEnabled(): boolean {
  return AppConfig.realtimeEnabled;
}

export function conversationChannelName(conversationId: string): string {
  return `conversation.${conversationId}`;
}

export function isCompleteMessagePayload(
  payload: MessageCreatedPayload | null | undefined
): payload is MessageCreatedPayload & {
  id: string;
  conversation_id: string;
  sender_user_id: number;
  body: string;
} {
  return Boolean(
    payload &&
      typeof payload.id === 'string' &&
      payload.id.length > 0 &&
      typeof payload.conversation_id === 'string' &&
      payload.conversation_id.length > 0 &&
      payload.sender_user_id != null &&
      typeof payload.body === 'string'
  );
}

export function mapRealtimeMessage(payload: MessageCreatedPayload): Message | null {
  if (!isCompleteMessagePayload(payload)) return null;
  return mapApiMessage({
    id: payload.id,
    conversation_id: payload.conversation_id,
    sender_user_id: payload.sender_user_id,
    body: payload.body,
    sender: payload.sender ?? null,
    created_at: payload.created_at ?? null,
    updated_at: payload.updated_at ?? null,
  });
}

type StatusListener = (status: RealtimeStatus) => void;
type ForegroundListener = () => void;

class MobileRealtimeService {
  private echo: Echo<'pusher'> | null = null;
  private status: RealtimeStatus = 'disconnected';
  private listeners = new Set<StatusListener>();
  private foregroundListeners = new Set<ForegroundListener>();
  private appStateSub: { remove: () => void } | null = null;
  private webVisHandler: ((this: Document, ev: Event) => void) | null = null;
  private lifecycleBound = false;
  private paused = false;
  private activeConversationId: string | null = null;
  private activeUnsubscribe: (() => void) | null = null;
  /** Instrumentation for M8 subscription leak checks. */
  private subscribeCount = 0;
  private unsubscribeCount = 0;

  getStatus(): RealtimeStatus {
    if (!isMessagingRealtimeEnabled()) return 'disabled';
    return this.status;
  }

  getSubscriptionStats(): {
    subscribeCount: number;
    unsubscribeCount: number;
    active: string | null;
  } {
    return {
      subscribeCount: this.subscribeCount,
      unsubscribeCount: this.unsubscribeCount,
      active: this.activeConversationId,
    };
  }

  onStatus(listener: StatusListener): () => void {
    this.listeners.add(listener);
    listener(this.getStatus());
    return () => this.listeners.delete(listener);
  }

  /**
   * Screens register once; AppState ownership stays in this service (no dual listeners).
   */
  onForeground(listener: ForegroundListener): () => void {
    this.foregroundListeners.add(listener);
    return () => this.foregroundListeners.delete(listener);
  }

  private setStatus(next: RealtimeStatus) {
    this.status = next;
    for (const l of this.listeners) l(this.getStatus());
  }

  private authEndpoint(): string {
    return `${AppConfig.apiBaseUrl.replace(/\/$/, '')}/broadcasting/auth`;
  }

  async connect(): Promise<RealtimeStatus> {
    if (!isMessagingRealtimeEnabled()) {
      this.setStatus('disabled');
      return 'disabled';
    }
    if (this.paused) {
      this.setStatus('disconnected');
      return 'disconnected';
    }

    if (this.echo) {
      const conn = (this.echo.connector as { pusher?: { connection?: { state?: string } } })
        ?.pusher?.connection?.state;
      if (conn === 'connected') {
        this.setStatus('connected');
        return 'connected';
      }
      // Dead/stalled connector — recreate.
      this.disconnect();
    }

    this.setStatus('connecting');
    this.ensureLifecycleHandling();

    try {
      this.echo = this.createEcho();
      await this.waitForConnected(12000);
      this.bindLifecycleOnce();
      this.setStatus('connected');
      return 'connected';
    } catch {
      this.disconnect();
      this.setStatus('rest_fallback');
      return 'rest_fallback';
    }
  }

  /** Explicit reconnect used by UI when status is REST fallback / disconnected. */
  async reconnect(): Promise<RealtimeStatus> {
    this.paused = false;
    this.disconnect();
    return this.connect();
  }

  private createEcho(): Echo<'pusher'> {
    const forceTLS = AppConfig.reverbScheme === 'https';
    const authEndpoint = this.authEndpoint();

    (globalThis as { Pusher?: unknown }).Pusher = Pusher;

    return new Echo({
      broadcaster: 'pusher',
      key: AppConfig.reverbKey,
      cluster: 'mt1',
      wsHost: AppConfig.reverbHost,
      wsPort: AppConfig.reverbPort,
      wssPort: AppConfig.reverbPort,
      forceTLS,
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      authEndpoint,
      authorizer: ((channel: { name: string }) => ({
        authorize: (
          socketId: string,
          callback: (error: Error | null, data: unknown) => void
        ) => {
          void (async () => {
            try {
              const token = await getAccessToken();
              const response = await fetch(authEndpoint, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  Accept: 'application/json',
                  ...(token ? { Authorization: `Bearer ${token}` } : {}),
                },
                body: JSON.stringify({
                  socket_id: socketId,
                  channel_name: channel.name,
                }),
              });
              if (!response.ok) {
                callback(new Error(`Broadcast auth failed (${response.status})`), null);
                return;
              }
              const data = await response.json();
              callback(null, data);
            } catch (error) {
              callback(error instanceof Error ? error : new Error('Broadcast auth error'), null);
            }
          })();
        },
      })) as any,
    });
  }

  private getPusherConnection() {
    return (this.echo?.connector as {
      pusher?: {
        connection?: {
          state?: string;
          bind: (event: string, cb: (...args: unknown[]) => void) => void;
          unbind: (event: string, cb?: (...args: unknown[]) => void) => void;
        };
      };
    })?.pusher?.connection;
  }

  private waitForConnected(timeoutMs: number): Promise<void> {
    return new Promise((resolve, reject) => {
      const pusher = this.getPusherConnection();

      if (!pusher) {
        reject(new Error('No pusher connection'));
        return;
      }
      if (pusher.state === 'connected') {
        resolve();
        return;
      }

      const onConnected = () => {
        cleanup();
        resolve();
      };
      const onError = () => {
        cleanup();
        reject(new Error('Pusher connection failed'));
      };
      const timer = setTimeout(() => {
        cleanup();
        reject(new Error('Pusher connect timeout'));
      }, timeoutMs);

      const cleanup = () => {
        clearTimeout(timer);
        pusher.unbind('connected', onConnected);
        pusher.unbind('unavailable', onError);
        pusher.unbind('failed', onError);
      };

      pusher.bind('connected', onConnected);
      pusher.bind('unavailable', onError);
      pusher.bind('failed', onError);
    });
  }

  private bindLifecycleOnce() {
    if (this.lifecycleBound) return;
    const pusher = this.getPusherConnection();
    if (!pusher) return;

    pusher.bind('connecting', () => {
      if (!this.paused) this.setStatus('connecting');
    });
    pusher.bind('connecting_in', () => {
      if (!this.paused) this.setStatus('reconnecting');
    });
    pusher.bind('disconnected', () => {
      if (this.paused || !isMessagingRealtimeEnabled()) {
        this.setStatus('disconnected');
      } else {
        this.setStatus('rest_fallback');
      }
    });
    this.lifecycleBound = true;
  }

  private onEnterBackground() {
    this.paused = true;
    this.clearActiveSubscription();
    this.disconnect();
    this.setStatus('disconnected');
  }

  private onEnterForeground() {
    this.paused = false;
    void this.connect().then(() => {
      for (const l of this.foregroundListeners) l();
    });
  }

  private ensureLifecycleHandling() {
    if (this.appStateSub || this.webVisHandler) return;

    if (Platform.OS === 'web' && typeof document !== 'undefined') {
      this.webVisHandler = () => {
        if (document.visibilityState === 'hidden') {
          this.onEnterBackground();
        } else if (document.visibilityState === 'visible') {
          this.onEnterForeground();
        }
      };
      document.addEventListener('visibilitychange', this.webVisHandler);
      return;
    }

    if (Platform.OS === 'web') return;

    this.appStateSub = AppState.addEventListener('change', (next: AppStateStatus) => {
      if (next === 'background' || next === 'inactive') {
        this.onEnterBackground();
      } else if (next === 'active') {
        this.onEnterForeground();
      }
    });
  }

  private clearActiveSubscription() {
    if (this.activeUnsubscribe) {
      try {
        this.activeUnsubscribe();
      } catch {
        // ignore
      }
      this.activeUnsubscribe = null;
      // leave() already increments unsubscribeCount when it clears active id
    }
    this.activeConversationId = null;
  }

  disconnect() {
    this.clearActiveSubscription();
    if (this.echo) {
      try {
        this.echo.disconnect();
      } catch {
        // ignore
      }
      this.echo = null;
    }
    this.lifecycleBound = false;
  }

  destroy() {
    this.appStateSub?.remove();
    this.appStateSub = null;
    if (this.webVisHandler && typeof document !== 'undefined') {
      document.removeEventListener('visibilitychange', this.webVisHandler);
      this.webVisHandler = null;
    }
    this.paused = false;
    this.foregroundListeners.clear();
    this.disconnect();
    this.listeners.clear();
    this.setStatus('disconnected');
  }

  /**
   * Subscribe to exactly one private conversation channel.
   * Replaces any previous subscription (prevents listener multiplication).
   */
  async subscribeConversation(
    conversationId: string,
    onMessage: (payload: MessageCreatedPayload) => void,
    onError?: (error: Error) => void
  ): Promise<() => void> {
    if (!isMessagingRealtimeEnabled()) {
      return () => {};
    }

    this.clearActiveSubscription();

    const status = await this.connect();
    if (status !== 'connected' || !this.echo) {
      onError?.(new Error('Realtime unavailable — using REST'));
      return () => {};
    }

    const name = conversationChannelName(conversationId);
    try {
      const channel = this.echo.private(name);
      channel.listen(MESSAGE_CREATED_EVENT, onMessage);
      this.activeConversationId = conversationId;
      this.subscribeCount += 1;

      const leave = () => {
        if (this.activeConversationId !== conversationId) return;
        try {
          channel.stopListening(MESSAGE_CREATED_EVENT);
          this.echo?.leave(name);
        } catch {
          // ignore
        }
        if (this.activeConversationId === conversationId) {
          this.activeConversationId = null;
          this.activeUnsubscribe = null;
          this.unsubscribeCount += 1;
        }
      };

      this.activeUnsubscribe = leave;
      return leave;
    } catch (error) {
      onError?.(error instanceof Error ? error : new Error('Subscribe failed'));
      this.setStatus('permission_denied');
      return () => {};
    }
  }
}

export const mobileRealtime = new MobileRealtimeService();
