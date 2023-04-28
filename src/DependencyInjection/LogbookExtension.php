<?php

namespace Solvrtech\Logbook\DependencyInjection;

use Exception;
use ReflectionException;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\Kernel;

class LogbookExtension extends Extension implements PrependExtensionInterface
{
    private array $logbookAPI;
    private array $handler;

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (isset($config['api'])) {
            $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('logbook.yaml');

            $this->setLogbookAPI($config['api']);

            if (!empty($this->handler)) {
                $this->buildHandler($container);
                $this->buildProcessor($container);
            }

            $this->buildAuthenticator($container);
        }
    }

    private function setLogbookAPI(array $config): self
    {
        foreach ($config as $key => $val) {
            if ('url' === $key) {
                // check if the last character is a slash and then remove that one
                if ('/' === substr($val, -1)) {
                    $val = substr($val, 0, -1);
                }
            }

            $this->logbookAPI[$key] = $val;
        }

        return $this;
    }

    /**
     * Build logbook handler
     *
     * @param ContainerBuilder $container
     */
    public function buildHandler(ContainerBuilder $container)
    {
        $id = 'monolog.handler.logbook';

        $definition = new Definition('Solvrtech\Logbook\Handler\LogbookHandler');
        $definition->setArguments([
            $this->logbookAPI['url'],
            $this->logbookAPI['key'],
            $this->handler['level'],
            $this->getAppVersion($container),
        ]);
        $container->setParameter($id, $id);
        $container->setDefinition($id, $definition);
    }

    /**
     * Get app and framework version.
     *
     * @param ContainerBuilder $container
     *
     * @return string
     */
    private function getAppVersion(ContainerBuilder $container): string
    {
        $version = [
            'core' => "Symfony v".Kernel::VERSION,
        ];

        if ($container->hasParameter('version')) {
            $appVersion = $container->getParameter('version');
            $version['app'] = is_string($appVersion) ? $appVersion : '';
        }

        return json_encode($version);
    }

    /**
     * Build logbook processor
     *
     * @param ContainerBuilder $container
     */
    public function buildProcessor(ContainerBuilder $container)
    {
        $id = 'logbook.processor';

        $definition = new Definition('Solvrtech\Logbook\Processor\LogbookProcessor');
        $container->setParameter($id, $id);
        $container->setDefinition($id, $definition);
    }

    /**
     * Build Logbook authenticator
     *
     * @param ContainerBuilder $container
     */
    public function buildAuthenticator(ContainerBuilder $container)
    {
        $id = 'logbook.authenticator';

        $definition = new Definition('Solvrtech\Logbook\Security\LogbookAuthenticator');
        $definition->setArguments([
            $this->logbookAPI['key'],
        ]);
        $container->setParameter($id, $id);
        $container->setDefinition($id, $definition);
    }

    /**
     * {@inheritDoc}
     * {monologConfiguration}
     *
     * @throws ReflectionException
     */
    public function prepend(ContainerBuilder $container)
    {
        $configs = $container->getExtensionConfig('monolog');
        $monologConfiguration = $this->getMonologConfiguration($container);
        $config = $this->processConfiguration($monologConfiguration, $configs);

        if (isset($config['handlers'])) {
            $this->handler = $config['handlers']['logbook'] ?? [];
        }
    }

    /**
     * Get monolog bundle configuration
     *
     * @param ContainerBuilder $containerBuilder
     *
     * @return Configuration|null
     *
     * @throws ReflectionException
     */
    private function getMonologConfiguration(ContainerBuilder $containerBuilder): ConfigurationInterface|null
    {
        $class = "Symfony\Bundle\MonologBundle\DependencyInjection\Configuration";
        $class = $containerBuilder->getReflectionClass($class);

        return $class->newInstance();
    }
}
