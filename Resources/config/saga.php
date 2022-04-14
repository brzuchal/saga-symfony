<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Brzuchal\Saga\Mapping\SagaMetadataFactory;
use Brzuchal\Saga\Repository\MappedRepositoryFactory;
use Brzuchal\Saga\SagaManagerFactory;
use Brzuchal\Saga\SagaRepositoryFactory;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('brzuchal_saga.metadata_factory', SagaMetadataFactory::class)
        ->arg(0, tagged_iterator('brzuchal_saga.metadata_driver'));

    $services->set('brzuchal_saga.repository_factory', MappedRepositoryFactory::class)
        ->args([
            abstract_arg('store locator for saga classes'),
            new Reference('brzuchal_saga.metadata_factory')
        ])
        ->alias(SagaRepositoryFactory::class, 'brzuchal_saga.repository_factory');

    $services->set('brzuchal_saga.manager_factory', SagaManagerFactory::class)
        ->arg(0, new Reference(SagaRepositoryFactory::class));
};
