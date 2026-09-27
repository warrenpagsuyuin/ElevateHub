<?php

namespace App\Services;

class ThesisEvidenceVerification
{
    public function path(): string
    {
        return config('thesis_evidence.verification_path', storage_path('app/private/thesis-evidence/verification.json'));
    }

    public function fingerprint(): string
    {
        $files = [];
        foreach (['app', 'routes', 'resources', 'tests', 'database/migrations', 'config'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && preg_match('/\.(php|js|css|json)$/', $file->getFilename())) {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
                    $files[$relative] = hash_file('sha256', $file->getPathname());
                }
            }
        }
        foreach (['composer.lock', 'phpunit.xml', 'bootstrap/app.php', 'scripts/verify-thesis-evidence.php', 'docs/thesis-functional-conditions.json'] as $file) {
            if (is_file(base_path($file))) $files[$file] = hash_file('sha256', base_path($file));
        }
        ksort($files);
        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }

    public function report(): array
    {
        $empty = ['current'=>false, 'status'=>'UNTESTED', 'reason'=>'No recorded test run for this source revision.', 'tests'=>[], 'total'=>0, 'passed'=>0, 'failed'=>0, 'skipped'=>0];
        if (!is_file($this->path())) return $empty;
        try { $run = json_decode(file_get_contents($this->path()), true, 512, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { return $empty; }
        if (!is_array($run) || ($run['source_sha256'] ?? '') !== $this->fingerprint()
            || ($run['source_unchanged_during_run'] ?? false) !== true || !is_array($run['tests'] ?? null)) {
            return array_replace($empty, ['reason'=>'Source changed after the recorded test run. Run scripts/verify-thesis-evidence.php again.']);
        }
        return array_replace($empty, $run, ['current'=>true]);
    }

    public function conditions(string $table, array $run): array
    {
        $path = base_path('docs/thesis-functional-conditions.json');
        $definitions = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        $rows = $definitions[$table] ?? [];
        foreach ($rows as &$row) {
            $names = $row['tests'] ?? [];
            $statuses = array_map(fn ($name) => $run['tests'][$name]['status'] ?? 'UNTESTED', $names);
            $row['status'] = !$run['current'] || !$names ? 'UNTESTED' : (in_array('FAIL', $statuses, true) ? 'FAIL' : (count(array_filter($statuses, fn ($s) => $s === 'PASS')) === count($names) ? 'PASS' : 'UNTESTED'));
            $row['actual'] = $row['status'] === 'PASS' ? ($row['verified_result'] ?? 'Assertions passed in the isolated test database.') : ($row['status'] === 'FAIL' ? 'At least one mapped automated test failed. See verification log.' : $run['reason']);
        }
        return $rows;
    }
}
