<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Position of a table on the floor plan, in grid units (null = not placed yet).
        Schema::table('shop_tables', function (Blueprint $table) {
            $table->decimal('plan_x', 6, 2)->nullable()->after('is_active');
            $table->decimal('plan_y', 6, 2)->nullable()->after('plan_x');
            $table->decimal('plan_w', 6, 2)->nullable()->after('plan_y');
            $table->decimal('plan_h', 6, 2)->nullable()->after('plan_w');
            $table->string('plan_shape', 10)->nullable()->after('plan_h');
        });

        // Everything else on the plan: stage, bar, doors, corridors, walls, technical tower, labels...
        Schema::create('floor_elements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('label', 100)->nullable();
            $table->decimal('x', 6, 2);
            $table->decimal('y', 6, 2);
            $table->decimal('w', 6, 2);
            $table->decimal('h', 6, 2);
            $table->string('color', 9)->nullable();
            $table->integer('z')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_elements');

        Schema::table('shop_tables', function (Blueprint $table) {
            $table->dropColumn(['plan_x', 'plan_y', 'plan_w', 'plan_h', 'plan_shape']);
        });
    }
};
