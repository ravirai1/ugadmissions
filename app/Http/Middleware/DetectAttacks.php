<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Models\SecurityEvent;
use App\Services\SecurityAlerts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A second line of defence. The code itself is safe from SQL injection (Eloquent / bound parameters only) and
 * XSS (Blade escapes output, strict CSP), but requests that are plainly attacks are refused here, saved in
 * security_events, and an IP that keeps trying is blocked from the whole site and the super admins are emailed.
 */
class DetectAttacks
{
    public const SITE = 'site';

    private const PATTERNS = [
        'sqli' => [
            '/\bunion\b[\s(]+(all\s+|distinct\s+)?select\b/i',
            '/[\'"`]\s*\)?\s*(or|and|\|\||&&)\s+[\'"`]?\w+[\'"`]?\s*(=|<>|!=|like)\s*[\'"`]?\w+/i',
            '/[\'"`]\s*\)?\s*(or|and)\s+\d+\s*=\s*\d+/i',
            '/\b(sleep|benchmark|pg_sleep)\s*\(\s*\d/i',
            '/\bwaitfor\s+delay\b/i',
            '/;\s*(drop|truncate|alter|delete|insert|update|create)\s+(table|database|from|into|user)\b/i',
            '/\b(information_schema|sqlite_master|mysql\.user|pg_catalog)\b/i',
            '/\b(load_file|into\s+(out|dump)file)\b/i',
            '/@@version|\bextractvalue\s*\(|\bupdatexml\s*\(/i',
            '/[\'"`]\s*;?\s*(--|#)\s*$/m',
        ],
        'xss' => [
            '/<\s*\/?\s*(script|iframe|object|embed|svg|math|base|meta|link)\b/i',
            '/\bjavascript\s*:/i',
            '/\bvbscript\s*:/i',
            '/\bon(error|load|mouseover|focus|click|toggle|animationstart|pointerover)\s*=/i',
            '/document\s*\.\s*(cookie|domain|write)|window\s*\.\s*location|\beval\s*\(/i',
        ],
        'traversal' => [
            '#\.\./|\.\.\\\\#',
            '#/etc/(passwd|shadow|hosts)|\bboot\.ini\b|win\.ini#i',
            '#\b(php|file|data|expect|phar|zip|glob)://#i',
            '/<\?(php|=)/i',
            '/\x00/',
        ],
    ];

    private const PROBE_PATHS = '#^/?(\.env|\.git|\.svn|\.aws|\.ht|wp-|wordpress|xmlrpc\.php|phpmyadmin|pma|myadmin|adminer|phpinfo|vendor/|storage/|cgi-bin|server-status|actuator|\.well-known/security|.*\.(php\d?|phtml|asp|aspx|jsp|cgi|sql|bak|old|swp|zip|tar|gz)$)#i';

    private const SCANNERS = '/sqlmap|nikto|nmap|acunetix|wpscan|masscan|zgrab|nuclei|havij|dirbuster|gobuster|ffuf|wfuzz|w3af|netsparker|burpcollaborator|openvas/i';

    public function __construct(private SecurityAlerts $alerts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();

        if ($this->siteBlocked($ip)) {
            abort(403, 'Access from your network has been blocked for security reasons. If you think this is a mistake, contact the admissions office.');
        }

        if (! config('security.detect_attacks') || $request->is(...config('security.skip_paths'))) {
            return $next($request);
        }

        if ($hit = $this->inspect($request)) {
            $this->record($request, ...$hit);
            abort(403, 'Your request was blocked for security reasons. If you think this is a mistake, contact the admissions office.');
        }

        return $next($request);
    }

    /** @return array{0: string, 1: ?string, 2: string}|null [type, field, sample] */
    private function inspect(Request $request): ?array
    {
        $path = '/'.ltrim($request->path(), '/');
        if ($path !== '/index.php' && preg_match(self::PROBE_PATHS, ltrim($path, '/'))) {
            return ['probe', null, $path];
        }
        if (preg_match(self::SCANNERS, (string) $request->userAgent())) {
            return ['scanner', 'user-agent', (string) $request->userAgent()];
        }
        if ($hit = $this->match($path, 'url')) {
            return $hit;
        }

        // Runs before routing (so unknown paths are caught too). Staff panel forms are only reached after login + OTP
        // and may legitimately contain anything (notice text, remarks), so only the staff login form body is inspected there.
        $admin = config('admissions.admin_path');
        if ($request->is($admin.'/*') && ! $request->is($admin.'/login', $admin.'/otp*')) {
            return null;
        }

        $skip = config('security.skip_fields');
        foreach ($this->flatten($request->query->all() + $request->request->all()) as $field => $value) {
            if (in_array(Str::afterLast($field, '.'), $skip, true)) {
                continue;
            }
            if (($hit = $this->match($field, 'field name')) || ($hit = $this->match($value, $field))) {
                return $hit;
            }
        }
        foreach ($request->allFiles() as $field => $file) {
            foreach (is_array($file) ? Arr::flatten($file) : [$file] as $f) {
                if ($f && preg_match('/\.(php\d?|phtml|phar|pht|shtml|htaccess|exe|sh|js|html?|svg)$/i', $f->getClientOriginalName())) {
                    return ['traversal', (string) $field, 'Uploaded file named '.$f->getClientOriginalName()];
                }
            }
        }

        return null;
    }

    private function match(string $value, string $field): ?array
    {
        if ($value === '') {
            return null;
        }
        // Undo URL encoding (twice, for double-encoded payloads) and SQL comments used to hide keywords.
        $v = rawurldecode(rawurldecode($value));
        $v = preg_replace('#/\*.*?\*/#s', ' ', $v);
        $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5);
        foreach (self::PATTERNS as $type => $patterns) {
            foreach ($patterns as $p) {
                if (preg_match($p, $v)) {
                    return [$type, $field, $value];
                }
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } elseif (is_scalar($v)) {
                $out[$key] = (string) $v;
            }
        }

        return $out;
    }

    private function record(Request $request, string $type, ?string $field, string $sample): void
    {
        $ip = (string) $request->ip();
        $event = SecurityEvent::create([
            'type' => $type,
            'ip' => $ip,
            'method' => $request->method(),
            'path' => Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
            'field' => $field ? Str::limit($field, 100, '') : null,
            'sample' => Str::limit(preg_replace('/[\x00-\x1F\x7F]/', ' ', $sample), 200, ''),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);
        logger()->warning('Attack attempt refused', ['type' => $type, 'ip' => $ip, 'path' => $event->path, 'field' => $field]);

        $cfg = config('security.auto_block');
        $count = SecurityEvent::where('ip', $ip)->where('created_at', '>=', now()->subMinutes($cfg['window_minutes']))->count();

        if ($count >= $cfg['max_events']) {
            BlockedIp::updateOrCreate(['ip' => $ip, 'guard' => self::SITE], [
                'reason' => "$count attack attempts in {$cfg['window_minutes']} minutes (".(SecurityEvent::TYPES[$type] ?? $type).')',
                'blocked_until' => now()->addMinutes($cfg['block_minutes']),
                'blocked_by' => null,
            ]);
            Cache::forget('site-block:'.$ip);
            $event->forceFill(['caused_block' => true])->save();
            $this->alerts->send('block:'.$ip, 'IP blocked after repeated attack attempts', [
                'IP' => $ip,
                'Attempts' => "$count in {$cfg['window_minutes']} minutes",
                'Last attempt' => (SecurityEvent::TYPES[$type] ?? $type).' on '.$event->method.' '.$event->path,
                'Blocked for' => $cfg['block_minutes'].' minutes',
            ], route('admin.security.threats', ['q' => $ip]));
        } elseif ($count === 1) {
            $this->alerts->send('attack:'.$ip, 'Attack attempt refused', [
                'IP' => $ip,
                'Type' => SecurityEvent::TYPES[$type] ?? $type,
                'Request' => $event->method.' '.$event->path,
            ], route('admin.security.threats', ['q' => $ip]));
        }
    }

    private function siteBlocked(string $ip): bool
    {
        return Cache::remember('site-block:'.$ip, 60, fn () => BlockedIp::active()->where('ip', $ip)->where('guard', self::SITE)->exists());
    }
}
