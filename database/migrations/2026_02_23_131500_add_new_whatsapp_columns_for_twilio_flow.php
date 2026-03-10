<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            return;
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_messages', 'from_number')) {
                $table->string('from_number')->nullable()->after('lead_id');
            }

            if (!Schema::hasColumn('whatsapp_messages', 'message_body')) {
                $table->text('message_body')->nullable()->after('from_number');
            }
        });

        if (Schema::hasColumn('whatsapp_messages', 'message') && Schema::hasColumn('whatsapp_messages', 'message_body')) {
            DB::table('whatsapp_messages')
                ->whereNull('message_body')
                ->update([
                    'message_body' => DB::raw('message'),
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            return;
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_messages', 'message_body')) {
                $table->dropColumn('message_body');
            }

            if (Schema::hasColumn('whatsapp_messages', 'from_number')) {
                $table->dropColumn('from_number');
            }
        });
    }
};
