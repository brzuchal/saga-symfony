<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Brzuchal\Saga\Mapping\AttributeMappingDriver;
use Brzuchal\Saga\Mapping\SagaMetadataFactory;
use Brzuchal\Saga\Repository\MappedRepositoryFactory;
use Brzuchal\Saga\SagaManager;
use Brzuchal\Saga\SagaManagerFactory;
use Brzuchal\Saga\SagaRepositoryFactory;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('brzuchal_saga.attribute_metadata', AttributeMappingDriver::class)
        ->tag('brzuchal_saga.metadata_driver');

    $services->set('brzuchal_saga.metadata_factory', SagaMetadataFactory::class)
        ->arg(0, tagged_iterator('brzuchal_saga.metadata_driver'));

    $services->set('brzuchal_saga.repository_factory', MappedRepositoryFactory::class)
        ->args([
            tagged_iterator('brzuchal_saga.store', 'class'),
            //tagged_iterator('brzuchal_saga.factory', 'class'),
            abstract_arg('saga factories for class creation'),
            new Reference('brzuchal_saga.metadata_factory')
        ])
        ->alias(SagaRepositoryFactory::class, 'brzuchal_saga.repository_factory');

    $services->set('brzuchal_saga.manager_factory', SagaManagerFactory::class)
        ->arg(0, new Reference(SagaRepositoryFactory::class));
};
