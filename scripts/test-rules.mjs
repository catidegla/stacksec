#!/usr/bin/env node
/**
 * Runs every rule against its fixture and checks the annotations.
 *
 * Semgrep ships its own `--test`, but it could not pair these fixtures with
 * their rules on this layout, and a test harness that silently reports an
 * empty rule set is worse than none. This drives semgrep directly and checks
 * the annotations itself, so a rule that matches nothing fails loudly.
 *
 * Fixture format, next to each rule file with the same basename:
 *
 *   // ruleid: some-rule-id     the following line must be flagged
 *   // ok: some-rule-id         the following line must not be
 *
 * The ok: cases are the point. Anyone can write a rule that catches the bad
 * case; the work is not catching the good one.
 */

import { readdir, readFile } from 'node:fs/promises';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';
import { dirname, join, basename, extname } from 'node:path';

const run = promisify(execFile);
const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const RULES = join(ROOT, 'rules');

const FIXTURE_EXTENSIONS = ['.php', '.tsx', '.ts', '.jsx', '.js'];

const colour = process.stdout.isTTY && !process.env.NO_COLOR;
const paint = (code, s) => (colour ? `\x1b[${code}m${s}\x1b[0m` : s);
const green = (s) => paint('32', s);
const red = (s) => paint('31', s);
const dim = (s) => paint('2', s);
const bold = (s) => paint('1', s);

/** Every rule file paired with its fixture. */
async function collectPairs(dir, pairs = []) {
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);

    if (entry.isDirectory()) {
      await collectPairs(full, pairs);
      continue;
    }

    if (!entry.name.endsWith('.yaml') && !entry.name.endsWith('.yml')) continue;

    const stem = basename(entry.name, extname(entry.name));
    const siblings = await readdir(dir);
    const fixture = siblings.find(
      (f) => FIXTURE_EXTENSIONS.some((ext) => f === stem + ext),
    );

    pairs.push({ rule: full, fixture: fixture ? join(dir, fixture) : null, stem });
  }

  return pairs;
}

/**
 * Annotations map to the *next* non-comment, non-blank line, which is what
 * semgrep's own convention does.
 */
function parseAnnotations(source) {
  const lines = source.split(/\r?\n/);
  const expected = new Map(); // line number -> Set of rule ids
  const forbidden = new Map();

  for (let i = 0; i < lines.length; i++) {
    const match = lines[i].match(/^\s*(?:\/\/|#|\*)\s*(ruleid|ok):\s*(.+?)\s*$/);
    if (!match) continue;

    const [, kind, ids] = match;

    // Find the next line that carries actual code.
    let target = i + 1;
    while (target < lines.length && /^\s*(?:\/\/|#|\*|$)/.test(lines[target])) target++;

    const bucket = kind === 'ruleid' ? expected : forbidden;
    const set = bucket.get(target + 1) ?? new Set();
    for (const id of ids.split(',').map((s) => s.trim())) set.add(id);
    bucket.set(target + 1, set);
  }

  return { expected, forbidden };
}

async function scan(rule, fixture) {
  try {
    const { stdout } = await run(
      'semgrep',
      ['--config', rule, fixture, '--json', '--quiet', '--no-git-ignore', '--disable-version-check'],
      { maxBuffer: 64 * 1024 * 1024 },
    );
    return JSON.parse(stdout);
  } catch (error) {
    // Semgrep exits non-zero when it finds something, which is not an error.
    if (error.stdout) {
      try {
        return JSON.parse(error.stdout);
      } catch {
        // fall through
      }
    }
    throw new Error(`semgrep failed on ${rule}: ${error.stderr || error.message}`);
  }
}

async function main() {
  const pairs = await collectPairs(RULES);
  if (pairs.length === 0) {
    console.error('No rule files found under rules/.');
    process.exit(1);
  }

  let failures = 0;
  let checked = 0;

  console.log('');

  for (const { rule, fixture, stem } of pairs) {
    if (!fixture) {
      console.log(`${red('MISSING')}  ${stem}`);
      console.log(dim(`         every rule needs a fixture, including the cases it must not flag`));
      failures++;
      continue;
    }

    const source = await readFile(fixture, 'utf8');
    const { expected, forbidden } = parseAnnotations(source);

    if (expected.size === 0) {
      console.log(`${red('NO CASES')} ${stem}`);
      console.log(dim('         fixture has no ruleid: annotations, so it proves nothing'));
      failures++;
      continue;
    }

    const report = await scan(rule, fixture);
    const found = new Map(); // line -> Set of rule ids
    for (const result of report.results ?? []) {
      const id = result.check_id.split('.').pop();
      const line = result.start.line;
      const set = found.get(line) ?? new Set();
      set.add(id);
      found.set(line, set);
    }

    const problems = [];

    for (const [line, ids] of expected) {
      for (const id of ids) {
        if (!found.get(line)?.has(id)) {
          problems.push(`missed ${id} at line ${line}: ${source.split(/\r?\n/)[line - 1]?.trim()}`);
        }
      }
    }

    for (const [line, ids] of forbidden) {
      for (const id of ids) {
        if (found.get(line)?.has(id)) {
          problems.push(`FALSE POSITIVE ${id} at line ${line}: ${source.split(/\r?\n/)[line - 1]?.trim()}`);
        }
      }
    }

    // A match on a line with no annotation at all is also unexpected.
    for (const [line, ids] of found) {
      for (const id of ids) {
        if (!expected.get(line)?.has(id) && !forbidden.get(line)?.has(id)) {
          problems.push(`unannotated match ${id} at line ${line}: ${source.split(/\r?\n/)[line - 1]?.trim()}`);
        }
      }
    }

    checked++;

    if (problems.length === 0) {
      const positives = [...expected.values()].reduce((n, s) => n + s.size, 0);
      const negatives = [...forbidden.values()].reduce((n, s) => n + s.size, 0);
      console.log(`${green('PASS')}     ${stem}  ${dim(`${positives} caught, ${negatives} correctly ignored`)}`);
    } else {
      console.log(`${red('FAIL')}     ${stem}`);
      for (const problem of problems) console.log(`         ${problem}`);
      failures++;
    }
  }

  console.log('');
  console.log(
    failures === 0
      ? bold(green(`All ${checked} rule file(s) behave as specified.`))
      : bold(red(`${failures} rule file(s) failed.`)),
  );
  console.log('');

  process.exit(failures === 0 ? 0 : 1);
}

main().catch((error) => {
  console.error(error.message);
  process.exit(2);
});
