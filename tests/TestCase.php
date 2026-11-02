<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        if (env('CI') || env('GITLAB_CI') || env('DB_CONNECTION') === 'mysql') {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.mysql.host', env('DB_HOST', 'mariadb'));
            $app['config']->set('database.connections.mysql.database', env('DB_DATABASE', 'testing_db'));
            $app['config']->set('database.connections.mysql.username', env('DB_USERNAME', 'test_user'));
            $app['config']->set('database.connections.mysql.password', env('DB_PASSWORD', 'test_password'));

            return $app;
        }

        // Sail exports development DB settings. Pin tests before RefreshDatabase runs.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }
}
