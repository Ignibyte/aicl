<?php

// PATTERN: Anonymous class migration (Laravel 11+ convention).
// PATTERN: Always include indexes on frequently queried columns.
// PATTERN: Foreign keys use constrained()->cascadeOnDelete().
// PATTERN: Many-to-many pivot tables go in the same migration.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PATTERN: Main entity table.
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // PATTERN: 'name' is the standard display field.
            $table->string('name');
            $table->text('description')->nullable();
            // PATTERN: State columns store the morph class string, default to the initial state.
            $table->string('status')->default('draft');
            // PATTERN: Enum columns store the backed value.
            $table->string('priority')->default('medium');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            // PATTERN: Money fields use decimal(12, 2).
            $table->decimal('budget', 12, 2)->nullable();
            // PATTERN: Every entity has is_active for soft visibility control.
            $table->boolean('is_active')->default(true);
            // PATTERN: Every entity has an owner_id foreign key to users.
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            // PATTERN: Always include softDeletes for entity tables.
            $table->softDeletes();

            // PATTERN: Index columns used in filters, sorting, and WHERE clauses.
            $table->index('status');
            $table->index('priority');
            $table->index('is_active');
        });

        // PATTERN: Many-to-many pivot table in the same migration.
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // PATTERN: Pivot tables can carry extra data (role, permissions, etc.).
            $table->string('role')->default('member');
            $table->timestamps();
            // PATTERN: Unique composite key prevents duplicate membership.
            $table->unique(['project_id', 'user_id']);
        });
    }

    // PATTERN: Always implement down() for rollback support.
    public function down(): void
    {
        // PATTERN: Drop pivot table first (has foreign key to main table).
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
