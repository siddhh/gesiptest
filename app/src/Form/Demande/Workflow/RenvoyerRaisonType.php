<?php

namespace App\Form\Demande\Workflow;

use App\Entity\References\MotifRenvoi;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class RenvoyerRaisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('motif', EntityType::class, [
                'class' => MotifRenvoi::class,
                'choice_label' => 'label',
                'placeholder' => '',
                'multiple' => false,
                'expanded' => false,
                'required' => true,
                'label' => 'Motif',
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => true,
                'constraints' => [
                    new NotBlank()
                ]
            ])
        ;
    }
}
