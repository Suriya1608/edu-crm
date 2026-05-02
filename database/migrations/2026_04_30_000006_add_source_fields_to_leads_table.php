<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('source_type', 20)->nullable()->after('quota');
            $table->string('source_category', 50)->nullable()->after('source_type');
            $table->string('source_detail', 255)->nullable()->after('source_category');
        });

        // Migrate existing source values to the new structured columns
        DB::statement("
            UPDATE leads SET
                source_type     = 'landing_page',
                source_category = CASE
                    WHEN source IN ('facebook_ads', 'Facebook Lead Ads') THEN 'facebook_ads'
                    WHEN source IN ('instagram_ads', 'Instagram Lead Ads') THEN 'instagram_ads'
                    WHEN source IN ('google_ads')                         THEN 'google_ads'
                    WHEN source IN ('Landing Page', 'landing_page')       THEN 'website'
                    WHEN source IN ('meta_ads')                           THEN 'facebook_ads'
                    ELSE 'other_digital'
                END
            WHERE source IS NOT NULL
              AND source NOT IN ('manual', 'Manual', '')
        ");

        DB::statement("
            UPDATE leads SET
                source_type     = 'manual',
                source_category = 'other'
            WHERE source IN ('manual', 'Manual', '') OR source IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_category', 'source_detail']);
        });
    }
};
