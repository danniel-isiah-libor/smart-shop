<?php

use App\Models\User;
use Database\Seeders\OnboardingSeeder;
use Wallacemartinss\FilamentOnboarding\Facades\Onboarding;

beforeEach(function () {
    $this->seed(OnboardingSeeder::class);
    $this->user = User::factory()->superAdmin()->create();
    $this->actingAs($this->user);
});

it('renders the onboarding progress page', function () {
    $this->get(route('filament.admin.pages.onboarding'))->assertOk();
});

it('renders the progress page once a step is completed', function () {
    Onboarding::for($this->user)->complete('welcome');

    $this->get(route('filament.admin.pages.onboarding'))
        ->assertOk()
        ->assertSee('Welcome to the shop admin');
});
