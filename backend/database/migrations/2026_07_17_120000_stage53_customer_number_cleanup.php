<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_headquarter')->default(false)->after('is_active');
            $table->boolean('exclude_from_field_collection')->default(false)->after('is_headquarter');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('source_contact_name')->nullable()->after('contact_name');
            $table->string('extracted_customer_number')->nullable()->after('customer_number');
            $table->string('clean_display_name')->nullable()->after('source_contact_name');
            $table->string('customer_number_source')->nullable()->after('extracted_customer_number');
            $table->string('customer_number_confidence')->nullable()->after('customer_number_source');
            $table->timestamp('customer_number_extracted_at')->nullable()->after('customer_number_confidence');
            $table->string('unmapped_classification')->nullable()->after('is_unmapped');
            $table->boolean('is_headquarter_owned')->default(false)->after('unmapped_classification');
            $table->boolean('is_test_customer')->default(false)->after('is_headquarter_owned');
            $table->boolean('is_non_operational')->default(false)->after('is_test_customer');
            $table->boolean('exclude_from_field_collection')->default(false)->after('is_non_operational');
            $table->text('classification_notes')->nullable()->after('exclude_from_field_collection');
            $table->foreignId('classified_by')->nullable()->after('classification_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('classified_at')->nullable()->after('classified_by');
            $table->index(['unmapped_classification', 'is_unmapped']);
            $table->index(['is_headquarter_owned', 'is_test_customer']);
        });

        Schema::table('customer_branch_mapping_conflicts', function (Blueprint $table) {
            $table->string('resolution_action')->nullable()->after('resolution_notes');
            $table->string('risk_level')->nullable()->after('resolution_action');
            $table->string('recommended_action')->nullable()->after('risk_level');
            $table->foreignId('resolved_branch_id')->nullable()->after('recommended_action')->constrained('branches')->nullOnDelete();
            $table->boolean('deferred')->default(false)->after('resolved_branch_id');
            $table->timestamp('deferred_until')->nullable()->after('deferred');
        });

        Schema::create('customer_number_backfill_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('before_customer_number')->nullable();
            $table->string('after_customer_number')->nullable();
            $table->string('extracted_customer_number')->nullable();
            $table->string('clean_display_name')->nullable();
            $table->string('source')->nullable();
            $table->string('confidence')->nullable();
            $table->string('result_class');
            $table->boolean('dry_run')->default(true);
            $table->boolean('applied')->default(false);
            $table->json('evidence')->nullable();
            $table->text('explanation')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('run_id')->nullable()->index();
            $table->timestamps();
            $table->index(['customer_id', 'result_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_number_backfill_histories');

        Schema::table('customer_branch_mapping_conflicts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_branch_id');
            $table->dropColumn(['resolution_action', 'risk_level', 'recommended_action', 'deferred', 'deferred_until']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classified_by');
            $table->dropColumn([
                'source_contact_name',
                'extracted_customer_number',
                'clean_display_name',
                'customer_number_source',
                'customer_number_confidence',
                'customer_number_extracted_at',
                'unmapped_classification',
                'is_headquarter_owned',
                'is_test_customer',
                'is_non_operational',
                'exclude_from_field_collection',
                'classification_notes',
                'classified_at',
            ]);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['is_headquarter', 'exclude_from_field_collection']);
        });
    }
};
