<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An end date so a rule set is never silently used past the period its sources cover,
// strict-input switches, and acknowledgements the company must accept before review.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_rule_sets', function (Blueprint $table) {
            $table->date('effective_to')->nullable()->after('effective_from');
            $table->boolean('strict_inputs')->default(false)->after('month_days_basis');
            $table->string('template_key', 60)->nullable()->after('strict_inputs');
            $table->json('required_acknowledgements')->nullable()->after('template_key');
            $table->json('accepted_acknowledgements')->nullable()->after('required_acknowledgements');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_rule_sets', function (Blueprint $table) {
            $table->dropColumn(['effective_to', 'strict_inputs', 'template_key', 'required_acknowledgements', 'accepted_acknowledgements']);
        });
    }
};
