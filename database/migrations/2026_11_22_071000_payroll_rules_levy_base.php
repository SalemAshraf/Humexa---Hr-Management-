<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which pay figure an extra levy is charged on. Chosen by the company; null means not chosen yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_rule_sets', function (Blueprint $table) {
            $table->string('levy_base', 20)->nullable()->after('after_tax_levy_label');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_rule_sets', function (Blueprint $table) {
            $table->dropColumn('levy_base');
        });
    }
};
