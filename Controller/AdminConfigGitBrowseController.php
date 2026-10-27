<?php
declare(strict_types=1);

namespace AstrX\GitBrowse\Controller;

use AstrX\Admin\AdminPermission;
use AstrX\Admin\AuditLogger;
use AstrX\Auth\Gate;
use AstrX\Config\ConfigDomain;
use AstrX\Config\ConfigWriter;
use AstrX\Csrf\CsrfHandler;
use AstrX\GitBrowse\GitBrowseClient;
use AstrX\GitBrowse\GitBrowseConfig;
use AstrX\Http\Request;
use AstrX\Http\Response;
use AstrX\I18n\Translator;
use AstrX\Kernel\AbstractController;
use AstrX\Page\Page;
use AstrX\Result\DiagnosticsCollector;
use AstrX\Result\Result;
use AstrX\Routing\UrlGenerator;
use AstrX\Session\FlashBag;
use AstrX\Session\PrgHandler;
use AstrX\Template\TemplateVars;

/**
 * Admin → Git server: which gitweb server the site's git browser reads, how
 * visitors reach it for `git clone`, and the account when gitweb runs with
 * --auth. Saved to GitBrowse.config.php; the page then checks the connection
 * by listing the server's repositories.
 *
 * ADMIN-only (AdminPermission::ACCESS, like the engine status page).
 */
#[ConfigDomain('GitBrowseConfig', file: 'GitBrowse')]
final class AdminConfigGitBrowseController extends AbstractController
{
    private const FORM = 'admin_config_gitbrowse';

    public function __construct(
        DiagnosticsCollector             $collector,
        private readonly TemplateVars    $ctx,
        private readonly Request         $request,
        private readonly ConfigWriter    $writer,
        private readonly Gate            $gate,
        private readonly CsrfHandler     $csrf,
        private readonly PrgHandler      $prg,
        private readonly FlashBag        $flash,
        private readonly Page            $page,
        private readonly UrlGenerator    $urlGen,
        private readonly Translator      $t,
        private readonly AuditLogger     $audit,
        private readonly GitBrowseConfig $config,
        private readonly GitBrowseClient $client,
    ) {
        parent::__construct($collector);
    }

    public function handle(): Result
    {
        if ($this->gate->cannot(AdminPermission::ACCESS)) {
            http_response_code(403);
            return $this->ok();
        }
        $this->t->load('GitBrowse');

        $urlId   = $this->page->i18n ? $this->t->t($this->page->urlId, fallback: $this->page->urlId) : $this->page->urlId;
        $selfUrl = $this->urlGen->toPage($urlId);

        $prgToken = $this->request->query()->get($this->prg->tokenQueryKey());
        if (is_string($prgToken) && $prgToken !== '') {
            $this->save($prgToken);
            Response::redirect($selfUrl)->send()->drainTo($this->collector);
            exit;
        }

        $repos = $this->client->repos();
        $this->ctx->set('csrf_token',   $this->csrf->generate(self::FORM));
        $this->ctx->set('prg_id',       $this->prg->createId($selfUrl));
        $this->ctx->set('cfg_api_url',  $this->config->apiUrl());
        $this->ctx->set('cfg_public_url', $this->config->publicUrl());
        $this->ctx->set('cfg_auth_user', $this->config->authUser());
        $this->ctx->set('cfg_has_password', $this->config->authPassword() !== '');
        $this->ctx->set('cfg_timeout',  $this->config->timeoutSeconds());
        $this->ctx->set('status_ok',    $repos['ok']);
        $this->ctx->set('status_text',  $repos['ok']
            ? $this->t->t('admin.gitbrowse.connected', ['count' => count($repos['repos'])])
            : $this->t->t('admin.gitbrowse.unreachable', ['url' => $this->config->apiUrl()]));
        foreach ([
            'heading', 'intro', 'api_url', 'api_url_hint', 'public_url', 'public_url_hint', 'auth',
            'auth_hint', 'auth_user', 'auth_password', 'password_keep', 'timeout', 'save', 'status',
        ] as $key) {
            $this->ctx->set('lbl_' . $key, $this->t->t('admin.gitbrowse.' . $key));
        }
        return $this->ok();
    }

    private function save(string $prgToken): void
    {
        $posted = $this->prg->pull($prgToken) ?? [];
        $csrf = $this->csrf->verify(self::FORM, self::mStr($posted, '_csrf', ''));
        if (!$csrf->isOk()) {
            $csrf->drainTo($this->collector);
            return;
        }
        $api    = GitBrowseConfig::httpUrl(self::mStr($posted, 'api_url'));
        $public = trim(self::mStr($posted, 'public_url'));
        if ($api === null || ($public !== '' && GitBrowseConfig::httpUrl($public) === null)) {
            $this->flash->set('error', $this->t->t('admin.gitbrowse.bad_url'));
            return;
        }
        $values = [
            'api_url'         => $api,
            'public_url'      => $public === '' ? '' : (string) GitBrowseConfig::httpUrl($public),
            'auth_user'       => trim(self::mStr($posted, 'auth_user')),
            'timeout_seconds' => max(1, min(10, self::mInt($posted, 'timeout_seconds', GitBrowseConfig::DEFAULT_TIMEOUT))),
        ];
        // An empty password field keeps the saved one; clearing the user clears both.
        $password = self::mStr($posted, 'auth_password');
        if ($values['auth_user'] === '') {
            $values['auth_password'] = '';
        } elseif ($password !== '') {
            $values['auth_password'] = $password;
        }
        $r = $this->writer->write('GitBrowse', ['GitBrowseConfig' => $values]);
        $r->drainTo($this->collector);
        if ($r->isOk()) {
            $this->flash->set('success', $this->t->t('admin.config.saved'));
            $this->audit->log('config.save', 'GitBrowse.config.php')->drainTo($this->collector);
        }
    }
}
