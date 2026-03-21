<?php

declare(strict_types=1);

/**
 * @see https://github.com/ace-of-aces/intellipest
 */

namespace {

    /**
     * @param-closure-this \LevelUp\Experience\Tests\TestCase $closure
     */
    function afterEach(?Closure $closure = null): Pest\PendingCalls\AfterEachCall {}

    /**
     * @param-closure-this \LevelUp\Experience\Tests\TestCase $closure
     */
    function beforeEach(?Closure $closure = null): Pest\PendingCalls\BeforeEachCall {}

    /**
     * @param-closure-this \LevelUp\Experience\Tests\TestCase $closure
     *
     * @return Pest\PendingCalls\TestCall|LevelUp\Experience\Tests\TestCase
     */
    function test(?string $description = null, ?Closure $closure = null): Pest\PendingCalls\TestCall {}

    /**
     * @param-closure-this \LevelUp\Experience\Tests\TestCase $closure
     *
     * @return Pest\PendingCalls\TestCall|LevelUp\Experience\Tests\TestCase
     */
    function it(string $description, ?Closure $closure = null): Pest\PendingCalls\TestCall {}

}

namespace Pest {

    /**
     * @method self toBeCarbon(string $expected, ?string $format = null)
     */
    class Expectation {}

}

namespace Pest\Expectations {

    /**
     * @method self toBeCarbon(string $expected, ?string $format = null)
     */
    class OppositeExpectation {}

}
