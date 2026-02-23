# Design: Polymorphic Visit + Division Settings + Custom Register Fields

**Date:** 2026-02-22
**Status:** Approved

## Problem

1. Visit hanya bisa ke Outlet (sudah approved). LEAD dan NOO tidak bisa dikunjungi, padahal sales perlu visit ke LEAD untuk prospecting dan ke NOO untuk follow-up.
2. Setiap divisi punya aturan berbeda — tidak semua divisi mengizinkan visit ke LEAD/Register.
3. Setiap divisi bisa punya kebutuhan data tambahan yang berbeda saat registrasi LEAD/NOO.

## Solution Overview

3 fitur yang saling terkait:

1. **Division Settings** — tabel konfigurasi per divisi
2. **Polymorphic Visit** — visit bisa ke Outlet ATAU Register (LEAD/NOO)
3. **Custom Register Fields** — form builder per divisi untuk field tambahan LEAD/NOO

## 1. Division Settings

### Table: `division_settings`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Auto increment |
| division_id | FK -> divisions, unique | One settings per division |
| allow_register_visit | boolean, default false | Whether visits to LEAD/NOO are allowed |
| max_visit_per_day | integer, default 0 | Max visits per user per day (0 = unlimited) |
| default_register_radius | integer, default 100 | Default GPS radius (meters) for register visits |
| created_at, updated_at | timestamps | |

### Behavior

- Managed via Filament as a relation manager on Division resource
- Validated in API during check-in
- If `allow_register_visit` is false and user tries to visit a register, return 403
- If `max_visit_per_day` > 0, count user's visits today before allowing check-in

## 2. Polymorphic Visit

### Migration Changes

**visits table:**
- ADD `visitable_type` (string, not null after migration)
- ADD `visitable_id` (bigint unsigned, not null after migration)
- MIGRATE existing data: `outlet_id` -> `visitable_id`, `visitable_type` = `'App\\Models\\Outlet'`
- DROP `outlet_id` column
- ADD composite index on `(visitable_type, visitable_id)`

**plan_visits table:**
- Same changes as visits table

**Archive tables** (visits_archives, plan_visits_archives):
- Same column changes, update indexes

### Model Changes

```php
// Visit model
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
```

Same pattern for PlanVisit model.

### API Contract

**Check-in request** accepts either `outlet_id` OR `register_id`:

```json
// Visit to Outlet
{ "outlet_id": 123, "tipe_visit": "PLANNED", ... }

// Visit to Register (LEAD/NOO)
{ "register_id": 456, "tipe_visit": "EXTRACALL", ... }
```

Validation: exactly one of `outlet_id` or `register_id` must be provided.

**Check-in logic for register visit:**
1. Check division settings: `allow_register_visit` must be true for user's division
2. Check max visits: if `max_visit_per_day` > 0, count today's visits
3. Radius: use `division_settings.default_register_radius` (default 100m)
4. Same duplicate prevention: no same-day visit to same target

**tipe_visit values** remain `PLANNED` and `EXTRACALL` — they indicate whether the visit was scheduled, not the target type. The target type is determined by `visitable_type`.

### API Response (backward compatible)

```json
{
  "id": 1,
  "visitable_type": "outlet",
  "visitable_id": 123,
  "visitable": { "id": 123, "nama_outlet": "Toko ABC", ... },
  "outlet_id": 123,
  "register_id": null,
  "tipe_visit": "PLANNED",
  ...
}
```

`outlet_id` and `register_id` are computed from `visitable_type`/`visitable_id` for backward compatibility.

### Filament Changes

- VisitResource form: select field allows choosing Outlet OR Register
- PlanVisitResource form: same
- Table columns show target name with badge (Outlet/LEAD/NOO)
- Filters support filtering by visitable_type

## 3. Custom Register Fields

### Table: `division_register_fields` (field definitions)

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| division_id | FK -> divisions | |
| name | string | Internal field name (slug) |
| label | string | Display label |
| type | enum | text, number, select, checkbox, date, file |
| options | JSON, nullable | For select type: `["option1", "option2"]` |
| is_required | boolean, default false | Whether field is required |
| applies_to | enum | `lead`, `noo`, `both` |
| sort_order | integer, default 0 | Display order |
| created_at, updated_at, deleted_at | timestamps | Soft deletes |

### Table: `register_field_values` (field data)

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| register_id | FK -> registers | |
| field_id | FK -> division_register_fields | |
| value | text, nullable | Value stored as text (numbers, dates, etc.) |
| file_path | string, nullable | For file/photo type fields |
| created_at, updated_at | timestamps | |

Unique constraint on (register_id, field_id).

### API

**GET /divisions/{id}/register-fields** — returns field definitions for a division, filtered by `applies_to` (lead/noo).

**POST /registers/leads** and **POST /registers/noos** — accept additional `custom_fields` object:

```json
{
  "nama_outlet": "...",
  "custom_fields": {
    "field_slug_1": "value1",
    "field_slug_2": "value2"
  },
  "custom_files": {
    "field_slug_3": "<file>"
  }
}
```

### Filament

- Division resource: new relation manager "Custom Fields" to manage field definitions
- Register resource: show custom field values in view/edit pages
- Register create (Filament): dynamically render custom fields based on division

## Files Impacted

| Category | Files | Changes |
|----------|-------|---------|
| Migrations | 1 new | Create 3 new tables, modify visits/plan_visits |
| Models | 4 | Visit, PlanVisit (polymorphic), new DivisionSetting, DivisionRegisterField, RegisterFieldValue |
| API Controllers | 3 | VisitController, PlanVisitController, RegisterController |
| Request Validation | 3 | CheckinVisitRequest, StorePlanVisitRequest, DeletePlanVisitRequest |
| API Resources | 4 | VisitResource, VisitCompactResource, PlanVisitResource, PlanVisitCompactResource |
| Observers | 2 | VisitObserver, RegisterObserver |
| Filament Resources | 3 | VisitResource, PlanVisitResource, DivisionResource (relation managers) |
| Filament Register | 1 | RegisterResource (custom fields display) |
| Imports/Exports | 2 | PlanVisitImport, UnvisitedOutletsSheet |
| Seeders/Factories | 3 | VisitSeeder, PlanVisitSeeder, VisitFactory |
| Services | 0 | No changes needed |

## Decisions

- **Visit flow identical** for Outlet, LEAD, and NOO (check-in photo+GPS, check-out photo+report+GPS)
- **tipe_visit stays PLANNED/EXTRACALL** — indicates scheduling, not target type
- **No radius on register** — use `division_settings.default_register_radius` (default 100m)
- **Division settings as separate table** — not flags on divisions table
- **Custom fields normalized** — separate tables for definitions and values
- **Custom field types** — text, number, select, checkbox, date, file (full form builder)
