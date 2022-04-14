<?php

declare(strict_types=1);

namespace Brzuchal\SagaBundle\Tests\Fixtures;

use Exception;

class BarMessage
{
    public function __construct(
        public readonly string $bar = 'bar',
        public readonly \Throwable|null $exception = null,
    ) {
    }
}
