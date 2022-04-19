<?php

declare(strict_types=1);

namespace Brzuchal\SagaBundle\DependencyInjection;

use Closure;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\DependencyInjection\Loader\Configurator\iterator;

final class TransformFactoriesToClosuresPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $factories = [];
        foreach ($container->findTaggedServiceIds('brzuchal_saga.factory') as $serviceId => $tags) {
            foreach ($tags as $index => $tag) {
                $id = $serviceId . '.' . $tag['method'];
                $container->register($id, Closure::class)
                    ->setFactory([Closure::class, 'fromCallable'])
                    ->setArgument(0, new Reference($serviceId));
                $factories[$tag['class']] = new Reference($id);
            }
        }

        /** @psalm-suppress UndefinedFunction */
        $container->getDefinition('brzuchal_saga.repository_factory')
            ->replaceArgument(1, iterator($factories));
    }
}
