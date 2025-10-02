<?php

namespace App\Imports;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class OutletImport implements ToModel, WithHeadingRow
{
    public function __construct(private string $mode = 'upsert')
    {
        // Modes:
        // - upsert (default): update if exists, otherwise create
        // - update_cluster: ONLY update cluster for existing outlets; skip create
        // - create_new: ONLY create new outlets; skip update existing
    }

    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        try {
            // Get Badan Usaha ID
            $badanusaha_id = $this->getBadanUsahaId($row['badan_usaha']);

            // Get Division ID
            $divisi_id = $this->getDivisionId($row['divisi'], $badanusaha_id);

            // Get Region ID, or create Region if not found
            $region_id = $this->getRegionId($row['region'], $divisi_id, $badanusaha_id);

            // Get Cluster ID, or create Cluster if not found
            $cluster_id = $this->getClusterId($row['cluster'], $badanusaha_id, $divisi_id, $region_id);

            // Search for existing Outlet, and update or create a new one based on mode
            $outlet = Outlet::where('kode_outlet', preg_replace('/\s+/', '', strtoupper($row['kode_outlet'])))
                ->where('divisi_id', $divisi_id);

            if ($outlet->exists()) {
                if ($this->mode === 'create_new') {
                    // Skip updates in create_new mode
                    return null;
                }

                // update existing
                $existing = $outlet->first();

                if ($this->mode === 'update_cluster') {
                    // Only update cluster (and related hierarchy if needed)
                    $existing->update([
                        'region_id' => $region_id,
                        'cluster_id' => $cluster_id,
                    ]);
                } else {
                    // upsert (default) - update key fields (keep address/radius/limit as in current import)
                    $existing->update([
                        'badanusaha_id' => $badanusaha_id,
                        'divisi_id' => $divisi_id,
                        'region_id' => $region_id,
                        'cluster_id' => $cluster_id,
                        'kode_outlet' => preg_replace('/\s+/', '', strtoupper($row['kode_outlet'])),
                        'nama_outlet' => strtoupper($row['nama_outlet']),
                        'status_outlet' => strtoupper($row['status']),
                    ]);
                }

                return null; // no new model created on update
            } else {
                if ($this->mode === 'update_cluster') {
                    // Skip creation in update_cluster mode
                    return null;
                }

                return new Outlet([
                    'badanusaha_id' => $badanusaha_id,
                    'divisi_id' => $divisi_id,
                    'region_id' => $region_id,
                    'cluster_id' => $cluster_id,
                    'kode_outlet' => strtoupper($row['kode_outlet']),
                    'nama_outlet' => strtoupper($row['nama_outlet']),
                    'alamat_outlet' => strtoupper($row['alamat_outlet']),
                    'distric' => strtoupper($row['distric']),
                    'status_outlet' => strtoupper($row['status']),
                    'radius' => $row['radius'] ?? 100,
                    'limit' => $row['limit'] ?? 0,
                    'latlong' => $row['latlong'] ?? null,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error importing row: '.json_encode($row).' - '.$e->getMessage());
            Session::flash('error', 'Error importing row: '.json_encode($row).' - '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get Badan Usaha ID
     */
    private function getBadanUsahaId($name)
    {
        return BadanUsaha::where('name', preg_replace('/\s+/', '', $name))->firstOrFail()->id;
    }

    /**
     * Get Division ID
     */
    private function getDivisionId($name, $badanusaha_id)
    {
        return Division::where('name', preg_replace('/\s+/', '', $name))
            ->where('badanusaha_id', $badanusaha_id)
            ->firstOrFail()->id;
    }

    /**
     * Get Region ID or create Region if not found
     */
    private function getRegionId($name, $divisi_id, $badanusaha_id)
    {
        $region = Region::where('name', preg_replace('/\s+/', '', $name))
            ->where('divisi_id', $divisi_id)
            ->where('badanusaha_id', $badanusaha_id)
            ->first();

        if ($region) {
            return $region->id;
        } else {
            // Create new region if not found
            $newRegion = Region::create([
                'name' => strtoupper($name),
                'divisi_id' => $divisi_id,
                'badanusaha_id' => $badanusaha_id,
            ]);
            Log::info("Region created: {$name}");

            return $newRegion->id;
        }
    }

    /**
     * Get Cluster ID or create Cluster if not found
     */
    private function getClusterId($name, $badanusaha_id, $divisi_id, $region_id)
    {
        $cluster = Cluster::where('name', preg_replace('/\s+/', '', $name))
            ->first();

        if ($cluster) {
            return $cluster->id;
        } else {
            // Create new cluster if not found
            $newCluster = Cluster::create([
                'name' => strtoupper($name),
                'badanusaha_id' => $badanusaha_id,
                'divisi_id' => $divisi_id,
                'region_id' => $region_id,
            ]);
            Log::info("Cluster created: {$name}");

            return $newCluster->id;
        }
    }
}
