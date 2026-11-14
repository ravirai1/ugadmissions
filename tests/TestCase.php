<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests switch between users in one session, which a real browser cannot do without logging in.
     * Forget the password fingerprint AuthenticateSession keeps, so the switch is not treated as a
     * password change on another device.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($this->app->bound('session.store')) {
            $store = $this->app['session.store'];
            $store->forget(['password_hash_web', 'password_hash_admin']);
            $store->save(); // the next request reloads the session from its handler
        }

        return parent::actingAs($user, $guard);
    }
}
