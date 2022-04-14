<?php

declare(strict_types=1);

namespace Brzuchal\SagaBundle\Tests\Fixtures;

use Brzuchal\Saga\Mapping\Saga;
use Brzuchal\Saga\Mapping\SagaEnd;
use Brzuchal\Saga\Mapping\SagaMessageHandler;
use Brzuchal\Saga\Mapping\SagaStart;
use Brzuchal\Saga\SagaLifecycle;

#[Saga]
class AttributedFoo
{
    public string|null $foo = null;
    public bool $fooInvoked = false;
    public string|null $bar = null;
    public bool $barInvoked = false;
    public string|null $baz = null;
    public bool $bazInvoked = false;

    #[SagaStart,SagaMessageHandler(key: 'keyInt', property: 'id')]
    public function foo(FooMessage $message): void
    {
        $this->foo = $message->id;
        $this->fooInvoked = true;
    }

    #[SagaMessageHandler(key: 'keyString', property: 'bar')]
    public function bar(BarMessage $message, SagaLifecycle $lifecycle): void
    {
        $this->bar = $message->bar;
        $this->barInvoked = true;
    }

    #[SagaEnd,SagaMessageHandler(key: 'keyInt', property: 'id')]
    public function baz(BazMessage $message, SagaLifecycle $lifecycle): void
    {
        $this->baz = $message->id;
        $this->bazInvoked = true;
    }
}
