<?php

namespace App\Models\Onboarding;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingStepProgress;

/**
 * The app uses immutable dates (see AppServiceProvider), but the onboarding
 * plugin's StepState::completedAt() is typed to return a mutable Carbon.
 * Returning a mutable date for this one column satisfies that contract.
 */
class StepProgress extends OnboardingStepProgress
{
    /**
     * @return Attribute<Carbon|null, never>
     */
    protected function completedAt(): Attribute
    {
        return Attribute::get(
            fn (mixed $value): ?Carbon => $value === null ? null : Carbon::parse($value),
        );
    }
}
