<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSqliteToMysql extends Command
{
    protected $signature = 'app:migrate-sqlite-mysql';
    protected $description = 'Migrate data exactly from sqlite_old to mysql';

    public function handle()
    {
        $this->info('Starting data migration from sqlite_old to mysql...');
        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0;');

        $tables = DB::connection('sqlite_old')->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations'");

        foreach ($tables as $table) {
            $tableName = $table->name;
            $this->info("Migrating table: $tableName");

            // Empty the target table
            DB::connection('mysql')->table($tableName)->truncate();

            // Fetch records from SQLite
            $rows = DB::connection('sqlite_old')->table($tableName)->get();
            $data = json_decode(json_encode($rows), true);
            
            if (empty($data)) continue;

            $chunks = array_chunk($data, 100);
            $bar = $this->output->createProgressBar(count($chunks));
            
            foreach ($chunks as $chunk) {
                try {
                    DB::connection('mysql')->table($tableName)->insert($chunk);
                } catch (\Exception $e) {
                    $this->error("\nFailed chunk in $tableName: " . mb_substr($e->getMessage(), 0, 200));
                    // try one by one to find exact failing row
                    foreach ($chunk as $row) {
                        try {
                            DB::connection('mysql')->table($tableName)->insert($row);
                        } catch (\Exception $e2) {
                            $this->error("Failed Row ID " . ($row['id'] ?? 'unknown') . ": " . mb_substr($e2->getMessage(), 0, 250));
                        }
                    }
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1;');
        $this->info('Migration completed successfully!');
    }
}
