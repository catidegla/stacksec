<?php

// Fixtures for laravel-raw-sql-interpolation.
//
// "ruleid:" marks a line that must be flagged. "ok:" marks a line that must
// not be. The ok cases carry as much weight as the ruleid ones: a rule that
// fires on parameterised queries gets switched off within a day.

use Illuminate\Support\Facades\DB;

class SqlInjectionFixtures
{
    public function vulnerable($request, $query)
    {
        // ruleid: laravel-raw-sql-interpolation
        $a = $query->whereRaw("name LIKE '%{$request->q}%'")->get();

        // ruleid: laravel-raw-sql-interpolation
        $b = $query->orderByRaw("{$request->sort} desc")->get();

        // ruleid: laravel-raw-sql-interpolation
        $c = DB::select("SELECT * FROM users WHERE email = '{$request->email}'");

        // ruleid: laravel-raw-sql-interpolation
        $d = $query->selectRaw("SUM({$request->column}) as total")->get();

        // ruleid: laravel-raw-sql-interpolation
        $e = DB::statement("DROP TABLE {$request->table}");

        return [$a, $b, $c, $d, $e];
    }

    public function safe($request, $query)
    {
        // ok: laravel-raw-sql-interpolation
        $a = $query->whereRaw('name LIKE ?', ["%{$request->q}%"])->get();

        // ok: laravel-raw-sql-interpolation
        $b = $query->where('email', $request->email)->first();

        // ok: laravel-raw-sql-interpolation
        $c = DB::select('SELECT * FROM users WHERE email = ?', [$request->email]);

        // A raw fragment with no request data in it is not injection. Flagging
        // every DB::raw call is how a rule earns a reputation for noise.
        // ok: laravel-raw-sql-interpolation
        $d = $query->selectRaw('COUNT(*) as aggregate')->get();

        // ok: laravel-raw-sql-interpolation
        $e = $query->orderByRaw('created_at desc')->get();

        // Column allowlisted before use, which is the correct fix for the
        // sort case since a column name cannot be bound as a parameter.
        $sortable = ['created_at', 'total', 'name'];
        abort_unless(in_array($request->sort, $sortable, true), 400);
        // ok: laravel-raw-sql-interpolation
        $f = $query->orderBy($request->sort, 'desc')->get();

        return [$a, $b, $c, $d, $e, $f];
        // See REJECTED.md for why unvalidated orderBy is not covered by a rule.
    }
}
