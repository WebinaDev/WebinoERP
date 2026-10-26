<?php

namespace Tests\Unit;

use Modules\SiteBuilder\Support\SiteDomainCaddy;
use PHPUnit\Framework\TestCase;

class SiteDomainCaddyTest extends TestCase
{
    public function test_renders_serve_and_redirect_blocks(): void
    {
        $out = SiteDomainCaddy::render('hassandoust.ir', 'sara', [
            ['domain' => 'hassandoust.ir', 'role' => 'primary', 'include_www' => true],
            ['domain' => 'alias.ir', 'role' => 'alias', 'include_www' => true],
            ['domain' => 'fatemehhasandust.ir', 'role' => 'redirect', 'include_www' => true, 'redirect_path' => 'fa/', 'redirect_status' => 301],
            ['domain' => 'sarahasandust.ir', 'role' => 'redirect', 'include_www' => false, 'redirect_status' => 302],
        ], ['contact.hassandoust.ir']);

        $this->assertStringContainsString('hassandoust.ir, alias.ir, contact.hassandoust.ir {', $out);
        $this->assertStringContainsString("www.hassandoust.ir {\n  redir https://hassandoust.ir{uri} 301", $out);
        $this->assertStringContainsString("www.alias.ir {\n  redir https://alias.ir{uri} 301", $out);
        $this->assertStringContainsString("fatemehhasandust.ir, www.fatemehhasandust.ir {\n  redir https://hassandoust.ir/fa{uri} 301", $out);
        $this->assertStringContainsString("sarahasandust.ir {\n  redir https://hassandoust.ir{uri} 302", $out);
    }

    public function test_path_and_status_sanitized(): void
    {
        $this->assertSame('/fa', SiteDomainCaddy::normalizePath(' /fa/ '));
        $this->assertSame('/', SiteDomainCaddy::normalizePath(''));
        $this->assertSame('/fa', SiteDomainCaddy::normalizePath('/f a"}'));
        $this->assertSame(301, SiteDomainCaddy::status(200));
    }
}
