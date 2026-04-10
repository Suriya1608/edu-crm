<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'phone_last10')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('phone_last10', 10)->nullable()->after('phone');
            });
        }

        DB::table('leads')
            ->select('id', 'phone')
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    $digits = preg_replace('/\D+/', '', (string) $row->phone) ?? '';
                    $last10 = strlen($digits) >= 10 ? substr($digits, -10) : null;

                    DB::table('leads')
                        ->where('id', $row->id)
                        ->update(['phone_last10' => $last10]);
                }
            });

        if (!$this->hasIndex('leads', 'leads_phone_last10_index')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->index('phone_last10', 'leads_phone_last10_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'phone_last10')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndexIfExists('leads_phone_last10_index');
                $table->dropColumn('phone_last10');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $result = DB::select("PRAGMA index_list('{$table}')");
            foreach ($result as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        return !empty($result);
    }
};

