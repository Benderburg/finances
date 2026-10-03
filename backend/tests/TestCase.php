<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'norocel_test') {
            throw new \LogicException('Tests require APP_ENV=testing and the isolated norocel_test database.');
        }

        return $app;
    }
}
