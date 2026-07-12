<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameDetail.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameDetailFormParser.php';
require_once __DIR__ . '/../wwwroot/classes/GameAvailabilityStatus.php';

final class GameDetailFormParserTest extends TestCase
{
    private GameDetailFormParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new GameDetailFormParser();
    }

    public function testParseGameIdAcceptsValidValues(): void
    {
        $this->assertSame(42, $this->parser->parseGameId(42));
        $this->assertSame(7, $this->parser->parseGameId('7'));
        $this->assertSame(0, $this->parser->parseGameId('0'));
        $this->assertSame(123, $this->parser->parseGameId(' 123 '));
    }

    public function testParseGameIdRejectsInvalidValues(): void
    {
        $this->assertSame(null, $this->parser->parseGameId(-1));
        $this->assertSame(null, $this->parser->parseGameId('-5'));
        $this->assertSame(null, $this->parser->parseGameId(''));
        $this->assertSame(null, $this->parser->parseGameId('abc'));
        $this->assertSame(null, $this->parser->parseGameId('12a'));
        $this->assertSame(null, $this->parser->parseGameId(null));
    }

    public function testParseNpCommunicationIdTrimsAndRejectsEmpty(): void
    {
        $this->assertSame('NPWR10853_00', $this->parser->parseNpCommunicationId(' NPWR10853_00 '));
        $this->assertSame(null, $this->parser->parseNpCommunicationId(''));
        $this->assertSame(null, $this->parser->parseNpCommunicationId('   '));
        $this->assertSame(null, $this->parser->parseNpCommunicationId(123));
    }

    public function testParseActionNormalizesCaseAndWhitespace(): void
    {
        $this->assertSame('update-status', $this->parser->parseAction('Update-Status'));
        $this->assertSame('update-detail', $this->parser->parseAction(' update-detail '));
        $this->assertSame('', $this->parser->parseAction(''));
        $this->assertSame('', $this->parser->parseAction(null));
    }

    public function testParseStatusAcceptsValidEnumValues(): void
    {
        $this->assertSame(GameAvailabilityStatus::NORMAL, $this->parser->parseStatus(0));
        $this->assertSame(GameAvailabilityStatus::OBSOLETE, $this->parser->parseStatus('3'));
        $this->assertSame(GameAvailabilityStatus::MERGED, $this->parser->parseStatus(' 2 '));
    }

    public function testParseStatusRejectsInvalidValues(): void
    {
        $this->assertSame(null, $this->parser->parseStatus(''));
        $this->assertSame(null, $this->parser->parseStatus('-1'));
        $this->assertSame(null, $this->parser->parseStatus('abc'));
        $this->assertSame(null, $this->parser->parseStatus(999));
        $this->assertSame(null, $this->parser->parseStatus(null));
    }

    public function testNormalizePlatformsOrdersKnownPlatforms(): void
    {
        $this->assertSame('PS4,PS5', $this->parser->normalizePlatforms(['PS5', 'PS4']));
        $this->assertSame('PS3,PC', $this->parser->normalizePlatforms('pc, ps3'));
        $this->assertSame('', $this->parser->normalizePlatforms([]));
        $this->assertSame('', $this->parser->normalizePlatforms('UNKNOWN'));
    }

    public function testNormalizeObsoleteIdsDeduplicatesAndFilters(): void
    {
        $this->assertSame('42,1,84', $this->parser->normalizeObsoleteIds('42, 1, 84, 42'));
        $this->assertSame('7', $this->parser->normalizeObsoleteIds('007'));
        $this->assertSame(null, $this->parser->normalizeObsoleteIds(''));
        $this->assertSame(null, $this->parser->normalizeObsoleteIds('abc,def'));
        $this->assertSame(null, $this->parser->normalizeObsoleteIds(null));
    }

    public function testCreateGameDetailFromPostBuildsGameDetail(): void
    {
        $detail = $this->parser->createGameDetailFromPost(10, [
            'np_communication_id' => ' NPWR-123 ',
            'name' => 'Example Game',
            'icon_url' => 'icon.png',
            'platform' => ['PS5', 'PS4'],
            'message' => 'hello',
            'set_version' => '01.00',
            'region' => ' US ',
            'psnprofiles_id' => ' 999 ',
            'obsolete_ids' => '42, 42, 84',
        ], GameAvailabilityStatus::OBSOLETE);

        $this->assertSame(10, $detail->getId());
        $this->assertSame('NPWR-123', $detail->getNpCommunicationId());
        $this->assertSame('Example Game', $detail->getName());
        $this->assertSame('icon.png', $detail->getIconUrl());
        $this->assertSame('PS4,PS5', $detail->getPlatform());
        $this->assertSame('hello', $detail->getMessage());
        $this->assertSame('01.00', $detail->getSetVersion());
        $this->assertSame('US', $detail->getRegion());
        $this->assertSame('999', $detail->getPsnprofilesId());
        $this->assertSame(GameAvailabilityStatus::OBSOLETE, $detail->getStatus());
        $this->assertSame('42,84', $detail->getObsoleteIds());
    }
}
