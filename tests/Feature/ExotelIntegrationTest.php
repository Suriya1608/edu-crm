<?php

namespace Tests\Feature;

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExotelIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSchema();
        $this->truncateTables();
    }

    public function test_incoming_webhook_creates_ringing_call_log_and_poll_returns_it(): void
    {
        $telecaller = $this->makeTelecaller([
            'is_online' => true,
            'last_seen_at' => now(),
        ]);

        $lead = Lead::create([
            'lead_code' => 'LD-001',
            'name' => 'Inbound Lead',
            'phone' => '+91 98765 43210',
            'assigned_to' => $telecaller->id,
            'status' => 'new',
        ]);

        $this->post('/exotel/incoming', [
            'From' => '09876543210',
            'CallSid' => 'EXO-IN-001',
        ])->assertOk();

        $callLog = CallLog::query()->latest('id')->first();

        $this->assertNotNull($callLog);
        $this->assertSame($lead->id, $callLog->lead_id);
        $this->assertSame($telecaller->id, $callLog->user_id);
        $this->assertSame('inbound', $callLog->direction);
        $this->assertSame('ringing', $callLog->status);
        $this->assertSame('exotel', $callLog->provider);
        $this->assertSame('919876543210', $callLog->customer_number);
        $this->assertSame('EXO-IN-001', $callLog->call_sid);

        $this->actingAs($telecaller)
            ->get('/exotel/incoming-poll')
            ->assertOk()
            ->assertJson([
                'has_incoming' => true,
                'call_log_id' => $callLog->id,
                'phone' => '919876543210',
                'lead_name' => 'Inbound Lead',
            ])
            ->assertJsonPath('lead_url', fn ($url) => is_string($url) && str_contains($url, '/telecaller/leads/'));
    }

    public function test_webhook_marks_inbound_no_answer_as_missed_and_updates_terminal_fields(): void
    {
        $callLog = CallLog::create([
            'lead_id' => null,
            'user_id' => null,
            'provider' => 'exotel',
            'call_sid' => 'EXO-IN-002',
            'customer_number' => '919812345678',
            'direction' => 'inbound',
            'status' => 'ringing',
        ]);

        $this->post('/exotel/webhook', [
            'CallSid' => 'EXO-IN-002',
            'Status' => 'no-answer',
            'Duration' => 0,
            'RecordingUrl' => 'https://recordings.test/exo-in-002.mp3',
        ])->assertOk();

        $callLog->refresh();

        $this->assertSame('missed', $callLog->status);
        $this->assertSame('no-answer', $callLog->end_reason);
        $this->assertSame(0, $callLog->duration);
        $this->assertSame('https://recordings.test/exo-in-002.mp3', $callLog->recording_url);
        $this->assertNotNull($callLog->ended_at);
    }

    public function test_webhook_updates_answered_and_completed_call_by_call_sid(): void
    {
        $callLog = CallLog::create([
            'lead_id' => null,
            'user_id' => null,
            'provider' => 'exotel',
            'call_sid' => 'EXO-OUT-001',
            'customer_number' => '919812345678',
            'direction' => 'outbound',
            'status' => 'initiated',
        ]);

        $this->post('/exotel/webhook', [
            'CallSid' => 'EXO-OUT-001',
            'Status' => 'in-progress',
        ])->assertOk();

        $callLog->refresh();
        $this->assertSame('in-progress', $callLog->status);
        $this->assertNotNull($callLog->answered_at);

        $this->post('/exotel/webhook', [
            'CallSid' => 'EXO-OUT-001',
            'Status' => 'completed',
            'Duration' => 48,
            'RecordingUrl' => 'https://recordings.test/exo-out-001.mp3',
        ])->assertOk();

        $callLog->refresh();

        $this->assertSame('completed', $callLog->status);
        $this->assertSame(48, $callLog->duration);
        $this->assertSame('https://recordings.test/exo-out-001.mp3', $callLog->recording_url);
        $this->assertNotNull($callLog->ended_at);
    }

    public function test_voip_call_creates_outbound_call_log_for_authenticated_user(): void
    {
        $telecaller = $this->makeTelecaller();

        $lead = Lead::create([
            'lead_code' => 'LD-002',
            'name' => 'Outbound Lead',
            'phone' => '9876543210',
            'assigned_to' => $telecaller->id,
            'status' => 'new',
        ]);

        DB::table('settings')->insert([
            ['key' => 'voip_username', 'value' => 'agent1'],
            ['key' => 'voip_domain', 'value' => 'insighthcm5m.voip.exotel.com'],
        ]);

        $this->actingAs($telecaller)
            ->postJson('/exotel/voip-call', [
                'lead_id' => $lead->id,
                'phone' => '09876543210',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => 'initiated',
                'dial_to' => 'sip:919876543210@insighthcm5m.voip.exotel.com',
            ]);

        $callLog = CallLog::query()->latest('id')->first();

        $this->assertNotNull($callLog);
        $this->assertSame($telecaller->id, $callLog->user_id);
        $this->assertSame($lead->id, $callLog->lead_id);
        $this->assertSame('outbound', $callLog->direction);
        $this->assertSame('exotel', $callLog->provider);
        $this->assertSame('919876543210', $callLog->customer_number);
        $this->assertNull($callLog->call_sid);
        $this->assertSame('initiated', $callLog->status);
    }

    public function test_browser_incoming_creates_inbound_call_log_for_authenticated_user(): void
    {
        $telecaller = $this->makeTelecaller();

        $lead = Lead::create([
            'lead_code' => 'LD-003',
            'name' => 'Inbound Browser Lead',
            'phone' => '6383702482',
            'assigned_to' => $telecaller->id,
            'status' => 'new',
        ]);

        $this->actingAs($telecaller)
            ->postJson('/exotel/browser-incoming', [
                'phone' => '+91 63837 02482',
                'call_sid' => 'SIP-CALL-001',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'phone' => '916383702482',
                'lead_name' => 'Inbound Browser Lead',
            ]);

        $callLog = CallLog::query()->latest('id')->first();

        $this->assertNotNull($callLog);
        $this->assertSame($lead->id, $callLog->lead_id);
        $this->assertSame($telecaller->id, $callLog->user_id);
        $this->assertSame('inbound', $callLog->direction);
        $this->assertSame('ringing', $callLog->status);
        $this->assertSame('SIP-CALL-001', $callLog->call_sid);
    }

    public function test_voip_config_sanitizes_proxy_host(): void
    {
        $telecaller = $this->makeTelecaller();

        DB::table('settings')->insert([
            ['key' => 'voip_enabled', 'value' => '1'],
            ['key' => 'voip_proxy', 'value' => 'wss://voip.in1.exotel.com:443/'],
            ['key' => 'voip_domain', 'value' => 'demo.voip.exotel.com'],
            ['key' => 'voip_username', 'value' => 'agent1'],
            ['key' => 'voip_password', 'value' => 'plain-test-password'],
        ]);

        $this->actingAs($telecaller)
            ->getJson('/settings/voip')
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'proxy' => 'voip.in1.exotel.com',
                'domain' => 'demo.voip.exotel.com',
                'username' => 'agent1',
            ]);
    }

    private function setUpSchema(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('phone')->nullable();
                $table->string('role')->nullable();
                $table->boolean('status')->default(true);
                $table->boolean('is_online')->default(false);
                $table->timestamp('last_seen_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->id();
                $table->string('lead_code')->nullable();
                $table->string('name');
                $table->string('phone');
                $table->string('email')->nullable();
                $table->unsignedBigInteger('course_id')->nullable();
                $table->string('source')->nullable();
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->string('status')->default('new');
                $table->date('next_followup')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('call_logs')) {
            Schema::create('call_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('provider');
                $table->string('call_sid')->nullable();
                $table->string('customer_number')->nullable();
                $table->string('direction')->nullable();
                $table->string('status')->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->string('ended_by')->nullable();
                $table->string('end_reason')->nullable();
                $table->integer('duration')->nullable();
                $table->string('recording_url')->nullable();
                $table->string('outcome')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    private function truncateTables(): void
    {
        foreach (['call_logs', 'leads', 'settings', 'users'] as $table) {
            DB::table($table)->delete();
        }
    }

    private function makeTelecaller(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Telecaller One',
            'email' => 'telecaller@example.com',
            'role' => 'telecaller',
            'status' => 1,
            'is_online' => false,
            'last_seen_at' => null,
            'phone' => '9876500001',
        ], $overrides));
    }
}
