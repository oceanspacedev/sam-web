<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ReadmeShelfTest extends TestCase
{
    public function test_root_readme_uses_the_oceanspacedev_onboarding_shelf(): void
    {
        $readmePath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'README.md';

        $this->assertFileExists($readmePath);

        $readme = file_get_contents($readmePath);

        $this->assertNotFalse($readme);
        $this->assertNotSame('', trim($readme));

        $requiredHeadings = [
            '## Daftar isi',
            '## Tujuan dan scope',
            '## Fitur utama',
            '## Cara kerja aplikasi',
            '## Arsitektur',
            '## Tech stack',
            '## Persiapan development',
            '## Konfigurasi environment',
            '## Menjalankan aplikasi',
            '## Workflow development',
            '## Testing dan quality check',
            '## Batasan dan technical debt',
            '## Troubleshooting',
            '## API dan dokumentasi',
        ];

        foreach ($requiredHeadings as $heading) {
            $this->assertStringContainsString(
                $heading,
                $readme,
                "Root README must include heading [{$heading}]",
            );
        }

        $this->assertMatchesRegularExpression(
            '/^## .*Role.*hak akses/m',
            $readme,
            'Root README must include a heading that contains Role and hak akses',
        );

        foreach (['SAM', '/admin', '/api', 'Asia/Jakarta'] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $readme,
                "Root README must include SAM identity marker [{$marker}]",
            );
        }
    }
}
