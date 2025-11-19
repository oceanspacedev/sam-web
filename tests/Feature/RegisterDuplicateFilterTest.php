<?php

namespace Tests\Feature;

use App\Filament\Resources\Registers\RegisterResource;
use App\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterDuplicateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_filter_returns_records_with_matching_creator_name_and_address(): void
    {
        $duplicates = Register::factory()->count(2)->create([
            'created_by' => 'Alice',
            'nama_outlet' => 'Outlet Satu',
            'alamat_outlet' => 'Jalan Mawar No. 1',
        ]);

        Register::factory()->create([
            'created_by' => 'Bob',
            'nama_outlet' => 'Outlet Dua',
            'alamat_outlet' => 'Jalan Melati No. 2',
        ]);

        $filteredIds = RegisterResource::applyDuplicateFilter(Register::query())
            ->pluck('id')
            ->all();

        $this->assertCount(2, $filteredIds);
        $this->assertEqualsCanonicalizing($duplicates->pluck('id')->all(), $filteredIds);
    }
}
