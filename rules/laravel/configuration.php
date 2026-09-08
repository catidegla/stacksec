<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class PaymentGateway
{
    public function readsEnvDirectly()
    {
        // Returns null once config:cache has run, and a null credential fails
        // open more often than it fails loudly.
        // ruleid: laravel-env-called-outside-config
        $key = env('STRIPE_SECRET');

        // ruleid: laravel-env-called-outside-config
        $mode = env('APP_ENV', 'production');

        return [$key, $mode];
    }

    public function readsConfig()
    {
        // config() is cached and keeps working. The env() call belongs in
        // config/services.php, which this rule excludes by path.
        // ok: laravel-env-called-outside-config
        $key = config('services.stripe.secret');

        // ok: laravel-env-called-outside-config
        $mode = config('app.env');

        return [$key, $mode];
    }
}

/*
 * The credential fixtures below use EXAMPLE runs rather than random looking
 * strings. They still match the rule, which cares about the shape, but secret
 * scanners recognise them as placeholders. A realistic looking fake in a
 * security repository gets the push blocked and, worse, trains people to click
 * past a genuine push protection warning.
 */
class Credentials
{
    public function hardcoded()
    {
        // ruleid: laravel-hardcoded-credential
        $stripe = 'sk_live_REDACTED';

        // ruleid: laravel-hardcoded-credential
        $github = 'ghp_EXAMPLEEXAMPLEEXAMPLEEXAMPLE00000000';

        return [$stripe, $github];
    }

    public function indirect()
    {
        // ok: laravel-hardcoded-credential
        $stripe = config('services.stripe.secret');

        // A test key is not a live credential and flagging it teaches people
        // to ignore the rule.
        // ok: laravel-hardcoded-credential
        $test = 'sk_test_EXAMPLEEXAMPLEEXAMPLE0000';

        // Ordinary strings must never trip this.
        // ok: laravel-hardcoded-credential
        $message = 'Votre paiement a bien ete recu.';

        // ok: laravel-hardcoded-credential
        $path = '/var/www/storage/app/public/invoices';

        return [$stripe, $test, $message, $path];
    }
}

class DebugSettings
{
    public function forcesDebugOn($app)
    {
        // ruleid: laravel-debug-forced-on
        Config::set('app.debug', true);
    }

    public function displaysErrors()
    {
        // ruleid: laravel-debug-forced-on
        ini_set('display_errors', '1');
    }

    public function leavesItToTheEnvironment()
    {
        // ok: laravel-debug-forced-on
        $debug = config('app.debug');

        // Turning it off in code is not the problem this rule is about.
        // ok: laravel-debug-forced-on
        Config::set('app.debug', false);

        return $debug;
    }
}
