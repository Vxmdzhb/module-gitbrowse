<?php
declare(strict_types=1);

/**
 * Git browser — the git server it reads (see GitBrowseConfig). The admin page
 * Git server writes the site's copy of this file; any key there overrides
 * this default.
 *
 * api_url is an address the SITE reaches (gitweb's JSON API); public_url is
 * the same server as VISITORS reach it, used for `git clone`. Only http(s)
 * addresses are accepted; anything else falls back to the default.
 */
return [
    'GitBrowseConfig' => [
        'api_url'         => 'http://127.0.0.1:8801',
        'public_url'      => '',
        'auth_user'       => '',
        'auth_password'   => '',
        'timeout_seconds' => 3,
    ],
];
