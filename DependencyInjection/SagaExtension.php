<?php

declare(strict_types=1);

namespace Brzuchal\SagaBundle\DependencyInjection;

use Brzuchal\Saga\Mapping\AttributeMappingDriver;
use Brzuchal\Saga\Mapping\SagaFactory;
use Brzuchal\Saga\SagaManager;
use Brzuchal\Saga\Store\DoctrineSagaStore;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function sprintf;

final class SagaExtension extends Extension
{
    /**
     * @psalm-param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('saga.php');

        foreach ($config['stores'] as $storeName => $storeConfig) {
            $repositoryFactoryId = \sprintf('brzuchal_saga.%s_store', $storeName);
            $storeConfig['options'] ??= [];
            ['driver' => $driverName, 'options' => $driverOptions] = $storeConfig;
            $storeDriverService = match ($driverName) {
                'doctrine' => $this->createDoctrineStoreService(
                    $driverOptions,
                    $container->register($repositoryFactoryId),
                ),
                default => throw new InvalidArgumentException(\sprintf(
                    'Unsupported store driver, given: %s',
                    $driverName,
                )),
            };
        }

        foreach ($config['mappings'] as $class => $mapping) {
            $mapping['type'] ??= 'attribute';
            $container->getDefinition(\sprintf('brzuchal_saga.%s_store', $mapping['store']))
                ->addTag('brzuchal_saga.store', ['class' => $class]);

            $classes = $this->extractMessageClasses($class);
            if (empty($classes)) {
                continue;
            }

            $definition = $container->register(sprintf('brzuchal_saga.manager.%s', $class), SagaManager::class)
                ->setFactory([new Reference('brzuchal_saga.manager_factory'), 'managerForClass'])
                ->setArgument(0, $class);
            foreach ($classes as $messageClass) {
                $definition->addTag('messenger.message_handler', ['handles' => $messageClass]);
            }
        }

        $container->registerAttributeForAutoconfiguration(
            SagaFactory::class,
            static function (ChildDefinition $definition, SagaFactory $attr, \Reflector $reflector): void {
                $class = $attr->class;
                $method = null;
                if (empty($class) && $reflector instanceof \ReflectionMethod) {
                    $returnType = $reflector->getReturnType();
                    // TODO: remove temporary simplification
                    \assert($returnType instanceof \ReflectionNamedType);
                    $class = $returnType->getName();
                    $method = $reflector->getName();
                }

                if (empty($class) && $reflector instanceof \ReflectionClass && $reflector->hasMethod('__invoke')) {
                    $returnType = $reflector->getMethod('__invoke')->getReturnType();
                    // TODO: remove temporary simplification
                    \assert($returnType instanceof \ReflectionNamedType);
                    $class = $returnType->getName();
                    $method = '__invoke';
                }

                $definition->addTag('brzuchal_saga.factory', ['class' => $class, 'method' => $method]);
            },
        );
    }

    /**
     * @psalm-param array<array-key, string> $options
     */
    protected function createDoctrineStoreService(array $options, Definition $service): Definition
    {
        $options['connection'] ??= 'default';

        return $service->setClass(DoctrineSagaStore::class)
            ->setArgument(0, new Reference(sprintf('doctrine.dbal.%s_connection', $options['connection'])))
            ->setArgument(1, new Reference('serializer'));
    }

    public function getAlias(): string
    {
        return 'brzuchal_saga';
    }

    /**
     * @psalm-return array<array-key, class-string>
     */
    private function extractMessageClasses(string $class): array
    {
        return \array_merge(...\array_map(
            static fn ($method) => $method->getTypes(),
            (new AttributeMappingDriver())->extractMethods(ReflectionClass::createFromName($class)),
        ));
    }
}
