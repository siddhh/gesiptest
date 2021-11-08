<?php

namespace App\Form\Composant;

use App\Entity\Composant\Annuaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnnuaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('id')
            ->add('mission')
            ->add('service')
            ->add('composant')
            ->add('balf', EmailType::class)
            ->add('ajouteLe', DateTimeType::class, [
                'with_seconds'  => true
            ])
            ->add('majLe', DateTimeType::class, [
                'with_seconds'  => true
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Annuaire::class,
        ]);
    }
}
