<?php

namespace App\Imports;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UserImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        try {
            // Check if user already exists
            $user = User::where('username', strtolower($row['username']))->first();

            if ($user) {
                // If user exists, update the user
                $this->updateUser($user, $row);
                Log::info("User updated: {$row['username']}");

                return null; // Skip creation, as we are updating
            } else {
                // If user does not exist, create a new user
                $user = $this->createUser($row);
                Log::info("User created: {$row['username']}");

                return $user;
            }
        } catch (\Exception $e) {
            // Log the error and skip the row
            Log::error("Error processing row for username {$row['username']}: ".$e->getMessage());

            return null; // Skip this row
        }
    }

    /**
     * Update existing user
     *
     * @param  User  $user
     * @param  array  $row
     */
    private function updateUser($user, $row)
    {
        // Ensure region exists, otherwise create it
        $badanusaha_id = $this->getBadanUsahaId($row['badan_usaha']);
        $divisi_id = $this->getDivisionId($row['divisi'], $badanusaha_id);

        $region_id = strtoupper($row['role']) === 'TM' ? null : $this->getRegionId($row['region'], $divisi_id, $badanusaha_id);
        if (! $region_id) {
            $region_id = Region::create([
                'name' => strtoupper($row['region']),
                'divisi_id' => $divisi_id,
                'badanusaha_id' => $badanusaha_id,
            ])->id;
            Log::info("Region created: {$row['region']}");
        }

        // Ensure cluster exists, otherwise create it
        $cluster_id = $this->getClusterId($row['cluster']);
        if (! $cluster_id) {
            $cluster_id = Cluster::create([
                'name' => strtoupper($row['cluster']),
                'badanusaha_id' => $badanusaha_id,
                'divisi_id' => $divisi_id,
                'region_id' => $region_id,
            ])->id;
            Log::info("Cluster created: {$row['cluster']}");
        }

        // Update user details
        $tm_id = $this->getTmId($row['tm']);

        $user->update([
            'nama_lengkap' => strtoupper($row['nama_lengkap']),
            'username' => strtolower($row['username']),
            'role_id' => $this->getRoleId($row['role']),
            'badanusaha_id' => $badanusaha_id,
            'divisi_id' => $divisi_id,
            'region_id' => $region_id,
            'cluster_id' => $cluster_id,
            'tm_id' => $tm_id,
        ]);
    }

    /**
     * Create a new user
     *
     * @param  array  $row
     * @return User
     */
    private function createUser($row)
    {
        // Ensure region exists, otherwise create it
        $badanusaha_id = $this->getBadanUsahaId($row['badan_usaha']);
        $divisi_id = $this->getDivisionId($row['divisi'], $badanusaha_id);

        $region_id = strtoupper($row['role']) === 'TM' ? null : $this->getRegionId($row['region'], $divisi_id, $badanusaha_id);
        if (! $region_id) {
            $region_id = Region::create([
                'name' => strtoupper($row['region']),
                'divisi_id' => $divisi_id,
                'badanusaha_id' => $badanusaha_id,
            ])->id;
            Log::info("Region created: {$row['region']}");
        }

        // Ensure cluster exists, otherwise create it
        $cluster_id = $this->getClusterId($row['cluster']);
        if (! $cluster_id) {
            $cluster_id = Cluster::create([
                'name' => strtoupper($row['cluster']),
                'badanusaha_id' => $badanusaha_id,
                'divisi_id' => $divisi_id,
                'region_id' => $region_id,
            ])->id;
            Log::info("Cluster created: {$row['cluster']}");
        }

        // Get the TM ID if available
        $tm_id = $this->getTmId($row['tm']);

        // Create and return a new user
        return new User([
            'nama_lengkap' => strtoupper($row['nama_lengkap']),
            'username' => strtolower($row['username']),
            'role_id' => $this->getRoleId($row['role']),
            'badanusaha_id' => $badanusaha_id,
            'divisi_id' => $divisi_id,
            'region_id' => $region_id,
            'cluster_id' => $cluster_id,
            'tm_id' => $tm_id,
            'password' => $row['password'] ? bcrypt($row['password']) : bcrypt('complete123'),
        ]);
    }

    private function getBadanUsahaId($name)
    {
        $badanusaha = BadanUsaha::where('name', preg_replace('/\s+/', '', $name))->first();

        return $badanusaha ? $badanusaha->id : null;
    }

    private function getDivisionId($name, $badanusaha_id)
    {
        $division = Division::where('name', preg_replace('/\s+/', '', $name))
            ->where('badanusaha_id', $badanusaha_id)
            ->first();

        return $division ? $division->id : null;
    }

    private function getRegionId($name, $divisi_id, $badanusaha_id)
    {
        $region = Region::where('name', preg_replace('/\s+/', '', $name))
            ->where('divisi_id', $divisi_id)
            ->where('badanusaha_id', $badanusaha_id)
            ->first();

        return $region ? $region->id : null;
    }

    private function getClusterId($name)
    {
        $cluster = Cluster::where('name', preg_replace('/\s+/', '', $name))->first();

        return $cluster ? $cluster->id : null;
    }

    private function getRoleId($name)
    {
        $role = Role::where('name', preg_replace('/\s+/', '', $name))->first();

        return $role ? $role->id : null;
    }

    private function getTmId($name)
    {
        $tm = User::where('nama_lengkap', $name)->first();

        return $tm ? $tm->id : null;
    }
}
