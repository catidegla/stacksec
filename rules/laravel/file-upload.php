<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AvatarController
{
    public function trustsClientMime($request)
    {
        // ruleid: laravel-upload-trusts-client-mime-type
        $request->validate(['avatar' => 'required|file|mimetypes:image/jpeg,image/png|max:2048']);
    }

    public function checksActualContent($request)
    {
        // mimes: inspects the file, mimetypes: trusts the header. Three
        // characters apart, opposite guarantees.
        // ok: laravel-upload-trusts-client-mime-type
        $request->validate(['avatar' => 'required|file|mimes:jpg,png,webp|max:2048']);
    }

    public function arrayRulesAreFine($request)
    {
        // ok: laravel-upload-trusts-client-mime-type
        $request->validate(['avatar' => ['required', 'file', 'mimes:jpg,png', 'max:2048']]);
    }

    public function usesClientName($request)
    {
        $file = $request->file('avatar');

        // ruleid: laravel-upload-uses-client-filename
        $file->storeAs('public/avatars', $file->getClientOriginalName());
    }

    public function usesClientNameViaMove($request)
    {
        $file = $request->file('avatar');

        // ruleid: laravel-upload-uses-client-filename
        $file->move('/var/www/uploads', $file->getClientOriginalName());
    }

    public function generatesItsOwnName($request)
    {
        $file = $request->file('avatar');

        // store() generates a random name, which is the whole point.
        // ok: laravel-upload-uses-client-filename
        $path = $file->store('avatars', 'public');

        // An explicit name the caller cannot influence is fine.
        // ok: laravel-upload-uses-client-filename
        $file->storeAs('avatars', 'profile-'.auth()->id().'.jpg');

        // A hashed name is not the client's.
        // ok: laravel-upload-uses-client-filename
        $file->storeAs('avatars', $file->hashName());

        return $path;
    }

    public function storesWithoutValidating($request)
    {
        // ruleid: laravel-upload-without-validation
        $path = $request->file('avatar')->store('avatars');

        return $path;
    }

    public function validatesFirst($request)
    {
        $request->validate(['avatar' => 'required|file|mimes:jpg,png|max:2048']);

        // ok: laravel-upload-without-validation
        $path = $request->file('avatar')->store('avatars');

        return $path;
    }

    public function validatesWithTheFacade($request)
    {
        Validator::make($request->all(), ['avatar' => 'required|file|mimes:jpg,png'])->validate();

        // ok: laravel-upload-without-validation
        $path = $request->file('avatar')->store('avatars');

        return $path;
    }
}
