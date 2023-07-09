<?php

test(description: 'Make multiplier command', closure: function () {
    $this->artisan(command: 'level-up:multiplier')
        ->expectsQuestion(question: 'What should the multiplier be named?', answer: 'isMonthDecember')
        ->assertExitCode(exitCode: 0);

    $this->assertFileExists(filename: app_path(path: 'Multipliers/isMonthDecember.php'));
});
