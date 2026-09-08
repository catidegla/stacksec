---
name: stack-security-review
description: Review Laravel and Next.js code for security defects using framework-aware rules, then check the things static analysis cannot see. Use when reviewing a diff or pull request in a Laravel or Next.js project, before merging changes to controllers, models, Blade templates, Server Actions or route handlers, and whenever the user asks about Laravel or Next.js security.
---

# Laravel and Next.js security review

Run the rules first, then do the part they cannot.

## Run the rules

```bash
npx --yes semgrep --config https://raw.githubusercontent.com/catidegla/stacksec/main/rules .
```

Or from a checkout of the repository:

```bash
semgrep --config rules/laravel .    # PHP
semgrep --config rules/nextjs .     # TypeScript
semgrep --config rules .            # both
```

Add `--severity ERROR` to see only the high confidence findings, and
`--baseline-commit main` to report only what the current branch introduced.
On an existing codebase the baseline is the difference between a report
somebody reads and thousands of pre-existing findings nobody does.

Every rule ships with the cases it must catch **and** the cases it must ignore,
so a finding is worth reading. If one still looks wrong, that is a bug worth
reporting rather than something to work around with a nosemgrep comment.

## What the rules cover

**Laravel.** Request data reaching raw SQL. `$guarded = []` and
`->update($request->all())`. Shell sinks, `unserialize` on request data,
secrets compared with `===`. Uploads validated with `mimetypes:` rather than
`mimes:`, and files stored under a client-supplied name. `env()` called outside
`config/`, hardcoded provider credentials, debug forced on in code.

**Next.js.** Server Actions with no authorization or no validation.
`NEXT_PUBLIC_` variables holding secrets. Open redirects and SSRF straight from
the query string. Unsanitized `dangerouslySetInnerHTML`.

## What the rules do not cover, so you have to

Read [REJECTED.md](../../REJECTED.md) for rules that were written and cut. The
short version, and the reason each needs a human or an agent rather than a
pattern:

**Authorization on a per-record basis.** A controller that loads a model by id
and never calls `authorize` is the most common real vulnerability in a Laravel
app, and static analysis cannot tell an intentional public endpoint from a
forgotten check. Ask, for each action that takes an id: what stops user A
passing user B's id? Nested routes deserve special attention, since
`/projects/{project}/tasks/{task}` needs the task to actually belong to the
project, which `->scopeBindings()` enforces and nothing else does.

**Allowlists that guard rather than transform.** Taint analysis follows values.
`in_array($request->sort, $sortable, true)` inspects a value without producing
a new one, so a scanner cannot see that the check happened. Read the code
around any `orderBy` or dynamic column name.

**Whole records serialized into client props.** A Next.js server component can
fetch a full user row and pass it to a client component, and everything in
those props lands in the HTML payload, including the fields nothing renders.
Prisma and Drizzle both return every column by default, so look for
`findUnique` without a `select` whose result crosses into a `'use client'`
component. This is the quietest data leak in the framework.

**Middleware treated as an authorization layer.** The `matcher` config silently
skips paths, and nothing warns you when a new route falls outside it. Compare
the matcher against the actual route tree, then check that the route handler
enforces authorization on its own.

**Business logic.** Whether a refund can exceed the original charge, whether a
discount code can be applied twice, whether an invoice can be paid by someone
who can read it. No rule will ever catch these.

## Reporting

Lead with anything that crosses a trust boundary: one user reaching another's
data, unauthenticated code execution, a credential leaving the server. For each
finding give the file and line, what an attacker sends, what they get back, and
the fix as a diff where it is short.

Separate live defects from hardening suggestions. Mixing them trains people to
skim the whole report, and then they skim past the one that mattered.

Say plainly when a diff is clean. "No findings, here is what I checked" is a
useful review.
