# Contributing

Thank you for considering contributing to `level-up`. This document covers the practical things you need to know before opening a pull request.

## Which branch?

`level-up` uses a Laravel-style branching model. **Pick the right branch for your change** — PRs that target the wrong branch will be asked to retarget before review.

| Your change | Target branch | PR title prefix |
|---|---|---|
| Bug fix for the current stable release | `2.x` | `[2.x]` |
| Minor, fully backward-compatible feature for current stable | `2.x` | `[2.x]` |
| New feature with breaking changes | `main` | `[3.x]` |
| Any change that affects public API signatures, config keys, or schema | `main` | `[3.x]` |
| Security fix | email `chris@mellor.pizza` first — do not open a public PR |

`main` is always the staging ground for the **next** major version. When v3.0.0 ships, `main` will be cut to a `3.x` maintenance branch and `main` itself will reset to v4 staging.

If you're not sure which branch fits, open an issue first and ask.

### Why the `[N.x]` PR title prefix?

It tells reviewers at a glance which major version your PR is destined for, independent of which branch you targeted. This is the same convention the Laravel framework uses.

## Reporting bugs

Open a [GitHub issue](https://github.com/cjmellor/level-up/issues) with:

- The version of `level-up`, Laravel, and PHP you're using.
- The database driver (SQLite / MySQL / PostgreSQL) and version.
- A minimal reproduction — a failing test case is ideal.
- The actual vs. expected behavior.

## Reporting security vulnerabilities

Do not open a public issue or PR. Email `chris@mellor.pizza` directly with details. You'll receive an acknowledgment within a few days.

## Setting up the repo locally

```bash
git clone https://github.com/cjmellor/level-up.git
cd level-up
composer install
```

The package's own tests run against SQLite by default and require no further setup.

## Tests, linting, and formatting

Before opening a pull request, run the full check suite locally:

```bash
composer test                # Full suite: lint, type coverage, static analysis, tests
composer test:lint           # Pint + Rector checks (dry-run)
composer test:type-coverage  # Pest type coverage (must be 100%)
composer test:types          # PHPStan static analysis (Larastan, level 5)
composer test:unit           # Pest with line coverage (must be exactly 100%)
```

To auto-fix style and refactoring findings, run `vendor/bin/pint` and `vendor/bin/rector`.

CI runs the same suite and will reject PRs that fail any part of it — including the
100% line- and type-coverage requirements, so new code must ship with tests.

## Pull request checklist

- [ ] Targeting the correct branch (see table above).
- [ ] PR title starts with `[2.x]` or `[3.x]` to indicate destination major.
- [ ] PR description explains the **why**, not just the what.
- [ ] Tests added or updated for the change.
- [ ] `composer test` passes (includes linting, static analysis, and the 100% coverage gates).
- [ ] If the change affects user-facing behavior: `CHANGELOG.md` updated under `## [Unreleased]`.
- [ ] If the change is a breaking change: `UPGRADE.md` updated with a "Likelihood Of Impact" entry.

## Coding style and refactoring

Code style is enforced by [Laravel Pint](https://laravel.com/docs/pint) and [Rector](https://getrector.com) — run `composer test:lint` to check both, and `vendor/bin/pint` / `vendor/bin/rector` to apply fixes. CI will reject PRs with unresolved violations.

## Working on a fork

```bash
# Add the upstream remote
git remote add upstream https://github.com/cjmellor/level-up.git

# Keep your fork in sync
git fetch upstream
git checkout main
git merge upstream/main
git push origin main

# For a v2.x bug fix, work from the 2.x branch instead:
git checkout -b fix/my-bug-fix upstream/2.x
```

## License

By contributing, you agree that your contributions will be licensed under the MIT License (see [LICENSE.md](LICENSE.md)).
