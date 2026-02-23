# Polymorphic Visit + Division Settings + Custom Register Fields — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable visits to LEAD/NOO registers (not just outlets), with per-division configuration and custom register fields.

**Architecture:** Polymorphic `visitable_type`/`visitable_id` replaces `outlet_id` on visits and plan_visits tables. Division settings table controls per-division rules. Normalized custom fields tables for dynamic form fields per division.

**Tech Stack:** Laravel 12, Filament 3, PHPUnit, MySQL, Sanctum API

**Design Doc:** `docs/plans/2026-02-22-polymorphic-visit-division-settings-design.md`

---

## Task 1: Division Settings — Migration & Model

**Files:**
- Create: `database/migrations/2026_02_22_000001_create_division_settings_table.php`
- Create: `app/Models/DivisionSetting.php`
- Modify: `app/Models/Division.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_division_settings_table
```

Migration content:

```php
public function up(): void
{
    Schema::create('division_settings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('division_id')->unique()->constrained('divisions')->cascadeOnDelete();
        $table->boolean('allow_register_visit')->default(false);
        $table->unsignedInteger('max_visit_per_day')->default(0);
        $table->unsignedInteger('default_register_radius')->default(100);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('division_settings');
}
```

**Step 2: Create DivisionSetting model**

Create `app/Models/DivisionSetting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DivisionSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'allow_register_visit' => 'boolean',
            'max_visit_per_day' => 'integer',
            'default_register_radius' => 'integer',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
```

**Step 3: Add relationship to Division model**

In `app/Models/Division.php`, add after line 57 (after `clusters()` method):

```php
public function setting(): HasOne
{
    return $this->hasOne(DivisionSetting::class);
}
```

Add `use Illuminate\Database\Eloquent\Relations\HasOne;` to imports.

**Step 4: Run migration**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/*division_settings* app/Models/DivisionSetting.php app/Models/Division.php
git commit -m "feat: add division_settings table and model"
```

---

## Task 2: Division Settings — Filament Relation Manager

**Files:**
- Create: `app/Filament/Resources/Divisions/RelationManagers/SettingRelationManager.php`
- Modify: `app/Filament/Resources/Divisions/DivisionResource.php`

**Step 1: Create relation manager**

Create `app/Filament/Resources/Divisions/RelationManagers/SettingRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\Divisions\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SettingRelationManager extends RelationManager
{
    protected static string $relationship = 'setting';

    protected static ?string $title = 'Pengaturan Divisi';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Toggle::make('allow_register_visit')
                ->label('Izinkan Visit ke LEAD/NOO')
                ->helperText('Jika aktif, sales bisa melakukan visit ke LEAD dan NOO selain ke outlet')
                ->default(false),

            Forms\Components\TextInput::make('max_visit_per_day')
                ->label('Maks Visit per Hari')
                ->helperText('0 = tidak dibatasi')
                ->numeric()
                ->default(0)
                ->minValue(0),

            Forms\Components\TextInput::make('default_register_radius')
                ->label('Radius Default Register (meter)')
                ->helperText('Radius GPS untuk validasi visit ke LEAD/NOO')
                ->numeric()
                ->default(100)
                ->minValue(0)
                ->suffix('meter'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('allow_register_visit')
                    ->label('Visit Register')
                    ->boolean(),
                Tables\Columns\TextColumn::make('max_visit_per_day')
                    ->label('Maks Visit/Hari')
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Tidak dibatasi' : $state),
                Tables\Columns\TextColumn::make('default_register_radius')
                    ->label('Radius Register')
                    ->suffix(' m'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => ! $this->getOwnerRecord()->setting()->exists()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }
}
```

**Step 2: Register relation manager in DivisionResource**

In `app/Filament/Resources/Divisions/DivisionResource.php`, add to the `getRelations()` method:

```php
public static function getRelations(): array
{
    return [
        RelationManagers\SettingRelationManager::class,
    ];
}
```

And add to pages (change ManageDivisions to ListDivisions + EditDivision if using simple resource, or add EditDivision page to support relation managers).

Check current pages config. If it uses `ManageDivisions` (simple resource), it needs to be changed to support relation managers — this requires a List + Edit page setup.

**Step 3: Commit**

```bash
git add app/Filament/Resources/Divisions/
git commit -m "feat: add division settings Filament relation manager"
```

---

## Task 3: Custom Register Fields — Migration & Models

**Files:**
- Create: `database/migrations/2026_02_22_000002_create_division_register_fields_table.php`
- Create: `database/migrations/2026_02_22_000003_create_register_field_values_table.php`
- Create: `app/Models/DivisionRegisterField.php`
- Create: `app/Models/RegisterFieldValue.php`
- Modify: `app/Models/Register.php`
- Modify: `app/Models/Division.php`

**Step 1: Create division_register_fields migration**

```php
public function up(): void
{
    Schema::create('division_register_fields', function (Blueprint $table) {
        $table->id();
        $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
        $table->string('name'); // slug
        $table->string('label');
        $table->string('type'); // text, number, select, checkbox, date, file
        $table->json('options')->nullable(); // for select: ["opt1","opt2"]
        $table->boolean('is_required')->default(false);
        $table->string('applies_to')->default('both'); // lead, noo, both
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
        $table->softDeletes();

        $table->index(['division_id', 'applies_to']);
    });
}
```

**Step 2: Create register_field_values migration**

```php
public function up(): void
{
    Schema::create('register_field_values', function (Blueprint $table) {
        $table->id();
        $table->foreignId('register_id')->constrained('registers')->cascadeOnDelete();
        $table->foreignId('field_id')->constrained('division_register_fields')->cascadeOnDelete();
        $table->text('value')->nullable();
        $table->string('file_path')->nullable();
        $table->timestamps();

        $table->unique(['register_id', 'field_id']);
    });
}
```

**Step 3: Create DivisionRegisterField model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DivisionRegisterField extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(RegisterFieldValue::class, 'field_id');
    }

    public function appliesToLead(): bool
    {
        return in_array($this->applies_to, ['lead', 'both']);
    }

    public function appliesToNoo(): bool
    {
        return in_array($this->applies_to, ['noo', 'both']);
    }
}
```

**Step 4: Create RegisterFieldValue model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisterFieldValue extends Model
{
    protected $guarded = ['id'];

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(DivisionRegisterField::class, 'field_id');
    }
}
```

**Step 5: Add relationships**

In `app/Models/Register.php`, add:

```php
public function fieldValues(): HasMany
{
    return $this->hasMany(RegisterFieldValue::class);
}
```

Add `use Illuminate\Database\Eloquent\Relations\HasMany;` to imports.

In `app/Models/Division.php`, add:

```php
public function registerFields(): HasMany
{
    return $this->hasMany(DivisionRegisterField::class);
}
```

**Step 6: Run migrations and commit**

```bash
php artisan migrate
git add database/migrations/*register_field* app/Models/DivisionRegisterField.php app/Models/RegisterFieldValue.php app/Models/Register.php app/Models/Division.php
git commit -m "feat: add custom register fields tables and models"
```

---

## Task 4: Polymorphic Visit — Migration

**Files:**
- Create: `database/migrations/2026_02_22_000004_convert_visits_to_polymorphic.php`

This is the most critical migration. It must:
1. Add new columns
2. Migrate existing data
3. Drop old column
4. Update indexes

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- visits table ---
        Schema::table('visits', function (Blueprint $table) {
            $table->string('visitable_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('visitable_id')->nullable()->after('visitable_type');
        });

        // Migrate existing data
        DB::table('visits')->whereNotNull('outlet_id')->update([
            'visitable_type' => 'App\\Models\\Outlet',
            'visitable_id' => DB::raw('outlet_id'),
        ]);

        // Make columns non-nullable, drop old column and indexes
        Schema::table('visits', function (Blueprint $table) {
            $table->string('visitable_type')->nullable(false)->change();
            $table->unsignedBigInteger('visitable_id')->nullable(false)->change();

            // Drop old index if exists
            try {
                $table->dropIndex('visits_outlet_date_idx');
            } catch (\Exception $e) {
                // Index may not exist
            }

            $table->dropColumn('outlet_id');

            // Add new indexes
            $table->index(['visitable_type', 'visitable_id'], 'visits_visitable_idx');
            $table->index(['visitable_type', 'visitable_id', 'tanggal_visit'], 'visits_visitable_date_idx');
        });

        // --- plan_visits table ---
        Schema::table('plan_visits', function (Blueprint $table) {
            $table->string('visitable_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('visitable_id')->nullable()->after('visitable_type');
        });

        DB::table('plan_visits')->whereNotNull('outlet_id')->update([
            'visitable_type' => 'App\\Models\\Outlet',
            'visitable_id' => DB::raw('outlet_id'),
        ]);

        Schema::table('plan_visits', function (Blueprint $table) {
            $table->string('visitable_type')->nullable(false)->change();
            $table->unsignedBigInteger('visitable_id')->nullable(false)->change();

            // Drop old indexes safely
            $indexesToDrop = [
                'plan_visits_outlet_date_idx',
                'plan_visits_outlet_id_index',
                'plan_visits_user_outlet_scope_realized_idx',
                'plan_visits_user_outlet_week_year_idx',
            ];
            foreach ($indexesToDrop as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Exception $e) {
                    // Index may not exist
                }
            }

            // Drop FK safely
            try {
                $table->dropForeign(['outlet_id']);
            } catch (\Exception $e) {
                // FK may not exist
            }

            $table->dropColumn('outlet_id');

            // New indexes
            $table->index(['visitable_type', 'visitable_id'], 'plan_visits_visitable_idx');
            $table->index(
                ['user_id', 'visitable_type', 'visitable_id', 'schedule_scope', 'realized_at'],
                'plan_visits_user_visitable_scope_realized_idx'
            );
        });

        // --- archive tables (if they exist) ---
        if (Schema::hasTable('visits_archives')) {
            Schema::table('visits_archives', function (Blueprint $table) {
                $table->string('visitable_type')->nullable()->after('user_id');
                $table->unsignedBigInteger('visitable_id')->nullable()->after('visitable_type');
            });

            DB::table('visits_archives')->whereNotNull('outlet_id')->update([
                'visitable_type' => 'App\\Models\\Outlet',
                'visitable_id' => DB::raw('outlet_id'),
            ]);

            Schema::table('visits_archives', function (Blueprint $table) {
                try { $table->dropIndex('visits_archives_outlet_date_idx'); } catch (\Exception $e) {}
                $table->dropColumn('outlet_id');
                $table->index(['visitable_type', 'visitable_id', 'tanggal_visit'], 'visits_archives_visitable_date_idx');
            });
        }

        if (Schema::hasTable('plan_visits_archives')) {
            Schema::table('plan_visits_archives', function (Blueprint $table) {
                $table->string('visitable_type')->nullable()->after('user_id');
                $table->unsignedBigInteger('visitable_id')->nullable()->after('visitable_type');
            });

            DB::table('plan_visits_archives')->whereNotNull('outlet_id')->update([
                'visitable_type' => 'App\\Models\\Outlet',
                'visitable_id' => DB::raw('outlet_id'),
            ]);

            Schema::table('plan_visits_archives', function (Blueprint $table) {
                try { $table->dropIndex('plan_visits_archives_outlet_date_idx'); } catch (\Exception $e) {}
                try { $table->dropIndex('plan_visits_archives_user_outlet_scope_realized_idx'); } catch (\Exception $e) {}
                try { $table->dropIndex('plan_visits_archives_user_outlet_week_year_idx'); } catch (\Exception $e) {}
                $table->dropColumn('outlet_id');
                $table->index(['visitable_type', 'visitable_id', 'tanggal_visit'], 'plan_visits_archives_visitable_date_idx');
            });
        }
    }

    public function down(): void
    {
        // Reverse: add outlet_id back, copy data, drop visitable columns
        foreach (['visits', 'plan_visits', 'visits_archives', 'plan_visits_archives'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'visitable_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('outlet_id')->nullable()->after('user_id');
            });

            DB::table($tableName)
                ->where('visitable_type', 'App\\Models\\Outlet')
                ->update(['outlet_id' => DB::raw('visitable_id')]);

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['visitable_type', 'visitable_id']);
            });
        }
    }
};
```

**Step 2: Run migration on a backup/test database first**

```bash
php artisan migrate
```

**Step 3: Commit**

```bash
git add database/migrations/*convert_visits_to_polymorphic*
git commit -m "feat: convert visits/plan_visits to polymorphic visitable"
```

---

## Task 5: Polymorphic Visit — Model Updates

**Files:**
- Modify: `app/Models/Visit.php`
- Modify: `app/Models/PlanVisit.php`
- Modify: `app/Models/Outlet.php`

**Step 1: Update Visit model**

Replace `app/Models/Visit.php` entirely:

```php
<?php

namespace App\Models;

use App\Traits\CleansUpMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use CleansUpMedia;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected array $mediaCleanupFields = [
        'picture_visit_in',
        'picture_visit_out',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_visit' => 'date',
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function visitable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function isOutletVisit(): bool
    {
        return $this->visitable_type === Outlet::class;
    }

    public function isRegisterVisit(): bool
    {
        return $this->visitable_type === Register::class;
    }

    /**
     * Backward-compat accessor: returns outlet_id if visitable is Outlet, null otherwise.
     */
    public function getOutletIdAttribute(): ?int
    {
        return $this->isOutletVisit() ? $this->visitable_id : null;
    }

    /**
     * Backward-compat accessor: returns register_id if visitable is Register, null otherwise.
     */
    public function getRegisterIdAttribute(): ?int
    {
        return $this->isRegisterVisit() ? $this->visitable_id : null;
    }

    /**
     * Backward-compat: load outlet relation via visitable when it's an Outlet.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'visitable_id')
            ->where($this->getTable().'.visitable_type', Outlet::class)
            ->withTrashed();
    }
}
```

**Step 2: Update PlanVisit model**

In `app/Models/PlanVisit.php`, replace the `outlet()` method and add `visitable()`:

```php
// Add to imports:
use Illuminate\Database\Eloquent\Relations\MorphTo;

// Replace outlet() with:
public function visitable(): MorphTo
{
    return $this->morphTo()->withTrashed();
}

public function isOutletVisit(): bool
{
    return $this->visitable_type === Outlet::class;
}

public function isRegisterVisit(): bool
{
    return $this->visitable_type === Register::class;
}

// Keep backward-compat outlet() accessor:
public function outlet(): BelongsTo
{
    return $this->belongsTo(Outlet::class, 'visitable_id')
        ->where($this->getTable().'.visitable_type', Outlet::class)
        ->withTrashed();
}
```

**Step 3: Update Outlet model**

In `app/Models/Outlet.php`, update the `planvisit()` and `visit()` relationships:

```php
public function planvisit(): \Illuminate\Database\Eloquent\Relations\MorphMany
{
    return $this->morphMany(PlanVisit::class, 'visitable');
}

public function visit(): \Illuminate\Database\Eloquent\Relations\MorphMany
{
    return $this->morphMany(Visit::class, 'visitable');
}
```

Add `use Illuminate\Database\Eloquent\Relations\MorphMany;` if needed.

**Step 4: Add visit/planvisit morphMany to Register model**

In `app/Models/Register.php`, add:

```php
public function visits(): \Illuminate\Database\Eloquent\Relations\MorphMany
{
    return $this->morphMany(Visit::class, 'visitable');
}

public function planVisits(): \Illuminate\Database\Eloquent\Relations\MorphMany
{
    return $this->morphMany(PlanVisit::class, 'visitable');
}
```

**Step 5: Commit**

```bash
git add app/Models/Visit.php app/Models/PlanVisit.php app/Models/Outlet.php app/Models/Register.php
git commit -m "feat: update models for polymorphic visitable relationship"
```

---

## Task 6: Update VisitObserver for Polymorphic

**Files:**
- Modify: `app/Observers/VisitObserver.php`

**Step 1: Update `markRelatedPlanVisit`**

Replace the method to use `visitable_type` and `visitable_id` instead of `outlet_id`:

In `app/Observers/VisitObserver.php`, change line 38 and 47:

```php
protected function markRelatedPlanVisit(Visit $visit): void
{
    // Only match PlanVisit for outlet visits (registers don't have plan visits yet)
    if (! $visit->user_id || ! $visit->visitable_id || ! $visit->tanggal_visit) {
        return;
    }

    $visitDate = Carbon::parse($visit->tanggal_visit)->startOfDay();

    /** @var PlanVisit|null $plan */
    $plan = PlanVisit::query()
        ->where('user_id', $visit->user_id)
        ->where('visitable_type', $visit->visitable_type)
        ->where('visitable_id', $visit->visitable_id)
        ->unrealized()
        ->where(function (Builder $query) use ($visitDate): void {
            $query->where(function (Builder $subQuery) use ($visitDate): void {
                $subQuery
                    ->where('schedule_scope', 'weekly')
                    ->whereDate('period_start', '<=', $visitDate->toDateString())
                    ->whereDate('period_end', '>=', $visitDate->toDateString());
            })
                ->orWhere(function (Builder $subQuery) use ($visitDate): void {
                    $subQuery
                        ->where('schedule_scope', 'daily')
                        ->whereDate('period_start', $visitDate->toDateString());
                });
        })
        ->orderByDesc('schedule_scope')
        ->orderBy('period_start')
        ->first();

    if (! $plan) {
        return;
    }

    $realizedAt = $visit->check_out_time
        ? Carbon::parse($visit->check_out_time)
        : ($visit->check_in_time ? Carbon::parse($visit->check_in_time) : now());

    $plan->markAsRealized($visit, $realizedAt);
}
```

**Step 2: Commit**

```bash
git add app/Observers/VisitObserver.php
git commit -m "feat: update VisitObserver for polymorphic visitable"
```

---

## Task 7: Update API Request Validation

**Files:**
- Modify: `app/Http/Requests/API/CheckinVisitRequest.php`
- Modify: `app/Http/Requests/API/StorePlanVisitRequest.php`
- Modify: `app/Http/Requests/API/DeletePlanVisitRequest.php`

**Step 1: Update CheckinVisitRequest**

Replace `app/Http/Requests/API/CheckinVisitRequest.php`:

```php
<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class CheckinVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id', 'required_without:register_id', 'prohibited_if:register_id,*'],
            'register_id' => ['nullable', 'integer', 'exists:registers,id', 'required_without:outlet_id', 'prohibited_if:outlet_id,*'],
            'picture_visit' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'latlong_in' => ['required', 'string', 'regex:/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/'],
            'tipe_visit' => ['required', 'string', 'in:PLANNED,EXTRACALL'],
        ];
    }

    public function messages(): array
    {
        return [
            'outlet_id.required_without' => 'Outlet atau Register wajib dipilih',
            'outlet_id.exists' => 'Outlet tidak ditemukan',
            'register_id.required_without' => 'Outlet atau Register wajib dipilih',
            'register_id.exists' => 'Register tidak ditemukan',
            'picture_visit.required' => 'Foto check-in wajib diupload',
            'picture_visit.image' => 'File harus berupa gambar',
            'picture_visit.mimes' => 'Format gambar harus jpg, jpeg, atau png',
            'picture_visit.max' => 'Ukuran gambar maksimal 3MB',
            'latlong_in.required' => 'Lokasi check-in wajib diisi',
            'latlong_in.regex' => 'Format lokasi tidak valid (gunakan format: latitude,longitude)',
            'tipe_visit.required' => 'Tipe visit wajib dipilih',
            'tipe_visit.in' => 'Tipe visit tidak valid',
        ];
    }
}
```

**Step 2: Update StorePlanVisitRequest**

Replace `outlet_id` rule with either/or logic (same pattern).

**Step 3: Update DeletePlanVisitRequest**

Same pattern — accept `outlet_id` OR `register_id`.

**Step 4: Commit**

```bash
git add app/Http/Requests/API/
git commit -m "feat: update request validation for polymorphic visit targets"
```

---

## Task 8: Update VisitController for Polymorphic Check-in/Checkout

**Files:**
- Modify: `app/Http/Controllers/API/VisitController.php`

**Step 1: Update checkin method**

Key changes in `checkin()`:
- Resolve target (Outlet or Register) from request
- Check division settings for register visits
- Check max_visit_per_day
- Use `visitable_type`/`visitable_id` instead of `outlet_id`

Replace the checkin method. Core logic changes:

```php
// After validating no active visit...

// Resolve visitable target
if ($request->filled('outlet_id')) {
    $target = Outlet::visibleTo($user)->where('id', $request->outlet_id)->first();
    if (! $target) {
        throw new ResourceNotFoundException('Outlet tidak ditemukan');
    }
    $visitableType = Outlet::class;
} else {
    $target = Register::visibleTo($user)->where('id', $request->register_id)->first();
    if (! $target) {
        throw new ResourceNotFoundException('Register tidak ditemukan');
    }
    $visitableType = Register::class;

    // Check division settings
    $divisionSetting = DivisionSetting::where('division_id', $target->divisi_id)->first();
    if (! $divisionSetting || ! $divisionSetting->allow_register_visit) {
        throw new BadRequestException('Divisi ini tidak mengizinkan visit ke LEAD/NOO');
    }
}

// Check max visit per day (for both outlet and register)
$this->enforceMaxVisitPerDay($user, $target);

// Check duplicate visit
$existingVisit = Visit::where('user_id', $user->id)
    ->where('visitable_type', $visitableType)
    ->where('visitable_id', $target->id)
    ->whereDate('tanggal_visit', today())
    ->first();

if ($existingVisit) {
    $name = $target->nama_outlet ?? $target->kode_outlet ?? 'target';
    throw (new BadRequestException(
        "Anda sudah pernah visit ke {$name} hari ini"
    ))->withData(['existing_visit_id' => $existingVisit->id]);
}

// Create visit with polymorphic fields
$visit = Visit::create([
    'tanggal_visit' => today(),
    'user_id' => $user->id,
    'visitable_type' => $visitableType,
    'visitable_id' => $target->id,
    'tipe_visit' => $request->tipe_visit,
    'latlong_in' => $request->latlong_in,
    'check_in_time' => now(),
    'picture_visit_in' => $temporaryPath,
]);
```

Add helper method:

```php
private function enforceMaxVisitPerDay(User $user, Outlet|Register $target): void
{
    $divisionId = $target instanceof Outlet ? $target->divisi_id : $target->divisi_id;
    $setting = DivisionSetting::where('division_id', $divisionId)->first();

    if (! $setting || $setting->max_visit_per_day === 0) {
        return;
    }

    $todayCount = Visit::where('user_id', $user->id)
        ->whereDate('tanggal_visit', today())
        ->count();

    if ($todayCount >= $setting->max_visit_per_day) {
        throw new BadRequestException(
            "Anda sudah mencapai batas maksimal {$setting->max_visit_per_day} visit per hari"
        );
    }
}
```

**Step 2: Update monitor and fetch methods**

Change `outlet_id` select columns to `visitable_type`, `visitable_id`. Update eager loading:

```php
// In monitor/fetch select columns, replace 'outlet_id' with:
'visitable_type',
'visitable_id',

// In relations, replace 'outlet:id,kode_outlet,nama_outlet' with:
'visitable',
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/API/VisitController.php
git commit -m "feat: update VisitController for polymorphic visit targets"
```

---

## Task 9: Update PlanVisitController for Polymorphic

**Files:**
- Modify: `app/Http/Controllers/API/PlanVisitController.php`

**Step 1: Update fetch, store, delete methods**

Same pattern as VisitController:
- Replace `outlet_id` with `visitable_type`/`visitable_id`
- Resolve target from `outlet_id` or `register_id` in request
- Update queries: `->where('outlet_id', ...)` → `->where('visitable_type', ...)->where('visitable_id', ...)`
- Update select columns

**Step 2: Commit**

```bash
git add app/Http/Controllers/API/PlanVisitController.php
git commit -m "feat: update PlanVisitController for polymorphic visit targets"
```

---

## Task 10: Update API Resources for Polymorphic

**Files:**
- Modify: `app/Http/Resources/Visit/VisitResource.php`
- Modify: `app/Http/Resources/Visit/VisitCompactResource.php`
- Modify: `app/Http/Resources/PlanVisit/PlanVisitResource.php`
- Modify: `app/Http/Resources/PlanVisit/PlanVisitCompactResource.php`

**Step 1: Update VisitResource**

Replace outlet_id line and outlet relation with:

```php
'visitable_type' => $this->visitable_type === \App\Models\Outlet::class ? 'outlet' : 'register',
'visitable_id' => $this->visitable_id,
// Backward compat
'outlet_id' => $this->outlet_id,
'register_id' => $this->register_id,

// Relationships
'visitable' => $this->whenLoaded('visitable', function () {
    if ($this->isOutletVisit()) {
        return $this->visitable ? new \App\Http\Resources\Outlet\OutletResource($this->visitable) : null;
    }
    return $this->visitable ? new \App\Http\Resources\Register\RegisterResource($this->visitable) : null;
}),
// Backward compat
'outlet' => $this->whenLoaded('visitable', function () {
    return $this->isOutletVisit() && $this->visitable
        ? new \App\Http\Resources\Outlet\OutletResource($this->visitable)
        : null;
}),
```

**Step 2: Same pattern for VisitCompactResource, PlanVisitResource, PlanVisitCompactResource**

**Step 3: Commit**

```bash
git add app/Http/Resources/
git commit -m "feat: update API resources for polymorphic visit with backward compat"
```

---

## Task 11: Update Filament Visit & PlanVisit Resources

**Files:**
- Modify: `app/Filament/Resources/Visits/VisitResource.php`
- Modify: `app/Filament/Resources/PlanVisits/PlanVisitResource.php`

**Step 1: Update VisitResource form**

Replace `Select::make('outlet_id')` with a target type selector + polymorphic select:

- Add `Select::make('visitable_type')` with options Outlet/Register
- Replace `Select::make('outlet_id')` with `Select::make('visitable_id')` that dynamically loads Outlet or Register based on `visitable_type`

**Step 2: Update table columns**

Replace outlet column with visitable column showing name + badge type.

**Step 3: Same for PlanVisitResource**

**Step 4: Commit**

```bash
git add app/Filament/Resources/Visits/ app/Filament/Resources/PlanVisits/
git commit -m "feat: update Filament visit resources for polymorphic target selection"
```

---

## Task 12: Custom Register Fields — Filament Relation Manager

**Files:**
- Create: `app/Filament/Resources/Divisions/RelationManagers/RegisterFieldsRelationManager.php`
- Modify: `app/Filament/Resources/Divisions/DivisionResource.php`

**Step 1: Create RegisterFieldsRelationManager**

Form with: name (auto-slug), label, type (select), options (repeater for select type), is_required, applies_to, sort_order.

Table showing all fields with type badge and applies_to badge.

**Step 2: Register in DivisionResource**

Add to `getRelations()` array.

**Step 3: Commit**

```bash
git add app/Filament/Resources/Divisions/
git commit -m "feat: add custom register fields relation manager to Division"
```

---

## Task 13: Custom Register Fields — API Endpoints

**Files:**
- Modify: `app/Http/Controllers/API/RegisterController.php`
- Modify: `routes/api.php`
- Create: `app/Http/Resources/DivisionRegisterFieldResource.php`

**Step 1: Add GET /divisions/{id}/register-fields endpoint**

Returns field definitions for a division, filtered by `applies_to` query param.

**Step 2: Update submitLead and submitNoo**

After creating the register, process `custom_fields` and `custom_files` from the request:

```php
// After $register = Register::create($data);
if ($request->has('custom_fields')) {
    $fields = DivisionRegisterField::where('division_id', $hierarchy['divisi_id'])
        ->whereIn('name', array_keys($request->custom_fields))
        ->get()
        ->keyBy('name');

    foreach ($request->custom_fields as $name => $value) {
        if ($field = $fields->get($name)) {
            RegisterFieldValue::create([
                'register_id' => $register->id,
                'field_id' => $field->id,
                'value' => $value,
            ]);
        }
    }
}

// Handle custom file uploads
if ($request->has('custom_files')) {
    // Similar logic with file upload service
}
```

**Step 3: Add route**

In `routes/api.php`:

```php
Route::get('/divisions/{id}/register-fields', [RegisterController::class, 'getRegisterFields']);
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/API/RegisterController.php routes/api.php app/Http/Resources/DivisionRegisterFieldResource.php
git commit -m "feat: add custom register fields API endpoints"
```

---

## Task 14: Custom Register Fields — Display in Filament Register Resource

**Files:**
- Modify: `app/Filament/Resources/Registers/RegisterResource.php`

**Step 1: Add custom fields section to form**

After the existing form sections, add a dynamic section that renders custom fields based on the selected division:

```php
Forms\Components\Section::make('Field Tambahan')
    ->schema(function (callable $get) {
        $divisionId = $get('divisi_id');
        if (! $divisionId) return [];

        $fields = DivisionRegisterField::where('division_id', $divisionId)
            ->orderBy('sort_order')
            ->get();

        return $fields->map(fn ($field) => match ($field->type) {
            'text' => Forms\Components\TextInput::make("custom_fields.{$field->name}")->label($field->label)->required($field->is_required),
            'number' => Forms\Components\TextInput::make("custom_fields.{$field->name}")->label($field->label)->numeric()->required($field->is_required),
            'select' => Forms\Components\Select::make("custom_fields.{$field->name}")->label($field->label)->options(array_combine($field->options, $field->options))->required($field->is_required),
            'checkbox' => Forms\Components\Checkbox::make("custom_fields.{$field->name}")->label($field->label),
            'date' => Forms\Components\DatePicker::make("custom_fields.{$field->name}")->label($field->label)->required($field->is_required),
            'file' => Forms\Components\FileUpload::make("custom_files.{$field->name}")->label($field->label)->required($field->is_required),
            default => Forms\Components\TextInput::make("custom_fields.{$field->name}")->label($field->label),
        })->toArray();
    })
    ->visible(fn (callable $get) => filled($get('divisi_id')))
    ->columns(2),
```

**Step 2: Add custom field values to infolist (view page)**

**Step 3: Commit**

```bash
git add app/Filament/Resources/Registers/
git commit -m "feat: display custom register fields in Filament register resource"
```

---

## Task 15: Update Imports & Exports

**Files:**
- Modify: `app/Imports/PlanVisitImport.php`
- Modify: `app/Exports/Visit/UnvisitedOutletsSheet.php`

**Step 1: Update PlanVisitImport**

Replace all `outlet_id` references with `visitable_type`/`visitable_id`:

```php
// Line ~106: where('outlet_id', $outlet->id) →
->where('visitable_type', Outlet::class)
->where('visitable_id', $outlet->id)

// Line ~115/125: 'outlet_id' => $outlet->id →
'visitable_type' => Outlet::class,
'visitable_id' => $outlet->id,
```

**Step 2: Update UnvisitedOutletsSheet**

```php
// Line ~26: ->pluck('outlet_id') →
->where('visitable_type', Outlet::class)
->pluck('visitable_id')
```

**Step 3: Commit**

```bash
git add app/Imports/PlanVisitImport.php app/Exports/Visit/UnvisitedOutletsSheet.php
git commit -m "feat: update imports/exports for polymorphic visit"
```

---

## Task 16: Update Seeders & Factory

**Files:**
- Modify: `database/factories/VisitFactory.php`
- Modify: `database/seeders/VisitSeeder.php`
- Modify: `database/seeders/PlanVisitSeeder.php`

**Step 1: Update VisitFactory**

```php
'visitable_type' => Outlet::class,
'visitable_id' => Outlet::factory(),
```

Remove `outlet_id`.

**Step 2: Update VisitSeeder and PlanVisitSeeder**

Replace `outlet_id` with `visitable_type` + `visitable_id`.

**Step 3: Commit**

```bash
git add database/factories/ database/seeders/
git commit -m "feat: update seeders and factory for polymorphic visit"
```

---

## Task 17: Final Verification

**Step 1: Run full test suite**

```bash
php artisan test
```

Fix any failures.

**Step 2: Run Pint (code style)**

```bash
vendor/bin/pint
```

**Step 3: Verify migration rollback works**

```bash
php artisan migrate:rollback --step=4
php artisan migrate
```

**Step 4: Smoke test API endpoints**

```bash
# Check routes are registered
php artisan route:list --path=visit
php artisan route:list --path=planvisit
php artisan route:list --path=divisions
```

**Step 5: Final commit**

```bash
git add .
git commit -m "chore: code style fixes and final verification"
```
