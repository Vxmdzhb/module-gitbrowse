<?php
declare(strict_types=1);

namespace AstrX\GitBrowse;

use AstrX\Module\Module;

/**
 * The git browser (the /gitbrowse page): the repositories on a gitweb server,
 * read through gitweb's JSON API and shown in the site's layout — the
 * repository list, files and READMEs, code with line numbers, the history and
 * each commit's diff. Admin → Git server says which server and how visitors
 * clone from it. It owns no database tables.
 *
 * No nav contributor or guard needed: its navbar entries are DB rows that
 * NavbarHandler drops when the module is off (via page.module), and the core
 * ModulePageGuard 404s its pages. See {@see \AstrX\Module\ModuleRegistry}.
 */
final class GitBrowseModule implements Module
{
    /** @return array<string, mixed> */
    public function manifest(): array
    {
        return [
            'key'          => 'gitbrowse',
            'name'         => 'Git browser',
            'version'      => '1.0.0',
            'nav'          => null,
            'nav_defaults' => [],
            'guards'       => [],
            // Page file_name => controller. Resolved by Site\ControllerMap.
            'controllers'  => [
                'git_browse' => \AstrX\GitBrowse\Controller\GitBrowseController::class,
                'admin_config_gitbrowse' => \AstrX\GitBrowse\Controller\AdminConfigGitBrowseController::class,
            ],
            'teardown'     => 'gitbrowse.down.sql',
            // Templates, translations and default config (see docs/MODULES.md).
            'resources'    => 'resources',
        ];
    }
}
