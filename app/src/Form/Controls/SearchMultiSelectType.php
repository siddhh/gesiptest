<?php

namespace App\Form\Controls;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchMultiSelectType extends EntityType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'multiple' => true,
            'urlRecherche' => null,
            'itemLabel' => 'label',
        ]);
        $resolver->setAllowedTypes('urlRecherche', 'string');
        $resolver->setAllowedTypes('itemLabel', 'string');
    }

    public function getBlockPrefix()
    {
        return 'search_multi_select';
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['urlRecherche'] = $options['urlRecherche'];
        $view->vars['itemLabel'] = $options['itemLabel'];
    }
}
