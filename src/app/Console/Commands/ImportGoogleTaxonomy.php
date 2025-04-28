<?php

namespace Backpack\Store\app\Console\Commands;

use Backpack\Store\app\Models\MerchantCategory;
use Illuminate\Console\Command;

class ImportGoogleTaxonomy extends Command
{
    protected $signature = 'import:google-taxonomy {filename}';
    protected $description = 'Import Google Product Taxonomy from TXT file located in Files folder';

    public function handle()
    {
        // Путь к папке Files относительно расположения команды
        $filePath = __DIR__ . '/Files/' . $this->argument('filename');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Пропускаем строки с заголовком
            if (str_starts_with($line, '#')) {
                continue;
            }

            // Разделяем строку на key и name
            $parts = explode(' - ', $line, 2);
            if (count($parts) !== 2) {
                $this->warn("Invalid line format: {$line}");
                continue;
            }

            $key = trim($parts[0]);
            $name = trim($parts[1]);

            // Создаем или обновляем запись
            MerchantCategory::updateOrCreate(
                ['key' => $key],
                ['name' => $name]
            );
        }

        $this->info('Taxonomy imported successfully!');
        return 0;
    }
}