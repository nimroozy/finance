<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_customer_prefixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('prefix', 32);
            $table->string('normalized_prefix', 32);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['normalized_prefix', 'active']);
            $table->index(['branch_id', 'active']);
            $table->index(['normalized_prefix', 'priority']);
        });

        Schema::create('customer_branch_mapping_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('conflict_type');
            $table->string('status')->default('open');
            $table->foreignId('prefix_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('location_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('existing_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('detected_prefix')->nullable();
            $table->string('customer_number')->nullable();
            $table->json('evidence')->nullable();
            $table->text('explanation')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'conflict_type']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('customer_branch_mapping_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('resolution_source');
            $table->string('confidence')->nullable();
            $table->string('detected_prefix')->nullable();
            $table->boolean('dry_run')->default(false);
            $table->boolean('applied')->default(false);
            $table->boolean('protected_history')->default(false);
            $table->json('evidence')->nullable();
            $table->text('explanation')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('run_id')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['run_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('branch_mapping_source')->nullable()->after('is_unmapped');
            $table->string('branch_mapping_confidence')->nullable()->after('branch_mapping_source');
            $table->string('branch_mapping_prefix')->nullable()->after('branch_mapping_confidence');
            $table->boolean('administrator_branch_override')->default(false)->after('branch_mapping_prefix');
            $table->foreignId('branch_override_by')->nullable()->after('administrator_branch_override')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('branch_mapped_at')->nullable()->after('branch_override_by');
            $table->timestamp('branch_mapping_confirmed_at')->nullable()->after('branch_mapped_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_override_by');
            $table->dropColumn([
                'branch_mapping_source',
                'branch_mapping_confidence',
                'branch_mapping_prefix',
                'administrator_branch_override',
                'branch_mapped_at',
                'branch_mapping_confirmed_at',
            ]);
        });
        Schema::dropIfExists('customer_branch_mapping_histories');
        Schema::dropIfExists('customer_branch_mapping_conflicts');
        Schema::dropIfExists('branch_customer_prefixes');
    }
};
