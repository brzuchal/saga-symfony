<?php declare(strict_types=1);

namespace Brzuchal\SagaBundle\DependencyInjection;

use Brzuchal\Saga\Mapping\AttributeMappingDriver;
use Brzuchal\Saga\Mapping\SagaMetadataFactory;
use Brzuchal\Saga\Mapping\SagaMethodMetadata;
use Brzuchal\Saga\SagaManager;
use Brzuchal\Saga\SagaManagerFactory;
use Brzuchal\Saga\SagaRepositoryFactory;
use Brzuchal\Saga\Store\DoctrineSagaStore;
use Brzuchal\Saga\Repository\MappedRepositoryFactory;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\DependencyInjection\Loader\Configurator\iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

final class SagaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
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

        $metadataDriverTag = $this->prefixName('metadata_driver');
        foreach ($metadata as $type => $enabled) {
            $metadataDriverId = $this->prefixName(sprintf('%s_metadata', $type));
            $container->register($metadataDriverId, AttributeMappingDriver::class)
                ->addTag($metadataDriverTag);
        }

        $metadataFactoryId = $this->prefixName('metadata_factory');
        $container->register($metadataFactoryId, SagaMetadataFactory::class)
            ->setArgument(0, tagged_iterator($metadataDriverTag));

        $repositoryFactoryId = $this->prefixName('repository_factory');
        $container->register($repositoryFactoryId, MappedRepositoryFactory::class)
            ->setArgument(0, iterator($locator))
            ->setArgument(1, new Reference($metadataFactoryId))
            ->setPublic(true);
        $container->setAlias(SagaRepositoryFactory::class, $repositoryFactoryId);
        $container->register($managerFactoryId, SagaManagerFactory::class)
            ->setArgument(0, new Reference(SagaRepositoryFactory::class));
    }

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

    private function extractMessageClasses(string $class): array
    {
        return \array_merge(...\array_map(
            static fn($method) => $method->getTypes(),
            (new AttributeMappingDriver())->extractMethods(ReflectionClass::createFromName($class)),
        ));
    }
}
