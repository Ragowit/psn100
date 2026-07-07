<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnTrophyApiPayloadInspector.php';

final class PsnTrophyApiPayloadInspectorTest extends TestCase
{
    private PsnTrophyApiPayloadInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inspector = new PsnTrophyApiPayloadInspector();
    }

    public function testNormalizeReturnsArrayPayloadUnchanged(): void
    {
        $payload = ['trophies' => [['trophyId' => 1]]];

        $this->assertSame($payload, $this->inspector->normalize($payload));
    }

    public function testNormalizeConvertsObjectPayloadToArray(): void
    {
        $payload = (object) [
            'trophies' => [
                (object) ['trophyId' => 1],
            ],
        ];

        $normalized = $this->inspector->normalize($payload);

        $this->assertSame([['trophyId' => 1]], $normalized['trophies']);
    }

    public function testExtractNpCommunicationIdsFromTrophyIconUrl(): void
    {
        $detected = $this->inspector->extractNpCommunicationIds([
            'trophies' => [
                [
                    'trophyGroupId' => 'all',
                    'trophyIconUrl' => 'https://image.api.playstation.com/trophy/np/NPWR99999_00_HASH/ICON.PNG',
                ],
            ],
        ]);

        $this->assertSame(['NPWR99999_00'], $detected);
    }

    public function testExtractNpCommunicationIdsFromTopLevelAndNestedFields(): void
    {
        $detected = $this->inspector->extractNpCommunicationIds([
            'npCommunicationId' => 'NPWR12345_00',
            'trophies' => [
                [
                    'np_communication_id' => 'NPWR12345_00',
                ],
            ],
            'trophyGroups' => [
                [
                    'npCommunicationId' => 'NPWR12345_00',
                    'trophies' => [
                        [
                            'trophyIconUrl' => 'https://image.api.playstation.com/trophy/np/NPWR12345_00_HASH/ICON.PNG',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(['NPWR12345_00'], $detected);
    }

    public function testAssertMatchesRequestedAllowsMatchingIds(): void
    {
        $this->inspector->assertMatchesRequested('NPWR12345_00', [
            'npCommunicationId' => 'NPWR12345_00',
        ], 'all/trophies');

        $this->assertTrue(true);
    }

    public function testAssertMatchesRequestedAllowsPayloadWithNoDetectableIds(): void
    {
        $this->inspector->assertMatchesRequested('NPWR12345_00', [
            'trophies' => [
                ['trophyGroupId' => 'all', 'trophyId' => 1],
            ],
        ], 'all/trophies');

        $this->assertTrue(true);
    }

    public function testAssertMatchesRequestedThrowsForMismatchedId(): void
    {
        try {
            $this->inspector->assertMatchesRequested('NPWR12345_00', [
                'trophies' => [
                    [
                        'trophyGroupId' => 'all',
                        'trophyIconUrl' => 'https://image.api.playstation.com/trophy/np/NPWR99999_00_HASH/ICON.PNG',
                    ],
                ],
            ], 'all/trophies');
            $this->fail('Expected PsnGameLookupException to be thrown for mismatched payload ID.');
        } catch (PsnGameLookupException $exception) {
            $this->assertStringContainsString(
                'PSN response integrity check failed for endpoint "all/trophies"',
                $exception->getMessage()
            );
            $this->assertStringContainsString('NPWR12345_00', $exception->getMessage());
            $this->assertStringContainsString('NPWR99999_00', $exception->getMessage());
        }
    }

    public function testAssertMatchesRequestedThrowsForConflictingIdsInPayload(): void
    {
        try {
            $this->inspector->assertMatchesRequested('NPWR12345_00', [
                'npCommunicationId' => 'NPWR12345_00',
                'trophies' => [
                    [
                        'trophyGroupId' => 'all',
                        'trophyId' => 1,
                        'npCommunicationId' => 'NPWR99999_00',
                    ],
                ],
            ], 'all/trophies');
            $this->fail('Expected PsnGameLookupException when payload contains conflicting npCommunicationIds.');
        } catch (PsnGameLookupException $exception) {
            $this->assertStringContainsString(
                'PSN response integrity check failed for endpoint "all/trophies"',
                $exception->getMessage()
            );
            $this->assertStringContainsString('NPWR12345_00', $exception->getMessage());
            $this->assertStringContainsString('NPWR99999_00', $exception->getMessage());
        }
    }

    public function testAssertMatchesRequestedThrowsForMismatchedTrophyGroupsId(): void
    {
        try {
            $this->inspector->assertMatchesRequested('NPWR12345_00', [
                'trophyGroups' => [
                    [
                        'trophyGroupId' => 'all',
                        'npCommunicationId' => 'NPWR99999_00',
                    ],
                ],
            ], 'trophyGroups');
            $this->fail('Expected PsnGameLookupException to be thrown for mismatched trophyGroups payload ID.');
        } catch (PsnGameLookupException $exception) {
            $this->assertStringContainsString(
                'PSN response integrity check failed for endpoint "trophyGroups"',
                $exception->getMessage()
            );
            $this->assertStringContainsString('NPWR12345_00', $exception->getMessage());
            $this->assertStringContainsString('NPWR99999_00', $exception->getMessage());
        }
    }

    public function testAssertMatchesRequestedThrowsForConflictingNestedTrophyGroupIds(): void
    {
        try {
            $this->inspector->assertMatchesRequested('NPWR12345_00', [
                'trophyGroups' => [
                    [
                        'npCommunicationId' => 'NPWR12345_00',
                        'trophies' => [
                            [
                                'trophyGroupId' => 'all',
                                'trophyId' => 2,
                                'trophyIconUrl' => 'https://image.api.playstation.com/trophy/np/NPWR99999_00_HASH/ICON.PNG',
                            ],
                        ],
                    ],
                ],
            ], 'trophyGroups');
            $this->fail('Expected PsnGameLookupException when trophyGroups contains conflicting nested IDs.');
        } catch (PsnGameLookupException $exception) {
            $this->assertStringContainsString(
                'PSN response integrity check failed for endpoint "trophyGroups"',
                $exception->getMessage()
            );
            $this->assertStringContainsString('NPWR12345_00', $exception->getMessage());
            $this->assertStringContainsString('NPWR99999_00', $exception->getMessage());
        }
    }
}
