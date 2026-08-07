<?php

namespace Wisymfony\SlugHistoryBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;



/**
 * Bundle class for Wisymfony Slug History.
 *
 * Responsible for loading bundle service configuration into the container.
 */
final class SlugHistoryBundle extends AbstractBundle
{
    /**
     * Load and configure the bundle extension.
     *
     * @param array               $config       Bundle configuration array.
     * @param ContainerConfigurator $configurator Configurator used to import service definitions.
     * @param ContainerBuilder     $container    The container builder instance.
     *
     * @return void
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import(__DIR__."/../config/services.yaml");
    }
}
