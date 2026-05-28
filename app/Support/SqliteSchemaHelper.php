<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SqliteSchemaHelper
{
    /**
     * Drop a column that has a foreign key on SQLite by rebuilding the table.
     * SQLite cannot drop a column whose FK definition still references it, and
     * it cannot drop foreign keys by name. The only safe path is a table rebuild.
     */
    public static function dropFkColumn(string $table, string $column): void
    {
        // legacy_alter_table prevents RENAME from auto-rewriting FK references
        // in other tables to point at the temporary name.
        DB::statement('PRAGMA legacy_alter_table = ON');
        DB::statement('PRAGMA foreign_keys = OFF');

        $keepCols = collect(DB::select("PRAGMA table_info(\"$table\")"))
            ->pluck('name')
            ->reject(fn ($c) => $c === $column)
            ->values();

        $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]);
        $createSql = $row->sql;

        $newCreateSql = preg_replace([
            '/,\s*"'.preg_quote($column, '/').'"[^,]*/',
            '/,\s*foreign key\("'.preg_quote($column, '/').'"\)\s+references\s+"?\w+"?\("\w+"\)(?:\s+on\s+\w+\s+(?:set\s+null|cascade|no\s+action|restrict))*(?=\s*[,)])/i',
        ], '', $createSql);

        $tempName = "__{$table}_old_sqlfix";

        DB::statement("ALTER TABLE \"$table\" RENAME TO \"$tempName\"");
        DB::statement($newCreateSql);

        $colList = $keepCols->map(fn ($c) => '"'.$c.'"')->implode(', ');
        DB::statement("INSERT INTO \"$table\" ($colList) SELECT $colList FROM \"$tempName\"");
        DB::statement("DROP TABLE \"$tempName\"");

        DB::statement('PRAGMA legacy_alter_table = OFF');
        DB::statement('PRAGMA foreign_keys = ON');
    }
}
