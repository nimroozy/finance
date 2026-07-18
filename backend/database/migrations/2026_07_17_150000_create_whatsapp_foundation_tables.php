<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('default');
            $table->string('phone_number_id')->nullable()->unique();
            $table->string('business_account_id')->nullable();
            $table->text('access_token')->nullable();
            $table->string('status')->default('disconnected');
            $table->boolean('is_paused')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_successful_api_call')->nullable();
            $table->timestamp('last_webhook_received')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->nullable()->constrained('whatsapp_connections')->nullOnDelete();
            $table->string('meta_template_id')->nullable();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('status')->default('UNKNOWN');
            $table->timestamps();
            $table->unique(['connection_id', 'name']);
        });

        Schema::create('whatsapp_template_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('whatsapp_templates')->cascadeOnDelete();
            $table->string('language_code', 16);
            $table->json('components')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'language_code']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('connection_id')->nullable()->constrained('whatsapp_connections')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->string('direction')->default('outbound');
            $table->string('type')->default('template');
            $table->string('status')->default('queued');
            $table->string('meta_message_id')->nullable()->index();
            $table->string('phone_e164')->nullable()->index();
            $table->string('event_type')->nullable();
            $table->string('template_name')->nullable();
            $table->string('language_code', 16)->nullable();
            $table->string('language', 16)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('receipt_id')->nullable()->index();
            $table->unsignedBigInteger('handover_id')->nullable()->index();
            $table->unsignedBigInteger('promise_id')->nullable()->index();
            $table->unsignedBigInteger('assignment_id')->nullable()->index();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('whatsapp_messages')->cascadeOnDelete();
            $table->string('phone_e164');
            $table->string('status')->default('queued');
            $table->string('failure_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('whatsapp_messages')->cascadeOnDelete();
            $table->string('event_id')->nullable()->unique();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->string('status')->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('template_name')->nullable();
            $table->string('language_code', 16)->default('fa');
            $table->string('language', 16)->nullable();
            $table->decimal('min_amount', 18, 2)->nullable();
            $table->string('severity')->nullable();
            $table->string('role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'event_type']);
        });

        Schema::create('whatsapp_contact_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone_e164');
            $table->string('locale', 8)->default('fa');
            $table->boolean('whatsapp_enabled')->default(true);
            $table->boolean('messaging_allowed')->default(true);
            $table->boolean('receipts_enabled')->default(true);
            $table->boolean('reminders_enabled')->default(true);
            $table->boolean('promotional_enabled')->default(false);
            $table->string('preferred_language', 16)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->text('opt_out_reason')->nullable();
            $table->timestamps();
            $table->unique('phone_e164');
        });

        Schema::create('whatsapp_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->string('phone_e164')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('inbound');
            $table->text('reason')->nullable();
            $table->timestamp('opted_out_at');
            $table->timestamps();
        });

        Schema::create('whatsapp_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('whatsapp_messages')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->text('message');
            $table->boolean('is_permanent')->default(false);
            $table->unsignedInteger('attempts')->default(1);
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_e164')->index();
            $table->string('status')->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('meta_message_id')->unique();
            $table->string('from_phone');
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'whatsapp_inbound_messages', 'whatsapp_conversations', 'whatsapp_failures',
            'whatsapp_opt_outs', 'whatsapp_contact_preferences', 'whatsapp_notification_rules',
            'whatsapp_webhook_events', 'whatsapp_message_events', 'whatsapp_message_recipients',
            'whatsapp_messages', 'whatsapp_template_languages', 'whatsapp_templates',
            'whatsapp_connections',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
