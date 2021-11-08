<?php

namespace App\Form\Demande\Workflow;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\References\MotifRefus;

class RefuserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('envoyerMail', CheckboxType::class, [
                'data' => true,
                'label' => 'Avec envoi de mail',
                'required' => false
            ])
            ->add('motif', EntityType::class, [
                'class' => MotifRefus::class,
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
                'required' => true,
                'constraints' => [
                    new NotBlank()
                ]
            ])
        ;
    }
}
