<?php

namespace App\Console\Commands;

use App\Services\ThesisEvidenceMarkdown;
use App\Services\ThesisEvidencePortableReport;
use App\Services\ThesisEvidenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExportThesisEvidence extends Command
{
    protected $signature = 'research:thesis-evidence {--format=md : md or json} {--output=storage/research/ : Directory under storage/research} {--force : Explicitly allow production export}';
    protected $description = 'Export paper-only evidence using SELECT queries; never change research data';

    public function handle(ThesisEvidenceService $builder, ThesisEvidencePortableReport $privacy, ThesisEvidenceMarkdown $markdown): int
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Production export refused. Use --force only for an authorized offline evidence export.');
            return self::FAILURE;
        }
        $format = $this->option('format');
        if (!in_array($format, ['md', 'json'], true)) {
            $this->error('Format must be md or json.');
            return self::FAILURE;
        }
        try {
            $directory = $this->directory((string) $this->option('output'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        // Guard before execution, rather than detecting writes after they happened.
        $guardActive = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$guardActive): void {
            if ($guardActive && !preg_match('/^\s*select\b/i', $sql)) {
                throw new RuntimeException('Evidence generation permits SELECT queries only.');
            }
        });
        try {
            $report = $privacy->prepare($builder->report());
            $bytes = $format === 'json'
                ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
                : $markdown->render($report);
        } catch (\Throwable) {
            // Database/prediction exceptions can contain source answers. Never print them.
            $this->error('Evidence generation failed. No report was exported; source details were withheld.');
            return self::FAILURE;
        } finally {
            $guardActive = false;
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            $this->error('Unable to create the private output directory.');
            return self::FAILURE;
        }
        // Recheck resolved paths after directory creation; never follow a public-storage link.
        try { $directory = $this->directory($directory); }
        catch (RuntimeException $exception) { $this->error($exception->getMessage()); return self::FAILURE; }
        $path = $directory.'/thesis-evidence-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.'.$format;
        $file = fopen($path, 'xb');
        if (!$file) { $this->error('Unable to create the export file.'); return self::FAILURE; }
        try {
            $written = fwrite($file, $bytes);
        } finally { fclose($file); }
        if ($written !== strlen($bytes)) {
            unlink($path);
            $this->error('Incomplete export removed.');
            return self::FAILURE;
        }
        chmod($path, 0600);
        $this->info('Evidence exported to '.$path);
        return self::SUCCESS;
    }

    private function directory(string $requested): string
    {
        $requested = str_replace('\\', '/', $requested);
        if ($requested === '' || str_contains($requested, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $requested)) {
            throw new RuntimeException('Output must be a directory under storage/research without traversal.');
        }
        $absolute = preg_match('~^(?:[A-Za-z]:/|/)~', $requested) ? $requested : base_path($requested);
        $absolute = rtrim(str_replace('\\', '/', $absolute), '/');
        $storage = str_replace('\\', '/', realpath(storage_path()));
        $root = $storage.'/research';
        if ($absolute !== $root && !str_starts_with($absolute, $root.'/')) {
            throw new RuntimeException('Output must stay under storage/research, outside all served storage disks.');
        }
        $cursor = $storage;
        foreach (explode('/', substr($absolute, strlen($storage) + 1)) as $part) {
            $cursor .= '/'.$part;
            if (is_link($cursor) || (file_exists($cursor) && (!is_dir($cursor) || str_replace('\\', '/', realpath($cursor)) !== $cursor))) {
                throw new RuntimeException('Linked or non-directory output paths are not permitted.');
            }
        }
        return $absolute;
    }
}
