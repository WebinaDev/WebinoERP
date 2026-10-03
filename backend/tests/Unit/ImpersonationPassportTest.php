<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Modules\SiteBuilder\Exceptions\SiteImpersonationException;
use Modules\SiteBuilder\Support\ImpersonationNextPath;
use Modules\SiteBuilder\Support\ImpersonationPassport;
use Tests\TestCase;

class ImpersonationPassportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_passport_round_trips_and_rejects_tampering_expiry_and_reuse(): void
    {
        $token = ImpersonationPassport::issue(42);
        $claims = ImpersonationPassport::parse($token);
        $this->assertSame(42, $claims['sid']);
        $this->assertGreaterThan(time(), $claims['exp']);

        $parts = explode('.', $token);
        $tampered = $parts[0].'.'.$parts[1].'x';
        try {
            ImpersonationPassport::parse(substr($token, 0, -1).($token[-1] === 'a' ? 'b' : 'a'));
            $this->fail('tampered passport was accepted');
        } catch (SiteImpersonationException $e) {
            $this->assertSame(401, $e->status);
        }
        $this->assertNotSame('', $tampered);

        $expired = ImpersonationPassport::issue(7, -30);
        $this->expectException(SiteImpersonationException::class);
        ImpersonationPassport::parse($expired);
    }

    public function test_revoked_passport_cannot_be_parsed(): void
    {
        $token = ImpersonationPassport::issue(9);
        ImpersonationPassport::revoke($token);

        $this->expectException(SiteImpersonationException::class);
        ImpersonationPassport::parse($token);
    }

    public function test_next_path_allows_dashboard_builder_routes_only(): void
    {
        $this->assertSame('/dashboard', ImpersonationNextPath::normalize(null));
        $this->assertSame('/dashboard', ImpersonationNextPath::normalize('https://evil.test/phish'));
        $this->assertSame('/dashboard', ImpersonationNextPath::normalize('//evil.test'));
        $this->assertSame('/dashboard', ImpersonationNextPath::normalize('/dashboard/../admin'));
        $this->assertSame('/dashboard/builder', ImpersonationNextPath::normalize('/dashboard/builder'));
        $this->assertSame('/dashboard/builder/15', ImpersonationNextPath::normalize('/dashboard/builder/15'));
        $this->assertSame(
            '/dashboard/theme-builder/header/3',
            ImpersonationNextPath::normalize('/dashboard/theme-builder/header/3'),
        );
    }
}
