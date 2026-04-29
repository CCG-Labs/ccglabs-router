# Design: Migrate `bhr` → `ccglabs`

**Date:** 2026-04-29
**Status:** Approved (pending implementation plan)
**Author:** Brian Reich

## Context

The repository moved from a personal GitHub account (`iambrianreich/bhr-router`) to an organization (`CCG-Labs/ccglabs-router`). The package is currently `bhr/router` with PHP namespace `BHR\Router\*`. It must be renamed to `ccglabs/router` with namespace `CCGLabs\Router\*`. The package is at version 1.0 and has no known downstream consumers, so a clean break is acceptable.

## Goals

- Rename the Composer package, PHP namespaces, test layout, and all prose references from `bhr`/`BHR` to `ccglabs`/`CCGLabs`
- Update GitHub URLs to point to the new org/repo
- Bump to version `2.0.0` to signal the breaking namespace change
- Leave the codebase in a PSR-4-correct state (fixing pre-existing test layout violations encountered during the rename)

## Non-Goals

- Backward compatibility shims (`class_alias`, deprecated namespace aliases, etc.). Clean break — anyone on `bhr/router` keeps the old version
- Marking `bhr/router` abandoned on Packagist (manual step performed by the user outside this work)
- Registering `ccglabs/router` on Packagist (manual step)
- Refactoring beyond what the rename and PSR-4 fixes require
- Changes to behavior, public API shape, dependencies, or tooling versions

## Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Namespace casing | `CCGLabs\Router` | Matches existing convention (`BHR\Router\HTTP` already uses uppercase acronym) |
| Composer name | `ccglabs/router` | Direct mapping; defer disambiguation until needed |
| GitHub URL | `CCG-Labs/ccglabs-router` | Hyphen in org name preserved as user confirmed |
| Version | `2.0.0` | Breaking change (namespace rename) requires major bump |
| Backward compat | None | No downstream consumers; aliases would clutter the new codebase |
| Pre-existing test PSR-4 issues | Fix in scope | Touching every test file anyway; otherwise we port known-broken state |
| Dependabot reviewer | Remove | User confirmed |

## Scope Summary

| Surface | From | To |
|---|---|---|
| Composer package name | `bhr/router` | `ccglabs/router` |
| Composer version | `1.0` | `2.0.0` |
| Source namespace | `BHR\Router\*` | `CCGLabs\Router\*` |
| Test namespace | `Tests\BHR\*` | `Tests\CCGLabs\*` |
| Source dir | `src/` (unchanged) | `src/` |
| Test dir | `tests/BHR/...` | `tests/CCGLabs/Router/...` |
| GitHub repo | `iambrianreich/bhr-router` | `CCG-Labs/ccglabs-router` |
| Packagist (old) | `bhr/router` | abandoned → `ccglabs/router` (manual, out of scope) |

## Files Touched

### Source (10 files)

All files under `src/` need namespace, `use`, and file-level docblock updates:

- `src/Application.php`
- `src/IRoute.php`
- `src/IParameterizedRoute.php`
- `src/Exceptions/RouteHandlerNotFoundException.php`
- `src/HandlerLocators/DefaultHandlerLocator.php`
- `src/HandlerLocators/IHandlerLocator.php`
- `src/HTTP/Verb.php`
- `src/RequestHandlers/CallableRequestHandler.php`
- `src/Routes/StringRoute.php`
- `src/Routes/TokenizedRoute.php` (also fix template artifact in docblock: `BHR\$CLASS` → `CCGLabs\Router\Routes\TokenizedRoute`)

### Tests (target layout)

Final layout under `tests/CCGLabs/Router/` (mirrors `src/`):

```
tests/CCGLabs/Router/
├── ApplicationTest.php
├── IRouteTest.php
├── IParameterizedRouteTest.php
├── IHandlerLocatorTest.php
├── Exceptions/RouteHandlerNotFoundExceptionTest.php
├── HandlerLocators/DefaultHandlerLocatorTest.php
├── HTTP/VerbTest.php
├── RequestHandlers/CallableRequestHandlerTest.php
└── Routes/
    ├── StringRouteTest.php
    └── TokenizedRouteTest.php
```

Test file moves and namespace fixes (folds in pre-existing PSR-4 violations):

| Current path | New path | Notes |
|---|---|---|
| `tests/BHR/Router/ApplicationTest.php` | `tests/CCGLabs/Router/ApplicationTest.php` | Canonical version |
| `tests/BHR/ApplicationTest.php` | **DELETE** | Duplicate; uses wrong `BHR\Router` namespace (collides with source) |
| `tests/BHR/IRouteTest.php` | `tests/CCGLabs/Router/IRouteTest.php` | File location was wrong; namespace was already `Tests\BHR\Router` |
| `tests/BHR/Router/IParameterizedRouteTest.php` | `tests/CCGLabs/Router/IParameterizedRouteTest.php` | |
| `tests/BHR/Router/IHandlerLocatorTest.php` | `tests/CCGLabs/Router/IHandlerLocatorTest.php` | |
| `tests/BHR/Router/Exceptions/RouteHandlerNotFoundExceptionTest.php` | `tests/CCGLabs/Router/Exceptions/RouteHandlerNotFoundExceptionTest.php` | |
| `tests/BHR/HandlerLocators/DefaultHandlerLocatorTest.php` | `tests/CCGLabs/Router/HandlerLocators/DefaultHandlerLocatorTest.php` | Fix typo `Test\BHR\Router\HandlerLocators` → `Tests\CCGLabs\Router\HandlerLocators` |
| `tests/BHR/HTTP/VerbTest.php` | `tests/CCGLabs/Router/HTTP/VerbTest.php` | Namespace `Tests\BHR\HTTP` → `Tests\CCGLabs\Router\HTTP` (mirrors source) |
| `tests/BHR/Router/RequestHandlers/CallableRequestHandlerTest.php` | `tests/CCGLabs/Router/RequestHandlers/CallableRequestHandlerTest.php` | |
| `tests/BHR/Router/Routes/StringRouteTest.php` | `tests/CCGLabs/Router/Routes/StringRouteTest.php` | |
| `tests/BHR/Router/Routes/TokenizedRouteTest.php` | `tests/CCGLabs/Router/Routes/TokenizedRouteTest.php` | |

After moves, remove the empty `tests/BHR/` tree.

### Configuration (4 files)

- `composer.json`
  - `name`: `bhr/router` → `ccglabs/router`
  - `version`: `1.0` → `2.0.0`
  - `autoload.psr-4`: `BHR\\Router\\` → `CCGLabs\\Router\\`
  - `autoload-dev.psr-4`: `Tests\\BHR\\` → `Tests\\CCGLabs\\Router\\` mapping to `tests/CCGLabs/Router/`
- `composer.lock` — regenerate via `composer update --lock` (no dependency changes)
- `phpunit.xml` — testsuite name `BHR Router Test Suite` → `CCGLabs Router Test Suite`
- `phpcs.xml` — no changes (no namespace refs)

### Docs / meta (4 files)

- `README.md` — title, badge URLs (`iambrianreich/bhr-router` → `CCG-Labs/ccglabs-router`), code samples, `composer require` command, prose
- `CLAUDE.md` — project overview text, namespace structure section, install command
- `docs/security-best-practices.md` — prose references to "BHR Router"
- `specs/steps-to-complete-version-1.md` — historical document; prose updates only (do not rewrite history)
- `.github/dependabot.yml` — remove `iambrianreich` from reviewers (both occurrences)

### Cleanup

- Delete `.phpunit.result.cache` — keys reference old class names; will regenerate on next test run

## Find/Replace Mapping

The migration is mechanically a series of casing-aware substitutions. Each replacement should be applied across all in-scope file types (`*.php`, `*.json`, `*.md`, `*.xml`, `*.yml`):

| Find | Replace |
|---|---|
| `BHR\Router` | `CCGLabs\Router` |
| `BHR\\Router` (escaped, composer.json) | `CCGLabs\\Router` |
| `Tests\BHR` | `Tests\CCGLabs\Router` (note: also adds `\Router` segment) |
| `Tests\\BHR` (escaped) | `Tests\\CCGLabs\\Router` |
| `Test\BHR` (typo) | `Tests\CCGLabs\Router` |
| `bhr/router` | `ccglabs/router` |
| `BHR Router` (prose) | `CCGLabs Router` |
| `iambrianreich/bhr-router` | `CCG-Labs/ccglabs-router` |
| `BHR\$CLASS` (template artifact in TokenizedRoute.php) | `CCGLabs\Router\Routes\TokenizedRoute` |

Some replacements need ordering care: `BHR\\Router` must run before `BHR\Router` only if your tool double-escapes; for raw file contents read by sed/php scripts, the escaped form lives only inside JSON strings, so no ordering issue exists in practice.

## Verification

Run after migration in this order:

1. `composer dump-autoload` — must succeed with no PSR-4 warnings
2. `composer test` — all tests pass with same count as before
3. `composer cs:check` — passes PSR-12
4. `grep -rEi "bhr|iambrianreich" --include='*.php' --include='*.json' --include='*.md' --include='*.xml' --include='*.yml' . | grep -v vendor | grep -v composer.lock | grep -v .phpunit.result.cache` — returns zero matches
5. Visual diff of `composer.lock` — only the package name field should change

## Risks

- **Composer.lock regeneration may pull newer transitive deps.** Mitigation: use `composer update --lock --no-scripts` which only refreshes the lockfile based on the new `composer.json` content hash without changing resolved versions of dependencies. If that's not available, use `composer install` and verify only the `content-hash` and package name change.
- **Hidden references in `.git/hooks/`, IDE configs, or untracked files.** Mitigation: scope verification grep to tracked files only via `git ls-files | xargs grep -i bhr`.
- **CI badges break temporarily** until the GitHub Actions runs on the renamed repo produce green builds at the new URL. Acceptable — purely cosmetic.

## Out of Scope

- Marking `bhr/router` abandoned on Packagist (manual UI step)
- Registering `ccglabs/router` on Packagist (manual UI step)
- Updating any GitHub repo settings, branch protection, secrets
- Anything in `vendor/` (regenerated)
- Changes to dependency versions, PHP version requirement, or tooling

## Implementation Note

The implementation plan (next step) will sequence the changes such that the codebase remains internally consistent at each step (no half-renamed state where autoload would fail), and place verification gates at natural checkpoints.
