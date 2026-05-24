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
composer test         # Pest test suite
composer lint         # Laravel Pint (code style)
composer format:dry   # Rector dry-run (refactoring suggestions)
composer format       # Apply Rector refactorings
```

CI runs the same commands and will reject PRs that fail any of them.

## Pull request checklist

- [ ] Targeting the correct branch (see table above).
- [ ] PR title starts with `[2.x]` or `[3.x]` to indicate destination major.
- [ ] PR description explains the **why**, not just the what.
- [ ] Tests added or updated for the change.
- [ ] `composer test`, `composer lint`, and `composer format:dry` all pass.
- [ ] If the change affects user-facing behavior: `CHANGELOG.md` updated under `## [Unreleased]`.
- [ ] If the change is a breaking change: `UPGRADE.md` updated with a "Likelihood Of Impact" entry.

## Coding style and refactoring

Code style is enforced by [Laravel Pint](https://laravel.com/docs/pint) — run `composer lint` to check (and auto-fix). [Rector](https://getrector.com) handles refactoring suggestions — run `composer format:dry` to preview changes and `composer format` to apply them. CI will reject PRs with unresolved Pint violations.

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
