<?php

namespace Tests\Unit;

use Illuminate\Database\ConfigurationUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseConfigurationTest extends TestCase
{
    #[DataProvider('databaseDrivers')]
    public function test_platform_database_url_is_used_when_db_url_is_absent(string $driver, int $port): void
    {
        $connection = $this->connectionConfig(null, $driver.'://app:example-password@database.internal:'.$port.'/pinjemin', $driver);

        $this->assertSame('database.internal', $connection['host']);
        $this->assertSame('pinjemin', $connection['database']);
        $this->assertSame('app', $connection['username']);
    }

    #[DataProvider('databaseDrivers')]
    public function test_explicit_db_url_takes_priority_over_platform_database_url(string $driver, int $port): void
    {
        $connection = $this->connectionConfig($driver.'://app:example-password@existing.internal:'.$port.'/existing', $driver.'://app:example-password@database.internal:'.$port.'/pinjemin', $driver);

        $this->assertSame('existing.internal', $connection['host']);
        $this->assertSame('existing', $connection['database']);
    }

    #[DataProvider('databaseDrivers')]
    public function test_explicit_empty_db_url_preserves_individual_connection_settings(string $driver, int $port): void
    {
        $connection = $this->connectionConfig('', $driver.'://app:example-password@database.internal:'.$port.'/pinjemin', $driver);

        $this->assertSame('fallback.internal', $connection['host']);
        $this->assertSame('fallback', $connection['database']);
    }

    #[DataProvider('databaseDrivers')]
    public function test_individual_connection_settings_remain_available_without_either_url(string $driver, int $port): void
    {
        $connection = $this->connectionConfig(null, null, $driver);

        $this->assertSame('fallback.internal', $connection['host']);
        $this->assertSame('fallback', $connection['database']);
    }

    /** @return array<string, array{string, int}> */
    public static function databaseDrivers(): array
    {
        return ['mysql' => ['mysql', 3306], 'mariadb' => ['mariadb', 3306], 'pgsql' => ['pgsql', 5432]];
    }

    /** @return array<string, mixed> */
    private function connectionConfig(?string $dbUrl, ?string $databaseUrl, string $driver): array
    {
        $previousValues = [];
        foreach (['DB_URL' => $dbUrl, 'DATABASE_URL' => $databaseUrl, 'DB_HOST' => 'fallback.internal', 'DB_DATABASE' => 'fallback'] as $name => $value) {
            $previousValues[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
            if ($value !== null) {
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                putenv($name.'='.$value);
            }
        }
        try {
            $configuration = require base_path('config/database.php');

            return (new ConfigurationUrlParser)->parseConfiguration($configuration['connections'][$driver]);
        } finally {
            foreach ($previousValues as $name => [$environment, $server, $process]) {
                unset($_ENV[$name], $_SERVER[$name]);
                if ($environment !== null) {
                    $_ENV[$name] = $environment;
                }
                if ($server !== null) {
                    $_SERVER[$name] = $server;
                }
                putenv($process === false ? $name : $name.'='.$process);
            }
        }
    }
}
