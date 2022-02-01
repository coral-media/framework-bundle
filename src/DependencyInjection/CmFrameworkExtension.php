<?php

declare(strict_types=1);

namespace CoralMedia\Bundle\FrameworkBundle\DependencyInjection;

use Exception;
use ReflectionException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

final class CmFrameworkExtension extends Extension implements PrependExtensionInterface
{
    private ?array $bundles = [];

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        try {
            $loader->load('services.yaml');
        } catch (Exception $throwable) {
            throw $throwable;
        }
    }

    /**
     * @param ContainerBuilder $container
     * @throws ReflectionException
     */
    public function prepend(ContainerBuilder $container)
    {
        $this->bundles = $this->findBundles($container->getParameter('kernel.bundles'));

        $this->prependApiPlatformResourcesMapping($container);
        $this->prependFrameworkSerializerMapping($container);
        $this->prependDoctrineDbalConfiguration($container);
        $this->prependDoctrineOrmConfiguration($container);
    }

    private function findBundles(?array $bundles): array
    {
        return array_filter(
            $bundles,
            function ($alias) {
                return (substr($alias, 0, 2) === 'Cm');
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param ContainerBuilder $container
     * @throws ReflectionException
     */
    private function prependDoctrineOrmConfiguration(ContainerBuilder $container)
    {
        if (false === $container->hasExtension('doctrine')) {
            return;
        }

        $filesystem = new Filesystem();
        $currentConfig = $container->getExtensionConfig('doctrine')[0];

        foreach ($this->bundles as $bundleClassName) {
            $bundleDirectory = dirname($container->getReflectionClass($bundleClassName)->getFileName());
            $configResource = $bundleDirectory . '/Resources/config/doctrine.yaml';
            if ($filesystem->exists($configResource)) {
                $bundleConfig = Yaml::parseFile(
                    $bundleDirectory . '/Resources/config/doctrine.yaml'
                )['doctrine'];
                $newDoctrineConfig = array_merge_recursive($bundleConfig, $currentConfig);
                if (isset($newDoctrineConfig['orm'])) {
                    $container->prependExtensionConfig('doctrine', ['orm' => $newDoctrineConfig['orm']]);
                }
            }
        }
    }

    /**
     * @param ContainerBuilder $container
     * @throws ReflectionException
     */
    private function prependDoctrineDbalConfiguration(ContainerBuilder $container)
    {
        if (false === $container->hasExtension('doctrine')) {
            return;
        }

        $filesystem = new Filesystem();
        $currentConfig = $container->getExtensionConfig('doctrine')[0];

        foreach ($this->bundles as $bundleClassName) {
            $bundleDirectory = dirname($container->getReflectionClass($bundleClassName)->getFileName());
            $configResource = $bundleDirectory . '/Resources/config/doctrine.yaml';
            if ($filesystem->exists($configResource)) {
                $bundleConfig = Yaml::parseFile(
                    $bundleDirectory . '/Resources/config/doctrine.yaml'
                )['doctrine'];
                $newDoctrineConfig = array_merge_recursive($bundleConfig, $currentConfig);
                $container->prependExtensionConfig('doctrine', ['dbal' => $newDoctrineConfig['dbal']]);
            }
        }
    }

    /**
     * @param ContainerBuilder $container
     * @throws ReflectionException
     */
    private function prependFrameworkSerializerMapping(ContainerBuilder $container)
    {
        $frameworkConfig = $container->getExtensionConfig('framework');

        $configIndex = array_search(
            'serializer',
            array_column(array_map('array_keys', $frameworkConfig), 0)
        );
        if (
            !isset($frameworkConfig[$configIndex]['serializer']) ||
            !isset($frameworkConfig[$configIndex]['serializer']['enabled'])
        ) {
            $frameworkConfig[$configIndex]['serializer']['enabled'] = true;
        }

        $filesystem = new Filesystem();
        if (!isset($frameworkConfig[$configIndex]['serializer']['mapping']['paths'])) {
            $frameworkConfig[$configIndex]['serializer']['mapping']['paths'] = [];
        }

        foreach ($this->bundles as $bundleClassName) {
            $bundleDirectory = dirname($container->getReflectionClass($bundleClassName)->getFileName());
            $configResource = $bundleDirectory . '/Resources/config/api/serialization';

            if ($filesystem->exists($configResource)) {
                $frameworkConfig[$configIndex]['serializer']['mapping']['paths'] = array_merge(
                    $frameworkConfig[$configIndex]['serializer']['mapping']['paths'],
                    [$configResource]
                );
            }
        }
        $container->prependExtensionConfig(
            'framework',
            ['serializer' => $frameworkConfig[$configIndex]['serializer']]
        );
    }

    /**
     * @param ContainerBuilder $container
     * @throws ReflectionException
     */
    private function prependApiPlatformResourcesMapping(ContainerBuilder $container)
    {
        if (false === $container->hasExtension('api_platform')) {
            return;
        }

        $filesystem = new Filesystem();
        $apiConfig = $container->getExtensionConfig('api_platform');
        $apiMappingConfig = $apiConfig[0]['mapping'];

        foreach ($this->bundles as $bundleClassName) {
            $bundleDirectory = dirname($container->getReflectionClass($bundleClassName)->getFileName());
            $configResource = $bundleDirectory . '/Resources/config/api/resources';
            $entityResource = $bundleDirectory . '/Entity';

            if ($filesystem->exists($configResource)) {
                $apiMappingConfig['paths'] = array_merge(
                    $apiMappingConfig['paths'],
                    [$configResource, $entityResource]
                );
            }
        }
        $container->prependExtensionConfig(
            'api_platform',
            ['mapping' => ['paths' => $apiMappingConfig['paths']]]
        );
    }
}
