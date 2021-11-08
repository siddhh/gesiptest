<?php

namespace App\Form\Fiabilisation;

use Doctrine\ORM\EntityRepository;
use App\Entity\Pilote;
use App\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class RechercheDemandeReferentielFluxType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('equipe', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.supprimeLe IS NULL')
                        ->andWhere('s.estPilotageDme = true')
                        ->orderBy('s.label', 'ASC');
                },
            ])
            ->add('pilote', EntityType::class, [
                'class' => Pilote::class,
                'choice_label' => 'nomCompletCourt',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->where('p.supprimeLe IS NULL')
                        ->orderBy('p.nom', 'ASC')
                        ->addOrderBy('p.prenom', 'ASC');
                },
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    ''          => '',
                    'Ajout'     => 'add',
                    'Retrait'   => 'remove',
                ],
                'required' => false,
            ])
            ->add('serviceDemandeur', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    $y = $er->createQueryBuilder('s')
                        ->where('s.supprimeLe IS NULL')
                        ->andWhere('NOT JSON_CONTAINS(s.roles, :adminRole) = 1')
                        ->setParameter('adminRole', json_encode(["ROLE_ADMIN"]))
                        ->andWhere('NOT JSON_CONTAINS(s.roles, :dmeRole) = 1')
                        ->setParameter('dmeRole', json_encode(["ROLE_DME"]))
                        ->orderBy('s.label', 'ASC');
                    return $y;
                },
            ])
            ->add('ajouteLe', DateType::class, [
                'widget'    => 'single_text',
                'required'  => false,
                'html5'     => false,
                'format'    => 'dd/MM/yyyy'
            ])
            ->add('reset', ResetType::class)
            ->add('search', SubmitType::class)
        ;
    }
}
