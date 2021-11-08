<?php

namespace App\Form\Meteo;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class PublicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('publication', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'html5' => false,
                'constraints' => [
                    new NotBlank(),
                    new LessThan('today'),
                ]
            ])
            ->add('depublication', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'html5' => false,
                'constraints' => [
                    new NotBlank(),
                    new LessThan('today'),
                ]
            ])
        ;
    }
}
