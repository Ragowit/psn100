<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/IpAddressResolver.php';

final class IpAddressResolverTest extends TestCase
{
    public function testResolveReturnsValidatedIpv4Address(): void
    {
        $this->assertSame('127.0.0.1', IpAddressResolver::resolve('  127.0.0.1  '));
    }

    public function testResolveReturnsValidatedIpv6Address(): void
    {
        $this->assertSame('::1', IpAddressResolver::resolve('::1'));
    }

    public function testResolveReturnsEmptyStringForInvalidAddress(): void
    {
        $this->assertSame('', IpAddressResolver::resolve('not-an-ip'));
    }

    public function testResolveUsesFirstArrayValue(): void
    {
        $this->assertSame('8.8.8.8', IpAddressResolver::resolve([' 8.8.8.8 ', '9.9.9.9 ']));
    }

    public function testResolveSupportsStringableObjects(): void
    {
        $stringable = new class {
            public function __toString(): string
            {
                return '203.0.113.12';
            }
        };

        $this->assertSame('203.0.113.12', IpAddressResolver::resolve($stringable));
    }

    public function testResolveReturnsEmptyStringForUnsupportedValues(): void
    {
        $resource = fopen('php://temp', 'r');

        $this->assertSame('', IpAddressResolver::resolve($resource));

        if (is_resource($resource)) {
            fclose($resource);
        }

        $this->assertSame('', IpAddressResolver::resolve(new stdClass()));
        $this->assertSame('', IpAddressResolver::resolve(null));
        $this->assertSame('', IpAddressResolver::resolve(''));
    }

    public function testResolveFromServerUsesRemoteAddrByDefault(): void
    {
        $ipAddress = IpAddressResolver::resolveFromServerWithTrustedProxies(
            ['REMOTE_ADDR' => '198.51.100.10'],
            []
        );

        $this->assertSame('198.51.100.10', $ipAddress);
    }

    public function testResolveFromServerUsesForwardedForWhenProxyIsTrusted(): void
    {
        $ipAddress = IpAddressResolver::resolveFromServerWithTrustedProxies(
            [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.5, 10.0.0.1',
            ],
            ['10.0.0.1']
        );

        $this->assertSame('203.0.113.5', $ipAddress);
    }

    public function testResolveFromServerIgnoresForwardedForFromUntrustedProxy(): void
    {
        $ipAddress = IpAddressResolver::resolveFromServerWithTrustedProxies(
            [
                'REMOTE_ADDR' => '198.51.100.44',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.5',
            ],
            ['10.0.0.1']
        );

        $this->assertSame('198.51.100.44', $ipAddress);
    }

    public function testNormalizeForAbuseControlsMapsEmptyIpToSharedIdentifier(): void
    {
        $this->assertSame(
            IpAddressResolver::UNKNOWN_CLIENT_IDENTIFIER,
            IpAddressResolver::normalizeForAbuseControls('')
        );
        $this->assertSame('192.0.2.1', IpAddressResolver::normalizeForAbuseControls('192.0.2.1'));
    }
}
