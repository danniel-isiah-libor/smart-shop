<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);
});

it('lists, searches and filters users by role', function () {
    $staff = Role::create(['name' => 'staff']);
    $withRole = User::factory()->create()->assignRole($staff);
    $withoutRole = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$withRole, $withoutRole])
        ->searchTable($withRole->email)
        ->assertCanSeeTableRecords([$withRole])
        ->assertCanNotSeeTableRecords([$withoutRole])
        ->searchTable('')
        ->filterTable('roles', [$staff->id])
        ->assertCanSeeTableRecords([$withRole])
        ->assertCanNotSeeTableRecords([$withoutRole]);
});

it('pre-fills the default password when creating a user', function () {
    config(['shop.default_user_password' => 'welcome-123']);

    Livewire::test(CreateUser::class)
        ->assertFormSet(['password' => 'welcome-123']);
});

it('creates a user with the default credentials and assigns roles', function () {
    config(['shop.default_user_password' => 'welcome-123']);
    $role = Role::create(['name' => 'staff']);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'roles' => [$role->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::firstWhere('email', 'jane@example.com');

    expect($user)->not->toBeNull()
        ->and(Hash::check('welcome-123', $user->password))->toBeTrue()
        ->and($user->hasRole('staff'))->toBeTrue();
});

it('validates required fields and unique email on create', function () {
    $existing = User::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '', 'email' => $existing->email, 'password' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique', 'password' => 'required']);
});

it('edits a user and syncs their roles', function () {
    $old = Role::create(['name' => 'staff']);
    $new = Role::create(['name' => 'manager']);
    $user = User::factory()->create()->assignRole($old);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['name' => 'Renamed', 'roles' => [$new->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Renamed')
        ->and($user->hasRole('manager'))->toBeTrue()
        ->and($user->hasRole('staff'))->toBeFalse();
});

it('keeps the current password when the password is left blank on edit', function () {
    $user = User::factory()->create(['password' => 'original-pass']);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['name' => 'Renamed', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('original-pass', $user->fresh()->password))->toBeTrue();
});

it('changes the password when a new one is provided on edit', function () {
    $user = User::factory()->create(['password' => 'original-pass']);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['password' => 'brand-new-pass'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

it('deletes a user', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertModelMissing($user);
});

it('does not let a user delete their own account', function () {
    Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])
        ->assertActionHidden(DeleteAction::class);

    expect($this->admin->can('delete', $this->admin))->toBeFalse();
});

it('forbids users without permissions from managing users', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ListUsers::class)->assertForbidden();
    Livewire::test(CreateUser::class)->assertForbidden();
});

it('allows a role with user permissions to manage users', function () {
    $role = Role::create(['name' => 'user_manager']);
    $role->givePermissionTo(
        Permission::findOrCreate('ViewAny:User'),
        Permission::findOrCreate('Create:User'),
    );
    $this->actingAs(User::factory()->create()->assignRole($role));

    Livewire::test(ListUsers::class)->assertSuccessful();
    Livewire::test(CreateUser::class)->assertSuccessful();
});
