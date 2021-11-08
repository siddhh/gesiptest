<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class RechercheBalfServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('balf', TextType::class, [
            'required' => true,
            'constraints' => [
                new NotBlank()
            ],
            'attr'  => [
                'placeholder' => 'Saisir la Balf à rechercher dans la base de données...',
            ],
        ]);
    }
}
