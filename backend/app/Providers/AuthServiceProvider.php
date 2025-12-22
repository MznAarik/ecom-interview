<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
class AuthServiceProvider extends \Illuminate\Foundation\Support\Providers\AuthServiceProvider
{

    public function boot(): void
    {
        Gate::define('checkAdmin', function ($user) {
            return $user->role === 'admin';
        });

        Gate::define('checkUser', function ($user) {
            return $user->role === 'user';
        });
    }
}