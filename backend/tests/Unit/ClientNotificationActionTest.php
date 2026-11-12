<?php

namespace Tests\Unit;

use App\Support\ClientNotificationAction;
use PHPUnit\Framework\TestCase;

class ClientNotificationActionTest extends TestCase
{
    public function test_allowlisted_kinds_with_uuid_are_projected(): void
    {
        $id = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

        $this->assertSame(
            ['kind' => 'shipment_details', 'id' => $id],
            ClientNotificationAction::toClientPayload('shipment_details', $id)
        );
        $this->assertSame(
            ['kind' => 'request_details', 'id' => $id],
            ClientNotificationAction::toClientPayload('request_details', $id)
        );
        $this->assertSame(
            ['kind' => 'conversation', 'id' => $id],
            ClientNotificationAction::toClientPayload('conversation', $id)
        );
        $this->assertSame(
            ['kind' => 'shipment_progress', 'id' => $id],
            ClientNotificationAction::toClientPayload('shipment_progress', $id)
        );
    }

    public function test_unknown_missing_and_invalid_targets_are_null(): void
    {
        $id = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

        $this->assertNull(ClientNotificationAction::toClientPayload('assignment', $id));
        $this->assertNull(ClientNotificationAction::toClientPayload('engine', $id));
        $this->assertNull(ClientNotificationAction::toClientPayload('shipment_details', null));
        $this->assertNull(ClientNotificationAction::toClientPayload('shipment_details', ''));
        $this->assertNull(ClientNotificationAction::toClientPayload('shipment_details', 'FX-1024'));
        $this->assertNull(ClientNotificationAction::toClientPayload('shipment_details', 'not-a-uuid'));
        $this->assertNull(ClientNotificationAction::toClientPayload(null, $id));
        $this->assertNull(ClientNotificationAction::toClientPayload('  ', $id));
    }
}
