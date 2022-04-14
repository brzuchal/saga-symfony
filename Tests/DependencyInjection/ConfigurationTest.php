<?php

declare(strict_types=1);

namespace Brzuchal\SagaBundle\Tests\DependencyInjection;

use Brzuchal\SagaBundle\DependencyInjection\Configuration;
use Brzuchal\SagaBundle\Tests\Fixtures\AttributedFoo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

// phpcs:disable
class ConfigurationTest extends TestCase
{
    protected const CONFIG_ROOT = 'brzuchal_saga';

    protected static function processConfig(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }

    public function testStoreDefaults(): void
    {
        $config = self::processConfig([
            self::CONFIG_ROOT => [
                'driver' => 'doctrine',
                'options' => [
                    'connection' => 'default_connection',
                ],
                'mappings' => [
                    AttributedFoo::class => null,
                ],
            ],
        ]);
        $this->assertArrayHasKey('stores', $config);
        $this->assertIsArray($config['stores']);
        $this->assertArrayHasKey('default', $config['stores']);
        $this->assertIsArray($config['stores']['default']);
        $this->assertEquals([
            'default' => [
                'driver' => 'doctrine',
                'options' => [
                    'connection' => 'default_connection',
                ],
            ],
        ], $config['stores']);
    }

    public function testStoreDetails(): void
    {
        $config = self::processConfig([
            self::CONFIG_ROOT => [
                'stores' =>  [
                    'default' => [
                        'driver' => 'doctrine',
                        'options' => [
                            'connection' => 'default_connection',
                        ],
                    ],
                ],
                'mappings' => [
                    AttributedFoo::class => null,
                ],
            ],
        ]);
        $this->assertEquals([
            'default' => [
                'driver' => 'doctrine',
                'options' => [
                    'connection' => 'default_connection',
                ],
            ],
        ], $config['stores']);
    }

    public function testMappingDefaults(): void
    {
        $config = self::processConfig([
            self::CONFIG_ROOT => [
                'driver' => 'doctrine',
                'mappings' => [
                    AttributedFoo::class => null,
                ],
            ],
        ]);
        $this->assertArrayHasKey('mappings', $config);
        $this->assertIsArray($config['mappings']);
        $this->assertArrayHasKey(AttributedFoo::class, $config['mappings']);
        $this->assertIsArray($config['mappings'][AttributedFoo::class]);
        $this->assertEquals([
            AttributedFoo::class => [
                'type' => 'attribute',
                'store' => 'default',
                'options' => [],
            ],
        ], $config['mappings']);
    }
}
