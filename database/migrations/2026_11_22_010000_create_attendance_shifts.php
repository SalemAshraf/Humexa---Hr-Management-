<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('name', 80);
            $table->time('start_time');
            $table->time('end_time'); // end <= start means the shift ends the next day
            $table->unsignedSmallInteger('grace_minutes')->nullable(); // null = use the company late grace
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable()->after('manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('shift_id'));
        Schema::dropIfExists('attendance_shifts');
    }
};
