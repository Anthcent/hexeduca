<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on compiled frontend assets (CI runs PHP
        // suites before, or without, `npm run build`).
        $this->withoutVite();
    }
}
