<?php
declare(strict_types=1);

namespace AstrX\GitBrowse;

/**
 * Reads repositories from a gitweb server's JSON API.
 *
 * @phpstan-type Commit array{sha:string, short:string, author:string, ts:int, subject:string}
 * @phpstan-type Summary array{ok:bool, found:bool, description:string, default_branch:string,
 *               branches:list<string>, tags:list<string>, commit_count:int, commits:list<Commit>}
 *
 * Every argument is checked here before it goes into a URL (repository name,
 * ref, path, commit id), and only fixed paths are added to the configured
 * address, so a visitor can't make the site request anything else. Requests
 * have a short timeout and a size cap and never follow redirects; a failure
 * becomes `ok: false`, never an exception.
 *
 * Text comes back as gitweb sent it, minus control characters other than
 * newline and tab. It is never HTML: the templates print it escaped.
 */
final class GitBrowseClient
{
    /** The largest response read: a file view is capped at 512 KiB by gitweb. */
    private const int MAX_BYTES = 1_048_576;

    public function __construct(private readonly GitBrowseConfig $config) {}

    /** @return array{ok:bool, repos:list<array{name:string, description:string, last_commit_ts:int}>} */
    public function repos(): array
    {
        $d = $this->get('/repos.json')['data'];
        if ($d === null) {
            return ['ok' => false, 'repos' => []];
        }
        $out = [];
        foreach (self::list($d['repos'] ?? null) as $r) {
            $name = self::s($r['name'] ?? null);
            if (!self::validRepo($name)) {
                continue;
            }
            $out[] = [
                'name'           => $name,
                'description'    => self::line($r['description'] ?? null),
                'last_commit_ts' => self::i($r['last_commit_ts'] ?? null),
            ];
        }
        usort($out, static fn(array $a, array $b): int => $b['last_commit_ts'] <=> $a['last_commit_ts']);
        return ['ok' => true, 'repos' => $out];
    }

    /** @return Summary */
    public function summary(string $repo): array
    {
        $fail = ['ok' => false, 'found' => false, 'description' => '', 'default_branch' => '', 'branches' => [],
                 'tags' => [], 'commit_count' => 0, 'commits' => []];
        if (!self::validRepo($repo)) {
            return ['ok' => true] + $fail;
        }
        ['status' => $status, 'data' => $d] = $this->get('/' . rawurlencode($repo) . '/summary.json');
        if ($d === null) {
            return ['ok' => $status === 404] + $fail;
        }
        return [
            'ok'             => true,
            'found'          => true,
            'description'    => self::line($d['description'] ?? null),
            'default_branch' => self::refOrEmpty($d['default_branch'] ?? null),
            'branches'       => self::refs($d['branches'] ?? null),
            'tags'           => self::refs($d['tags'] ?? null),
            'commit_count'   => max(0, self::i($d['commit_count'] ?? null)),
            'commits'        => self::commits($d['commits'] ?? null),
        ];
    }

    /**
     * A directory, or `is_file: true` when the path is a file.
     *
     * @return array{ok:bool, found:bool, is_file:bool, ref:string, entries:list<array{name:string, path:string, type:string, size:int}>,
     *               truncated:bool, readme_name:string, readme:string}
     */
    public function tree(string $repo, string $ref, string $path): array
    {
        $fail = ['ok' => false, 'found' => false, 'is_file' => false, 'ref' => '', 'entries' => [],
                 'truncated' => false, 'readme_name' => '', 'readme' => ''];
        if (!self::validRepo($repo) || !self::validRef($ref) || !self::validPath($path)) {
            return ['ok' => true] + $fail;
        }
        ['status' => $status, 'error' => $error, 'data' => $d] = $this->get('/' . rawurlencode($repo) . '/tree.json?' . self::query($ref, $path));
        if ($d === null) {
            // gitweb answers a file path with 400 "not a directory".
            if ($status === 400 && $error === 'not a directory') {
                return ['ok' => true, 'found' => true, 'is_file' => true] + $fail;
            }
            return ['ok' => $status === 404 || $status === 400] + $fail;
        }
        $entries = [];
        foreach (self::list($d['entries'] ?? null) as $e) {
            $p = self::s($e['path'] ?? null);
            $type = self::s($e['type'] ?? null);
            if (!self::validPath($p) || !in_array($type, ['tree', 'blob', 'commit'], true)) {
                continue;
            }
            $entries[] = [
                'name' => self::line($e['name'] ?? null),
                'path' => $p,
                'type' => $type,
                'size' => max(0, self::i($e['size'] ?? null)),
            ];
        }
        $readme = is_array($d['readme'] ?? null) ? $d['readme'] : [];
        return [
            'ok'          => true,
            'found'       => true,
            'is_file'     => false,
            'ref'         => self::refOrEmpty($d['ref'] ?? null),
            'entries'     => $entries,
            'truncated'   => (bool) ($d['truncated'] ?? false),
            'readme_name' => self::line($readme['name'] ?? null),
            'readme'      => self::text($readme['text'] ?? null),
        ];
    }

    /** @return array{ok:bool, found:bool, size:int, binary:bool, truncated:bool, text:string} */
    public function blob(string $repo, string $ref, string $path): array
    {
        $fail = ['ok' => false, 'found' => false, 'size' => 0, 'binary' => false, 'truncated' => false, 'text' => ''];
        if (!self::validRepo($repo) || !self::validRef($ref) || !self::validPath($path) || $path === '') {
            return ['ok' => true] + $fail;
        }
        ['status' => $status, 'data' => $d] = $this->get('/' . rawurlencode($repo) . '/blob.json?' . self::query($ref, $path));
        if ($d === null) {
            return ['ok' => $status === 404 || $status === 400] + $fail;
        }
        return [
            'ok'        => true,
            'found'     => true,
            'size'      => max(0, self::i($d['size'] ?? null)),
            'binary'    => (bool) ($d['binary'] ?? false),
            'truncated' => (bool) ($d['truncated'] ?? false),
            'text'      => self::text($d['text'] ?? null),
        ];
    }

    /** @return array{ok:bool, found:bool, has_more:bool, commits:list<array{sha:string, short:string, author:string, ts:int, subject:string}>} */
    public function log(string $repo, string $ref, int $page): array
    {
        $fail = ['ok' => false, 'found' => false, 'has_more' => false, 'commits' => []];
        if (!self::validRepo($repo) || !self::validRef($ref)) {
            return ['ok' => true] + $fail;
        }
        $q = 'page=' . max(1, $page) . ($ref !== '' ? '&ref=' . rawurlencode($ref) : '');
        ['status' => $status, 'data' => $d] = $this->get('/' . rawurlencode($repo) . '/log.json?' . $q);
        if ($d === null) {
            return ['ok' => $status === 404 || $status === 400] + $fail;
        }
        return [
            'ok'       => true,
            'found'    => true,
            'has_more' => (bool) ($d['has_more'] ?? false),
            'commits'  => self::commits($d['commits'] ?? null),
        ];
    }

    /**
     * @return array{ok:bool, found:bool, sha:string, short:string, author:string, date:string, parents:list<string>,
     *               subject:string, body:string, signed:bool, verified:bool, diff:string, diff_truncated:bool}
     */
    public function commit(string $repo, string $id): array
    {
        $fail = ['ok' => false, 'found' => false, 'sha' => '', 'short' => '', 'author' => '', 'date' => '',
                 'parents' => [], 'subject' => '', 'body' => '', 'signed' => false, 'verified' => false,
                 'diff' => '', 'diff_truncated' => false];
        if (!self::validRepo($repo) || preg_match('/^[0-9a-f]{7,64}$/', $id) !== 1) {
            return ['ok' => true] + $fail;
        }
        ['status' => $status, 'data' => $d] = $this->get('/' . rawurlencode($repo) . '/commit.json?id=' . $id);
        if ($d === null) {
            return ['ok' => $status === 404 || $status === 400] + $fail;
        }
        $parents = [];
        foreach (is_array($d['parents'] ?? null) ? $d['parents'] : [] as $p) {
            if (is_string($p) && preg_match('/^[0-9a-f]{7,64}$/', $p) === 1) {
                $parents[] = $p;
            }
        }
        return [
            'ok'             => true,
            'found'          => true,
            'sha'            => self::hex($d['sha'] ?? null),
            'short'          => self::hex($d['short'] ?? null),
            'author'         => self::line($d['author_name'] ?? null),
            'date'           => self::line($d['author_date'] ?? null),
            'parents'        => $parents,
            'subject'        => self::line($d['subject'] ?? null),
            'body'           => self::text($d['body'] ?? null),
            'signed'         => (bool) ($d['signed'] ?? false),
            'verified'       => (bool) ($d['signature_verified'] ?? false),
            'diff'           => self::text($d['diff'] ?? null),
            'diff_truncated' => (bool) ($d['diff_truncated'] ?? false),
        ];
    }

    // ── validation ───────────────────────────────────────────────────────────

    /** A repository name as gitweb names them: one path segment, no dot-files. */
    public static function validRepo(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_][A-Za-z0-9._-]{0,127}$/', $name) === 1;
    }

    /** '' (the default branch), a branch, tag or commit id. */
    public static function validRef(string $ref): bool
    {
        return $ref === '' || (preg_match('#^[A-Za-z0-9_][A-Za-z0-9._/-]{0,199}$#', $ref) === 1
            && !str_contains($ref, '..') && !str_ends_with($ref, '/') && !str_ends_with($ref, '.lock'));
    }

    /** '' (the root) or a relative path without `.`/`..` segments or control characters. */
    public static function validPath(string $path): bool
    {
        if ($path === '') {
            return true;
        }
        if (strlen($path) > 1024 || preg_match('/[\x00-\x1f\x7f]/', $path) === 1 || str_starts_with($path, '/')) {
            return false;
        }
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..') {
                return false;
            }
        }
        return true;
    }

    // ── transport ────────────────────────────────────────────────────────────

    /**
     * GET a JSON object. `data` is null on any failure; `status` is the HTTP
     * status (0 when the server couldn't be reached) and `error` gitweb's
     * error message.
     *
     * @return array{status:int, error:string, data:array<string,mixed>|null}
     */
    private function get(string $pathAndQuery): array
    {
        $headers = "Accept: application/json\r\nConnection: close\r\n";
        if ($this->config->authUser() !== '') {
            $headers .= 'Authorization: Basic '
                . base64_encode($this->config->authUser() . ':' . $this->config->authPassword()) . "\r\n";
        }
        $context = stream_context_create(['http' => [
            'method'          => 'GET',
            'timeout'         => $this->config->timeoutSeconds(),
            'ignore_errors'   => true,   // read the body of a 4xx/5xx too
            'follow_location' => 0,
            'max_redirects'   => 0,
            'header'          => $headers,
        ]]);
        // '@': a refused connection would otherwise be a warning, promoted to a 500.
        $raw = @file_get_contents($this->config->apiUrl() . $pathAndQuery, false, $context, 0, self::MAX_BYTES);
        // PHP 8.4 has a function for the response headers; older PHP fills
        // $http_response_header instead.
        $lines = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : $http_response_header;
        $first  = is_array($lines) ? ($lines[0] ?? '') : '';
        $status = is_string($first) && preg_match('#^HTTP/\S+\s+(\d{3})#', $first, $m) === 1 ? (int) $m[1] : 0;
        /** @var mixed $data */
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return ['status' => $status, 'error' => '', 'data' => null];
        }
        if ($status !== 200) {
            return ['status' => $status, 'error' => self::line($data['error'] ?? null), 'data' => null];
        }
        /** @var array<string,mixed> $data */
        return ['status' => $status, 'error' => '', 'data' => $data];
    }

    private static function query(string $ref, string $path): string
    {
        $q = 'path=' . rawurlencode($path);
        return $ref !== '' ? $q . '&ref=' . rawurlencode($ref) : $q;
    }

    // ── shaping untrusted values ─────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    private static function list(mixed $v): array
    {
        $out = [];
        foreach (is_array($v) ? $v : [] as $row) {
            if (is_array($row)) {
                /** @var array<string,mixed> $row */
                $out[] = $row;
            }
        }
        return $out;
    }

    /** @return list<array{sha:string, short:string, author:string, ts:int, subject:string}> */
    private static function commits(mixed $v): array
    {
        $out = [];
        foreach (self::list($v) as $c) {
            $sha = self::hex($c['sha'] ?? null);
            if ($sha === '') {
                continue;
            }
            $out[] = [
                'sha'     => $sha,
                'short'   => self::hex($c['short'] ?? null) ?: substr($sha, 0, 7),
                'author'  => self::line($c['author'] ?? null),
                'ts'      => max(0, self::i($c['ts'] ?? null)),
                'subject' => self::line($c['subject'] ?? null),
            ];
        }
        return $out;
    }

    /** @return list<string> */
    private static function refs(mixed $v): array
    {
        $out = [];
        foreach (is_array($v) ? $v : [] as $r) {
            if (is_string($r) && self::validRef($r) && $r !== '') {
                $out[] = $r;
            }
        }
        return $out;
    }

    private static function refOrEmpty(mixed $v): string
    {
        $s = self::s($v);
        return self::validRef($s) ? $s : '';
    }

    private static function hex(mixed $v): string
    {
        $s = self::s($v);
        return preg_match('/^[0-9a-f]{4,64}$/', $s) === 1 ? $s : '';
    }

    /** One line of text: control characters (newlines included) become spaces. */
    private static function line(mixed $v): string
    {
        $s = self::s($v);
        $clean = preg_replace('/[\x00-\x1f\x7f]+/', ' ', $s);
        return trim(is_string($clean) ? $clean : '');
    }

    /** Multi-line text: control characters other than newline and tab are dropped. */
    private static function text(mixed $v): string
    {
        $s = str_replace("\r\n", "\n", self::s($v));
        $clean = preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/', '', $s);
        return is_string($clean) ? $clean : '';
    }

    private static function s(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }

    private static function i(mixed $v): int
    {
        return is_int($v) ? $v : (is_numeric($v) ? (int) $v : 0);
    }
}
