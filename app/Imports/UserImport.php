<?php

namespace App\Imports;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationalName;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UserImport implements ToModel, WithHeadingRow
{
    /**
     * @return Model|null
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
        } catch (Exception $e) {
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
                'code' => OrganizationalName::formatCode($row['region']),
                'name' => trim((string) $row['region']),
                'divisi_id' => $divisi_id,
                'badanusaha_id' => $badanusaha_id,
            ])->id;
            Log::info("Region created: {$row['region']}");
        }

        // Ensure cluster exists, otherwise create it
        $cluster_id = $this->getClusterId($row['cluster'], $badanusaha_id, $divisi_id, $region_id);
        if (! $cluster_id) {
            $cluster_id = Cluster::create([
                'code' => OrganizationalName::formatCode($row['cluster']),
                'name' => trim((string) $row['cluster']),
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
            'tm_id' => $tm_id,
        ]);

        // Sync organizational data
        if ($badanusaha_id) {
            $user->badanUsahas()->sync([$badanusaha_id]);
        }
        if ($divisi_id) {
            $user->divisis()->sync([$divisi_id]);
        }
        if ($region_id) {
            $user->regions()->sync([$region_id]);
        }
        if ($cluster_id) {
            $user->clusters()->sync([$cluster_id]);
        }
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
                'code' => OrganizationalName::formatCode($row['region']),
                'name' => trim((string) $row['region']),
                'divisi_id' => $divisi_id,
                'badanusaha_id' => $badanusaha_id,
            ])->id;
            Log::info("Region created: {$row['region']}");
        }

        // Ensure cluster exists, otherwise create it
        $cluster_id = $this->getClusterId($row['cluster'], $badanusaha_id, $divisi_id, $region_id);
        if (! $cluster_id) {
            $cluster_id = Cluster::create([
                'code' => OrganizationalName::formatCode($row['cluster']),
                'name' => trim((string) $row['cluster']),
                'badanusaha_id' => $badanusaha_id,
                'divisi_id' => $divisi_id,
                'region_id' => $region_id,
            ])->id;
            Log::info("Cluster created: {$row['cluster']}");
        }

        // Get the TM ID if available
        $tm_id = $this->getTmId($row['tm']);

        // Create a new user
        $user = User::create([
            'nama_lengkap' => strtoupper($row['nama_lengkap']),
            'username' => strtolower($row['username']),
            'role_id' => $this->getRoleId($row['role']),
            'tm_id' => $tm_id,
            'password' => $row['password'] ? bcrypt($row['password']) : bcrypt('complete123'),
        ]);

        // Sync organizational data
        if ($badanusaha_id) {
            $user->badanUsahas()->sync([$badanusaha_id]);
        }
        if ($divisi_id) {
            $user->divisis()->sync([$divisi_id]);
        }
        if ($region_id) {
            $user->regions()->sync([$region_id]);
        }
        if ($cluster_id) {
            $user->clusters()->sync([$cluster_id]);
        }

        return $user;
    }

    private function getBadanUsahaId($name)
    {
        return $this->findOrganizationalId(BadanUsaha::class, $name);
    }

    private function getDivisionId($name, $badanusaha_id)
    {
        return $this->findOrganizationalId(Division::class, $name, [
            'badanusaha_id' => $badanusaha_id,
        ]);
    }

    private function getRegionId($name, $divisi_id, $badanusaha_id)
    {
        return $this->findOrganizationalId(Region::class, $name, [
            'divisi_id' => $divisi_id,
            'badanusaha_id' => $badanusaha_id,
        ]);
    }

    private function getClusterId($name, $badanusaha_id, $divisi_id, $region_id)
    {
        return $this->findOrganizationalId(Cluster::class, $name, [
            'badanusaha_id' => $badanusaha_id,
            'divisi_id' => $divisi_id,
            'region_id' => $region_id,
        ]);
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

    private function findOrganizationalId(string $modelClass, mixed $name, array $scope = []): ?int
    {
        $normalized = OrganizationalName::normalizeLookup((string) $name);
        $query = $modelClass::query();

        foreach ($scope as $column => $value) {
            $query->where($column, $value);
        }

        $record = $query
            ->select(['id', 'code', 'name'])
            ->get()
            ->first(function ($item) use ($normalized): bool {
                return OrganizationalName::normalizeLookup((string) $item->code) === $normalized
                    || OrganizationalName::normalizeLookup((string) $item->name) === $normalized;
            });

        return $record?->id;
    }
}
