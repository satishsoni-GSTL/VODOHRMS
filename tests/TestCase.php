<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but the in-memory SQLite database. Tests use
     * RefreshDatabase, which drops every table — a cached config (`php artisan config:cache`)
     * makes phpunit.xml's DB settings ignored and would point tests at the live database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app['config'];
        $connection = $config->get('database.default');

        if ($config->get("database.connections.{$connection}.driver") !== 'sqlite'
            || $config->get("database.connections.{$connection}.database") !== ':memory:') {
            fwrite(STDERR, "\nTests aborted: not using the in-memory SQLite database (connection '{$connection}'). "
                ."Run `php artisan config:clear` and try again.\n");
            exit(1);
        }

        return $app;
    }
}
