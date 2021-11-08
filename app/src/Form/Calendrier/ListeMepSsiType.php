<?php

namespace App\Form\Calendrier;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use App\Entity\Pilote;
use App\Repository\PiloteRepository;

class ListeMepSsiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('projet', CheckboxType::class)
            ->add('confirme', CheckboxType::class)
            ->add('archive', CheckboxType::class)
            ->add('erreur', CheckboxType::class)
            ->add('pilote', EntityType::class, [
                'required' => false,
                'class' => Pilote::class,
                'choice_label' => 'nomCompletCourt',
                'placeholder' => 'Aucune sélection',
                'attr' => ['class' => 'pilote'],
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (PiloteRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.nom', 'ASC')
                    ->Where('s.supprimeLe is null');
                }
            ])
            ->add('mois', HiddenType::class, [
                'required' => true,
                'data' => (new \DateTime())->format('Ym'),
                'attr' => ['class' => 'mois-traite']
            ])
        ;
    }
}
