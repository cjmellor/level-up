<?php

namespace LevelUp\Experience\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class LevelExistsException extends Exception
{
    public static function handle(int $levelNumber, Throwable $exception): static
    {
        Log::error($exception->getMessage());

        return new static(message: sprintf('The level with number "%d" already exists.', $levelNumber));
    }
}
