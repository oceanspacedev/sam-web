{{--
    Filament v4 moved sidebar chrome to Livewire (no Blade component).
    Locked mekaya-theme still emits <x-filament-panels::sidebar> inside a
    compile-time @else branch, and `view:cache` resolves that tag even when
    the branch is dead.
--}}
@props([
    'navigation' => [],
])
