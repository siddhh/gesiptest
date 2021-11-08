<?php

namespace App\Form\Demande\Workflow;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class DonnerAvisCdbType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('envoyerMail', CheckboxType::class, [
                'data' => true,
                'label' => 'Avec envoi de mail',
                'required' => false
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => true,
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('avis', ChoiceType::class, [
                'label' => 'Avis',
                'choices'   =>  [
                    'Favorable' =>  'ok',
                    'Non favorable' =>  'ko'
                ],
                'expanded' => true,
                'multiple' => false,
                'constraints' => [
                    new NotBlank()
                ]
            ])
        ;
    }
}
