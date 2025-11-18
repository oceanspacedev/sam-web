<?php

namespace Database\Seeders;

use App\Models\PlanVisit;
use Illuminate\Database\Seeder;

class PlanVisitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        collect([
            ['user_id' => 1, 'outlet_id' => 1, 'tanggal_visit' => '2021-09-11'],
            ['user_id' => 1, 'outlet_id' => 2, 'tanggal_visit' => '2021-09-11'],
            ['user_id' => 1, 'outlet_id' => 1, 'tanggal_visit' => '2020-09-10'],
        ])->each(function (array $data): void {
            PlanVisit::create(array_merge(
                PlanVisit::schedulePayload($data['tanggal_visit']),
                [
                    'user_id' => $data['user_id'],
                    'outlet_id' => $data['outlet_id'],
                ]
            ));
        });
    }
}
