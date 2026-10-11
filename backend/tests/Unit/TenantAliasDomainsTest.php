<?php

namespace Tests\Unit;

use Modules\Platform\Support\TenantAliasDomains;
use Modules\Platform\Support\TenantSiteStack;
use PHPUnit\Framework\TestCase;

class TenantAliasDomainsTest extends TestCase
{
    public function test_hostname_derives_from_primary(): void
    {
        $this->assertSame('contact.shop.example.com', TenantAliasDomains::hostname('shop.example.com', 'contact'));
        $this->assertSame('bio.example.com', TenantAliasDomains::hostname('www.example.com', 'Bio!'));
    }

    public function test_caddy_snippet_includes_aliases(): void
    {
        $snippet = TenantSiteStack::caddySnippet('shop.example.com', 'demo', ['contact.shop.example.com']);
        $this->assertStringContainsString('shop.example.com, contact.shop.example.com {', $snippet);
        $this->assertStringContainsString('ws-demo-backend:8080', $snippet);
        $this->assertStringContainsString('ws-demo-frontend:3000', $snippet);
    }
}
