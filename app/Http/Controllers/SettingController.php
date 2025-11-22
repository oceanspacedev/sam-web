<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseFormatter;
use App\Models\BadanUsaha;
use App\Models\Division;
use App\Models\Region;
use Exception;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function getdivisi(Request $request)
    {
        try {
            // Optional filter by business unit (accepts id or name)
            $divisions = Division::query()
                ->when($request->filled('bu'), function ($query) use ($request) {
                    $bu = $request->input('bu');
                    $badanUsaha = is_numeric($bu)
                        ? BadanUsaha::findOrFail($bu)
                        : BadanUsaha::where('name', $bu)->firstOrFail();

                    $query->where('badanusaha_id', $badanUsaha->id);
                })
                ->get(['id', 'name', 'badanusaha_id']);

            return ResponseFormatter::success($divisions, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error([], $e->getMessage());
        }
    }

    public function getregion(Request $request)
    {
        try {
            // Optional filter by division (accepts id or name, and optional business unit)
            $regions = Region::query()
                ->when($request->filled('div'), function ($query) use ($request) {
                    $div = $request->input('div');
                    $division = is_numeric($div)
                        ? Division::findOrFail($div)
                        : Division::where('name', $div)->firstOrFail();

                    $query->where('divisi_id', $division->id);

                    // Additional guard: if bu provided, ensure division belongs to it
                    if ($request->filled('bu')) {
                        $bu = $request->input('bu');
                        $badanUsaha = is_numeric($bu)
                            ? BadanUsaha::findOrFail($bu)
                            : BadanUsaha::where('name', $bu)->firstOrFail();

                        $query->where('badanusaha_id', $badanUsaha->id);
                    }
                })
                ->get(['id', 'name', 'badanusaha_id', 'divisi_id']);

            return ResponseFormatter::success($regions, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error([], $e->getMessage());
        }
    }
}
