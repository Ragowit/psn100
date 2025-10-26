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

    public function testSettersNormalizeValuesAndAreChainable(): void
    {
        $pageMetaData = new PageMetaData();

        $result = $pageMetaData
            ->setTitle('  Example Title  ')
            ->setDescription("\nExample Description\n")
            ->setImage('  https://example.com/image.png  ')
            ->setUrl('  https://example.com  ');

        $this->assertSame($pageMetaData, $result);
        $this->assertSame('Example Title', $pageMetaData->getTitle());
        $this->assertSame('Example Description', $pageMetaData->getDescription());
        $this->assertSame('https://example.com/image.png', $pageMetaData->getImage());
        $this->assertSame('https://example.com', $pageMetaData->getUrl());
    }

    public function testIsEmptyIndicatesWhetherAnyMetadataIsPresent(): void
    {
        $pageMetaData = new PageMetaData();
        $this->assertTrue($pageMetaData->isEmpty());

        $pageMetaData->setDescription('Description');
        $this->assertFalse($pageMetaData->isEmpty());

        $pageMetaData->setDescription('   ');
        $this->assertTrue($pageMetaData->isEmpty());
    }
}
