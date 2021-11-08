<?php

namespace App\Form\Composant;

use App\Entity\Composant\PlageUtilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlageUtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('jour')
            ->add('debut')
            ->add('fin')
            ->add('composant')
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
            'data_class' => PlageUtilisateur::class,
        ]);
    }
}
