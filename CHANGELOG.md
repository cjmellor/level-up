# Changelog

All notable changes to `level-up` will be documented in this file.

## v1.3.0 - 2024-10-24

### What's Changed

* Allow model customisation by @Simoneu01 in https://github.com/cjmellor/level-up/pull/89

### New Contributors

* @Simoneu01 made their first contribution in https://github.com/cjmellor/level-up/pull/89

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v1.2.4...v1.3.0

## v1.2.4 - 2024-07-13

### What's Changed

* build(deps): Bump dependabot/fetch-metadata from 1.6.0 to 2.0.0 by @dependabot in https://github.com/cjmellor/level-up/pull/68
* fix: migrations roll back by @xmuntane in https://github.com/cjmellor/level-up/pull/71
* fix: custom experiences table by @xmuntane in https://github.com/cjmellor/level-up/pull/74
* Remove `level_id` From Table by @cjmellor in https://github.com/cjmellor/level-up/pull/75
* Bump aglipanci/laravel-pint-action from 2.3.1 to 2.4 by @dependabot in https://github.com/cjmellor/level-up/pull/77
* fix Remove 'level_id' migration by @xmuntane in https://github.com/cjmellor/level-up/pull/78
* Bump dependabot/fetch-metadata from 2.0.0 to 2.1.0 by @dependabot in https://github.com/cjmellor/level-up/pull/79
* fix: Wrap logic in intval by @cjmellor in https://github.com/cjmellor/level-up/pull/84
* Bump dependabot/fetch-metadata from 2.1.0 to 2.2.0 by @dependabot in https://github.com/cjmellor/level-up/pull/85
* fix: Users without XP don't show in Leaderboard by @cjmellor in https://github.com/cjmellor/level-up/pull/86

### New Contributors

* @xmuntane made their first contribution in https://github.com/cjmellor/level-up/pull/71

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v1.2.3...v1.2.4

## v1.2.3 - 2024-03-01

### What's Changed

* Fixed one migration problem and  null level handling in getLevel() & getPoints(),  causing some error by @joydeep-bhowmik in https://github.com/cjmellor/level-up/pull/59

### New Contributors

* @joydeep-bhowmik made their first contribution in https://github.com/cjmellor/level-up/pull/59

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v1.2.2...v1.2.3

## v1.2.2 - 2024-02-28

### What's Changed

* adding null safety, when no experience records by @guptarakesh198 in https://github.com/cjmellor/level-up/pull/64

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v1.2.1...v1.2.2

## v1.2.1 - 2024-02-26

### What's Changed

* Update LeaderboardService.php by @guptarakesh198 in https://github.com/cjmellor/level-up/pull/63

### New Contributors

* @guptarakesh198 made their first contribution in https://github.com/cjmellor/level-up/pull/63

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v1.2.0...v1.2.1

## v1.2.0 - 2024-02-21

### What's Changed

* build(deps): Bump stefanzweifel/git-auto-commit-action from 4 to 5 by @dependabot in https://github.com/cjmellor/level-up/pull/51
* fix: Support foreign key configuration throughout by @cjmellor in https://github.com/cjmellor/level-up/pull/52
* build(deps): Bump aglipanci/laravel-pint-action from 2.3.0 to 2.3.1 by @dependabot in https://github.com/cjmellor/level-up/pull/56
* Update run-tests.yml by @imabulhasan99 in https://github.com/cjmellor/level-up/pull/62
* Update composer.json for Laravel 11 support by @imabulhasan99 in https://github.com/cjmellor/level-up/pull/61

### New Contributors

* @imabulhasan99 made their first contribution in https://github.com/cjmellor/level-up/pull/62

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v1.1.0...v1.2.0

## v1.1.0 - 2023-10-08

### What's Changed

- feat: Conditional Multipliers by @cjmellor in https://github.com/cjmellor/level-up/pull/50

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v1.0.1...v1.1.0

## v1.0.1 - 2023-10-04

### What's Changed

- fix: update level_id in experiences table when levelled up by @7OMI in https://github.com/cjmellor/level-up/pull/48

### New Contributors

- @7OMI made their first contribution in https://github.com/cjmellor/level-up/pull/48

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v1.0.0...v1.0.1

## v1.0.0 - 2023-10-02

Package seems stable enough to tag v1.

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.14...v1.0.0

## v0.0.14 - 2023-09-23

### What's Changed

- build(deps): Bump actions/checkout from 3 to 4 by @dependabot in https://github.com/cjmellor/level-up/pull/40
- Update README.md by @mohamedalwhaidi in https://github.com/cjmellor/level-up/pull/42
- Add Listener for PointsDecreased Event by @cjmellor in https://github.com/cjmellor/level-up/pull/46

### New Contributors

- @mohamedalwhaidi made their first contribution in https://github.com/cjmellor/level-up/pull/42

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.13...v0.0.14

## v0.0.13 - 2023-08-31

### What's Changed

- Fire Multiple Levelled Up Events by @cjmellor in https://github.com/cjmellor/level-up/pull/39

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.12...v0.0.13

## v0.0.12 - 2023-08-30

### What's Changed

- Add level calculation and event dispatch on initial experience gain by @cjmellor in https://github.com/cjmellor/level-up/pull/38

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.11...v0.0.12

## v0.0.11 - 2023-08-28

### What's Changed

- Add custom expectation for Carbon instances and level cap validation by @cjmellor in https://github.com/cjmellor/level-up/pull/37

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.10...v0.0.11

## v0.0.10 - 2023-08-23

### What's Changed

- Refactor Tests by @cjmellor in https://github.com/cjmellor/level-up/pull/33

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.9...v0.0.10

## v0.0.9 - 2023-08-21

### What's Changed

- Adds a feature to freeze a streak by @cjmellor in https://github.com/cjmellor/level-up/pull/32

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.8...v0.0.9

## v0.0.8 - 2023-08-20

### What's Changed

- Possible `nextLevelAt()` Bug by @cjmellor in https://github.com/cjmellor/level-up/pull/26
- Missing Test by @cjmellor in https://github.com/cjmellor/level-up/pull/27
- fix: prevent grant of secret achievement twice by @ibrunotome in https://github.com/cjmellor/level-up/pull/31

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.7...v0.0.8

## v0.0.7 - 2023-08-18

### What's Changed

- fix: set timestamps when creating achievements by @ibrunotome in https://github.com/cjmellor/level-up/pull/28
- Streaks by @cjmellor in https://github.com/cjmellor/level-up/pull/29

### New Contributors

- @ibrunotome made their first contribution in https://github.com/cjmellor/level-up/pull/28

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.6...v0.0.7

## v0.0.6 - 2023-08-01

### What's Changed

- fix: Return correct value on next level check by @cjmellor in https://github.com/cjmellor/level-up/pull/22

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.5...v0.0.6

## v0.0.5 - 2023-07-31

### What's Changed

- fix: Relationship Association by @cjmellor in https://github.com/cjmellor/level-up/pull/19
- Support PHP 8.1 by @QuintenJustus in https://github.com/cjmellor/level-up/pull/18

### New Contributors

- @QuintenJustus made their first contribution in https://github.com/cjmellor/level-up/pull/18

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.4...v0.0.5

## 0.0.4 - 2023-07-24

### What's Changed

- Adding configurable user's table to level relationship migration. by @matthewscalf in https://github.com/cjmellor/level-up/pull/16

### New Contributors

- @matthewscalf made their first contribution in https://github.com/cjmellor/level-up/pull/16

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.3...0.0.4

## v0.0.3 - 2023-07-22

### What's Changed

- build(deps-dev): Update rector/rector requirement from ^0.16.0 to ^0.17.6 by @dependabot in https://github.com/cjmellor/level-up/pull/7
- build(deps-dev): Update driftingly/rector-laravel requirement from ^0.17.0 to ^0.21.0 by @dependabot in https://github.com/cjmellor/level-up/pull/6
- fix: Add a Default Level by @cjmellor in https://github.com/cjmellor/level-up/pull/14
- feat: Customise constraints by @cjmellor in https://github.com/cjmellor/level-up/pull/13
- fix: Bypass Multiplier Folder Check by @cjmellor in https://github.com/cjmellor/level-up/pull/15

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.2...v0.0.3

## v0.0.2 - 2023-07-14

### What's Changed

- tests: Update Test Runner by @cjmellor in https://github.com/cjmellor/level-up/pull/5

### New Contributors

- @cjmellor made their first contribution in https://github.com/cjmellor/level-up/pull/5

**Full Changelog**: https://github.com/cjmellor/level-up/compare/v0.0.1...v0.0.2

## v0.0.1 - 2023-07-13

Initial release
