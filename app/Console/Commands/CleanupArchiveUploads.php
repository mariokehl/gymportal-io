<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Removes staging directories of member archive uploads that were never
 * imported, e.g. because the user abandoned the page after the analysis.
 */
class CleanupArchiveUploads extends Command
{
    protected $signature = 'archive-uploads:cleanup {--hours=24 : Remove staging directories older than this}';

    protected $description = 'Remove abandoned member archive upload directories';

    public function handle(): int
    {
        $directory = storage_path('app/tmp');

        if (! is_dir($directory)) {
            $this->info('No staging directory present.');

            return self::SUCCESS;
        }

        $threshold = now()->subHours((int) $this->option('hours'))->getTimestamp();
        $removed = 0;

        foreach (glob($directory.'/member-archive-*') ?: [] as $path) {
            if (! is_dir($path) || filemtime($path) > $threshold) {
                continue;
            }

            $this->deleteDirectory($path);
            $removed++;
        }

        $this->info("Removed {$removed} abandoned archive upload(s).");

        return self::SUCCESS;
    }

    /**
     * Remove a directory and everything below it.
     */
    private function deleteDirectory(string $path): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
