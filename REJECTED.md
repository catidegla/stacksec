# Rules considered and not shipped

A ruleset is judged by what it does not flag as much as by what it catches. These
were written, tested against real code, and cut. They are recorded here so nobody
spends an afternoon rediscovering the same dead end.

## laravel-order-by-unvalidated-column

**What it would catch.** A column name or sort direction taken straight from the
request and passed to `orderBy`. This is a genuine issue: column names cannot be
bound as parameters, so the only correct fix is an allowlist.

**Why it was cut.** The correct fix is a guard, not a transformation:

```php
$sortable = ['created_at', 'total', 'name'];
abort_unless(in_array($request->sort, $sortable, true), 400);

$query->orderBy($request->sort, 'desc');
```

Taint analysis follows values, and `in_array` inspects its argument without
producing a new one, so `$request->sort` is still tainted on the next line. The
rule fires on the fixed code exactly as loudly as on the broken code. Semgrep's
`by-side-effect: true` sanitizer was meant for this case and does not clear it
here, verified against a minimal two-function fixture.

A rule that flags correct, idiomatic framework code is worse than no rule. It
trains people to skim past the report, and then they skim past a real finding.

**What would make it shippable.** Either a sanitizer that models guard clauses,
or a narrower rule that only fires when the enclosing function contains no
membership check at all. The second is expressible in principle but semgrep's
PHP parser rejected the `pattern-not-inside` function form needed for it.

Until then this belongs in the review skill, where a human or an agent can weigh
the surrounding code, rather than in a ruleset that gates a build.
