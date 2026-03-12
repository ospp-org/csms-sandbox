<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $token = (int) (getenv('TEST_TOKEN') ?: 0);
        $db = $token + 2; // DB 0 = app, DB 1 = cache, DB 2+ = test workers

        $config = $this->app['config']->get('database.redis');
        $config['default']['database'] = $db;
        $config['cache']['database'] = $db;

        $this->app->instance('redis', new RedisManager(
            $this->app,
            $config['client'] ?? 'phpredis',
            $config,
        ));

        Redis::connection('default')->flushdb();
    }
}
