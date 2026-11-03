<?php

namespace Tests\Unit\Services;

use App\Models\Gym;
use App\Services\WidgetOriginService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WidgetOriginServiceTest extends TestCase
{
    private WidgetOriginService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://app.gymportal.test']);
        $this->service = new WidgetOriginService;
    }

    private function gymWithOrigins(?array $origins): Gym
    {
        return new Gym([
            'widget_settings' => $origins === null ? [] : ['security' => ['allowed_origins' => $origins]],
        ]);
    }

    public static function normalizeProvider(): array
    {
        return [
            'wildcard' => ['*', '*'],
            'bare domain gets https' => ['www.example.com', 'https://www.example.com'],
            'path is stripped' => ['https://www.example.com/kontakt?x=1', 'https://www.example.com'],
            'case is lowered' => ['HTTPS://WWW.Example.COM', 'https://www.example.com'],
            'default https port dropped' => ['https://example.com:443', 'https://example.com'],
            'default http port dropped' => ['http://example.com:80', 'http://example.com'],
            'custom port kept' => ['http://localhost:8080', 'http://localhost:8080'],
            'surrounding whitespace' => ['  example.com  ', 'https://example.com'],
            'other scheme' => ['ftp://example.com', null],
            'javascript scheme' => ['javascript://alert(1)', null],
            'subdomain wildcard' => ['https://*.example.com', null],
            'credentials' => ['https://user:pass@example.com', null],
            'spaces in host' => ['mein studio.de', null],
            'empty' => ['', null],
        ];
    }

    #[Test]
    #[DataProvider('normalizeProvider')]
    public function it_normalizes_origins(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->service->normalize($input));
    }

    #[Test]
    public function an_unconfigured_gym_allows_every_origin(): void
    {
        $gym = $this->gymWithOrigins(null);

        $this->assertSame(['*'], $this->service->allowedOrigins($gym));
        $this->assertTrue($this->service->isAllowed($gym, 'https://anywhere.example'));
    }

    #[Test]
    public function the_wildcard_allows_every_origin(): void
    {
        $gym = $this->gymWithOrigins(['*']);

        $this->assertTrue($this->service->isAllowed($gym, 'https://anywhere.example'));
        $this->assertTrue($this->service->isAllowed($gym, 'null'));
    }

    #[Test]
    public function a_restricted_gym_only_allows_listed_origins(): void
    {
        $gym = $this->gymWithOrigins(['https://www.studio.de']);

        $this->assertTrue($this->service->isAllowed($gym, 'https://www.studio.de'));
        $this->assertTrue($this->service->isAllowed($gym, 'https://WWW.studio.de'));
        $this->assertFalse($this->service->isAllowed($gym, 'https://studio.de'));
        $this->assertFalse($this->service->isAllowed($gym, 'http://www.studio.de'));
        $this->assertFalse($this->service->isAllowed($gym, 'https://www.studio.de.evil.com'));
        $this->assertFalse($this->service->isAllowed($gym, 'null'));
    }

    #[Test]
    public function the_application_origin_is_always_allowed_for_the_preview(): void
    {
        $gym = $this->gymWithOrigins(['https://www.studio.de']);

        $this->assertTrue($this->service->isAllowed($gym, 'https://app.gymportal.test'));
    }

    #[Test]
    public function requests_without_origin_are_not_subject_to_cors(): void
    {
        $gym = $this->gymWithOrigins(['https://www.studio.de']);

        $this->assertTrue($this->service->isAllowed($gym, null));
        $this->assertTrue($this->service->isAllowed($gym, ''));
    }

    #[Test]
    public function an_empty_list_allows_no_foreign_origin(): void
    {
        $gym = $this->gymWithOrigins([]);

        $this->assertFalse($this->service->isAllowed($gym, 'https://www.studio.de'));
    }

    #[Test]
    public function it_normalizes_and_deduplicates_lists(): void
    {
        $this->assertSame(
            ['https://www.studio.de', 'https://studio.de'],
            $this->service->normalizeList(['www.studio.de', 'https://www.studio.de/', 'studio.de'])
        );
        $this->assertSame(['*'], $this->service->normalizeList(['studio.de', '*']));
    }
}
