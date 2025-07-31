<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class KamusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Memasuki metode run() dari KamusSeeder...');

        $mainDirectory = storage_path('app/kbbi-main');

        if (!File::isDirectory($mainDirectory)) {
            $this->command->error('Direktori kbbi-main tidak ditemukan di storage/app/.');
            return;
        }

        $this->command->info('Menghitung jumlah file JSON untuk diimpor...');
        
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mainDirectory));
        $jsonFiles = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() == 'json') {
                $jsonFiles[] = $file->getPathname();
            }
        }
        
        $fileCount = count($jsonFiles);

        if ($fileCount === 0) {
            $this->command->error("Tidak ada file .json yang ditemukan untuk diimpor.");
            return;
        }

        $this->command->info("Ditemukan total {$fileCount} file JSON. Memulai proses impor (ini mungkin memakan waktu beberapa menit)...");

        DB::table('kamuses')->truncate();
        
        $bar = $this->command->getOutput()->createProgressBar($fileCount);
        $bar->start();

        $totalEntries = 0;
        $chunk = []; // Array untuk menampung data sebelum di-insert

        foreach ($jsonFiles as $filePath) {
            $content = File::get($filePath);
            $data = json_decode($content, true);

            if (isset($data['entri']) && is_array($data['entri'])) {
                foreach ($data['entri'] as $entri) {
                    $kata = $entri['nama'] ?? null;
                    $allMakna = [];

                    if ($kata && isset($entri['makna']) && is_array($entri['makna'])) {
                        foreach ($entri['makna'] as $makna) {
                            if (isset($makna['submakna']) && is_array($makna['submakna'])) {
                                $allMakna = array_merge($allMakna, $makna['submakna']);
                            }
                        }
                    }

                    if ($kata && !empty($allMakna)) {
                        $definisi = implode('; ', $allMakna);
                        
                        $chunk[] = [
                            'kata' => $kata,
                            'arti' => $definisi,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
            
            // --- FIX: Use upsert to handle duplicate entries gracefully ---
            if (count($chunk) >= 500) {
                $affectedRows = DB::table('kamuses')->upsert(
                    $chunk,
                    ['kata'], // Column to check for uniqueness
                    ['arti', 'updated_at'] // Columns to update if duplicate found
                );
                $totalEntries += $affectedRows;
                $chunk = []; // Clear the chunk
            }
            
            $bar->advance();
        }

        // Upsert any remaining data in the last chunk
        if (!empty($chunk)) {
            $affectedRows = DB::table('kamuses')->upsert(
                $chunk,
                ['kata'],
                ['arti', 'updated_at']
            );
            $totalEntries += $affectedRows;
        }

        $bar->finish();
        $this->command->info("\nProses impor selesai. Total " . number_format($totalEntries) . " entri berhasil diproses.");
    }
}
