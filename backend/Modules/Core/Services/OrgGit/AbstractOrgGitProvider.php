<?php

namespace Modules\Core\Services\OrgGit;

use Illuminate\Support\Facades\Http;
use Modules\Core\Services\GitHttpUrlAuthInjector;

abstract class AbstractOrgGitProvider implements OrgGitProviderInterface
{
    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $token,
        protected readonly ?string $org = null,
    ) {}

    abstract protected function apiBase(): string;

    /** @return array<string, string> */
    abstract protected function authHeaders(): array;

    protected function http()
    {
        return Http::timeout(20)
            ->acceptJson()
            ->withHeaders($this->authHeaders());
    }

    public function authenticatedCloneUrl(string $cloneUrl): string
    {
        if ($this->token === '' || ! str_starts_with(strtolower($cloneUrl), 'http')) {
            return $cloneUrl;
        }

        return GitHttpUrlAuthInjector::inject($cloneUrl, $this->authUsername(), $this->token);
    }

    protected function authUsername(): string
    {
        return 'oauth2';
    }

    public function allowedHosts(): array
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? [strtolower($host)] : [];
    }

    public function verifyWebhook(string $payload, ?string $signatureHeader, string $secret): bool
    {
        if ($secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        if (str_starts_with($signatureHeader, 'sha256=')) {
            $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

            return hash_equals($expected, $signatureHeader);
        }

        return hash_equals($secret, $signatureHeader);
    }

    /**
     * @return array{owner: string, repo: string}|null
     */
    public static function parseOwnerRepo(string $cloneOrFullName): ?array
    {
        $value = trim($cloneOrFullName);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, '://')) {
            $path = parse_url($value, PHP_URL_PATH);
            if (! is_string($path) || $path === '') {
                return null;
            }
            $parts = array_values(array_filter(explode('/', trim($path, '/'))));
            if (count($parts) < 2) {
                return null;
            }
            $owner = $parts[0];
            $repo = preg_replace('/\.git$/', '', $parts[1]) ?? $parts[1];

            return ['owner' => $owner, 'repo' => $repo];
        }

        $parts = array_values(array_filter(explode('/', $value)));
        if (count($parts) < 2) {
            return null;
        }

        return [
            'owner' => $parts[0],
            'repo' => preg_replace('/\.git$/', '', $parts[1]) ?? $parts[1],
        ];
    }
}
