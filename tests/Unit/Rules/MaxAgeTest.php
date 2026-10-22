<?php

namespace Tests\Unit\Rules;

use App\Rules\MaxAge;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MaxAgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22');
    }

    private function passes(mixed $birthDate): bool
    {
        return Validator::make(['birth_date' => $birthDate], ['birth_date' => [new MaxAge]])->passes();
    }

    public function test_earliest_birth_date_is_100_years_ago(): void
    {
        $this->assertSame('1926-10-22', MaxAge::earliestBirthDate());
    }

    public function test_accepts_birth_date_exactly_100_years_ago(): void
    {
        $this->assertTrue($this->passes('1926-10-22'));
    }

    public function test_rejects_birth_date_older_than_100_years(): void
    {
        $this->assertFalse($this->passes('1926-10-21'));
    }

    public function test_accepts_recent_birth_date(): void
    {
        $this->assertTrue($this->passes('1990-01-01'));
    }

    public function test_ignores_empty_values(): void
    {
        $this->assertTrue($this->passes(null));
        $this->assertTrue($this->passes(''));
    }

    public function test_leaves_unparseable_values_to_the_date_rule(): void
    {
        $this->assertTrue($this->passes('not-a-date'));
    }
}
