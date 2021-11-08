<?php

namespace App\Form\Meteo;

use App\Form\Meteo\EvenementType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ListeEvenementsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('evenements', CollectionType::class, [
                'entry_type'    => EvenementType::class,
                'allow_add'     => true,
                'allow_delete'  => true,
                'delete_empty'  => true,
                'prototype'     => true,
                'constraints'   => [
                    new NotBlank()
                ]
            ])
        ;
    }
}
