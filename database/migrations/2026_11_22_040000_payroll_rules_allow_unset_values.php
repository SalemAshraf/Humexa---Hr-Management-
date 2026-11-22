<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rates and limits may now be left unset, so a new company starts with no
// statutory number pre-filled. Existing rows keep their values.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_rule_sets', function (Blueprint $table) {
            $table->decimal('insurance_employee_rate', 6, 4)->nullable()->default(null)->change();
            $table->decimal('insurance_employer_rate', 6, 4)->nullable()->default(null)->change();
            $table->decimal('insurable_min', 12, 2)->nullable()->default(null)->change();
            $table->decimal('insurable_max', 12, 2)->nullable()->default(null)->change();
            $table->decimal('personal_exemption_annual', 12, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
    }
};
