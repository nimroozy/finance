<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('installation_task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->json('steps'); // [{key,title,department_code,work_type,depends_on[]}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
        });

        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 64)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('installation_task_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable(); // inventory/radius placeholders
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('installation_id')->references('id')->on('installations')->nullOnDelete();
        });

        Schema::create('staff_action_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_action_tokens');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['installation_id']);
        });
        Schema::dropIfExists('installations');
        Schema::dropIfExists('installation_task_templates');
        Schema::dropIfExists('installation_sequences');
    }
};
