<?php

namespace Wisoft\SlugHistoryBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Bundle class for Wisoft Slug History.
 *
 * Responsible for loading bundle service configuration into the container.
 */
final class SlugHistoryBundle extends AbstractBundle
{
    public const VERSION = '1.0.0';
    private string $bundleRoot = "";
    public function __construct(){
        $this->bundleRoot = substr(__DIR__, 0, strlen(__DIR__) - 4);
    }

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
        $configurator->import($this->bundleRoot."/config/services.yaml");
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig')) {
            $pathTemplate  = $this->bundleRoot."/templates";
            $builder->prependExtensionConfig('twig', [
                "paths" => [$pathTemplate => "WsSlugHistory"],
                'form_themes' => ['@WsSlugHistory/form/slugged-type.html.twig']
            ]);
        }
    }
}
