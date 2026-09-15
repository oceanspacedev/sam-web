<?php

it('caches views without missing filament panel chrome components', function () {
    expect(view()->exists('filament-panels::components.sidebar.index'))->toBeTrue()
        ->and(view()->exists('filament-panels::components.topbar.index'))->toBeTrue();

    $this->artisan('view:cache')
        ->assertSuccessful()
        ->doesntExpectOutputToContain('Unable to locate a class or view for component [filament-panels::sidebar]')
        ->doesntExpectOutputToContain('Unable to locate a class or view for component [filament-panels::topbar]');
});
