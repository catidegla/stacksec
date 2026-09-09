<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AvatarController
{
    public function trustsClientMime($request)
    {
        $file = $request->file('avatar');

        // ruleid: laravel-upload-trusts-client-mime-type
        if (in_array($file->getClientMimeType(), ['image/jpeg', 'image/png'])) {
            $file->store('avatars');
        }
    }

    public function comparesClientMimeDirectly($request)
    {
        $file = $request->file('avatar');

        // ruleid: laravel-upload-trusts-client-mime-type
        if ($file->getClientMimeType() === 'image/jpeg') {
            $file->store('avatars');
        }
    }

    public function mimetypesIsContentBased($request)
    {
        // Both mimes: and mimetypes: go through getMimeType(), which Symfony
        // derives from the file contents. Neither reads the client header.
        // ok: laravel-upload-trusts-client-mime-type
        $request->validate(['avatar' => 'required|file|mimetypes:image/jpeg,image/png|max:2048']);
    }

    public function mimesIsAlsoContentBased($request)
    {
        // ok: laravel-upload-trusts-client-mime-type
        $request->validate(['avatar' => 'required|file|mimes:jpg,png,webp|max:2048']);
    }

    public function bothTogetherIsStrongest($request)
    {
        // ok: laravel-upload-trusts-client-mime-type
        $request->validate(['avatar' => ['required', 'file', 'mimes:jpg,png', 'mimetypes:image/jpeg,image/png']]);
    }

    public function loggingTheClientTypeIsFine($request)
    {
        $file = $request->file('avatar');

        // ok: laravel-upload-trusts-client-mime-type
        logger()->info('client claimed', ['type' => $file->getClientMimeType()]);
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
