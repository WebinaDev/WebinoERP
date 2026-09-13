<?php

namespace Modules\Core\Services\OrgGit;

use InvalidArgumentException;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Marketplace\Entities\MarketplaceGiteaSetting;
use Modules\Platform\Entities\PlatformSource;

class OrgGitProviderFactory
{
    public function make(?string $provider = null, ?PlatformSource $source = null): OrgGitProviderInterface
    {
        if ($source) {
            return $this->fromPlatformSource($source);
        }

        $settings = MarketplaceGiteaSetting::query()->first();
        $hosting = CoreHostingSetting::current();

        $provider = strtolower($provider
            ?: ($settings?->provider ?? null)
            ?: ($hosting->git_provider ?? 'gitea'));

        if ($settings?->platform_source_id) {
            $linked = PlatformSource::query()->find($settings->platform_source_id);
            if ($linked) {
                return $this->fromPlatformSource($linked);
            }
        }

        $token = '';
        $baseUrl = '';
        $org = $settings?->org;

        if ($settings) {
            $token = (string) ($settings->token ?? '');
            $baseUrl = (string) ($settings->base_url ?: $this->normalizeHost($settings->host ?? ''));
        }

        if ($token === '' && is_string($hosting->git_pat) && $hosting->git_pat !== '') {
            $token = $hosting->git_pat;
        }
        if ($baseUrl === '' && is_string($hosting->git_base_url) && $hosting->git_base_url !== '') {
            $baseUrl = rtrim($hosting->git_base_url, '/');
        }

        return $this->instantiate($provider, $baseUrl, $token, $org);
    }

    public function fromPlatformSource(PlatformSource $source): OrgGitProviderInterface
    {
        $meta = is_array($source->meta) ? $source->meta : [];
        $org = isset($meta['org']) && is_string($meta['org']) ? $meta['org'] : null;

        return $this->instantiate(
            (string) $source->provider,
            (string) ($source->base_url ?? ''),
            (string) ($source->token ?? ''),
            $org,
        );
    }

    public function instantiate(string $provider, string $baseUrl, string $token, ?string $org = null): OrgGitProviderInterface
    {
        $provider = strtolower($provider);
        $baseUrl = $this->defaultBaseUrl($provider, $baseUrl);

        return match ($provider) {
            'github' => new GithubOrgGitProvider($baseUrl, $token, $org),
            'gitlab' => new GitlabOrgGitProvider($baseUrl, $token, $org),
            'gitea' => new GiteaOrgGitProvider($baseUrl, $token, $org),
            default => throw new InvalidArgumentException('Unsupported git provider: '.$provider),
        };
    }

    protected function defaultBaseUrl(string $provider, string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl !== '') {
            return $baseUrl;
        }

        return match ($provider) {
            'github' => 'https://github.com',
            'gitlab' => 'https://gitlab.com',
            default => '',
        };
    }

    protected function normalizeHost(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }
        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            return rtrim($host, '/');
        }

        return 'https://'.rtrim($host, '/');
    }

    /** @return list<string> */
    public function allowedCloneHosts(): array
    {
        try {
            $hosts = $this->make()->allowedHosts();

            return $hosts === [] ? [] : $hosts;
        } catch (\Throwable) {
            // No restriction when org git is not configured yet.
            return [];
        }
    }
}
