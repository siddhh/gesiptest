<?php

namespace App\Form\Calendrier;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Composant;
use App\Repository\ComposantRepository;

class VueInterApplicativeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('composant', EntityType::class, [
                'required' => true,
                'class' => Composant::class,
                'choice_label' => 'label',
                'placeholder' => 'Aucune sélection',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (ComposantRepository $er) {
                    return $er->createQueryBuilder('c')
                    ->where('c.archiveLe IS NULL')
                    ->orderBy('LOWER(c.label)', 'ASC')
                    ->addSelect('cs', 'ce')
                    ->leftjoin('c.composantsImpactes', 'cs')
                    ->leftjoin('c.impactesParComposants', 'ce');
                }
            ])
        ;
    }
}
