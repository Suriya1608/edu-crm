<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_messages', 'media_type')) {
                $table->string('media_type', 50)->nullable()->after('meta_data');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'media_url')) {
                $table->text('media_url')->nullable()->after('media_type');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'media_filename')) {
                $table->string('media_filename')->nullable()->after('media_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            foreach (['media_type', 'media_url', 'media_filename'] as $col) {
                if (Schema::hasColumn('whatsapp_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
