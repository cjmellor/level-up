## Level Up Package

- This application uses `cjmellor/level-up` for gamification — experience points (XP), levels, tiers, achievements, streaks, multipliers, leaderboards, and auditing.
- Users gain XP via `addPoints()`, automatically progress through levels, and earn named tier status (Bronze, Silver, Gold, etc.) based on XP thresholds.
- Achievements can be instant or progressive (0-100%), optionally gated by tier. Streaks track consecutive daily activities with freeze support.
- Refer to the package README for Laravel-specific patterns for working with gamification.

### Key Traits

- `LevelUp\Experience\Concerns\GiveExperience` — XP operations: `addPoints()`, `deductPoints()`, `setPoints()`, `getPoints()`, `getLevel()`, `nextLevelAt()`, `levelUp()`.
- `LevelUp\Experience\Concerns\HasAchievements` — Achievement operations: `grantAchievement()`, `revokeAchievement()`, `incrementAchievementProgress()`.
- `LevelUp\Experience\Concerns\HasStreaks` — Streak operations: `recordStreak()`, `resetStreak()`, `freezeStreak()`, `unFreezeStreak()`.
- `LevelUp\Experience\Concerns\HasTiers` — Tier operations: `getTier()`, `getNextTier()`, `tierProgress()`, `nextTierAt()`, `isAtTier()`, `isAtOrAboveTier()`.
- `LevelUp\Experience\Concerns\HasChallenges` — Challenge operations: `enrollInChallenge()`, `unenrollFromChallenge()`, `getChallengeProgress()`, `getChallengeCompletionPercentage()`.

### Key Models

- `LevelUp\Experience\Models\Level` — Level definitions with XP thresholds. Created via `Level::add(...)`.
- `LevelUp\Experience\Models\Tier` — Named status brackets with XP thresholds and optional metadata. Created via `Tier::add(...)`.
- `LevelUp\Experience\Models\Achievement` — Unlockable rewards with optional progress and tier gating.
- `LevelUp\Experience\Models\Activity` — Named activities for streak tracking.
- `LevelUp\Experience\Models\Experience` — Stores a user's XP total, level, and tier.
- `LevelUp\Experience\Models\Multiplier` — Database-backed point multipliers with scoping, scheduling, and stacking strategies.
- `LevelUp\Experience\Models\Challenge` — Multi-condition goals with enrollment, progress tracking, and rewards.

### Key Enums

- `LevelUp\Experience\Enums\AuditType` — Audit trail types: `Add`, `Remove`, `Reset`, `LevelUp`, `TierUp`, `TierDown`.
- `LevelUp\Experience\Enums\TierDirection` — Tier change direction: `Promoted`, `Demoted`.

### Setup and Usage

<code-snippet name="Basic Gamification Setup" lang="php">
use LevelUp\Experience\Concerns\GiveExperience;
use LevelUp\Experience\Concerns\HasAchievements;
use LevelUp\Experience\Concerns\HasStreaks;
use LevelUp\Experience\Concerns\HasTiers;

class User extends Authenticatable
{
    use GiveExperience;     // Required — XP and levels
    use HasAchievements;    // Optional — achievements
    use HasStreaks;          // Optional — streaks
    use HasTiers;           // Optional — tiers
    use HasChallenges;      // Optional — challenges
}

// Define levels and tiers
Level::add(
    ['level' => 1, 'next_level_experience' => null],
    ['level' => 2, 'next_level_experience' => 100],
    ['level' => 3, 'next_level_experience' => 250],
);

Tier::add(
    ['name' => 'Bronze', 'experience' => 0],
    ['name' => 'Silver', 'experience' => 500],
    ['name' => 'Gold', 'experience' => 2000],
);

// Award XP — auto-levels and auto-tiers
$user->addPoints(150);

// Query state
$user->getLevel();       // 2
$user->getTier();        // Bronze
$user->tierProgress();   // 30 (percent toward Silver)
</code-snippet>

### Events

All events use the `Dispatchable` trait:

- `PointsIncreased` — Points added via `addPoints()`
- `PointsDecreased` — Points removed via `deductPoints()`
- `UserLevelledUp` — User reaches a new level (fires per level gained)
- `UserTierUpdated` — Tier promotion or demotion
- `AchievementAwarded` — Achievement granted (at 100% progress)
- `AchievementRevoked` — Achievement revoked
- `AchievementProgressionIncreased` — Achievement progress incremented
- `StreakStarted` — First streak record for an activity
- `StreakIncreased` — Consecutive day recorded
- `StreakBroken` — Day skipped, streak reset
- `StreakFrozen` — Streak frozen
- `StreakUnfroze` — Streak unfrozen
- `MultiplierApplied` — DB or inline multiplier applied during `addPoints()`
- `ChallengeCompleted` — Challenge conditions all met, rewards dispatched
- `ChallengeEnrolled` — User enrolled in a challenge
- `ChallengeUnenrolled` — User unenrolled from a challenge
