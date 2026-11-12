<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Log broadcaster that STILL enforces private-channel authorization.
 *
 * Laravel's stock LogBroadcaster::auth() is a no-op, which would allow any
 * authenticated JWT user to "subscribe" without ConversationPolicy checks.
 * This driver keeps log-only delivery for BROADCAST_CONNECTION=log while
 * verifying channel callbacks (participant OR conversation.manage).
 */
final class AuthorizedLogBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function auth($request)
    {
        $channelName = $this->normalizeChannelName((string) $request->channel_name);

        if (empty($request->channel_name) ||
            ($this->isGuardedChannel($request->channel_name) &&
                ! $this->retrieveUser($request, $channelName))) {
            throw new AccessDeniedHttpException;
        }

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    /**
     * {@inheritdoc}
     */
    public function validAuthenticationResponse($request, $result)
    {
        // Socket signature is meaningless without a WS provider; still return a
        // structured OK so clients/tests can distinguish authorized vs denied.
        if (is_bool($result)) {
            return ['auth' => 'log:authorized'];
        }

        return [
            'auth' => 'log:authorized',
            'channel_data' => json_encode([
                'user_id' => $request->user()?->getAuthIdentifier(),
                'user_info' => $result,
            ]),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $channelList = implode(', ', $this->formatChannels($channels));
        $encoded = json_encode($payload, JSON_PRETTY_PRINT);

        $this->logger->info(
            'Broadcasting ['.$event.'] on channels ['.$channelList.'] with payload:'.PHP_EOL.$encoded
        );
    }
}
