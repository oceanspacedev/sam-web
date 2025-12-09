<?php

namespace App\Observers;

use App\Models\Outlet;
use App\Models\Register;
use Illuminate\Support\Facades\Log;

class OutletObserver
{
    /**
     * Handle the Outlet "created" event.
     */
    public function created(Outlet $outlet): void
    {
        // Tidak usah sync, hanya update kode_outlet ke register yang approved
        $this->syncKodeOutletToRegisters($outlet);
        Log::channel('outlet')->info('Outlet created - syncing kode_outlet to registers', [
            'outlet_id' => $outlet->id,
            'kode_outlet' => $outlet->kode_outlet,
            'register_id' => $outlet->register_id,
        ]);
    }

    /**
     * Handle the Outlet "updated" event.
     */
    public function updated(Outlet $outlet): void
    {
        // Check if kode_outlet was changed
        if ($outlet->wasChanged('kode_outlet')) {
            $this->syncKodeOutletToRegisters($outlet);
            Log::channel('outlet')->info('Outlet kode_outlet updated', [
                'outlet_id' => $outlet->id,
                'old_kode_outlet' => $outlet->getOriginal('kode_outlet'),
                'new_kode_outlet' => $outlet->kode_outlet,
                'register_id' => $outlet->register_id,
            ]);
        }
    }

    /**
     * Handle the Outlet "deleted" event.
     */
    public function deleted(Outlet $outlet): void
    {
        // No action needed for soft delete
    }

    /**
     * Handle the Outlet "restored" event.
     */
    public function restored(Outlet $outlet): void
    {
        // Sync kode_outlet to related registers when restored
        $this->syncKodeOutletToRegisters($outlet);
    }

    /**
     * Handle the Outlet "force deleted" event.
     */
    public function forceDeleted(Outlet $outlet): void
    {
        // No action needed for force delete
    }

    /**
     * Sync kode_outlet to related registers.
     */
    private function syncKodeOutletToRegisters(Outlet $outlet): void
    {
        try {
            // Update registers that are related to this outlet
            // Prioritas 1: Register dengan register_id = outlet.id (sudah approved)
            // Prioritas 2: Register dengan kode_outlet lama

            $updated = Register::where(function ($query) use ($outlet) {
                // 1. Register dengan ID yang sama dengan outlet.register_id
                if ($outlet->register_id) {
                    $query->where('id', $outlet->register_id)
                        ->where('status', 'APPROVED');
                }
            })
                ->orWhere(function ($query) use ($outlet) {
                    // 2. Register dengan kode_outlet lama yang sama
                    if ($outlet->getOriginal('kode_outlet')) {
                        $query->where('kode_outlet', $outlet->getOriginal('kode_outlet'))
                            ->where('status', 'APPROVED');
                    }
                })
                ->whereNull('deleted_at') // Only active registers
                ->update(['kode_outlet' => $outlet->kode_outlet]);

            if ($updated > 0) {
                Log::channel('outlet')->info('Synced outlet kode_outlet to registers', [
                    'outlet_id' => $outlet->id,
                    'kode_outlet' => $outlet->kode_outlet,
                    'updated_registers' => $updated,
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('outlet')->error('Error syncing kode_outlet to registers', [
                'outlet_id' => $outlet->id,
                'kode_outlet' => $outlet->kode_outlet,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
