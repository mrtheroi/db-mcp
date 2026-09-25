<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE observations ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(content, '')), 'B')
            ) STORED
            SQL);

        DB::statement('CREATE INDEX observations_search_vector_index ON observations USING GIN (search_vector)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS observations_search_vector_index');
        DB::statement('ALTER TABLE observations DROP COLUMN IF EXISTS search_vector');
    }
};
