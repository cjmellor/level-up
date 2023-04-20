<?php

test('no debugging methods are being used')
    ->expect(value: ['dd', 'ddd', 'dump'])
    ->each
    ->not
    ->toBeUsed();
