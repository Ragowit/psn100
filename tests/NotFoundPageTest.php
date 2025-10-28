<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/NotFoundPage.php';

final class NotFoundPageTest extends TestCase
{
    public function testCreateDefaultProvidesExpectedValues(): void
    {
        $page = NotFoundPage::createDefault();

        $this->assertSame('404 ~ PSN 100%', $page->getTitle());
        $this->assertSame('404', $page->getHeading());
        $this->assertSame('There are no trophies here.', $page->getMessage());
    }

    public function testWithHeadingReturnsNewInstanceWithUpdatedHeading(): void
    {
        $original = NotFoundPage::createDefault();
        $updated = $original->withHeading('Lost in the Void');

        $this->assertTrue($original !== $updated, 'Expected cloned instance when updating heading.');
        $this->assertSame('404', $original->getHeading());
        $this->assertSame('Lost in the Void', $updated->getHeading());
    }

    public function testWithTitleAndMessageAreChainableAndPreserveOtherValues(): void
    {
        $page = NotFoundPage::createDefault()
            ->withTitle('Where did it go?')
            ->withMessage('Try searching for another trophy list.');

        $this->assertSame('Where did it go?', $page->getTitle());
        $this->assertSame('404', $page->getHeading());
        $this->assertSame('Try searching for another trophy list.', $page->getMessage());
    }
}
