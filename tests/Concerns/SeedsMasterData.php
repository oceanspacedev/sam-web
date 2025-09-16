<?php

namespace Tests\Concerns;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;

trait SeedsMasterData
{
    /**
     * Seed minimal master data required for most feature flows.
     * Optionally force a specific Role ID to avoid controller branching.
     *
     * @param int|null $forceRoleId
     * @return array{bu: \App\Models\BadanUsaha, div: \App\Models\Division, reg: \App\Models\Region, clus: \App\Models\Cluster, role: \App\Models\Role}
     */
    protected function seedMasterData(?int $forceRoleId = null): array
    {
        $bu = BadanUsaha::create(['name' => 'BU']);
        $div = Division::create(['name' => 'DIV', 'badanusaha_id' => $bu->id]);
        $reg = Region::create(['name' => 'REG', 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
        $clus = Cluster::create(['name' => 'CLUS', 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

        $role = new Role(['name' => 'DM', 'can_access_web' => 1]);
        if ($forceRoleId !== null) {
            // Some controllers branch on specific role IDs; allow tests to avoid those code paths.
            $role->id = $forceRoleId;
        }
        $role->save();

        return compact('bu', 'div', 'reg', 'clus', 'role');
    }
}
