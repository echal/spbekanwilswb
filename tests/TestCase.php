<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pengaman: hentikan test sebelum trait seperti RefreshDatabase berjalan
     * bila koneksi bukan SQLite in-memory (mencegah database `spbe` ikut terhapus).
     */
    protected function setUpTraits()
    {
        $default = config('database.default');

        if ($default !== 'sqlite' || config("database.connections.{$default}.database") !== ':memory:') {
            throw new RuntimeException("Test dibatalkan: koneksi database [{$default}] bukan SQLite :memory:.");
        }

        return parent::setUpTraits();
    }
}
