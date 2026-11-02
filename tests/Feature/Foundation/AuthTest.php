<?php

use App\Livewire\Profile\Security;
use App\Models\User;
use Livewire\Livewire;

it('shows the login page in Arabic RTL by default', function () {
    $this->get('/login')->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false);
});

it('switches the locale', function () {
    $this->from('/login')->get('/locale/en')->assertRedirect('/login');
    $this->get('/login')->assertSee('dir="ltr"', false)->assertSee('lang="en"', false);

    $this->get('/locale/xx')->assertNotFound();
});

it('has registration disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'password'])->assertNotFound();
});

it('redirects each role to its own area after login', function (string $role, string $path) {
    $user = User::factory()->{$role}()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($path);

    $this->assertAuthenticatedAs($user);
})->with([
    ['admin', '/admin'],
    ['supervisor', '/supervisor'],
    ['analyst', '/analyst'],
    ['customer', '/portal'],
]);

it('routes /dashboard by role', function () {
    $this->actingAs(User::factory()->supervisor()->create())->get('/dashboard')->assertRedirect('/supervisor');
});

it('blocks inactive users from logging in', function () {
    $user = User::factory()->analyst()->inactive()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects wrong passwords', function () {
    $user = User::factory()->analyst()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('lets a user enable and confirm two-factor authentication', function () {
    $user = User::factory()->analyst()->create();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/profile/security')->assertOk();

    Livewire::actingAs($user)->test(Security::class)
        ->call('enable')
        ->assertSee(__('common.tfa.scan'));

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});
