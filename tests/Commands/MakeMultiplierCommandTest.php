<?php

declare(strict_types=1);

test(description: 'Make multiplier command', closure: function (): void {
    $this->artisan(command: 'level-up:multiplier')
        ->expectsQuestion(question: 'What should the multiplier be named?', answer: 'IsMonthDecember')
        ->assertExitCode(exitCode: 0);

    $this->assertFileExists(filename: app_path(path: 'Multipliers/IsMonthDecember.php'));
});
