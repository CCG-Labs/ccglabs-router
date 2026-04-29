# bhr → ccglabs Migration Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the package, namespaces, test layout, and prose references from `bhr`/`BHR` to `ccglabs`/`CCGLabs` after the move from a personal repo to the CCG-Labs GitHub organization.

**Architecture:** Three atomic commits, each leaving the repo in a working (tests-passing) state. Commit 1 is the namespace+layout rename (must be atomic to keep autoload+tests valid). Commit 2 is package metadata (composer.json name/version, lockfile, phpunit, dependabot). Commit 3 is documentation prose. Final verification gate ensures zero `bhr`/`iambrianreich` matches in tracked files.

**Tech Stack:** PHP 8.4+, Composer, PHPUnit 12, PHP_CodeSniffer (PSR-12), GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-04-29-bhr-to-ccglabs-migration-design.md`

---

## File Structure

No new files are created. The migration touches:

- `src/**/*.php` (10 files) — namespace + use + docblock updates
- `tests/BHR/...` (11 files) — moved to `tests/CCGLabs/Router/...` with namespace fixes; 1 duplicate deleted
- `composer.json` — name, version, autoload, autoload-dev
- `composer.lock` — regenerated
- `phpunit.xml` — testsuite name
- `phpcs.xml` — no changes
- `.github/dependabot.yml` — reviewer entries
- `.phpunit.result.cache` — deleted
- `README.md`, `CLAUDE.md`, `docs/security-best-practices.md`, `specs/steps-to-complete-version-1.md` — prose

---

## Pre-flight

- [ ] **Step 0: Confirm working tree is clean and on `main`**

```bash
git status
git rev-parse --abbrev-ref HEAD
```

Expected: `nothing to commit, working tree clean` and `main`. Recent commits should include the design spec from this brainstorming session.

---

## Chunk 1: Atomic namespace and layout rename

This chunk MUST commit as a single unit. The intermediate state between editing `src/` namespaces and updating `composer.json` autoload mappings is autoload-broken; do not run `composer test` in the middle.

### Task 1.1: Update source file namespaces

**Files (10):**
- Modify: `src/Application.php`
- Modify: `src/IRoute.php`
- Modify: `src/IParameterizedRoute.php`
- Modify: `src/Exceptions/RouteHandlerNotFoundException.php`
- Modify: `src/HandlerLocators/DefaultHandlerLocator.php`
- Modify: `src/HandlerLocators/IHandlerLocator.php`
- Modify: `src/HTTP/Verb.php`
- Modify: `src/RequestHandlers/CallableRequestHandler.php`
- Modify: `src/Routes/StringRoute.php`
- Modify: `src/Routes/TokenizedRoute.php`

- [ ] **Step 1: Apply substitutions to every file in `src/`**

In each file, perform these textual substitutions (literal, case-sensitive):

| Find | Replace |
|---|---|
| `BHR\Router` | `CCGLabs\Router` |
| `BHR Router` (any prose in docblocks) | `CCGLabs Router` |

Special case for `src/Routes/TokenizedRoute.php` line 4 — it has a template artifact:

| Find | Replace |
|---|---|
| `BHR\$CLASS` | `CCGLabs\Router\Routes\TokenizedRoute` |

- [ ] **Step 2: Spot-verify with grep**

```bash
grep -rEn "BHR" src/
```

Expected: zero matches. Any match here means a file was missed.

### Task 1.2: Move and rename test files

**Files moved (with namespace updates baked in):**

| From | To | Namespace change |
|---|---|---|
| `tests/BHR/Router/ApplicationTest.php` | `tests/CCGLabs/Router/ApplicationTest.php` | `Tests\BHR\Router` → `Tests\CCGLabs\Router` |
| `tests/BHR/Router/IParameterizedRouteTest.php` | `tests/CCGLabs/Router/IParameterizedRouteTest.php` | `Tests\BHR\Router` → `Tests\CCGLabs\Router` |
| `tests/BHR/Router/IHandlerLocatorTest.php` | `tests/CCGLabs/Router/IHandlerLocatorTest.php` | `Tests\BHR\Router\HandlerLocators` → `Tests\CCGLabs\Router\HandlerLocators` |
| `tests/BHR/Router/Exceptions/RouteHandlerNotFoundExceptionTest.php` | `tests/CCGLabs/Router/Exceptions/RouteHandlerNotFoundExceptionTest.php` | `Tests\BHR\Router\Exceptions` → `Tests\CCGLabs\Router\Exceptions` |
| `tests/BHR/Router/RequestHandlers/CallableRequestHandlerTest.php` | `tests/CCGLabs/Router/RequestHandlers/CallableRequestHandlerTest.php` | `Tests\BHR\Router\RequestHandlers` → `Tests\CCGLabs\Router\RequestHandlers` |
| `tests/BHR/Router/Routes/StringRouteTest.php` | `tests/CCGLabs/Router/Routes/StringRouteTest.php` | `Tests\BHR\Router\Routes` → `Tests\CCGLabs\Router\Routes` |
| `tests/BHR/Router/Routes/TokenizedRouteTest.php` | `tests/CCGLabs/Router/Routes/TokenizedRouteTest.php` | `Tests\BHR\Router\Routes` → `Tests\CCGLabs\Router\Routes` |
| `tests/BHR/IRouteTest.php` | `tests/CCGLabs/Router/IRouteTest.php` | `Tests\BHR\Router` → `Tests\CCGLabs\Router` (file location now matches namespace) |
| `tests/BHR/HandlerLocators/DefaultHandlerLocatorTest.php` | `tests/CCGLabs/Router/HandlerLocators/DefaultHandlerLocatorTest.php` | `Test\BHR\Router\HandlerLocators` (typo) → `Tests\CCGLabs\Router\HandlerLocators` |
| `tests/BHR/HTTP/VerbTest.php` | `tests/CCGLabs/Router/HTTP/VerbTest.php` | `Tests\BHR\HTTP` → `Tests\CCGLabs\Router\HTTP` (note: `Router` segment added to mirror source layout) |

**File deleted:**

- `tests/BHR/ApplicationTest.php` — duplicate of canonical `tests/BHR/Router/ApplicationTest.php`. Confirmed during planning: 80 lines vs 357 lines, all 3 of its tests (`testClassExists`, `testConstructor`, `testhandlerRunsMiddlewareInOrder`) are subsumed by canonical's 13 tests including the comprehensive middleware suite. No coverage lost.

- [ ] **Step 1: Create the new directory tree**

```bash
mkdir -p tests/CCGLabs/Router/Exceptions \
         tests/CCGLabs/Router/HandlerLocators \
         tests/CCGLabs/Router/HTTP \
         tests/CCGLabs/Router/RequestHandlers \
         tests/CCGLabs/Router/Routes
```

- [ ] **Step 2: Move files with `git mv`** (preserves history)

```bash
git rm tests/BHR/ApplicationTest.php

git mv tests/BHR/Router/ApplicationTest.php tests/CCGLabs/Router/ApplicationTest.php
git mv tests/BHR/Router/IParameterizedRouteTest.php tests/CCGLabs/Router/IParameterizedRouteTest.php
git mv tests/BHR/Router/IHandlerLocatorTest.php tests/CCGLabs/Router/IHandlerLocatorTest.php
git mv tests/BHR/Router/Exceptions/RouteHandlerNotFoundExceptionTest.php tests/CCGLabs/Router/Exceptions/RouteHandlerNotFoundExceptionTest.php
git mv tests/BHR/Router/RequestHandlers/CallableRequestHandlerTest.php tests/CCGLabs/Router/RequestHandlers/CallableRequestHandlerTest.php
git mv tests/BHR/Router/Routes/StringRouteTest.php tests/CCGLabs/Router/Routes/StringRouteTest.php
git mv tests/BHR/Router/Routes/TokenizedRouteTest.php tests/CCGLabs/Router/Routes/TokenizedRouteTest.php
git mv tests/BHR/IRouteTest.php tests/CCGLabs/Router/IRouteTest.php
git mv tests/BHR/HandlerLocators/DefaultHandlerLocatorTest.php tests/CCGLabs/Router/HandlerLocators/DefaultHandlerLocatorTest.php
git mv tests/BHR/HTTP/VerbTest.php tests/CCGLabs/Router/HTTP/VerbTest.php
```

- [ ] **Step 3: Apply substitutions to every file under `tests/CCGLabs/`**

For each moved file, apply these substitutions in order (the typo rule must run first to avoid being clobbered by the `Tests\BHR` rule's partial match):

| Order | Find | Replace |
|---|---|---|
| 1 | `Test\BHR\Router\HandlerLocators` | `Tests\CCGLabs\Router\HandlerLocators` |
| 2 | `Tests\BHR\HTTP` | `Tests\CCGLabs\Router\HTTP` |
| 3 | `Tests\BHR\Router` | `Tests\CCGLabs\Router` |
| 4 | `Tests\BHR` | `Tests\CCGLabs\Router` |
| 5 | `BHR\Router` | `CCGLabs\Router` |

Special-case `tests/CCGLabs/Router/ApplicationTest.php`: its file-level docblock currently reads "This file contains BHR\Router\ApplicationTest" — change to "This file contains Tests\CCGLabs\Router\ApplicationTest".

- [ ] **Step 4: Spot-verify**

```bash
grep -rEn "BHR|Test\\\\BHR" tests/
ls tests/BHR/ 2>/dev/null
```

Expected: first command returns zero matches; second command shows nothing under `tests/BHR/` (or only empty dirs to be cleaned next).

- [ ] **Step 5: Remove now-empty `tests/BHR/` tree**

```bash
find tests/BHR -type d -empty -delete 2>/dev/null
ls tests/BHR 2>&1
```

Expected: `ls: cannot access 'tests/BHR': No such file or directory`.

### Task 1.3: Update composer.json autoload mappings

**Files:**
- Modify: `composer.json` (autoload section only — name and version stay for now to keep this commit scoped to "namespace rename")

- [ ] **Step 1: Edit `composer.json`**

Change:

```json
    "autoload": {
        "psr-4": {
            "BHR\\Router\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\BHR\\": "tests/BHR/"
        }
    },
```

To:

```json
    "autoload": {
        "psr-4": {
            "CCGLabs\\Router\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\CCGLabs\\Router\\": "tests/CCGLabs/Router/"
        }
    },
```

Note the autoload-dev mapping now points directly at `tests/CCGLabs/Router/` (the new layout's deepest common parent), since every test now lives under `Tests\CCGLabs\Router\*`. This is cleaner than the old `Tests\BHR\` → `tests/BHR/` mapping which had to span multiple sub-namespaces.

### Task 1.4: Delete stale PHPUnit cache

- [ ] **Step 1: Delete the cache file**

```bash
rm -f .phpunit.result.cache
```

The cache stores test result keys keyed by fully-qualified class name. After namespace rename, every key is stale; PHPUnit will regenerate on next run. The file is in `.gitignore` (verify with `git check-ignore .phpunit.result.cache`); if not ignored, the deletion still appears in the commit.

### Task 1.5: Verify autoload + tests

- [ ] **Step 1: Regenerate autoload**

```bash
composer dump-autoload
```

Expected: completes with no PSR-4 warnings. Any "does not comply with psr-4 autoloading standard" warning means a test file has the wrong namespace for its location — re-check Task 1.2 substitutions.

- [ ] **Step 2: Run tests**

```bash
composer test
```

Expected: same number of tests pass as before the rename. (Pre-rename baseline was passing CI per the badge in README; if you have a recent local run, compare counts.)

- [ ] **Step 3: Run code style check**

```bash
composer cs:check
```

Expected: passes PSR-12 (no new violations introduced).

### Task 1.6: Commit

- [ ] **Step 1: Stage and commit**

```bash
git add -A
git status
```

Verify the staged diff includes: 10 modified `src/` files, 11 moved `tests/` files (rename detection should show as renames, not delete+add), 1 deleted duplicate test, 1 modified `composer.json`, 1 deleted `.phpunit.result.cache`.

```bash
git commit -m "refactor!: rename namespace BHR\\Router to CCGLabs\\Router

Renames source PSR-4 root from BHR\\Router to CCGLabs\\Router and
relocates tests from tests/BHR/... to tests/CCGLabs/Router/... so the
test layout mirrors src/ exactly.

Folds in pre-existing PSR-4 violations encountered during the move:
- Removes duplicate tests/BHR/ApplicationTest.php (subsumed by the
  canonical 357-line version with full middleware coverage)
- Fixes Test\\BHR (singular) typo in DefaultHandlerLocatorTest
- Realigns IRouteTest, VerbTest, HandlerLocators tests to live under
  paths that match their declared namespaces

BREAKING CHANGE: Consumers must update use statements from
\`BHR\\Router\\\\*\` to \`CCGLabs\\Router\\\\*\`."
```

The `!` and `BREAKING CHANGE:` footer follow Conventional Commits to make the major-version intent obvious in `git log`.

---

## Chunk 2: Package metadata

After Chunk 1, autoload and tests work but the package is still named `bhr/router` at version `1.0` and the testsuite/CI metadata still says BHR. This chunk fixes those.

### Task 2.1: Update composer.json name and version

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Edit name and version**

Change:

```json
    "name": "bhr/router",
    "description": "A simple router for simple applications.",
    "type": "library",
    "version": "1.0",
```

To:

```json
    "name": "ccglabs/router",
    "description": "A simple router for simple applications.",
    "type": "library",
    "version": "2.0.0",
```

`description` is unchanged (no "BHR" in it). Version bumps to `2.0.0` per the spec's clean-break decision.

### Task 2.2: Regenerate composer.lock

- [ ] **Step 1: Refresh lockfile content-hash without changing resolved dependency versions**

```bash
composer update --lock
```

Expected output: a single "Updating dependencies" line followed by "Writing lock file" — no packages should appear as upgraded. If Composer reports package upgrades, abort and use `composer install --no-dev=false` semantics or pin specific versions; the goal of this commit is metadata-only.

- [ ] **Step 2: Verify only the content-hash and package-name fields changed**

```bash
git diff composer.lock | grep -E "^[+-]" | grep -v "^[+-]\s*\"(content-hash|name)\"" | head -20
```

Expected: minimal output. Any large diff means dependency versions shifted — investigate before continuing.

### Task 2.3: Update phpunit.xml testsuite name

**Files:**
- Modify: `phpunit.xml:9`

- [ ] **Step 1: Edit testsuite name**

Change:

```xml
        <testsuite name="BHR Router Test Suite">
```

To:

```xml
        <testsuite name="CCGLabs Router Test Suite">
```

### Task 2.4: Remove iambrianreich from dependabot reviewers

**Files:**
- Modify: `.github/dependabot.yml`

- [ ] **Step 1: Remove the two reviewer entries**

The file has `- "iambrianreich"` listed twice (lines 12 and 29) under `reviewers:` blocks. Remove those two lines. If this leaves an empty `reviewers:` array, remove the entire `reviewers:` key from each block (yaml-syntactically a `reviewers: []` is also valid but the empty key is cleaner).

- [ ] **Step 2: Verify yaml is still valid**

```bash
python3 -c "import yaml; yaml.safe_load(open('.github/dependabot.yml'))"
```

Expected: no output (success). If you don't have python3 with PyYAML, any yaml linter or `gh dependabot` will do.

### Task 2.5: Verify and commit

- [ ] **Step 1: Re-run tests and style check**

```bash
composer test
composer cs:check
```

Expected: both pass. (No code changed, but the testsuite name change in `phpunit.xml` should be visible in the test output header.)

- [ ] **Step 2: Stage and commit**

```bash
git add composer.json composer.lock phpunit.xml .github/dependabot.yml
git commit -m "chore: rename package to ccglabs/router and bump to 2.0.0

Updates composer package name from bhr/router to ccglabs/router,
bumps version to 2.0.0 to signal the breaking namespace change shipped
in the previous commit, refreshes the lockfile content-hash, renames
the PHPUnit testsuite to match, and removes a personal-account
reviewer from dependabot now that the repo lives under CCG-Labs."
```

---

## Chunk 3: Documentation prose

The codebase, package metadata, and CI config are all renamed. This chunk updates human-readable docs.

### Task 3.1: Update README.md

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update title and badges**

Change line 1: `# bhr/router` → `# ccglabs/router`

Change badge URLs (lines 3-4):
- `https://github.com/iambrianreich/bhr-router/workflows/CI/badge.svg` → `https://github.com/CCG-Labs/ccglabs-router/workflows/CI/badge.svg`
- `https://github.com/iambrianreich/bhr-router/actions/workflows/ci.yml` → `https://github.com/CCG-Labs/ccglabs-router/actions/workflows/ci.yml`
- Same pattern for the Security badge (lines 4 area).

- [ ] **Step 2: Update install command and code samples**

Apply substitutions throughout the file:

| Find | Replace |
|---|---|
| `composer require bhr/router` | `composer require ccglabs/router` |
| `BHR\Router` | `CCGLabs\Router` |
| `BHR Router` | `CCGLabs Router` |

The `✓` glyph in the verify-installation snippet is fine — leave it as-is.

- [ ] **Step 3: Verify**

```bash
grep -nE "bhr|BHR|iambrianreich" README.md
```

Expected: zero matches.

### Task 3.2: Update CLAUDE.md

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Apply substitutions**

| Find | Replace |
|---|---|
| `bhr/router` | `ccglabs/router` |
| `BHR\HTTP` | `CCGLabs\Router\HTTP` |
| ``Root namespace: `BHR` `` | ``Root namespace: `CCGLabs\Router` `` |
| `tests/BHR/HTTP/VerbTest.php` | `tests/CCGLabs/Router/HTTP/VerbTest.php` |

Re-read the resulting file to make sure the architecture description still reads sensibly — if the original said "Root namespace: `BHR`" but the actual root is `BHR\Router`, that's an existing inaccuracy worth correcting to `CCGLabs\Router` while we're here. The path-reference rule on line 29 (in the "Run a specific test file" example) updates the documented command to point at the new test layout.

- [ ] **Step 2: Verify**

```bash
grep -nE "bhr|BHR" CLAUDE.md
```

Expected: zero matches.

### Task 3.3: Update docs/security-best-practices.md

**Files:**
- Modify: `docs/security-best-practices.md`

- [ ] **Step 1: Apply prose substitution**

| Find | Replace |
|---|---|
| `BHR Router` | `CCGLabs Router` |

(All four matches identified during planning are prose mentions of "BHR Router".)

- [ ] **Step 2: Verify**

```bash
grep -nE "bhr|BHR" docs/security-best-practices.md
```

Expected: zero matches.

### Task 3.4: Update specs/steps-to-complete-version-1.md

**Files:**
- Modify: `specs/steps-to-complete-version-1.md`

- [ ] **Step 1: Apply substitutions**

This is a historical document; preserve its structure but update prose references.

| Find | Replace |
|---|---|
| `BHR Router Library` | `CCGLabs Router Library` |
| `BHR Router` | `CCGLabs Router` |
| `BHR\Router\Application` | `CCGLabs\Router\Application` |
| `BHR\Application` | `CCGLabs\Application` (preserves the original incorrect-namespace point being made; the surrounding sentence says "the namespace is actually correct - it IS `BHR\Router\Application`, not `BHR\Application`", so both forms need to update together to keep the sentence coherent) |

- [ ] **Step 2: Verify**

```bash
grep -nE "bhr|BHR" specs/steps-to-complete-version-1.md
```

Expected: zero matches.

### Task 3.5: Final repository-wide verification

- [ ] **Step 1: Confirm zero matches in tracked files**

```bash
git ls-files | grep -v -E "^(vendor/|composer\.lock$|\.phpunit\.result\.cache$|docs/superpowers/)" | xargs grep -lEi "bhr|iambrianreich" 2>/dev/null
```

Expected: zero matches. The exclusion list:
- `vendor/` — third-party, not ours
- `composer.lock` — the only legitimate `bhr` reference would be a stale package-name entry from before Chunk 2; if it appears, Chunk 2 didn't take effect, abort.
- `.phpunit.result.cache` — should not exist post-Chunk 1
- `docs/superpowers/` — historical brainstorming artifacts that legitimately reference the old names; out of scope

If any unexpected match appears, do NOT commit — fix the source first.

- [ ] **Step 2: Run full verification suite**

```bash
composer dump-autoload
composer test
composer cs:check
```

All three must pass.

### Task 3.6: Commit

- [ ] **Step 1: Stage and commit**

```bash
git add README.md CLAUDE.md docs/security-best-practices.md specs/steps-to-complete-version-1.md
git status
```

Verify only those four files are staged.

```bash
git commit -m "docs: update prose references from BHR to CCGLabs

Refreshes README badges to point at the CCG-Labs repo, updates the
install command, code samples, and prose mentions across README,
CLAUDE.md, security-best-practices, and the version-1 spec."
```

---

## Post-flight (manual, out of band)

These steps are NOT part of this plan but are required for the migration to be fully complete from the user's perspective:

1. **Push to origin** — once you've verified the three commits look right, `git push` to the new `CCG-Labs/ccglabs-router` remote. Verify `git remote -v` shows the new URL first.
2. **Mark `bhr/router` abandoned on Packagist** — in the Packagist UI for `bhr/router`, click "abandon" and enter `ccglabs/router` as the replacement.
3. **Submit `ccglabs/router` to Packagist** — register the new package URL.
4. **Verify CI badges resolve** — visit the README on GitHub and confirm both badges render (workflows must have run at least once on the new repo).

---

## Risks recap (from spec)

- **`composer update --lock` may report dependency upgrades** if a transitive dep released a new minor version since the lockfile was last refreshed. If that happens, abort Chunk 2 Task 2.2 and use `composer install --no-update-with-dependencies` semantics or revert and discuss with user before proceeding.
- **Hidden references in untracked files** are caught by the `git ls-files`-scoped grep in Task 3.5.
- **CI badges break temporarily** until the new repo's first workflow run completes. Cosmetic, accepted.
