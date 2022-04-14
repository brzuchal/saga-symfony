<?php

declare(strict_types=1);

namespace Brzuchal\SagaBundle\DependencyInjection;

use Brzuchal\Saga\Mapping\AttributeMappingDriver;
use Brzuchal\Saga\Mapping\SagaMetadataFactory;
use Brzuchal\Saga\Repository\MappedRepositoryFactory;
use Brzuchal\Saga\SagaManager;
use Brzuchal\Saga\SagaManagerFactory;
use Brzuchal\Saga\SagaRepositoryFactory;
use Brzuchal\Saga\Store\DoctrineSagaStore;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\AbstractConfigurator;
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
            $repositoryFactoryId = $this->prefixName(\sprintf('stores.%s', $storeName));
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
            $storeDriverService->addTag($this->prefixName('store'), ['name' => $storeName]);
        }

        $managerFactoryId = $this->prefixName('manager_factory');
        $locator = [];
        $metadata = [];
        foreach ($config['mappings'] as $class => $mapping) {
            $mapping['type'] ??= 'attribute';
            $locator[$class] = new Reference($this->prefixName(sprintf('stores.%s', $mapping['store'])));
            $metadata[$mapping['type']] = true;

            $classes = $this->extractMessageClasses($class);
            $id = $this->prefixName(sprintf('manager.%s', $class));
            $definition = $container->register($id, SagaManager::class)
                ->setFactory([new Reference($managerFactoryId), 'managerForClass'])
                ->setArgument(0, $class);
            foreach ($classes as $messageClass) {
                $definition->addTag('messenger.message_handler', ['handles' => $messageClass]);
            }
        }

        $storeLocator = new IteratorArgument(AbstractConfigurator::processValue(
            $locator,
            true,
        ));
        $container->getDefinition('brzuchal_saga.repository_factory')
            ->replaceArgument(0, $storeLocator);

        $metadataDriverTag = $this->prefixName('metadata_driver');
        foreach ($metadata as $type => $enabled) {
            $metadataDriverId = $this->prefixName(sprintf('%s_metadata', $type));
            $container->register($metadataDriverId, AttributeMappingDriver::class)
                ->addTag($metadataDriverTag);
        }
    }

    /**
     * @psalm-param array<array-key, string> $options
     */
    protected function createDoctrineStoreService(array $options, Definition $service): Definition
    {
        $options['connection'] ??= 'default';

        return $service->setClass(DoctrineSagaStore::class)
            ->setArgument(0, new Reference(sprintf('doctrine.dbal.%s_connection', $options['connection'])));
    }

    public function getAlias(): string
    {
        return 'brzuchal_saga';
    }

    private function prefixName(string $name): string
    {
        return \sprintf('%s.%s', $this->getAlias(), $name);
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
