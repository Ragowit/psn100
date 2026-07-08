<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/ContentSecurityPolicy.php';

final class ContentSecurityPolicyTest extends TestCase
{
    public function testHeaderNameIsEnforcedPolicy(): void
    {
        $this->assertSame('Content-Security-Policy', ContentSecurityPolicy::HEADER_NAME);
    }

    public function testValueAllowsSelfHostedAssetsAndInlineStyles(): void
    {
        $policy = ContentSecurityPolicy::value();

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString("img-src 'self' data: https:", $policy);
        $this->assertStringContainsString("connect-src 'self'", $policy);
        $this->assertFalse(str_contains($policy, 'cdn.jsdelivr.net'));
        $this->assertFalse(str_contains($policy, 'Report-Only'));
    }
}
