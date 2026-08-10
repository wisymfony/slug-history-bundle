<?php

namespace Wisymfony\SlugHistoryBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SluggedType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "route" => [
                'name' => '',
                'slugParam' => '',
                'params' => []
            ],
            "from" => '',
            'showLabel' => 'Visit &#10138;',
        ]);
    }

    public function buildView(\Symfony\Component\Form\FormView $view, \Symfony\Component\Form\FormInterface $form, array $options): void
    {
        $options = array_merge(['from' => ''], $options);
        
        if (!is_string($options['from'])) {
            throw new \InvalidArgumentException("The 'from' option must be an string of field name.");
        }

        $route = $options['route'];
        if (!is_array($route) || !isset($route['name']) || !isset($route['slugParam'])) {
            throw new \InvalidArgumentException("The 'route' option must be an array with 'name' and 'slugParam' keys.");
        }

        if (!isset($route['params'])) {
            $route['params'] = [];
        }

        if (!is_array($route['params'])) {
            throw new \InvalidArgumentException("The 'params' key in 'route' option must be an array.");
        }

        if (!is_string($route['name'])) {
            throw new \InvalidArgumentException("The 'name' key in the 'route' option must be strings.");
        }

        if (!empty($route['slugParam']) && isset($route['params'][$route['slugParam']])) {
            unset($route['params'][$route['slugParam']]);
        }

        $mappingFrom = [];
        foreach ($route['params'] as $key => $value) {
            if (is_string($key) && is_string($value)) {
                if (str_starts_with($value, '@')) {
                    $migrationKey = substr($value, 1);
                    $value = sprintf("__%s__", strtoupper($migrationKey));
                    $mappingFrom[$migrationKey] = $value;
                }
                $route['params'][$key] = $value;
            }
        }

        $view->vars['from'] = $options['from'];
        $view->vars['showLabel'] = $options['showLabel'];
        $view->vars['route'] = $route;
        $view->vars['params'] = $route['params'];
        $view->vars['mappingFrom'] = $mappingFrom;
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
