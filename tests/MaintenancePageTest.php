<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/MaintenancePage.php';

final class MaintenancePageTest extends TestCase
{
    public function testCreateDefaultProvidesExpectedMetadata(): void
    {
        $page = MaintenancePage::createDefault();

        $this->assertSame('Maintenance ~ PSN 100%', $page->getTitle());
        $this->assertSame('Maintenance', $page->getHeading());
        $this->assertSame(
            'Check your leaderboard position against other PlayStation trophy hunters!',
            $page->getDescription()
        );
        $this->assertSame(
            "Markus 'Ragowit' Persson, and other contributors via GitHub project",
            $page->getAuthor()
        );
        $this->assertSame('The site is undergoing some maintenance. Should be back soon!', $page->getMessage());

        $stylesheets = $page->getStylesheets();
        $this->assertCount(1, $stylesheets);

        $stylesheet = $stylesheets[0];
        $this->assertSame(
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
            $stylesheet->getHref()
        );
        $this->assertSame('stylesheet', $stylesheet->getRel());
        $this->assertSame(
            'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB',
            $stylesheet->getIntegrity()
        );
        $this->assertSame('anonymous', $stylesheet->getCrossorigin());
    }

    public function testWithMessageReturnsClonedInstanceWithUpdatedMessage(): void
    {
        $page = MaintenancePage::createDefault();
        $updated = $page->withMessage("Custom\nMessage");

        $this->assertTrue($page !== $updated, 'Expected a cloned instance.');
        $this->assertSame("Custom\nMessage", $updated->getMessage());
        $this->assertSame('The site is undergoing some maintenance. Should be back soon!', $page->getMessage());
        $this->assertSame($page->getTitle(), $updated->getTitle());
        $this->assertSame($page->getHeading(), $updated->getHeading());
        $this->assertSame($page->getDescription(), $updated->getDescription());
        $this->assertSame($page->getAuthor(), $updated->getAuthor());
        $this->assertSame($page->getStylesheets(), $updated->getStylesheets());
    }

    public function testStylesheetFactoryCreatesConfiguredInstances(): void
    {
        $custom = MaintenancePageStylesheet::create('style.css', 'alternate', 'hash', 'use-credentials');

        $this->assertSame('style.css', $custom->getHref());
        $this->assertSame('alternate', $custom->getRel());
        $this->assertSame('hash', $custom->getIntegrity());
        $this->assertSame('use-credentials', $custom->getCrossorigin());

        $bootstrap = MaintenancePageStylesheet::bootstrapCdn('5.3.0');

        $this->assertSame(
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
            $bootstrap->getHref()
        );
        $this->assertSame('stylesheet', $bootstrap->getRel());
        $this->assertSame(
            'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB',
            $bootstrap->getIntegrity()
        );
        $this->assertSame('anonymous', $bootstrap->getCrossorigin());
    }
}
