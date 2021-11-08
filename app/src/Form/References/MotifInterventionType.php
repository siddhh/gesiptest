<?php

namespace App\Form\References;

use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\References\MotifIntervention;

class MotifInterventionType extends ReferenceType
{

    // Utilisé pour récupérer les champs du formulaire parent
    public function getParent()
    {
        return ReferenceType::class;
    }

    // désactive le champ CSRF... à voir si nécessaire par la suite.
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MotifIntervention::class,
            'csrf_protection'   => false,
        ]);
    }
}
