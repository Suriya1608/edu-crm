<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertToInnoDB extends Command
{
    protected $signature = 'db:convert-innodb
                            {--connection=mysql : DB connection to use}
                            {--database=        : Database name (defaults to current connection DB)}';

    protected $description = 'Convert all MyISAM tables to InnoDB in the specified database';

    public function handle(): int
    {
        $connection = $this->option('connection');
        $database   = $this->option('database') ?: config("database.connections.{$connection}.database");

        // If a custom database is specified, switch the connection to point at it
        if ($this->option('database')) {
            config(["database.connections.{$connection}.database" => $database]);
            DB::purge($connection);
            DB::reconnect($connection);
        }

        $tables = DB::connection($connection)
            ->select("SELECT table_name FROM information_schema.tables
                      WHERE table_schema = ? AND engine = 'MyISAM'", [$database]);

        if (empty($tables)) {
            $this->info("No MyISAM tables found in '{$database}'. All good.");
            return self::SUCCESS;
        }

        $this->info("Found " . count($tables) . " MyISAM table(s) in '{$database}'. Converting...");

        foreach ($tables as $row) {
            $table = $row->table_name ?? $row->TABLE_NAME;
            DB::connection($connection)->statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            $this->line("  ✓ {$table}");
        }

        $this->newLine();
        $this->info('All tables converted to InnoDB.');
        return self::SUCCESS;
    }
}
