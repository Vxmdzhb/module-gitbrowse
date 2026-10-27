<?php
declare(strict_types=1);

namespace AstrX\GitBrowse\Controller;

use AstrX\Auth\Gate;
use AstrX\GitBrowse\GitBrowseClient;
use AstrX\GitBrowse\GitBrowseConfig;
use AstrX\Http\Request;
use AstrX\I18n\Translator;
use AstrX\Kernel\AbstractController;
use AstrX\News\NewsPermission;
use AstrX\Result\DiagnosticsCollector;
use AstrX\Result\Result;
use AstrX\Routing\UrlGenerator;
use AstrX\Template\TemplateVars;

/**
 * @phpstan-import-type Summary from GitBrowseClient
 * @phpstan-import-type Commit from GitBrowseClient
 *
 * The site's git browser: the repositories on the git server the admin
 * connected (Admin → Git server), read through gitweb's JSON API and shown in
 * the site's own layout.
 *
 *   ?                                   the repositories
 *   ?repo=R[&ref=][&path=]              a directory: its files and its README
 *   ?repo=R&view=blob&path=P[&ref=]     one file, with line numbers
 *   ?repo=R&view=log[&ref=][&page=]     the history
 *   ?repo=R&view=commit&id=SHA          one commit and its diff
 *
 * The page is seeded with file_name 'git_browse'; its slug is
 * WORDING_GITBROWSE ('gitbrowse'). Public: gated by NEWS_VIEW, like the other
 * public pages. Every value from the server is printed escaped.
 */
final class GitBrowseController extends AbstractController
{
    /** The most lines a file view shows. */
    private const int MAX_LINES = 5000;

    private string $slug = '';

    public function __construct(
        DiagnosticsCollector             $collector,
        private readonly TemplateVars    $ctx,
        private readonly Request         $request,
        private readonly Gate            $gate,
        private readonly Translator      $t,
        private readonly UrlGenerator    $urlGen,
        private readonly GitBrowseClient $client,
        private readonly GitBrowseConfig $config,
    ) {
        parent::__construct($collector);
    }

    public function handle(): Result
    {
        $this->t->load('GitBrowse');

        if ($this->gate->cannot(NewsPermission::VIEW)) {
            http_response_code(404);
            exit;
        }
        $this->slug = $this->t->t('WORDING_GITBROWSE');

        $repo = self::queryStr($this->request, 'repo');
        $view = self::queryStr($this->request, 'view');
        $ref  = self::queryStr($this->request, 'ref');
        $path = trim(self::queryStr($this->request, 'path'), '/');

        $this->ctx->set('home_url', $this->url([]));
        $this->ctx->set('in_repo', false);
        foreach (['list', 'tree', 'blob', 'log', 'commit', 'unavailable', 'not_found'] as $v) {
            $this->ctx->set('view_' . $v, false);
        }

        if ($repo === '') {
            $this->listView();
        } elseif ($view === 'log') {
            $this->logView($repo, $ref);
        } elseif ($view === 'commit') {
            $this->commitView($repo, self::queryStr($this->request, 'id'));
        } elseif ($view === 'blob') {
            $this->blobView($repo, $ref, $path);
        } else {
            $this->treeView($repo, $ref, $path);
        }
        $this->setLabels();
        return $this->ok();
    }

    // ── views ────────────────────────────────────────────────────────────────

    private function listView(): void
    {
        $r = $this->client->repos();
        if (!$r['ok']) {
            $this->ctx->set('view_unavailable', true);
            return;
        }
        $rows = [];
        foreach ($r['repos'] as $repo) {
            $rows[] = [
                'name'        => self::display($repo['name']),
                'url'         => $this->url(['repo' => $repo['name']]),
                'description' => $repo['description'],
                'updated'     => self::date($repo['last_commit_ts']),
            ];
        }
        $this->ctx->set('view_list', true);
        $this->ctx->set('repos', $rows);
        $this->ctx->set('has_repos', $rows !== []);
    }

    private function treeView(string $repo, string $ref, string $path): void
    {
        $summary = $this->client->summary($repo);
        if (!$this->repoHeader($repo, $summary, 'code', $ref)) {
            return;
        }
        $tree = $this->client->tree($repo, $ref, $path);
        if (!$tree['ok']) {
            $this->ctx->set('view_unavailable', true);
            return;
        }
        if ($tree['is_file']) {
            $this->blobView($repo, $ref, $path, $summary);
            return;
        }
        if (!$tree['found']) {
            $this->ctx->set('view_not_found', true);
            return;
        }
        $rows = [];
        if ($path !== '') {
            $parent = str_contains($path, '/') ? substr($path, 0, (int) strrpos($path, '/')) : '';
            $rows[] = ['name' => '..', 'cls' => 'dir', 'url' => $this->url(['repo' => $repo, 'ref' => $ref, 'path' => $parent]), 'size' => ''];
        }
        foreach ($tree['entries'] as $e) {
            $isDir = $e['type'] === 'tree';
            $rows[] = [
                'name' => $e['name'] . ($isDir ? '/' : ''),
                'cls'  => $isDir ? 'dir' : ($e['type'] === 'commit' ? 'sub' : 'file'),
                'url'  => $e['type'] === 'commit'
                    ? '#'
                    : $this->url(['repo' => $repo, 'ref' => $ref, 'path' => $e['path']] + ($isDir ? [] : ['view' => 'blob'])),
                'size' => $isDir || $e['type'] === 'commit' ? '' : self::size($e['size']),
            ];
        }
        $this->ctx->set('view_tree', true);
        $this->ctx->set('crumbs', $this->crumbs($repo, $ref, $path));
        $this->ctx->set('entries', $rows);
        $this->ctx->set('tree_truncated', $tree['truncated']);
        $this->ctx->set('has_readme', $tree['readme'] !== '');
        $this->ctx->set('readme_name', $tree['readme_name']);
        $this->ctx->set('readme', $tree['readme']);
    }

    /** @param Summary|null $summary */
    private function blobView(string $repo, string $ref, string $path, ?array $summary = null): void
    {
        if ($summary === null) {
            $summary = $this->client->summary($repo);
            if (!$this->repoHeader($repo, $summary, 'code', $ref)) {
                return;
            }
        }
        $b = $this->client->blob($repo, $ref, $path);
        if (!$b['ok']) {
            $this->ctx->set('view_unavailable', true);
            return;
        }
        if (!$b['found']) {
            $this->ctx->set('view_not_found', true);
            return;
        }
        $lines = [];
        if (!$b['binary']) {
            $text = rtrim($b['text'], "\n");
            $all  = $text === '' ? [] : explode("\n", $text);
            foreach (array_slice($all, 0, self::MAX_LINES) as $i => $line) {
                $lines[] = ['n' => $i + 1, 'text' => $line];
            }
        }
        $this->ctx->set('view_blob', true);
        $this->ctx->set('crumbs', $this->crumbs($repo, $ref, $path));
        $this->ctx->set('file_size', self::size($b['size']));
        $this->ctx->set('file_lines', count($lines));
        $this->ctx->set('is_binary', $b['binary']);
        $this->ctx->set('lines', $lines);
        $this->ctx->set('file_truncated', $b['truncated'] || count($lines) >= self::MAX_LINES);
    }

    private function logView(string $repo, string $ref): void
    {
        $summary = $this->client->summary($repo);
        if (!$this->repoHeader($repo, $summary, 'log', $ref)) {
            return;
        }
        $page = max(1, self::queryInt($this->request, 'page', 1));
        $log  = $this->client->log($repo, $ref, $page);
        if (!$log['ok']) {
            $this->ctx->set('view_unavailable', true);
            return;
        }
        $rows = [];
        foreach ($log['commits'] as $c) {
            $rows[] = $this->commitRow($repo, $c);
        }
        $this->ctx->set('view_log', true);
        $this->ctx->set('commits', $rows);
        $this->ctx->set('has_prev', $page > 1);
        $this->ctx->set('prev_url', $this->url(['repo' => $repo, 'view' => 'log', 'ref' => $ref, 'page' => $page - 1]));
        $this->ctx->set('has_next', $log['has_more']);
        $this->ctx->set('next_url', $this->url(['repo' => $repo, 'view' => 'log', 'ref' => $ref, 'page' => $page + 1]));
    }

    private function commitView(string $repo, string $id): void
    {
        $summary = $this->client->summary($repo);
        if (!$this->repoHeader($repo, $summary, 'log', '')) {
            return;
        }
        $c = $this->client->commit($repo, $id);
        if (!$c['ok']) {
            $this->ctx->set('view_unavailable', true);
            return;
        }
        if (!$c['found']) {
            $this->ctx->set('view_not_found', true);
            return;
        }
        $diff = [];
        foreach (explode("\n", rtrim($c['diff'], "\n")) as $line) {
            $diff[] = ['cls' => self::diffClass($line), 'text' => $line];
        }
        $parents = [];
        foreach ($c['parents'] as $p) {
            $parents[] = ['short' => substr($p, 0, 7), 'url' => $this->url(['repo' => $repo, 'view' => 'commit', 'id' => $p])];
        }
        $this->ctx->set('view_commit', true);
        $this->ctx->set('c_subject', $c['subject']);
        $this->ctx->set('c_body', $c['body']);
        $this->ctx->set('c_has_body', $c['body'] !== '');
        $this->ctx->set('c_author', $c['author']);
        $this->ctx->set('c_date', $c['date']);
        $this->ctx->set('c_sha', $c['sha']);
        $this->ctx->set('c_signed', $c['verified']);
        $this->ctx->set('c_parents', $parents);
        $this->ctx->set('c_tree_url', $this->url(['repo' => $repo, 'ref' => $c['sha']]));
        $this->ctx->set('diff_lines', $diff);
        $this->ctx->set('diff_truncated', $c['diff_truncated']);
    }

    // ── shared parts ─────────────────────────────────────────────────────────

    /**
     * The repository header shared by every view: name, description, the
     * Code/History tabs, branches and the clone command. False when the
     * repository isn't there (the right message is already set).
     *
     * @param Summary $summary
     */
    private function repoHeader(string $repo, array $summary, string $tab, string $ref): bool
    {
        if (!$summary['ok']) {
            $this->ctx->set('view_unavailable', true);
            return false;
        }
        if (!$summary['found']) {
            $this->ctx->set('view_not_found', true);
            return false;
        }
        $branches = [];
        foreach ($summary['branches'] as $b) {
            $branches[] = [
                'name'   => $b,
                'url'    => $this->url(['repo' => $repo, 'ref' => $b] + ($tab === 'log' ? ['view' => 'log'] : [])),
                'active' => $b === ($ref !== '' ? $ref : $summary['default_branch']),
            ];
        }
        $public = $this->config->publicUrl();
        $this->ctx->set('in_repo', true);
        $this->ctx->set('repo_name', self::display($repo));
        $this->ctx->set('repo_url', $this->url(['repo' => $repo]));
        $this->ctx->set('repo_description', $summary['description']);
        $this->ctx->set('tab_code', $tab === 'code');
        $this->ctx->set('tab_log', $tab === 'log');
        $this->ctx->set('code_url', $this->url(['repo' => $repo, 'ref' => $ref]));
        $this->ctx->set('log_url', $this->url(['repo' => $repo, 'view' => 'log', 'ref' => $ref]));
        $this->ctx->set('branches', $branches);
        $this->ctx->set('commit_count', $summary['commit_count']);
        $this->ctx->set('branch_count', count($summary['branches']));
        $this->ctx->set('tag_count', count($summary['tags']));
        $this->ctx->set('has_clone', $public !== '');
        $this->ctx->set('clone_cmd', $public !== '' ? 'git clone ' . $public . '/' . $repo : '');
        $latest = $summary['commits'][0] ?? null;
        $this->ctx->set('latest', $latest === null ? [] : [$this->commitRow($repo, $latest)]);
        return true;
    }

    /**
     * @param Commit $c
     * @return array<string,mixed>
     */
    private function commitRow(string $repo, array $c): array
    {
        return [
            'short'   => $c['short'],
            'subject' => $c['subject'],
            'author'  => $c['author'],
            'date'    => self::date($c['ts']),
            'url'     => $this->url(['repo' => $repo, 'view' => 'commit', 'id' => $c['sha']]),
        ];
    }

    /** @return list<array{name:string, url:string}> */
    private function crumbs(string $repo, string $ref, string $path): array
    {
        $out = [['name' => self::display($repo), 'url' => $this->url(['repo' => $repo, 'ref' => $ref])]];
        if ($path === '') {
            return $out;
        }
        $acc = '';
        foreach (explode('/', $path) as $seg) {
            $acc = $acc === '' ? $seg : $acc . '/' . $seg;
            $out[] = ['name' => $seg, 'url' => $this->url(['repo' => $repo, 'ref' => $ref, 'path' => $acc])];
        }
        return $out;
    }

    /** @param array<string, string|int> $params */
    private function url(array $params): string
    {
        return $this->urlGen->toPage($this->slug, array_filter($params, static fn($v) => $v !== '' && $v !== 0));
    }

    private static function diffClass(string $line): string
    {
        return match (true) {
            str_starts_with($line, 'diff --git') => 'd-file',
            str_starts_with($line, '+++'), str_starts_with($line, '---') => 'd-meta',
            str_starts_with($line, '@@') => 'd-hunk',
            str_starts_with($line, '+') => 'd-add',
            str_starts_with($line, '-') => 'd-del',
            default => '',
        };
    }

    /** "astrx" for "astrx.git". */
    private static function display(string $repo): string
    {
        return str_ends_with($repo, '.git') && strlen($repo) > 4 ? substr($repo, 0, -4) : $repo;
    }

    private static function date(int $ts): string
    {
        return $ts > 0 ? gmdate('Y-m-d H:i', $ts) : '';
    }

    private static function size(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB'];
        $n = (float) $bytes;
        foreach ($units as $i => $u) {
            if ($n < 1024 || $i === count($units) - 1) {
                return $u === 'B' ? $bytes . ' B' : sprintf('%.1f %s', $n, $u);
            }
            $n /= 1024;
        }
        return $bytes . ' B';
    }

    private function setLabels(): void
    {
        foreach ([
            'lbl_heading', 'lbl_intro', 'lbl_unavailable', 'lbl_not_found', 'lbl_no_repos',
            'lbl_repository', 'lbl_description', 'lbl_updated', 'lbl_code', 'lbl_log',
            'lbl_commits', 'lbl_branches', 'lbl_branch_count', 'lbl_tags', 'lbl_clone', 'lbl_latest', 'lbl_size',
            'lbl_lines', 'lbl_binary', 'lbl_truncated', 'lbl_prev', 'lbl_next', 'lbl_author',
            'lbl_date', 'lbl_parents', 'lbl_browse_tree', 'lbl_signed', 'lbl_tree_truncated',
        ] as $key) {
            $this->ctx->set($key, $this->t->t('gitbrowse.' . substr($key, 4)));
        }
    }
}
