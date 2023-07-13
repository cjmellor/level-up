[![Latest Version on Packagist](https://img.shields.io/packagist/v/cjmellor/level-up?color=rgb%2856%20189%20248%29&label=release&style=for-the-badge)](https://packagist.org/packages/cjmellor/level-up)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/cjmellor/level-up/run-tests.yml?branch=main&label=tests&style=for-the-badge&color=rgb%28134%20239%20128%29)](https://github.com/cjmellor/level-up/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cjmellor/level-up.svg?color=rgb%28249%20115%2022%29&style=for-the-badge)](https://packagist.org/packages/cjmellor/level-up)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/cjmellor/level-up/php?color=rgb%28165%20180%20252%29&logo=php&logoColor=rgb%28165%20180%20252%29&style=for-the-badge)
![Laravel Version](https://img.shields.io/badge/laravel-^10-rgb(235%2068%2050)?style=for-the-badge&logo=laravel)

This package allows users to gain experience points (XP) and progress through levels by performing actions on your site. It can provide a simple way to track user progress and implement gamification elements into your application

![Banner](https://banners.beyondco.de/Level%20Up.png?theme=dark&packageManager=composer+require&packageName=cjmellor%2Flevel-up&pattern=ticTacToe&style=style_1&description=Enable+gamification+via+XP%2C+levels%2C+leaderboards%2C+achievements%2C+and+dynamic+multipliers&md=1&showWatermark=0&fontSize=100px&images=puzzle&widths=auto)

# Installation

You can install the package via composer:

```
composer require cjmellor/level-up
```

You can publish and run the migrations with:

```
php artisan vendor:publish --tag="level-up-migrations"
php artisan migrate
```

You can publish the config file with:

```
php artisan vendor:publish --tag="level-up-config"
```

This is the contents of the published config file:

```php
return [
    /*
    |--------------------------------------------------------------------------
    | User Foreign Key
    |--------------------------------------------------------------------------
    |
    | This value is the foreign key that will be used to relate the Experience model to the User model.
    |
     */
    'user' => [
        'foreign_key' => 'user_id',
        'model' => App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Experience Table
    |--------------------------------------------------------------------------
    |
    | This value is the name of the table that will be used to store experience data.
    |
     */
    'table' => 'experiences',

    /*
    |-----------------------------------------------------------------------
    | Starting Level
    |-----------------------------------------------------------------------
    |
    | The level that a User starts with.
    |
    */
    'starting_level' => 1,

    /*
    |-----------------------------------------------------------------------
    | Multiplier Paths
    |-----------------------------------------------------------------------
    |
    | Set the path and namespace for the Multiplier classes.
    |
    */
    'multiplier' => [
        'enabled' => env(key: 'MULTIPLIER_ENABLED', default: true),
        'path' => env(key: 'MULTIPLIER_PATH', default: app_path(path: 'Multipliers')),
        'namespace' => env(key: 'MULTIPLIER_NAMESPACE', default: 'App\\Multipliers\\'),
    ],

    /*
    |-----------------------------------------------------------------------
    | Level Cap
    |-----------------------------------------------------------------------
    |
    | Set the maximum level a User can reach.
    |
    */
    'level_cap' => [
        'enabled' => env(key: 'LEVEL_CAP_ENABLED', default: true),
        'level' => env(key: 'LEVEL_CAP', default: 100),
        'points_continue' => env(key: 'LEVEL_CAP_POINTS_CONTINUE', default: true),
    ],

    /*
    | -------------------------------------------------------------------------
    | Audit
    | -------------------------------------------------------------------------
    |
    | Set the audit configuration.
    |
    */
    'audit' => [
        'enabled' => env(key: 'AUDIT_POINTS', default: false),
    ],
];
```

# Usage

## 💯 Experience Points (XP)

> **Note**
> 
> XP is enabled by default. You can disable it in the config

Add the `GiveExperience` trait to your `User` model.

```php
use LevelUp\Experience\Concerns\GiveExperience;

class User extends Model
{
    use GiveExperience;

    // ...
}
```

**Give XP points to a User**

```php
$user->addPoints(10);
```

A new record will be added to the `experiences` table which stores the Users’ points. If a record already exists, it will be updated instead. All new records will be given a `level_id` of `1`.

**Deduct XP points from a User**

```php
$user->deductPoints(10);
```

**Set XP points to a User**

For an event where you just want to directly add a certain amount of points to a User. Points can only be ***set*** if the User has an Experience Model.

```php
$user->setPoints(10);
```

**Retrieve a Users’ points**

```php
$user->getPoints();
```

### Multipliers

Point multipliers can be used to modify the experience point value of an event by a certain multiplier, such as doubling or tripling the point value. This can be useful for implementing temporary events or promotions that offer bonus points.

To get started, you can use an Artisan command to crease a new Multiplier.

```bash
php artisan level-up:multiplier IsMonthDecember
```

This will create a file at `app\Multipliers\IsMonthDecember.php`.

Here is how the class looks:

```php
<?php

namespace LevelUp\Experience\Tests\Fixtures\Multipliers;

use LevelUp\Experience\Contracts\Multiplier;

class IsMonthDecember implements Multiplier
{
    public bool $enabled = true;
    
    public function qualifies(array $data): bool
    {
        return now()->month === 12;
    }

    public function setMultiplier(): int
    {
        return 2;
    }
}
```

Multipliers are enabled by default, but you can change the `$enabled` variable to `false` so that it won’t even run.

The `qualifies` method is where you put your logic to check against and multiply if the result is true.

This can be as simple as checking that the month is December.

```php
public function qualifies(array $data): bool
{
    return now()->month === 12;
}
```

or passing extra data along to check against. This is a bit more complex.

You can pass extra data along when you’re adding points to a User. Any enabled Multiplier can then use that data to check against.

```php
$user
    ->withMultiplierData([
        'event_id' => 222,
    ])
    ->addPoints(10);

//

public function qualifies(array $data): bool
{
    return isset($data['event_id']) && $data['event_id'] === 222;
}
```

The `setMultiplier` method expects an `int` which is the number it will be multiplied by.

**Multiply Manually**

You can skip this altogether and just multiply the points manually if you desire.

```php
$user->addPoints(
    amount: 10, 
    multiplier: 2
);
```

### Events

**PointsIncrease** - When points are added.

```php
public int $pointsAdded,
public int $totalPoints,
public string $type,
public ?string $reason,
public Model $user,
```

**PointsDecreased** - When points are decreased.

```php
public int $pointsDecreasedBy,
public int $totalPoints,
```

## ⬆️ Levelling

### Set-up your levelling structure

The package has a handy facade to help you create your levels.

```php
Level::add(
    ['level' => 1, 'next_level_experience' => null],
    ['level' => 2, 'next_level_experience' => 100],
    ['level' => 3, 'next_level_experience' => 250],
);
```

**Level 1** should always be `null` for the `next_level_experience` as it is the default starting point.

As soon as a User gains the correct amount of points listed for the next level, they will level-up.

> **Example:** a User gains 50 points, they’ll still be on Level 1, but gets another 50 points, so the User will now move onto Level 2

**See how many points until next level**

```php
$user->nextLevelAt();
```

**Get the Users’ current Level**

```php
$user->getLevel();
```

### Level Cap

A level cap sets the maximum level that a user can reach. Once a user reaches the level cap, they will not be able to gain any more levels, even if they continue to earn experience points. The level cap is enabled by default and capped to level `100`. These options can be changed in the packages config file at `config/level-up.php` or by adding them to your `.env` file.

```
LEVEL_CAP_ENABLED=
LEVEL_CAP=
LEVEL_CAP_POINTS_CONTINUE
```

By default, even when a user hits the level cap, they will continue to earn experience points. To freeze this, so points do not increase once the cap is hit, turn on the `points_continue` option in the config file, or set it in the `.env`.

### Events

**UserLevelledUp** - When a User levels-up

```php
public Model $user,
public int $level
```

## 🏆 Achievements

This a feature that allows you to recognise and reward users for completing specific tasks or reaching certain milestones. You can define your own achievements and criteria for earning them.  Achievements can be static or have progression. Static meaning the achievement can be earned instantly. Achievements with progression can be earned in increments, like an achievement can only be obtained once the progress is 100% complete.

### Creating Achievements

There is no built in methods for creating achievements, there is just an `Achievement` model that you can use as normal:

```php
Achievement::create([
    'name' => 'Hit Level 20',
    'is_secret' => false,
    'description' => 'When a User hits Level 20',
    'image' => 'storage/app/achievements/level-20.png',
]);
```

### Gain Achievement

To use Achievements in your User mode, you must first add the Trait.

```php
// App\Models\User.php

use LevelUp\Experience\Concerns\HasAchievements;

class User extends Authenticable
{
	use HasAchievements;
	
	// ...
}
```

Then you can start using its methods, like to grant a User an Achievement:

```php
$achievement = Achievement::find(1);

$user->grantAchievement($achievement);
```

To retrieve your Achievements:

```php
$user->achievements;
```

### Add progress to Achievement

```php
$user->grantAchievement(
    achievement: $achievement, 
    progress: 50 // 50%
);
```

> **Note**
> 
> an Achievements progress is capped to 100%

### Check Achievement Progression

Check at what progression your Achievements are at.

```php
$user->achievementsWithProgress()->get();
```

Check Achievements that have a certain amount of progression:

```php
$user->achievements
    ->first()
    ->pivot()
    ->withProgress(25)
    ->get();
```

### Increase Achievement Progression

You can increment the progression of an Achievement up-to 100.

```php
$user->incrementAchievementProgress(
    achievement: $achievement, 
    amount: 10
);
```

A `AchievementProgressionIncreased` Event runs on method execution.

### Secret Achievements

Secret achievements are achievements that are hidden from users until they are unlocked.

Secret achievements are made secret when created. If you want to make a non-secret Achievement secret, you can just update the Model.

```php
$achievement->update(['is_secret' => true]);
```

You can retrieve the secret Achievements.

```php
$user->secretAchievements;
```

To view *********all********* Achievements, both secret and non-secret:

```php
$user->allAchievements;
```

### Events

**AchievementAwarded** - When an Achievement is attached to the User

```php
public Achievement $achievement,
public Model $user,
```

> **Note**
> 
> This event only runs if the progress of the Achievement is 100%

**AchievementProgressionIncreased** - When a Users’ progression for an Achievement is increased.

```php
public Achievement $achievement,
public Model $user,
public int $amount,
```

## 📈 Leaderboard

The package also includes a leaderboard feature to track and display user rankings based on their experience points.

The Leaderboard comes as a Service.

```php
Leaderboard::generate();
```

This generates a User model along with it’s Experience and Level data and ordered by the Users’ experience points.

> The Leaderboard is very basic and has room for improvement
>

## 🔍 Auditing

You can enable an Auditing feature in the config, which keeps a track each time a User gains points, levels up and what level to.

The `type` and `reason` fields will be populated automatically based on the action taken, but you can overwrite these when adding points to a User

```php
$user->addPoints(
    amount: 50,
    multiplier: 2,
    type: AuditType::Add->value,
    reason: "Some reason here",
);
```

**View a Users’ Audit Experience**

```php
$user->experienceHistory;
```

# Testing

```
composer test
```

# Changelog

Please see [CHANGELOG](notion://www.notion.so/CHANGELOG.md) for more information on what has changed recently.

# License

The MIT License (MIT). Please see [License File](notion://www.notion.so/LICENSE.md) for more information.
