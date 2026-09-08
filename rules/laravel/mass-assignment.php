<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WideOpen extends Model
{
    // ruleid: laravel-guarded-disabled
    protected $guarded = [];
}

class Guarded extends Model
{
    // Naming the columns is the whole point. Not a finding.
    // ok: laravel-guarded-disabled
    protected $guarded = ['id', 'is_admin'];
}

class Fillable extends Model
{
    // ok: laravel-guarded-disabled
    protected $fillable = ['name', 'email'];
}

class ProfileController
{
    public function vulnerable($request, $user, $model)
    {
        // ruleid: laravel-mass-assign-unfiltered-request
        $user->update($request->all());

        // ruleid: laravel-mass-assign-unfiltered-request
        $user->fill($request->all());

        // ruleid: laravel-mass-assign-unfiltered-request
        $a = $model::create($request->all());

        // ruleid: laravel-mass-assign-unfiltered-request
        $b = $model->create(request()->all());

        // ruleid: laravel-mass-assign-unfiltered-request
        $user->update($_POST);

        return [$a, $b];
    }

    public function safe($request, $user, $model)
    {
        // The form request decides which keys exist, in one place.
        // ok: laravel-mass-assign-unfiltered-request
        $user->update($request->validated());

        // ok: laravel-mass-assign-unfiltered-request
        $user->update($request->only(['name', 'email']));

        // ok: laravel-mass-assign-unfiltered-request
        $user->fill($request->safe()->only(['name']));

        // Explicit keys cannot be widened by the caller.
        // ok: laravel-mass-assign-unfiltered-request
        $user->update(['name' => $request->name, 'email' => $request->email]);

        // ok: laravel-mass-assign-unfiltered-request
        $a = $model::create($request->validated());

        // Not a request at all. A rule that flags every ->all() call would
        // fire on ordinary collection code all day.
        $settings = collect(['a' => 1]);
        // ok: laravel-mass-assign-unfiltered-request
        $b = $model->update($settings->all());

        return [$a, $b];
    }

    public function forced($user, $attributes)
    {
        // ruleid: laravel-force-fill
        $user->forceFill($attributes);

        // Already covered with a better message by the unfiltered-request
        // rule, so laravel-force-fill must stay quiet rather than double
        // reporting the same line.
        // ok: laravel-force-fill
        // ruleid: laravel-mass-assign-unfiltered-request
        $user->forceFill(request()->all());
    }
}
