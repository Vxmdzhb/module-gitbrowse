<?php
declare(strict_types=1);

namespace AstrX\GitBrowse;

use AstrX\Config\InjectConfig;

/**
 * Where the git server is and how to reach it. Set from GitBrowse.config.php,
 * which the admin page Git server writes.
 *
 *   api_url        the gitweb server the site reads repositories from (its
 *                  JSON API); an address the SITE can reach, e.g.
 *                  http://gitweb:8801 inside Docker
 *   public_url     the same server as VISITORS reach it, for `git clone` and
 *                  the "open in gitweb" link; '' hides both
 *   auth_user,     the account, when gitweb runs with --auth
 *   auth_password
 *   timeout_seconds  per request, 1–10
 */
final class GitBrowseConfig
{
    public const string DEFAULT_API_URL = 'http://127.0.0.1:8801';
    public const int DEFAULT_TIMEOUT = 3;

    private string $apiUrl = self::DEFAULT_API_URL;
    private string $publicUrl = '';
    private string $authUser = '';
    private string $authPassword = '';
    private int $timeout = self::DEFAULT_TIMEOUT;

    #[InjectConfig('api_url')]
    public function setApiUrl(string $v): void { $this->apiUrl = self::httpUrl($v) ?? self::DEFAULT_API_URL; }

    #[InjectConfig('public_url')]
    public function setPublicUrl(string $v): void { $this->publicUrl = self::httpUrl($v) ?? ''; }

    #[InjectConfig('auth_user')]
    public function setAuthUser(string $v): void { $this->authUser = trim($v); }

    #[InjectConfig('auth_password')]
    public function setAuthPassword(string $v): void { $this->authPassword = $v; }

    #[InjectConfig('timeout_seconds')]
    public function setTimeoutSeconds(int $v): void { $this->timeout = max(1, min(10, $v)); }

    public function apiUrl(): string { return $this->apiUrl; }
    public function publicUrl(): string { return $this->publicUrl; }
    public function authUser(): string { return $this->authUser; }
    public function authPassword(): string { return $this->authPassword; }
    public function timeoutSeconds(): int { return $this->timeout; }

    /** An http(s) URL without its trailing slash, or null for anything else. */
    public static function httpUrl(string $v): ?string
    {
        $v = rtrim(trim($v), '/');
        if ($v === '' || preg_match('/[\x00-\x20\x7f]/', $v) === 1) {
            return null;
        }
        $scheme = parse_url($v, PHP_URL_SCHEME);
        $host   = parse_url($v, PHP_URL_HOST);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)
            || !is_string($host) || $host === '') {
            return null;
        }
        return $v;
    }
}
