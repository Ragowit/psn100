<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/database.php';

final class DatabaseConfigTest extends TestCase
{
    public function testFromEnvironmentReadsValuesFromArray(): void
    {
        $config = DatabaseConfig::fromEnvironment([
            'DB_HOST' => 'db.example.test',
            'DB_NAME' => 'psn100',
            'DB_USER' => 'psn100',
            'DB_PASSWORD' => 'secret',
        ]);

        $this->assertTrue($config->isComplete());
        $this->assertSame('mysql:host=db.example.test;dbname=psn100;charset=utf8mb4', $config->getDsn());
        $this->assertSame('psn100', $config->getUser());
        $this->assertSame('secret', $config->getPassword());
    }

    public function testFromEnvironmentFallsBackToGetenvWhenArrayValueMissing(): void
    {
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_NAME=psn100');
        putenv('DB_USER=psn100');
        putenv('DB_PASSWORD=psn100');

        try {
            $config = DatabaseConfig::fromEnvironment([]);

            $this->assertTrue($config->isComplete());
            $this->assertSame('127.0.0.1', $this->readPrivateProperty($config, 'host'));
        } finally {
            putenv('DB_HOST');
            putenv('DB_NAME');
            putenv('DB_USER');
            putenv('DB_PASSWORD');
        }
    }

    public function testIsCompleteRequiresHostDatabaseUserAndPassword(): void
    {
        $missingDatabase = DatabaseConfig::fromEnvironment([
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => '',
            'DB_USER' => 'psn100',
            'DB_PASSWORD' => 'psn100',
        ]);

        $this->assertFalse($missingDatabase->isComplete());

        $missingPassword = DatabaseConfig::fromEnvironment([
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'psn100',
            'DB_USER' => 'psn100',
            'DB_PASSWORD' => '',
        ]);

        $this->assertFalse($missingPassword->isComplete());
    }

    public function testFromEnvironmentPreservesExactDbPasswordValue(): void
    {
        $config = DatabaseConfig::fromEnvironment([
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'psn100',
            'DB_USER' => 'psn100',
            'DB_PASSWORD' => '  secret-password  ',
        ]);

        $this->assertTrue($config->isComplete());
        $this->assertSame('  secret-password  ', $config->getPassword());
    }

    public function testDatabaseConstructorThrowsWhenPasswordIsMissing(): void
    {
        try {
            new Database(DatabaseConfig::fromEnvironment([
                'DB_HOST' => '127.0.0.1',
                'DB_NAME' => 'psn100',
                'DB_USER' => 'psn100',
                'DB_PASSWORD' => '',
            ]));
            $this->fail('Expected DatabaseConnectionException for missing DB_PASSWORD.');
        } catch (DatabaseConnectionException $exception) {
            $this->assertStringContainsString('Database connection is not configured', $exception->getMessage());
            $this->assertStringContainsString('DB_PASSWORD', $exception->getMessage());
        }
    }

    public function testDatabaseConstructorThrowsWhenConfigurationIsIncomplete(): void
    {
        try {
            new Database(DatabaseConfig::fromEnvironment([
                'DB_HOST' => '',
                'DB_NAME' => '',
                'DB_USER' => '',
                'DB_PASSWORD' => '',
            ]));
            $this->fail('Expected DatabaseConnectionException for incomplete configuration.');
        } catch (DatabaseConnectionException $exception) {
            $this->assertStringContainsString('Database connection is not configured', $exception->getMessage());
            $this->assertStringContainsString('DB_HOST', $exception->getMessage());
        }
    }

    private function readPrivateProperty(DatabaseConfig $config, string $property): string
    {
        $reflection = new ReflectionClass($config);
        $propertyReflection = $reflection->getProperty($property);
        $propertyReflection->setAccessible(true);

        return (string) $propertyReflection->getValue($config);
    }
}
