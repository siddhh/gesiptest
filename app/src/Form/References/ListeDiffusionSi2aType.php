<?php

namespace App\Form\References;

use App\Entity\References\ListeDiffusionSi2a;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListeDiffusionSi2aType extends ReferenceType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Prénom NOM*',
                'attr' => ['class' => 'form-control']
            ])
            ->add('fonction', TextType::class, [
                'label' => 'Fonction*',
                'attr' => ['class' => 'form-control']
            ])
            ->add('balp', EmailType::class, [
                'label' => 'BALP*',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    // désactive le champ CSRF... à voir si nécessaire par la suite.
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ListeDiffusionSi2a::class,
            'csrf_protection'   => false,
        ]);
    }
}
