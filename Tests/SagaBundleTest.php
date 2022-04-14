<?php

declare(strict_types=1);

namespace Brzuchal\SagaBundle\Tests;

use Brzuchal\Saga\SagaRepositoryFactory;
use Brzuchal\Saga\Tests\Fixtures\AttributedFoo;
use Brzuchal\Saga\Tests\Fixtures\FooMessage;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SagaBundleTest extends KernelTestCase
{
    // phpcs:ignore
    protected static $class = TestKernel::class;
    protected SagaRepositoryFactory $repositoryFactory;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        \assert($container instanceof ContainerInterface);
        $this->repositoryFactory = $container->get(SagaRepositoryFactory::class);
    }

    public function testCreate(): void
    {
        $repository = $this->repositoryFactory->create(AttributedFoo::class);
        $this->assertTrue($repository->supports(new FooMessage()));
    }
}
