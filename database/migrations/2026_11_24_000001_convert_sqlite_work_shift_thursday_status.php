<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 2026_07_08_000001 skips SQLite, so its enum columns keep string values there. Convert them here with the same map.
return new class extends Migration
{
    public function up(): void
    {
        $this->convertAll(toInteger: true);
    }

    public function down(): void
    {
        $this->convertAll(toInteger: false);
    }

    private function convertAll(bool $toInteger): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        foreach ($this->schemaChanges() as $table => $columns) {
            foreach ($columns as $column => $definition) {
                if (! Schema::hasColumn($table, $column) || $this->isInteger($table, $column) === $toInteger) {
                    continue;
                }

                $this->convertColumn($table, $column, $definition, $toInteger);
            }
        }
    }

    /** Reuse the column map of the original migration so the two cannot drift apart. */
    private function schemaChanges(): array
    {
        $original = require __DIR__.'/2026_07_08_000001_convert_enum_columns_to_integer_values.php';

        return (new ReflectionMethod($original, 'schemaChanges'))->invoke($original);
    }

    private function convertColumn(string $table, string $column, array $definition, bool $toInteger): void
    {
        $nullable = $definition['nullable'] ?? false;
        $default = $toInteger ? $definition['integer_default'] : $definition['string_default'];
        $map = $toInteger ? $definition['map'] : array_flip($definition['map']);
        $temp = "{$column}_new";

        Schema::table($table, function (Blueprint $blueprint) use ($temp, $nullable, $default, $toInteger) {
            $new = $toInteger ? $blueprint->unsignedTinyInteger($temp) : $blueprint->string($temp, 50);
            $nullable ? $new->nullable() : $new->default($default);
        });

        $cases = collect($map)
            ->map(fn ($to, $from) => 'WHEN '.DB::getPdo()->quote((string) $from).' THEN '.($toInteger ? (int) $to : DB::getPdo()->quote((string) $to)))
            ->implode(' ');
        $else = $default === null ? 'NULL' : ($toInteger ? (int) $default : DB::getPdo()->quote((string) $default));

        DB::statement("UPDATE \"{$table}\" SET \"{$temp}\" = CASE CAST(\"{$column}\" AS TEXT) {$cases} ELSE {$else} END");

        // SQLite cannot drop a column that is part of an index, so drop and recreate those around the swap.
        $indexes = collect(Schema::getIndexes($table))->filter(fn ($index) => in_array($column, $index['columns'], true) && ! $index['primary']);

        Schema::table($table, fn (Blueprint $blueprint) => $indexes->each(fn ($index) => $blueprint->dropIndex($index['name'])));
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($temp, $column));
        Schema::table($table, fn (Blueprint $blueprint) => $indexes->each(
            fn ($index) => $index['unique']
                ? $blueprint->unique($index['columns'], $index['name'])
                : $blueprint->index($index['columns'], $index['name'])
        ));
    }

    private function isInteger(string $table, string $column): bool
    {
        return in_array(Schema::getColumnType($table, $column), ['integer', 'int', 'tinyint', 'bigint'], true);
    }
};
