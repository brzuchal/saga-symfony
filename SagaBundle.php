<?php

declare(strict_types=1);

namespace Brzuchal\SagaBundle;

use Brzuchal\SagaBundle\DependencyInjection\SagaExtension;
use Brzuchal\SagaBundle\DependencyInjection\TransformFactoriesToClosuresPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SagaBundle extends Bundle
{
    public function getContainerExtension(): Extension
    {
        return new SagaExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TransformFactoriesToClosuresPass());
    }
}
