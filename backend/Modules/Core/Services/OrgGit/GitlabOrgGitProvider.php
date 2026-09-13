<?php

namespace Modules\Core\Services\OrgGit;

class GitlabOrgGitProvider extends AbstractOrgGitProvider
{
    public function provider(): string
    {
        return 'gitlab';
    }

    protected function apiBase(): string
    {
        return rtrim($this->baseUrl !== '' ? $this->baseUrl : 'https://gitlab.com', '/').'/api/v4';
    }

    protected function authHeaders(): array
    {
        return [
            'PRIVATE-TOKEN' => $this->token,
        ];
    }

    public function testConnection(): array
    {
        $res = $this->http()->get($this->apiBase().'/user');
        if (! $res->successful()) {
            return ['ok' => false, 'message' => 'GitLab auth failed: HTTP '.$res->status()];
        }

        return ['ok' => true, 'user' => $res->json('username')];
    }

    public function resolveCloneUrl(string $owner, string $repo): string
    {
        $host = rtrim($this->baseUrl !== '' ? $this->baseUrl : 'https://gitlab.com', '/');

        return $host.'/'.$owner.'/'.$repo.'.git';
    }

    public function listOrgRepos(?string $org = null): array
    {
        $org = $org ?: $this->org;
        if (! $org) {
            return [];
        }

        $res = $this->http()->get($this->apiBase().'/groups/'.rawurlencode($org).'/projects', [
            'per_page' => 100,
            'include_subgroups' => true,
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
                'full_name' => (string) ($row['path_with_namespace'] ?? ''),
                'clone_url' => (string) ($row['http_url_to_repo'] ?? ''),
                'default_branch' => (string) ($row['default_branch'] ?? 'main'),
            ];
        }

        return $out;
    }

    public function getDefaultBranch(string $owner, string $repo): ?string
    {
        $id = rawurlencode($owner.'/'.$repo);
        $res = $this->http()->get($this->apiBase().'/projects/'.$id);
        if (! $res->successful()) {
            return null;
        }
        $branch = $res->json('default_branch');

        return is_string($branch) && $branch !== '' ? $branch : null;
    }

    public function getLatestTag(string $owner, string $repo): ?string
    {
        $id = rawurlencode($owner.'/'.$repo);
        $res = $this->http()->get($this->apiBase().'/projects/'.$id.'/repository/tags', [
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
        $id = rawurlencode($owner.'/'.$repo);
        $query = ['ref' => $ref ?: 'HEAD'];
        $res = $this->http()->get($this->apiBase().'/projects/'.$id.'/repository/files/README.md/raw', $query);
        if (! $res->successful()) {
            $res = $this->http()->get($this->apiBase().'/projects/'.$id.'/repository/files/readme.md/raw', $query);
        }
        if (! $res->successful()) {
            return null;
        }

        return mb_substr($res->body(), 0, 8000);
    }

    public function createDeployToken(string $owner, string $repo, string $name): ?array
    {
        $id = rawurlencode($owner.'/'.$repo);
        $res = $this->http()->post($this->apiBase().'/projects/'.$id.'/deploy_tokens', [
            'name' => $name,
            'scopes' => ['read_repository'],
        ]);
        if (! $res->successful()) {
            if ($this->token === '') {
                return null;
            }

            return ['token' => $this->token, 'username' => 'oauth2'];
        }

        $token = $res->json('token');
        $username = $res->json('username');
        if (! is_string($token) || $token === '') {
            return null;
        }

        return [
            'token' => $token,
            'username' => is_string($username) && $username !== '' ? $username : 'gitlab+deploy-token-1',
        ];
    }

    public function allowedHosts(): array
    {
        $hosts = parent::allowedHosts();
        $hosts[] = 'gitlab.com';
        $hosts[] = 'www.gitlab.com';

        return array_values(array_unique($hosts));
    }
}
