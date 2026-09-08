<div align="center">

# stacksec

**Security rules that know your framework.**

Semgrep rules for Laravel and Next.js, where every rule ships with the code it must ignore as well as the code it must catch.

[![CI](https://github.com/catidegla/stacksec/actions/workflows/ci.yml/badge.svg)](https://github.com/catidegla/stacksec/actions/workflows/ci.yml)
[![Rules](https://img.shields.io/badge/rules-19-7c3aed)](#what-it-catches)
[![Precision](https://img.shields.io/badge/fixtures-42%20caught%20%2F%2057%20ignored-brightgreen)](#the-ratio-is-the-point)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

</div>

---

```php
$request->validate(['avatar' => 'required|file|mimetypes:image/jpeg|max:2048']);
```

That line validates nothing useful. `mimetypes:` trusts the `Content-Type` header the client chose. `mimes:` inspects the actual file contents. Four characters apart, opposite guarantees, and no general purpose scanner knows the difference because it is not a language feature. It is a Laravel one.

That is what these rules are for.

## Quick start

```bash
# Scan a project without installing anything
npx --yes semgrep --config https://raw.githubusercontent.com/catidegla/stacksec/main/rules .
```

<details>
<summary>As a GitHub Action</summary>

```yaml
- uses: catidegla/stacksec@v0.1.0
  with:
    stack: laravel          # laravel, nextjs, or all
    baseline-ref: main      # only what this branch introduced
    fail-on: ERROR

- uses: github/codeql-action/upload-sarif@v3
  with:
    sarif_file: stacksec.sarif
```

`baseline-ref` matters on an existing codebase. Without it the first run reports every pre-existing finding at once, which is how a security tool gets muted in week one.

</details>

<details>
<summary>As a Claude Code plugin</summary>

```
/plugin marketplace add catidegla/stacksec
/plugin install stacksec@stacksec
```

Adds a review skill that runs the rules and then covers what they cannot: per-record authorization, guard-clause allowlists, and whole records serialized into client props.

</details>

## The ratio is the point

**42 findings caught. 57 pieces of correct code deliberately not flagged.**

More cases ignored than caught, and that is the harder half. Anyone can write a rule that catches `exec($_GET['x'])`. The work is not catching `escapeshellarg`, `hash_equals`, `->update($request->validated())`, `whereRaw` with bindings, a validated Server Action, `NEXT_PUBLIC_SUPABASE_ANON_KEY`, or `DOMPurify.sanitize`.

That last one is worth dwelling on. A Supabase anon key and a Stripe publishable key both contain the word KEY and are public by design. A rule that flags them is wrong about the obvious case, and a tool that is wrong about the obvious case does not get a second chance.

Every rule has a fixture beside it carrying both kinds of case, and CI fails on either a miss or a false positive:

```
PASS     command-injection  7 caught, 10 correctly ignored
PASS     configuration      6 caught,  8 correctly ignored
PASS     file-upload        4 caught,  7 correctly ignored
PASS     mass-assignment    8 caught,  9 correctly ignored
PASS     sql-injection      5 caught,  6 correctly ignored
PASS     client-exposure    8 caught, 11 correctly ignored
PASS     server-actions     4 caught,  6 correctly ignored
```

## What it catches

### Laravel

| Rule | |
| :--- | :--- |
| `laravel-raw-sql-interpolation` | Request data in `whereRaw`, `orderByRaw`, `DB::select` and friends. Parameterised calls are not flagged. |
| `laravel-guarded-disabled` | `$guarded = []`, which turns mass assignment protection off entirely. |
| `laravel-mass-assign-unfiltered-request` | `->update($request->all())`. `->validated()` and `->only()` are not flagged. |
| `laravel-force-fill` | `forceFill` bypasses `$fillable` and `$guarded` by design. |
| `laravel-command-injection` | Request data reaching a shell. Taint tracked, with `escapeshellarg` and casts as sanitizers. |
| `laravel-unserialize-request-data` | Remote code execution whenever a gadget chain exists, which in a Laravel dependency tree it does. |
| `laravel-timing-unsafe-token-comparison` | Secrets compared with `===` rather than `hash_equals`. |
| `laravel-upload-trusts-client-mime-type` | `mimetypes:` where `mimes:` was meant. |
| `laravel-upload-uses-client-filename` | `getClientOriginalName` carries traversal sequences and double extensions. |
| `laravel-upload-without-validation` | A file stored with no validation anywhere in the method. |
| `laravel-env-called-outside-config` | See below. |
| `laravel-hardcoded-credential` | Provider issued credential shapes only, so `sk_test_` keys stay quiet. |
| `laravel-debug-forced-on` | Debug enabled in code rather than by environment. |

**`env()` outside `config/` is the one people underrate.** It is usually filed as a correctness bug: after `php artisan config:cache`, `env()` returns `null` everywhere except config files. The security half is that a null credential tends to fail *open*. A signature verified against `null`. A comparison to an empty string. A client that quietly sends no credential at all.

### Next.js

| Rule | |
| :--- | :--- |
| `nextjs-server-action-without-authorization` | Every exported function in a `"use server"` file is a callable HTTP endpoint. |
| `nextjs-server-action-without-validation` | The type signature is a suggestion. The client controls shapes, not just values. |
| `nextjs-public-env-holds-a-secret` | `NEXT_PUBLIC_` is inlined into the browser bundle permanently. Publishable and anon keys excluded. |
| `nextjs-open-redirect-from-query` | `//evil.example.com` is protocol relative and starts with a slash. |
| `nextjs-ssrf-from-query` | The server has network reach the browser does not, including metadata endpoints. |
| `nextjs-dangerously-set-inner-html` | Sanitized values are not flagged, or nobody would sanitize. |

The Server Action rules are TypeScript only. Semgrep's JavaScript parser rejects the block body patterns their exclusions depend on, and a rule that errors out covers nothing while appearing installed, so it is scoped rather than left broken.

## What it does not catch

Being explicit, because a security tool that implies more coverage than it has is its own risk. [REJECTED.md](REJECTED.md) records rules that were written, tested and cut.

- **Per-record authorization.** A controller that loads a model by id and never calls `authorize` is the most common real vulnerability in a Laravel app, and no pattern distinguishes an intentional public endpoint from a forgotten check.
- **Allowlists that guard rather than transform.** `in_array($request->sort, $sortable, true)` inspects a value without producing a new one, so taint analysis cannot see the check happened. A rule for this fired on correctly written code, so it was cut rather than shipped.
- **Whole records serialized into client props.** Prisma and Drizzle return every column by default, and everything passed to a client component lands in the HTML payload.
- **Business logic.** Whether a refund can exceed the charge. Nothing will ever catch that.

The Claude Code plugin covers these, because they need judgement rather than a pattern.

## Contributing

```bash
pip install semgrep
node scripts/test-rules.mjs
```

A rule is not accepted without a fixture, and the fixture is not accepted without `ok:` cases. Fixtures live beside the rule with the same basename:

```php
// ruleid: my-rule     the next line must be flagged
// ok: my-rule         the next line must not be
```

The harness also fails on an **unannotated** match, so a rule that quietly widens its blast radius cannot slip through.

<details>
<summary>Semgrep constraints that cost us time</summary>

Recorded here and in the rule files so nobody rediscovers them. Every one of these disables a rule *silently*, because semgrep reports a bad pattern as a rule error rather than as zero matches.

- A function body written `{ ... call(...) ... }` on one line does not parse in TypeScript or JavaScript. Use the multi-line block form.
- A bare JSX attribute does not parse. It has to be a whole element: `<$EL ... attr={...} ... />`.
- The JSX brace form contains a colon, so YAML reads it as a mapping unless it is a block scalar.
- `protected` is not matchable as part of a PHP property pattern. Match the property bare and scope it with `pattern-inside: class $C { ... }`.
- `$KEY => $VALUE` is not a statement, so it is not a valid standalone PHP pattern. Write it inside an array literal.
- `metavariable-regex` works on scalars. A compound node such as a rules array needs `metavariable-pattern`.
- `pattern-regex` has no AST awareness and fires inside comments.

</details>

## License

[MIT](LICENSE)
