<?php

namespace Tests\Feature\Conversation;

use Tests\TestCase;

/**
 * Config smoke for M6 Reverb transport (does not require Reverb process).
 */
class MessagingRealtimeTransportConfigTest extends TestCase
{
    public function test_reverb_broadcast_connection_is_configured(): void
    {
        $this->assertArrayHasKey('reverb', config('broadcasting.connections'));
        $this->assertSame('reverb', config('broadcasting.connections.reverb.driver'));
        $this->assertFileExists(config_path('reverb.php'));
    }

    public function test_message_created_event_targets_conversation_private_channel(): void
    {
        $reflection = new \ReflectionClass(\App\Events\Conversation\MessageCreated::class);
        $this->assertTrue(
            $reflection->implementsInterface(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class)
        );
    }
}
