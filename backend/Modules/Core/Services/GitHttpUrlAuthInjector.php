<?php

namespace Modules\Core\Services;

/**
 * Builds an HTTPS Git clone URL with embedded HTTP basic userinfo (e.g. oauth2 + PAT).
 */
class GitHttpUrlAuthInjector
{
    public static function inject(string $cloneUrl, string $username, string $secret): string
    {
        $parts = parse_url($cloneUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $cloneUrl;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return $cloneUrl;
        }

        $auth = rawurlencode($username).':'.rawurlencode($secret);
        $host = $parts['host'];
        if (! empty($parts['port'])) {
            $host .= ':'.$parts['port'];
        }
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.'://'.$auth.'@'.$host.$path.$query.$fragment;
    }
}
