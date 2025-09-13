<?php

namespace App\Imports;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class OutletImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
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

            // Search for existing Outlet, and update or create a new one
            $outlet = Outlet::where('kode_outlet', preg_replace('/\s+/', '', strtoupper($row['kode_outlet'])))
                ->where('divisi_id', $divisi_id);

            if ($outlet->exists()) {
                $outlet = $outlet->first();
                $outlet->update([
                    'badanusaha_id' => $badanusaha_id,
                    'divisi_id' => $divisi_id,
                    'region_id' => $region_id,
                    'cluster_id' => $cluster_id,
                    'kode_outlet' => preg_replace('/\s+/', '', strtoupper($row['kode_outlet'])),
                    'nama_outlet' => strtoupper($row['nama_outlet']),
                    // 'alamat_outlet' => strtoupper($row['alamat_outlet']),
                    // 'distric' => strtoupper($row['distric']),
                    'status_outlet' => strtoupper($row['status']),
                    // 'radius' => $row['radius'] ?? $outlet->radius,
                    // 'limit' => $row['limit'] ?? $outlet->limit,
                ]);
                return null;
            } else {
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
        } catch (\Exception $e) {
            Log::error('Error importing row: ' . json_encode($row) . ' - ' . $e->getMessage());
            Session::flash('error', 'Error importing row: ' . json_encode($row) . ' - ' . $e->getMessage());
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
                'badanusaha_id' => $badanusaha_id
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
                'region_id' => $region_id
            ]);
            Log::info("Cluster created: {$name}");
            return $newCluster->id;
        }
    }
}


// <?php

// namespace App\Imports;

// use App\Models\BadanUsaha;
// use App\Models\Cluster;
// use App\Models\Division;
// use App\Models\Outlet;
// use App\Models\Region;
// use Carbon\Carbon;
// use Maatwebsite\Excel\Concerns\ToModel;
// use Maatwebsite\Excel\Concerns\WithHeadingRow;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Session;

// class OutletImport implements ToModel, WithHeadingRow
// {
//     /**
//      * @param array $row
//      *
//      * @return \Illuminate\Database\Eloquent\Model|null
//      */
//     public function model(array $row)
//     {
//         try {
//             // Get Badan Usaha ID
//             $badanusaha_id = $this->getBadanUsahaId($row['badan_usaha']);

//             // Get Division ID
//             $divisi_id = $this->getDivisionId($row['divisi'], $badanusaha_id);

//             // Get Region ID, or create Region if not found
//             $region_id = $this->getRegionId($row['region'], $divisi_id, $badanusaha_id);

//             // Get Cluster ID, or create Cluster if not found
//             $cluster_id = $this->getClusterId($row['cluster'], $badanusaha_id, $divisi_id, $region_id);

//             // Search for existing Outlet, and update or create a new one
//             $outlet = Outlet::where('kode_outlet', preg_replace('/\s+/', '', strtoupper($row['kode_outlet'])))
//                 ->where('divisi_id', $divisi_id);

//             if ($outlet->exists()) {
//                 $outlet = $outlet->first();
//                 $outlet->update([
//                     'badanusaha_id' => $badanusaha_id,
//                     'divisi_id' => $divisi_id,
//                     'region_id' => $region_id,
//                     'cluster_id' => $cluster_id,
//                     'kode_outlet' => preg_replace('/\s+/', '', strtoupper($row['kode_outlet'])),
//                     'nama_outlet' => strtoupper($row['nama_outlet']),
//                     'alamat_outlet' => strtoupper($row['alamat_outlet']),
//                     'distric' => strtoupper($row['distric']),
//                     'status_outlet' => strtoupper($row['status']),
//                     'radius' => $row['radius'] ?? $outlet->radius,
//                     'limit' => $row['limit'] ?? $outlet->limit,
//                 ]);
//                 return null;
//             } else {
//                 return new Outlet([
//                     'badanusaha_id' => $badanusaha_id,
//                     'divisi_id' => $divisi_id,
//                     'region_id' => $region_id,
//                     'cluster_id' => $cluster_id,
//                     'kode_outlet' => strtoupper($row['kode_outlet']),
//                     'nama_outlet' => strtoupper($row['nama_outlet']),
//                     'alamat_outlet' => strtoupper($row['alamat_outlet']),
//                     'distric' => strtoupper($row['distric']),
//                     'status_outlet' => strtoupper($row['status']),
//                     'radius' => $row['radius'] ?? 100,
//                     'limit' => $row['limit'] ?? 0,
//                     'latlong' => $row['latlong'] ?? null,
//                     'nama_pemilik_outlet' => strtoupper($row['nama_pemilik_outlet'] ?? null),
//                     'nomer_tlp_outlet' => $row['nomer_tlp_outlet'] ?? null,
//                     'created_at' => isset($row['created_at']) && !empty($row['created_at']) ? Carbon::parse($row['created_at'])->format('Y-m-d H:i:s') : now(),
//                     'poto_shop_sign' => $row['poto_shop_sign'] ?? null,
//                     'poto_depan' => $row['poto_depan'] ?? null,
//                     'poto_kiri' => $row['poto_kiri'] ?? null,
//                     'poto_kanan' => $row['poto_kanan'] ?? null,
//                     'poto_ktp' => $row['poto_ktp'] ?? null,
//                     'video' => $row['video'] ?? null,
//                 ]);
//             }
//         } catch (\Exception $e) {
//             Log::error('Error importing row: ' . json_encode($row) . ' - ' . $e->getMessage());
//             Session::flash('error', 'Error importing row: ' . json_encode($row) . ' - ' . $e->getMessage());
//             return null;
//         }
//     }

//     /**
//      * Get Badan Usaha ID
//      */
//     private function getBadanUsahaId($name)
//     {
//         return BadanUsaha::where('name', preg_replace('/\s+/', '', $name))->firstOrFail()->id;
//     }

//     /**
//      * Get Division ID
//      */
//     private function getDivisionId($name, $badanusaha_id)
//     {
//         return Division::where('name', preg_replace('/\s+/', '', $name))
//             ->where('badanusaha_id', $badanusaha_id)
//             ->firstOrFail()->id;
//     }

//     /**
//      * Get Region ID or create Region if not found
//      */
//     private function getRegionId($name, $divisi_id, $badanusaha_id)
//     {
//         $region = Region::where('name', preg_replace('/\s+/', '', $name))
//             ->where('divisi_id', $divisi_id)
//             ->where('badanusaha_id', $badanusaha_id)
//             ->first();

//         if ($region) {
//             return $region->id;
//         } else {
//             // Create new region if not found
//             $newRegion = Region::create([
//                 'name' => strtoupper($name),
//                 'divisi_id' => $divisi_id,
//                 'badanusaha_id' => $badanusaha_id
//             ]);
//             Log::info("Region created: {$name}");
//             return $newRegion->id;
//         }
//     }

//     /**
//      * Get Cluster ID or create Cluster if not found
//      */
//     private function getClusterId($name, $badanusaha_id, $divisi_id, $region_id)
//     {
//         $cluster = Cluster::where('name', preg_replace('/\s+/', '', $name))
//             ->first();

//         if ($cluster) {
//             return $cluster->id;
//         } else {
//             // Create new cluster if not found
//             $newCluster = Cluster::create([
//                 'name' => strtoupper($name),
//                 'badanusaha_id' => $badanusaha_id,
//                 'divisi_id' => $divisi_id,
//                 'region_id' => $region_id
//             ]);
//             Log::info("Cluster created: {$name}");
//             return $newCluster->id;
//         }
//     }
// }
