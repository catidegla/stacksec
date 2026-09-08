<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class MediaController
{
    public function vulnerable($request)
    {
        // ruleid: laravel-command-injection
        exec('convert ' . $request->file . ' out.png');

        // ruleid: laravel-command-injection
        $a = shell_exec("ffprobe {$request->path}");

        // ruleid: laravel-command-injection
        system('rm -rf ' . $_GET['dir']);

        // ruleid: laravel-command-injection
        passthru('gzip ' . $request->input('name'));

        return $a;
    }

    public function safe($request)
    {
        // No shell involved. Arguments are passed as separate array entries,
        // so nothing can be interpreted as a command separator.
        // ok: laravel-command-injection
        $process = new Process(['convert', $request->file, 'out.png']);
        $process->run();

        // Transformed rather than merely checked, so taint can follow it.
        // ok: laravel-command-injection
        exec('convert ' . escapeshellarg($request->file) . ' out.png');

        // ok: laravel-command-injection
        exec('cleanup ' . (int) $request->id);

        // ok: laravel-command-injection
        exec('convert ' . basename($request->file));

        // A fixed command with no request data in it.
        // ok: laravel-command-injection
        exec('php artisan queue:restart');

        return $process;
    }
}

class ImportController
{
    public function vulnerable($request)
    {
        // ruleid: laravel-unserialize-request-data
        $a = unserialize($request->payload);

        // ruleid: laravel-unserialize-request-data
        $b = unserialize($_COOKIE['state']);

        return [$a, $b];
    }

    public function safe($request, $trustedBlob)
    {
        // ok: laravel-unserialize-request-data
        $a = json_decode($request->payload, true);

        // Restricting the classes removes the gadget chain.
        // ok: laravel-unserialize-request-data
        $b = unserialize($request->payload, ['allowed_classes' => false]);

        // Not request data. Flagging every unserialize call in a codebase is
        // how a rule gets muted.
        // ok: laravel-unserialize-request-data
        $c = unserialize($trustedBlob);

        return [$a, $b, $c];
    }
}

class WebhookController
{
    public function vulnerable($request, $expected)
    {
        $signature = $request->header('X-Signature');

        // ruleid: laravel-timing-unsafe-token-comparison
        if ($signature === $expected) {
            return true;
        }

        return false;
    }

    public function safe($request, $expected, $user)
    {
        $signature = $request->header('X-Signature');

        // ok: laravel-timing-unsafe-token-comparison
        if (hash_equals($expected, $signature)) {
            return true;
        }

        // Comparing a count, not a secret.
        $total = 3;
        // ok: laravel-timing-unsafe-token-comparison
        if ($total === 3) {
            return true;
        }

        return false;
    }
}
