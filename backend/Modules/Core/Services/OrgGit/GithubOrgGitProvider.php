<?php

namespace Modules\Core\Services\OrgGit;

class GithubOrgGitProvider extends AbstractOrgGitProvider
{
    public function provider(): string
    {
        return 'github';
    }

    protected function apiBase(): string
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST);
        if ($host && ! in_array(strtolower($host), ['github.com', 'www.github.com'], true)) {
            return rtrim($this->baseUrl, '/').'/api/v3';
        }

        return 'https://api.github.com';
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-GitHub-Api-Version' => '2022-11-28',
            'Accept' => 'application/vnd.github+json',
        ];
    }

    protected function authUsername(): string
    {
        return 'x-access-token';
    }

    public function testConnection(): array
    {
        $res = $this->http()->get($this->apiBase().'/user');
        if (! $res->successful()) {
            return ['ok' => false, 'message' => 'GitHub auth failed: HTTP '.$res->status()];
        }

        return ['ok' => true, 'user' => $res->json('login')];
    }

    public function resolveCloneUrl(string $owner, string $repo): string
    {
        return 'https://github.com/'.$owner.'/'.$repo.'.git';
    }

    public function listOrgRepos(?string $org = null): array
    {
        $org = $org ?: $this->org;
        if (! $org) {
            return [];
        }

        $res = $this->http()->get($this->apiBase().'/orgs/'.rawurlencode($org).'/repos', [
            'per_page' => 100,
            'type' => 'all',
        ]);
        if (! $res->successful()) {
            return [];
        }

        $out = [];
        foreach ($res->json() ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'full_name' => (string) ($row['full_name'] ?? ''),
                'clone_url' => (string) ($row['clone_url'] ?? ''),
                'default_branch' => (string) ($row['default_branch'] ?? 'main'),
            ];
        }

        return $out;
    }

    public function getDefaultBranch(string $owner, string $repo): ?string
    {
        $res = $this->http()->get($this->apiBase().'/repos/'.rawurlencode($owner).'/'.rawurlencode($repo));
        if (! $res->successful()) {
            return null;
        }

        $branch = $res->json('default_branch');

        return is_string($branch) && $branch !== '' ? $branch : null;
    }

    public function getLatestTag(string $owner, string $repo): ?string
    {
        $res = $this->http()->get($this->apiBase().'/repos/'.rawurlencode($owner).'/'.rawurlencode($repo).'/tags', [
            'per_page' => 1,
        ]);
        if (! $res->successful()) {
            return null;
        }
        $tag = $res->json('0.name');

        return is_string($tag) && $tag !== '' ? $tag : null;
    }

    public function getReadme(string $owner, string $repo, ?string $ref = null): ?string
    {
        $query = $ref ? ['ref' => $ref] : [];
        $res = $this->http()
            ->withHeaders(['Accept' => 'application/vnd.github.raw'])
            ->get($this->apiBase().'/repos/'.rawurlencode($owner).'/'.rawurlencode($repo).'/readme', $query);
        if (! $res->successful()) {
            return null;
        }

        $body = $res->body();

        return is_string($body) && $body !== '' ? mb_substr($body, 0, 8000) : null;
    }

    public function createDeployToken(string $owner, string $repo, string $name): ?array
    {
        // Classic PATs are account-scoped; return the configured token for clone auth.
        if ($this->token === '') {
            return null;
        }

        return ['token' => $this->token, 'username' => 'x-access-token'];
    }

    public function allowedHosts(): array
    {
        $hosts = parent::allowedHosts();
        $hosts[] = 'github.com';
        $hosts[] = 'www.github.com';

        return array_values(array_unique($hosts));
    }
}
