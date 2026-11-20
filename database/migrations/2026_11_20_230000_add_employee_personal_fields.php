<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('personal_email', 190)->nullable()->after('work_email');
            $table->string('alternate_phone', 30)->nullable()->after('phone');
            $table->string('nationality', 100)->nullable()->after('gender');
            $table->string('marital_status', 30)->nullable()->after('nationality');
            $table->string('profile_photo_path', 500)->nullable()->after('marital_status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'personal_email',
                'alternate_phone',
                'nationality',
                'marital_status',
                'profile_photo_path',
            ]);
        });
    }
};
