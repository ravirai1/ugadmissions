<?php

namespace App\Console\Commands;

use App\Services\SecurityAlerts;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Detects a defaced or back-doored site: compares every code file (index.php, app, config, routes, views, public
 * assets ...) with the fingerprints taken at deploy time, and looks for PHP files where none should be
 * (uploads, public/ other than index.php). Any difference is emailed to the super admins.
 *
 * After each deploy:  php artisan security:integrity --baseline
 */
class CheckFileIntegrity extends Command
{
    protected $signature = 'security:integrity {--baseline : Record the current files as the trusted version (run after each deploy)}';

    protected $description = 'Check that no code file was changed, added or removed since the last deploy';

    /** Folders (relative to the app root) whose files are fingerprinted. */
    private const WATCH = ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes'];

    private const EXTRA_FILES = ['artisan', 'composer.json', 'composer.lock', '.htaccess', 'public/.htaccess', 'public/index.php', 'vendor/autoload.php', 'vendor/composer/installed.json'];

    public static function baselinePath(): string
    {
        return storage_path('app/private/security/integrity-baseline.json');
    }

    public static function statusPath(): string
    {
        return storage_path('app/private/security/integrity-status.json');
    }

    public function handle(SecurityAlerts $alerts): int
    {
        $current = $this->fingerprint();

        if ($this->option('baseline')) {
            $this->save(self::baselinePath(), ['files' => $current, 'taken_at' => now()->toIso8601String(), 'sig' => $this->sign($current)]);
            $this->status('ok', 'Baseline recorded ('.count($current).' files).', []);
            $this->info('Baseline recorded for '.count($current).' files.');

            return self::SUCCESS;
        }

        $base = is_file(self::baselinePath()) ? json_decode((string) file_get_contents(self::baselinePath()), true) : null;
        if (! $base || ! isset($base['files'], $base['sig'])) {
            $this->status('warning', 'No baseline yet. Run php artisan security:integrity --baseline after deploying.', []);
            $this->warn('No baseline. Run with --baseline after deploying.');

            return self::FAILURE;
        }
        if (! hash_equals((string) $base['sig'], $this->sign($base['files']))) {
            $this->report($alerts, 'The file-integrity baseline itself was altered', ['The saved fingerprints do not match their signature. Someone may have tried to hide a change.']);

            return self::FAILURE;
        }

        $changed = array_keys(array_filter($current, fn ($h, $f) => isset($base['files'][$f]) && $base['files'][$f] !== $h, ARRAY_FILTER_USE_BOTH));
        $added = array_keys(array_diff_key($current, $base['files']));
        $removed = array_keys(array_diff_key($base['files'], $current));
        $rogue = $this->rogueScripts();

        $lines = [];
        foreach (['Changed' => $changed, 'New' => $added, 'Deleted' => $removed, 'PHP/script file where none should be' => $rogue] as $label => $files) {
            foreach (array_slice($files, 0, 25) as $f) {
                $lines[] = "$label: $f";
            }
            if (count($files) > 25) {
                $lines[] = "$label: ... and ".(count($files) - 25).' more';
            }
        }

        if (! $lines) {
            $this->status('ok', 'All '.count($current).' files match the deployed version.', []);
            $this->info('No changes.');

            return self::SUCCESS;
        }

        $this->report($alerts, 'Website files changed outside a deploy', $lines);

        return self::FAILURE;
    }

    /** @return array<string, string> relative path => sha256 */
    private function fingerprint(): array
    {
        $root = base_path();
        $dirs = array_values(array_filter(array_map(fn ($d) => "$root/$d", self::WATCH), 'is_dir'));
        $out = [];
        $finder = (new Finder)->files()->in($dirs)->ignoreDotFiles(false)->ignoreVCS(true)
            ->exclude(['cache', 'storage'])   // bootstrap/cache is rebuilt by artisan; public/storage is the upload link
            ->notPath('#^cache/#')
            ->notName(['*.sqlite', '*.sqlite-journal', '*.sqlite-wal', '*.sqlite-shm']); // the local database is data, not code
        foreach ($finder as $f) {
            $out[ltrim(str_replace($root, '', $f->getPathname()), '/')] = hash_file('sha256', $f->getPathname());
        }
        foreach (self::EXTRA_FILES as $f) {
            if (is_file("$root/$f")) {
                $out[$f] = hash_file('sha256', "$root/$f");
            }
        }
        ksort($out);

        return $out;
    }

    /** PHP or server-script files in places that must only hold uploads or static assets. */
    private function rogueScripts(): array
    {
        $root = base_path();
        $found = [];
        $places = array_filter([public_path(), storage_path('app')], 'is_dir');
        $finder = (new Finder)->files()->in($places)->ignoreDotFiles(false)
            ->name('/\.(php\d?|phtml|phar|pht|cgi|pl|py|sh|asp|aspx|jsp)$/i')->name('.htaccess')->name('.user.ini');
        foreach ($finder as $f) {
            $rel = ltrim(str_replace($root, '', $f->getPathname()), '/');
            if (! in_array($rel, ['public/index.php', 'public/.htaccess'], true)) {
                $found[] = $rel;
            }
        }

        return $found;
    }

    private function report(SecurityAlerts $alerts, string $title, array $lines): void
    {
        $this->status('alert', $title, $lines);
        logger()->critical($title, ['files' => $lines]);
        $this->error($title);
        foreach ($lines as $l) {
            $this->line("  $l");
        }
        $alerts->send('integrity:'.sha1(implode("\n", $lines)), $title, [
            'Server' => (string) gethostname(),
            'Details' => implode(' | ', array_slice($lines, 0, 40)),
            'What to do' => 'If this was not a planned deploy, take the site offline and restore from the repository. If it was a deploy, run php artisan security:integrity --baseline.',
        ], route('admin.security.threats'));
    }

    private function status(string $state, string $message, array $lines): void
    {
        $this->save(self::statusPath(), ['state' => $state, 'message' => $message, 'lines' => array_slice($lines, 0, 50), 'checked_at' => now()->toIso8601String()]);
    }

    private function sign(array $files): string
    {
        return hash_hmac('sha256', json_encode($files), (string) config('app.key'));
    }

    private function save(string $path, array $data): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0750, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
