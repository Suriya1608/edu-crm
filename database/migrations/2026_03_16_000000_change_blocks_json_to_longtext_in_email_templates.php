<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change from json (has implicit JSON_VALID CHECK constraint in MySQL 8.x)
        // to longtext so GrapesJS project data can be stored without constraint failures.
        // Laravel's 'array' cast in the model still encodes/decodes correctly.
        DB::statement('ALTER TABLE email_templates MODIFY COLUMN blocks_json LONGTEXT NULL');
    }

    public function down(): void
    {
        // Only safe to revert if all stored values are valid JSON
        DB::statement('ALTER TABLE email_templates MODIFY COLUMN blocks_json JSON NULL');
    }
};
