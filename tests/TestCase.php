<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests that render a page must not depend on a built frontend: the
        // Vite manifest is produced by `npm run build` and is not committed, so
        // it is missing in CI and would turn the request into a 500.
        $this->withoutVite();
    }
}
