<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('entity')->unique();
            $table->timestamp('successful_cursor')->nullable();
            $table->timestamp('last_requested_from')->nullable();
            $table->timestamp('last_requested_to')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('zoho_circuit_breakers', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type')->unique();
            $table->string('state')->default('closed');
            $table->unsignedInteger('failure_count')->default(0);
            $table->string('last_error_class')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('resume_after')->nullable();
            $table->timestamps();
        });

        Schema::create('zoho_sync_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('component')->unique();
            $table->string('status')->default('idle');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_start_at')->nullable();
            $table->timestamp('last_completion_at')->nullable();
            $table->unsignedBigInteger('last_duration_ms')->nullable();
            $table->timestamp('next_expected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('zoho_locations', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_location_id')->unique();
            $table->string('organization_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->json('raw')->nullable();
            $table->string('sync_status')->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('zoho_reporting_tags', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_tag_id')->unique();
            $table->string('organization_id');
            $table->string('name');
            $table->string('associated_with')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('raw')->nullable();
            $table->string('sync_status')->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('zoho_reporting_tag_options', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_tag_option_id')->unique();
            $table->string('zoho_tag_id')->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('raw')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('zoho_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_account_id')->unique();
            $table->string('organization_id');
            $table->string('name');
            $table->string('account_type');
            $table->string('account_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('raw')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('zoho_payment_modes', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_payment_mode_id')->unique();
            $table->string('organization_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('raw')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('zoho_branch_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('alias');
            $table->string('normalized_alias');
            $table->timestamps();
            $table->unique(['branch_id', 'normalized_alias']);
        });

        Schema::create('customer_branch_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('old_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('new_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('source');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('zoho_location_id')->nullable()->index();
            $table->string('zoho_reporting_tag_id')->nullable();
            $table->string('zoho_reporting_tag_option_id')->nullable();
            $table->string('mapping_type')->nullable();
            $table->string('zoho_sync_status')->nullable();
            $table->timestamp('last_structure_sync_at')->nullable();
            $table->boolean('local_override')->default(true);
        });

        Schema::table('zoho_sync_jobs', function (Blueprint $table) {
            $table->string('error_class')->nullable()->index();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->boolean('circuit_open')->default(false);
            $table->timestamp('cursor_from')->nullable();
            $table->timestamp('cursor_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('zoho_sync_jobs', function (Blueprint $table) {
            $table->dropColumn(['error_class', 'retry_count', 'next_retry_at', 'circuit_open', 'cursor_from', 'cursor_to']);
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'zoho_location_id', 'zoho_reporting_tag_id', 'zoho_reporting_tag_option_id',
                'mapping_type', 'zoho_sync_status', 'last_structure_sync_at', 'local_override',
            ]);
        });
        Schema::dropIfExists('customer_branch_change_logs');
        Schema::dropIfExists('zoho_branch_aliases');
        Schema::dropIfExists('zoho_payment_modes');
        Schema::dropIfExists('zoho_accounts');
        Schema::dropIfExists('zoho_reporting_tag_options');
        Schema::dropIfExists('zoho_reporting_tags');
        Schema::dropIfExists('zoho_locations');
        Schema::dropIfExists('zoho_sync_heartbeats');
        Schema::dropIfExists('zoho_circuit_breakers');
        Schema::dropIfExists('zoho_sync_cursors');
    }
};
