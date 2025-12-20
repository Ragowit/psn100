<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PageMetaData.php';

final class PageMetaDataTest extends TestCase
{
    public function testConstructorNormalizesValues(): void
    {
        $pageMetaData = new PageMetaData('  Title  ', '   ', null, "\nhttps://example.com/page\n");

        $this->assertSame('Title', $pageMetaData->getTitle());
        $this->assertSame(null, $pageMetaData->getDescription());
        $this->assertSame(null, $pageMetaData->getImage());
        $this->assertSame('https://example.com/page', $pageMetaData->getUrl());
    }

    public function testWithersNormalizeValuesAndCreateNewInstances(): void
    {
        $pageMetaData = new PageMetaData();

        $result = $pageMetaData
            ->withTitle('  Example Title  ')
            ->withDescription("\nExample Description\n")
            ->withImage('  https://example.com/image.png  ')
            ->withUrl('  https://example.com  ');

        $this->assertTrue($pageMetaData !== $result);
        $this->assertTrue($pageMetaData->isEmpty());

        $this->assertSame('Example Title', $result->getTitle());
        $this->assertSame('Example Description', $result->getDescription());
        $this->assertSame('https://example.com/image.png', $result->getImage());
        $this->assertSame('https://example.com', $result->getUrl());
    }

    public function testIsEmptyIndicatesWhetherAnyMetadataIsPresent(): void
    {
        $pageMetaData = new PageMetaData();
        $this->assertTrue($pageMetaData->isEmpty());

        $pageMetaDataWithDescription = $pageMetaData->withDescription('Description');
        $this->assertFalse($pageMetaDataWithDescription->isEmpty());

        $pageMetaDataCleared = $pageMetaDataWithDescription->withDescription('   ');
        $this->assertTrue($pageMetaDataCleared->isEmpty());
    }
}
