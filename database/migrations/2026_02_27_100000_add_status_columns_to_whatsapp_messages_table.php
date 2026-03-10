<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            return;
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_messages', 'provider_message_id')) {
                $table->string('provider_message_id')->nullable()->after('direction');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('provider_message_id');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'meta_data')) {
                $table->json('meta_data')->nullable()->after('sent_at');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('meta_data');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            return;
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            foreach (['provider_message_id', 'sent_at', 'meta_data', 'is_read'] as $col) {
                if (Schema::hasColumn('whatsapp_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
