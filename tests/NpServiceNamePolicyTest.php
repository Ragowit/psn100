<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Policy/NpServiceNamePolicy.php';

final class NpServiceNamePolicyTest extends TestCase
{
    public function testResolvePreferredNpServiceNamePrefersLegacyServiceForLegacyPlatform(): void
    {
        $policy = new NpServiceNamePolicy();

        $this->assertSame('trophy', $policy->resolvePreferredNpServiceName('PS3,PS5'));
    }

    public function testResolvePreferredNpServiceNamePrefersTrophy2ForModernPlatform(): void
    {
        $policy = new NpServiceNamePolicy();

        $this->assertSame('trophy2', $policy->resolvePreferredNpServiceName('PS5'));
    }

    public function testBuildLookupQueryVariantsHonorsPreferredServiceAndKeepsUniqueVariants(): void
    {
        $policy = new NpServiceNamePolicy();

        $variants = $policy->buildLookupQueryVariants('trophy2');

        $this->assertSame(['npServiceName' => 'trophy2'], $variants[0]);
        $this->assertSame(3, count($variants));
    }

    public function testResolveAlternateQueryVariantReturnsFirstDifferentQuery(): void
    {
        $policy = new NpServiceNamePolicy();
        $variants = $policy->buildLookupQueryVariants(null);

        $alternate = $policy->resolveAlternateQueryVariant(
            $variants,
            []
        );

        $this->assertSame(['npServiceName' => 'trophy'], $alternate);
    }
}
