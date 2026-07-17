<?php

namespace Tests\Feature;

use App\Events\BusinessNotificationRequested;
use App\Listeners\OrchestrateBusinessNotification;
use App\Models\User;
use App\Models\WhatsApp\WhatsAppConnection;
use App\Models\WhatsApp\WhatsAppContactPreference;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\WhatsApp\WhatsAppMessageRecipient;
use App\Models\WhatsApp\WhatsAppNotificationRule;
use App\Models\WhatsApp\WhatsAppOptOut;
use App\Models\WhatsApp\WhatsAppWebhookEvent;
use App\Services\WhatsApp\PhoneNumberNormalizer;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Services\WhatsApp\WhatsAppTemplateSyncService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WhatsAppNotificationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(WhatsAppNotificationRuleSeeder::class);
        config([
            'whatsapp.phone_number_id' => 'PN-1',
            'whatsapp.business_account_id' => 'BA-1',
            'whatsapp.access_token' => 'secret-token-do-not-log',
            'whatsapp.verify_token' => 'verify-me',
            'whatsapp.webhook_secret' => '',
            'whatsapp.api_version' => 'v23.0',
            'whatsapp.default_country' => 'AF',
            'whatsapp.outgoing_paused' => false,
        ]);
    }

    private function seedConnection(): void
    {
        WhatsAppConnection::query()->create([
            'phone_number_id' => 'PN-1',
            'business_account_id' => 'BA-1',
            'access_token' => 'secret-token-do-not-log',
            'status' => 'connected',
            'is_paused' => false,
        ]);
    }

    public function test_phone_normalization_afghanistan_formats(): void
    {
        $n = app(PhoneNumberNormalizer::class);
        $this->assertSame('+93791234567', $n->normalize('0791234567'));
        $this->assertSame('+93701234567', $n->normalize('+93 70 123 4567'));
        $this->assertSame('+93701234567', $n->normalize('9370-123-4567'));
        $this->assertNull($n->tryNormalize('123'));
        $this->assertSame('+14155552671', $n->normalize('+1 (415) 555-2671'));
    }

    public function test_webhook_verification_challenge(): void
    {
        $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=abc123')
            ->assertOk()
            ->assertSee('abc123');

        $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')
            ->assertStatus(403);
    }

    public function test_webhook_deduplication(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'BA-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.TEST1',
                            'status' => 'delivered',
                            'timestamp' => (string) time(),
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/v1/whatsapp/webhook', $payload)->assertOk();
        $this->postJson('/api/v1/whatsapp/webhook', $payload)->assertOk();

        $this->assertSame(1, WhatsAppWebhookEvent::query()->count());
        $this->assertSame('processed', WhatsAppWebhookEvent::query()->value('status'));
    }

    public function test_webhook_signature_rejects_bad_hmac_when_secret_set(): void
    {
        config(['whatsapp.webhook_secret' => 'top-secret']);
        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];

        $this->postJson('/api/v1/whatsapp/webhook', $payload, ['X-Hub-Signature-256' => 'sha256=deadbeef'])
            ->assertStatus(401);

        $body = json_encode($payload);
        $sig = 'sha256='.hash_hmac('sha256', $body, 'top-secret');
        $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], [
            'HTTP_X-Hub-Signature-256' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    public function test_template_sync_mocked(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'data' => [[
                    'name' => 'payment_received',
                    'status' => 'APPROVED',
                    'category' => 'UTILITY',
                    'language' => 'en',
                    'components' => [],
                ]],
            ], 200),
        ]);

        $this->seedConnection();

        $count = app(WhatsAppTemplateSyncService::class)->sync();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_opt_out_and_invalid_number_block_send(): void
    {
        $this->seedConnection();

        $branch = $this->makeBranch();
        $customer = $this->makeCustomer($branch, ['mobile' => '0790000001']);
        WhatsAppOptOut::query()->create([
            'customer_id' => $customer->id,
            'phone_e164' => '+93790000001',
            'reason' => 'stop',
            'opted_out_at' => now(),
        ]);

        Http::fake();
        $svc = app(WhatsAppMessageService::class);
        $msg = $svc->queueTemplate('+93790000001', 'payment_received', 'en', [], $branch->id, $customer->id);
        $this->assertSame('failed', $msg->status);

        $invalid = $svc->queueTemplate('12', 'payment_received', 'en', [], $branch->id, $customer->id);
        $this->assertSame('failed', $invalid->status);

        Http::assertNothingSent();
    }

    public function test_contact_preference_blocks_send(): void
    {
        $this->seedConnection();
        $branch = $this->makeBranch();
        WhatsAppContactPreference::query()->create([
            'phone_e164' => '+93791112233',
            'messaging_allowed' => false,
            'whatsapp_enabled' => false,
        ]);

        Http::fake();
        $msg = app(WhatsAppMessageService::class)->queueTemplate(
            '+93791112233',
            'payment_received',
            'fa',
            [],
            $branch->id,
            null,
            null,
            'payment_confirmed',
        );
        $this->assertSame('failed', $msg->status);
        Http::assertNothingSent();
    }

    public function test_temporary_failure_sets_retrying_and_can_succeed_on_retry(): void
    {
        $this->seedConnection();
        Http::fake([
            'https://graph.facebook.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'Application request limit reached', 'code' => 4]], 429)
                ->push(['messages' => [['id' => 'wamid.RETRY-OK']]], 200),
        ]);

        $message = WhatsAppMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'template_name' => 'payment_received',
            'language_code' => 'fa',
            'status' => 'queued',
            'direction' => 'outbound',
            'type' => 'template',
            'payload' => ['components' => []],
        ]);
        WhatsAppMessageRecipient::query()->create([
            'message_id' => $message->id,
            'phone_e164' => '+93791234567',
            'status' => 'queued',
        ]);

        $svc = app(WhatsAppMessageService::class);
        try {
            $svc->send($message->id);
            $this->fail('Expected retryable API failure.');
        } catch (\Throwable) {
            // expected for retryable failures
        }

        $this->assertSame('retrying', $message->fresh()->status);
        $this->assertSame(1, (int) $message->fresh()->retry_count);

        $sent = $svc->send($message->fresh()->id);
        $this->assertSame('sent', $sent->status);
    }

    public function test_permanent_invalid_number_does_not_retry(): void
    {
        $this->seedConnection();
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid parameter', 'code' => 131026],
            ], 400),
        ]);

        $message = WhatsAppMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'template_name' => 'payment_received',
            'language_code' => 'fa',
            'status' => 'queued',
            'direction' => 'outbound',
            'type' => 'template',
            'payload' => ['components' => []],
        ]);
        WhatsAppMessageRecipient::query()->create([
            'message_id' => $message->id,
            'phone_e164' => '+93791234567',
            'status' => 'queued',
        ]);

        $result = app(WhatsAppMessageService::class)->send($message->id);
        $this->assertSame('failed', $result->status);
        $this->assertSame(0, (int) $result->retry_count);
    }

    public function test_safe_retry_classification(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 4, 'message' => 'rate']], 429)]);
        $response = Http::post('https://graph.facebook.com/v23.0/PN-1/messages', ['x' => 1]);
        $exception = $response->toException();
        $this->assertNotNull($exception);

        $classified = app(WhatsAppMessageService::class)->classifyThrowable($exception);
        $this->assertFalse($classified['permanent']);
        $this->assertSame('rate_limit', $classified['code']);
    }

    public function test_orchestrator_queues_whatsapp_when_rule_enabled(): void
    {
        $this->seedConnection();
        Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        WhatsAppNotificationRule::query()
            ->where('event_type', 'payment_confirmed')
            ->whereNull('branch_id')
            ->update(['whatsapp_enabled' => true, 'template_name' => 'payment_received']);

        $branch = $this->makeBranch();
        $listener = app(OrchestrateBusinessNotification::class);
        $listener->handle(new BusinessNotificationRequested('payment_confirmed', [
            'branch_id' => $branch->id,
            'phone' => '+93791234567',
            'user_id' => User::factory()->create(['status' => 'active'])->id,
            'title' => 'Payment confirmed',
            'body' => 'Test',
        ]));

        $this->assertSame(1, WhatsAppMessage::query()->count());
    }

    public function test_connection_status_masks_token_and_permissions(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole(User::ROLE_SUPER_ADMIN);
        $this->actingAsUser($admin);

        $res = $this->getJson('/api/v1/whatsapp/status')->assertOk();
        $encoded = json_encode($res->json());
        $this->assertStringNotContainsString('secret-token-do-not-log', (string) $encoded);
    }

    public function test_branch_isolation_on_message_list(): void
    {
        $a = $this->makeBranch(['code' => 'WA']);
        $b = $this->makeBranch(['code' => 'WB']);
        WhatsAppMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'branch_id' => $a->id,
            'template_name' => 'x',
            'language_code' => 'en',
            'status' => 'queued',
            'direction' => 'outbound',
            'type' => 'template',
        ]);
        WhatsAppMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'branch_id' => $b->id,
            'template_name' => 'x',
            'language_code' => 'en',
            'status' => 'queued',
            'direction' => 'outbound',
            'type' => 'template',
        ]);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole(User::ROLE_SUPER_ADMIN);
        $this->actingAsUser($admin);
        $all = $this->getJson('/api/v1/whatsapp/messages')->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(2, count($all));
        $filtered = $this->getJson('/api/v1/whatsapp/messages?branch_id='.$a->id)->assertOk()->json('data');
        foreach ($filtered as $row) {
            $this->assertSame($a->id, (int) $row['branch_id']);
        }
    }
}
