<?php

namespace App\Console\Commands;

use App\Models\Satellite;
use App\Services\SatisfactionService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Ramsey\Uuid\Uuid;

class SeedSatisfactionDemo extends Command
{
    protected $signature = 'satisfaction:seed-demo {--confirm-local : Confirm this is a disposable local demo database} {--dry-run : Print the plan without writing} {--reset : Delete only responses listed in the private manifest}';
    protected $description = 'Seed or remove deterministic anonymous Satisfaction demo responses on a confirmed local DEBUG database.';

    private const BATCH = 'seed:satisfaction-demo-v1';
    private const SEED = 'elevate-satisfaction-20261019-v1';
    private const GROUPS = [
        ['G1', 'Anonas', '2026-10-12', 6, ['Church experience' => 6, 'Attendance' => 3, 'Activities' => 4]],
        ['G2', 'Anonas', '2026-10-15', 5, ['Church experience' => 5, 'Attendance' => 2, 'Activities' => 3]],
        ['G3', 'EASTWOOD', '2026-10-16', 4, []],
        ['G4', 'UBELT', '2026-10-13', 8, ['Church experience' => 7, 'Attendance' => 4, 'Activities' => 5]],
        ['G5', 'THE HUB', '2026-10-14', 1, []],
        ['G6', 'MAIN ORTIGAS', '2026-10-17', 3, []],
        ['G7', 'Anonas', '2026-10-19', 5, []],
        ['G8', 'Anonas', '2026-09-20', 9, []],
        ['G9', 'UBELT', '2026-09-22', 5, []],
        ['G10', 'Anonas', '2025-08-20', 6, []],
    ];

    public function handle(SatisfactionService $service): int
    {
        if (!$this->guard()) return self::FAILURE;
        $path = $this->manifestPath();
        if ($this->option('reset')) return $this->reset($path);
        $plan = $this->plan();
        if ($plan === null) return self::FAILURE;
        $this->table(['Group', 'Satellite', 'Service date', 'New responses', 'Privacy result'],
            array_map(fn ($group) => [$group['code'], $group['satellite'], $group['date'], $group['count'], $group['count'] >= SatisfactionService::MIN_RESPONSES ? 'Shown when isolated' : 'Hidden: fewer than 5'], $plan['groups']));
        $this->line('Only active, general Yes/No questions are seeded; DGroup-only questions are skipped. The response schema has no submission-time column.');
        if ($this->option('dry-run')) {
            $this->info('Dry run: no database rows or manifest were written.');
            return self::SUCCESS;
        }
        $manifest = File::exists($path) ? $this->readManifest($path) : $plan;
        if ($manifest === null || !$this->sameDatabase($manifest)) return self::FAILURE;
        if (($manifest['batch'] ?? null) !== self::BATCH || ($manifest['rows'] ?? null) !== $plan['rows']) {
            $this->error('Manifest does not match this deterministic plan. Nothing was changed.');
            return self::FAILURE;
        }
        if (!$this->safeToSeed($manifest)) return self::FAILURE;
        if (!File::exists($path)) {
            File::ensureDirectoryExists(dirname($path));
            $temporary = $path.'.tmp';
            File::put($temporary, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            if (!rename($temporary, $path)) {
                $this->error('Could not publish the manifest; no database rows were written.');
                return self::FAILURE;
            }
        }
        $created = DB::transaction(function () use ($manifest) {
            $created = 0;
            foreach ($manifest['rows'] as $row) {
                if (DB::table('satisfaction_responses')->where('id', $row['id'])->exists()) continue;
                DB::table('satisfaction_responses')->insert([
                    'id' => $row['id'], 'service_date' => $row['date'],
                    'satellite_id' => $row['satellite_id'], 'hub' => $row['hub'],
                ]);
                DB::table('satisfaction_answers')->insert(array_map(fn ($question, $yes) => [
                    'response_id' => $row['id'], 'question_id' => (int) $question, 'yes' => $yes,
                ], array_keys($row['answers']), array_values($row['answers'])));
                $created++;
            }
            return $created;
        });
        $this->info("Created {$created} anonymous demo responses; ".count($manifest['rows']).' manifest-listed responses now belong to this batch.');
        $this->verification($service, $manifest);
        return self::SUCCESS;
    }

    private function guard(): bool
    {
        if (!$this->option('confirm-local')) {
            $this->error('Refusing to run: pass --confirm-local after checking the target database.');
            return false;
        }
        if (!config('app.debug')) {
            $this->error('Refusing to run: APP_DEBUG must be true.');
            return false;
        }
        $driver = DB::getDefaultConnection();
        $config = config('database.connections.'.$driver, []);
        if ($driver === 'sqlite') {
            $database = (string) ($config['database'] ?? '');
            $local = ($database === ':memory:' && app()->environment('testing'))
                || ($database !== '' && $database !== ':memory:' && !str_starts_with($database, '\\\\')
                    && !str_starts_with($database, '//') && !str_starts_with($database, 'file:') && is_file($database));
        } else {
            $local = in_array(strtolower((string) ($config['host'] ?? '')), ['localhost', '127.0.0.1'], true);
        }
        if (!$local) {
            $this->error('Refusing to run: the configured database is not a local SQLite file or a localhost database host.');
            return false;
        }
        return true;
    }

    private function manifestPath(): string
    {
        return (string) config('satisfaction_demo.manifest_path', storage_path('app/private/satisfaction-demo/seed_manifest.json'));
    }

    private function databaseFingerprint(): string
    {
        $driver = DB::getDefaultConnection();
        $config = config('database.connections.'.$driver, []);
        $location = $driver === 'sqlite' ? realpath((string) ($config['database'] ?? '')) ?: ':memory:'
            : ($config['host'] ?? '').':'.($config['port'] ?? '').'/'.($config['database'] ?? '');
        return hash('sha256', $driver.'|'.$location);
    }

    private function plan(): ?array
    {
        $required = array_values(array_unique(array_column(self::GROUPS, 1)));
        $satellites = Satellite::whereIn('name', $required)->get()->keyBy('name');
        if ($satellites->count() !== count($required) || $satellites->contains(fn ($satellite) => !$satellite->hub)) {
            $this->error('The required existing demo satellites with hubs are unavailable; no satellite was created.');
            return null;
        }
        $questions = DB::table('satisfaction_questions as q')->join('satisfaction_categories as c', 'c.id', '=', 'q.category_id')
            ->where('q.active', true)->where('c.active', true)->where('q.dgroup_only', false)
            ->orderBy('c.position')->orderBy('q.position')->get(['q.id', 'c.name as category']);
        if ($questions->isEmpty()) {
            $this->error('No active general Satisfaction questions exist.');
            return null;
        }
        $today = now(config('app.timezone'))->toDateString();
        $rows = [];
        $groups = [];
        foreach (self::GROUPS as [$code, $name, $date, $count, $targets]) {
            if ($date > $today) {
                $this->error("{$code} is future-dated in the configured app timezone. Nothing was written.");
                return null;
            }
            $satellite = $satellites[$name];
            $groups[] = compact('code', 'date', 'count') + ['satellite' => $name, 'satellite_id' => $satellite->id];
            $questionAnswers = [];
            foreach ($questions as $question) {
                $target = $targets[$question->category] ?? ($count === 1 ? (hexdec(substr(hash('sha256', self::SEED.'|'.$code.'|'.$question->id), 0, 2)) % 2) : max(1, min($count - 1, (int) round($count * .6))));
                $order = range(0, $count - 1);
                usort($order, fn ($a, $b) => strcmp(hash('sha256', self::SEED.'|'.$code.'|'.$question->id.'|'.$a), hash('sha256', self::SEED.'|'.$code.'|'.$question->id.'|'.$b)));
                $questionAnswers[$question->id] = array_fill(0, $count, false);
                foreach (array_slice($order, 0, $target) as $index) $questionAnswers[$question->id][$index] = true;
            }
            for ($index = 0; $index < $count; $index++) {
                $answers = [];
                foreach ($questions as $question) $answers[(string) $question->id] = $questionAnswers[$question->id][$index];
                $rows[] = ['id' => Uuid::uuid5(Uuid::NAMESPACE_URL, self::SEED.'|'.$code.'|'.$index)->toString(),
                    'group' => $code, 'date' => $date, 'satellite_id' => $satellite->id, 'hub' => $satellite->hub,
                    'planned_submission_time' => sprintf('%02d:%02d %s', 8 + intdiv($index * 47, 60), ($index * 47) % 60, config('app.timezone')),
                    'answers' => $answers];
            }
        }
        return ['batch' => self::BATCH, 'database_fingerprint' => $this->databaseFingerprint(), 'groups' => $groups, 'rows' => $rows];
    }

    private function readManifest(string $path): ?array
    {
        try {
            $manifest = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !isset($manifest['rows']) || !is_array($manifest['rows'])) throw new \RuntimeException('Malformed manifest.');
            return $manifest;
        } catch (\Throwable $error) {
            $this->error('Cannot read the private seed manifest: '.$error->getMessage());
            return null;
        }
    }

    private function sameDatabase(array $manifest): bool
    {
        if (($manifest['database_fingerprint'] ?? null) === $this->databaseFingerprint()) return true;
        $this->error('The manifest belongs to a different database. Nothing was changed.');
        return false;
    }

    private function safeToSeed(array $manifest): bool
    {
        $ids = array_column($manifest['rows'], 'id');
        foreach ($manifest['groups'] as $group) {
            if (DB::table('satisfaction_responses')->where('satellite_id', $group['satellite_id'])
                ->where('service_date', $group['date'])->whereNotIn('id', $ids)->exists()) {
                $this->error("{$group['code']} overlaps an unmarked response. No demo data was written.");
                return false;
            }
        }
        foreach ($manifest['rows'] as $row) {
            if (!$this->rowMatches($row)) return false;
        }
        return true;
    }

    private function rowMatches(array $row): bool
    {
        $existing = DB::table('satisfaction_responses')->where('id', $row['id'])->first();
        if (!$existing) return true;
        $answers = DB::table('satisfaction_answers')->where('response_id', $row['id'])->pluck('yes', 'question_id')->map(fn ($yes) => (bool) $yes)->all();
        $expected = array_map(fn ($yes) => (bool) $yes, $row['answers']);
        if ($existing->service_date === $row['date'] && (int) $existing->satellite_id === (int) $row['satellite_id']
            && $existing->hub === $row['hub'] && $answers == $expected) return true;
        $this->error("Manifest-listed response {$row['id']} differs from its expected values. Nothing was changed.");
        return false;
    }

    private function reset(string $path): int
    {
        if (!File::exists($path)) {
            $this->info('No seed manifest exists; nothing to reset.');
            return self::SUCCESS;
        }
        $manifest = $this->readManifest($path);
        if ($manifest === null || !$this->sameDatabase($manifest) || ($manifest['batch'] ?? null) !== self::BATCH) return self::FAILURE;
        foreach ($manifest['rows'] as $row) if (!$this->rowMatches($row)) return self::FAILURE;
        if ($this->option('dry-run')) {
            $this->info('Dry run: would remove at most '.count($manifest['rows']).' manifest-listed anonymous responses. Nothing was written.');
            return self::SUCCESS;
        }
        $ids = array_column($manifest['rows'], 'id');
        $deleted = DB::transaction(fn () => DB::table('satisfaction_responses')->whereIn('id', $ids)->delete());
        File::delete($path);
        $this->info("Removed {$deleted} manifest-listed demo responses and their answers. Unlisted responses and tokens were untouched.");
        return self::SUCCESS;
    }

    private function verification(SatisfactionService $service, array $manifest): void
    {
        $table = [];
        foreach ($manifest['groups'] as $group) {
            $rows = array_values(array_filter($manifest['rows'], fn ($row) => $row['group'] === $group['code']));
            $yes = [];
            foreach ($rows as $row) foreach ($row['answers'] as $question => $value) $yes[$question] = ($yes[$question] ?? 0) + (int) $value;
            $request = Request::create('/admin/analytics/sentiment', 'GET', ['day' => $group['date'], 'satellite' => $group['satellite_id']]);
            $results = $service->results($request, $service->period($request));
            $outcome = $this->card($results['overall']);
            $table[] = [$group['code'], $group['satellite'], $group['date'], count($rows),
                implode(' ', array_map(fn ($question, $count) => 'Q'.$question.':'.$count, array_keys($yes), array_values($yes))), $outcome];
        }
        $this->table(['Group', 'Satellite', 'Date', 'N', 'Seed-only Yes counts per question', 'Expected filtered Overall'], $table);
        $scopes = [
            ['Live (all dates)', []],
            ['October 2026', ['month' => 'October', 'year' => 2026]],
            ['September 2026', ['month' => 'September', 'year' => 2026]],
            ['Anonas, Live', ['satellite' => $manifest['groups'][0]['satellite_id']]],
        ];
        $summary = [];
        foreach ($scopes as [$name, $parameters]) {
            $request = Request::create('/admin/analytics/sentiment', 'GET', $parameters);
            $results = $service->results($request, $service->period($request));
            $cards = ['Overall' => $results['overall']];
            foreach ($results['categories'] as $category) $cards[$category['name']] = $category;
            $summary[] = [$name, ...array_map(fn ($row) => $this->card($row), array_values($cards))];
        }
        $this->table(['Scope', 'Overall', 'Church experience', 'Attendance', 'Activities'], $summary);
        $this->line('These are aggregate checks only. The manifest is private; the app never displays individual answers.');
    }

    private function card(array $row): string
    {
        if (!$row['responses']) return 'No responses';
        if (!($row['show'] ?? true) || $row['responses'] < SatisfactionService::MIN_RESPONSES) return 'Hidden: privacy threshold';
        return round($row['yes'] / $row['total'] * 100).'% ('.$row['yes'].'/'.$row['total'].')';
    }
}
