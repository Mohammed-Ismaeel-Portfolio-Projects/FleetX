/**
 * Laravel Echo Configuration
 *
 * Shared singleton used by Tracking and Communication.
 *
 * Transport: Laravel Reverb (Pusher protocol) when enabled.
 * Set VITE_REALTIME_ENABLED=true only when `php artisan reverb:start` (or equivalent) is running.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { FLEETX_TOKEN_KEY } from '../../auth/sessionLifecycle';

function apiBaseUrl(): string {
  const raw = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1';
  return raw.replace(/\/$/, '');
}

function envString(name: string, fallback: string): string {
  const value = import.meta.env[name];
  return typeof value === 'string' && value.length > 0 ? value : fallback;
}

/**
 * Prefer Reverb env vars (from `php artisan reverb:install`), fall back to VITE_PUSHER_*.
 * Defaults match local Reverb (localhost:8080, http).
 */
export function getEchoConfig() {
  const authEndpoint = `${apiBaseUrl()}/broadcasting/auth`;
  const key = envString('VITE_REVERB_APP_KEY', envString('VITE_PUSHER_APP_KEY', 'fleetx-key'));
  const host = envString('VITE_REVERB_HOST', envString('VITE_PUSHER_HOST', 'localhost'));
  const portRaw = envString('VITE_REVERB_PORT', envString('VITE_PUSHER_PORT', '8080'));
  const scheme = envString('VITE_REVERB_SCHEME', envString('VITE_PUSHER_SCHEME', 'http'));
  const port = parseInt(portRaw, 10) || 8080;
  const forceTLS = scheme === 'https';

  return {
    broadcaster: 'pusher' as const,
    key,
    cluster: envString('VITE_PUSHER_APP_CLUSTER', 'mt1'),
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS,
    enabledTransports: ['ws', 'wss'] as const,
    disableStats: true,
    authEndpoint,
    authorizer: (channel: { name: string }) => ({
      authorize: (
        socketId: string,
        callback: (error: Error | null, data: unknown) => void
      ) => {
        const token = localStorage.getItem(FLEETX_TOKEN_KEY);
        fetch(authEndpoint, {
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
        })
          .then(async (response) => {
            if (!response.ok) {
              const err = new Error(`Broadcast auth failed (${response.status})`);
              callback(err, null);
              return;
            }
            const data = await response.json();
            callback(null, data);
          })
          .catch((error: Error) => {
            callback(error, null);
          });
      },
    }),
  };
}

export function createEcho(): any {
  const config = getEchoConfig();
  (window as any).Pusher = Pusher;
  return new Echo(config);
}

export let echoInstance: any = null;

export function initializeEcho(): any {
  if (!echoInstance) {
    echoInstance = createEcho();
  }
  return echoInstance;
}

/**
 * Force a fresh Echo instance (e.g. after JWT login or env change).
 */
export function reinitializeEcho(): any {
  destroyEcho();
  return initializeEcho();
}

export function destroyEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}
