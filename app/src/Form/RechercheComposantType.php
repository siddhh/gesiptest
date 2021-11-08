<?php

namespace App\Form;

use App\Entity\Composant;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Pilote;
use App\Entity\Service;
use App\Entity\References\Domaine;
use App\Entity\References\Usager;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;

class RechercheComposantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('label', EntityType::class, [
                'class' => Composant::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'label' => 'Label :',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                    ->where('c.archiveLe IS NULL')
                    ->orderBy('LOWER(c.label)', 'ASC');
                }
            ])
            ->add('equipe', EntityType::class, [
                'class'         => Service::class,
                'choice_label'  => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.estPilotageDme = true')
                        ->orderBy('s.label', 'ASC');
                }
            ])
            ->add('pilote', EntityType::class, [
                'class' => Pilote::class,
                'choice_label' => 'nomCompletCourt',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'label' => 'Pilote :',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->orderBy('p.nom', 'ASC')
                        ->addOrderBy('p.prenom', 'ASC');
                }
            ])
            ->add('exploitant', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'label' => 'Exploitant :',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->where('e.estServiceExploitant = true')
                        ->orderBy('e.label', 'ASC');
                }
            ])
            ->add('domaine', EntityType::class, [
                'class' => Domaine::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'label' => 'Domaine :',
                'required' => false,
            ])
            ->add('usager', EntityType::class, [
                'class' => Usager::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'label' => 'Usager :',
                'required' => false,
            ])
            ->add('is_archived', CheckboxType::class, [
                'required' => false,
            ])
            ->add('reset', ResetType::class, [
                'attr' => [ 'class'=>'save' ],
                'label' => 'Réinitialisation de la recherche',
            ]);
    }
}
