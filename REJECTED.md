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

## laravel-upload-trusts-client-mime-type, first version

**Cut because it was wrong, not because it was noisy.**

The original rule flagged the `mimetypes:` validation rule at ERROR severity, on the claim that it trusts the `Content-Type` header the client sent while `mimes:` inspects the file. That is false, and reading Laravel's source settles it in a minute.

`validateMimes` calls `$value->guessExtension()`. `validateMimetypes` calls `$value->getMimeType()`. `UploadedFile` does not override `getMimeType()`, so both resolve to `File::getMimeType()`, which is `MimeTypes::getDefault()->guessMimeType($path)`. Both inspect the contents. Neither reads the client header. The method that does is `getClientMimeType()`, and no validation rule calls it.

So the rule fired on correct code, at high confidence, in a security tool. Worse than noise: it would have talked people out of a safe rule and into believing they had found a vulnerability.

The replacement flags a comparison against `getClientMimeType()` or `guessClientExtension()`, which is the decision the original was reaching for.

Two things worth keeping from this. Reading the framework source beats reading summaries of it, including confident ones. And a rule whose message asserts how a framework works is a claim that needs the same checking as the pattern.

