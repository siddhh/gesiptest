<?php

namespace App\Form\References;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use App\Entity\References\MotifRenvoi;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MotifRenvoiType extends ReferenceType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        //$service = $builder->getData();
        $builder
            ->add('label', TextType::class, [
                'label' => 'Valeur',
                'attr' => ['class' => 'form-control']
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    '' => '',
                    'Demande'   => 'Demande',
                    'Impact'    => 'Impact'
                ],
                'label' => 'Type*',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    // Utilisé pour récupérer les champs du formulaire parent
    public function getParent()
    {
        return ReferenceType::class;
    }

    // désactive le champ CSRF... à voir si nécessaire par la suite.
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MotifRenvoi::class,
            'csrf_protection'   => false,
        ]);
    }
}
