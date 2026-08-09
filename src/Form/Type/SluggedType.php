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
                    'defaultParams' => [],
                    'mappingFrom' => []
                ],
                "from" => [],
                'showLabel' => 'Visit &#10138;',
            ]);
        }

        public function buildView(\Symfony\Component\Form\FormView $view, \Symfony\Component\Form\FormInterface $form, array $options): void
        {
            if (isset($options['from']) && !is_array($options['from'])) {
                throw new \InvalidArgumentException("The 'from' option must be an array of field names.");
            }
            
            $route = $options['route'];
            if (!is_array($route) || !isset($route['name']) || !isset($route['slugParam'])) {
                throw new \InvalidArgumentException("The 'route' option must be an array with 'name' and 'slugParam' keys.");
            }

            if (!isset($route['mappingFrom'])) {
                $route['mappingFrom'] = [];
            }

            if (!is_array($route['mappingFrom'])) {
                throw new \InvalidArgumentException("The 'mappingFrom' option must be an array.");
            }

            if (!is_string($route['name']) || !is_string($route['slugParam'])) {
                throw new \InvalidArgumentException("The 'name' and 'slugParam' keys in the 'route' option must be strings.");
            }

            if (!isset($route['defaultParams'])) {
                $route['defaultParams'] = [];
            }

            if(!is_array($route['defaultParams']))
            {
                throw new \InvalidArgumentException("The 'defaultParams' option must be an array.");
            }
            foreach ($route['mappingFrom'] as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $mappingValue = sprintf("__%s__", strtoupper($value));
                    $route['defaultParams'][$key] = $mappingValue;
                    $route['mappingFrom'][$key] = $mappingValue;
                }
            }

            $view->vars['from'] = $options['from'];
            $view->vars['showLabel'] = $options['showLabel'];
            $view->vars['route'] = $route;
            $view->vars['mappingFrom'] = $route['mappingFrom'];
        }

        public function getParent(): string
        {
            return TextType::class;
        }
    }