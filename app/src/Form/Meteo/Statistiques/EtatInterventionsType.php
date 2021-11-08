<?php

namespace App\Form\Meteo\Statistiques;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class EtatInterventionsType extends AbstractType
{
    private const DUREE_RETENTION_INTERVENTIONS = 5;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $annneeEnCours = intval((new \DateTime())->format("Y"));
        $builder
            ->add('anneeDebut', ChoiceType ::class, [
                'required' => true,
                'choices' => range($annneeEnCours, $annneeEnCours - self::DUREE_RETENTION_INTERVENTIONS),
                'choice_label' => function ($choice, $key, $value) {
                    return $value;
                }
            ])
            ->add('anneeFin', ChoiceType ::class, [
                'required' => true,
                'choices' => range($annneeEnCours, $annneeEnCours - self::DUREE_RETENTION_INTERVENTIONS),
                'choice_label' => function ($choice, $key, $value) {
                    return $value;
                }
            ])
            ->add('typeRestitution', ChoiceType ::class, [
                'required' => true,
                'choices' => [
                    "Nombre d'interventions",
                    "Pourcentage"
                ],
                'choice_label' => function ($choice, $key, $value) {
                    return $value;
                }
            ])
            ->add('bureauRattachement', CheckboxType ::class, [
                'required' => false
            ])
        ;
    }
}
