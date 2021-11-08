<?php

namespace App\Form;

use App\Entity\Composant;
use App\Entity\Pilote;
use App\Entity\Service;
use App\Entity\References\Usager;
use App\Entity\References\Domaine;
use App\Entity\References\TypeElement;
use App\Form\Composant\AnnuaireType;
use App\Form\Composant\PlageUtilisateurType;
use App\Repository\ServiceRepository;
use App\Repository\PiloteRepository;
use App\Repository\References\UsagerRepository;
use App\Repository\References\DomaineRepository;
use App\Repository\References\TypeElementRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ComposantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Composant $composant */
        $composant = $builder->getData();

        $builder
            ->add('label', TextType::class, [
                'required' => true,
            ])
            ->add('codeCarto', TextType::class, [
                'required' => false,
            ])
            ->add('intitulePlageUtilisateur', TextType::class, [
                'required' => false
            ])
            ->add('meteoActive', ChoiceType::class, [
                'required' => true,
                'choices'  => [
                    '' => null,
                    'Oui' => true,
                    'Non' => false,
                ],
            ])
            ->add('estSiteHebergement', ChoiceType::class, [
                'required' => false,
                'choices'  => [
                    'Oui' => true,
                    'Non' => false,
                ],
            ])
            ->add('usager', EntityType::class, [
                'required' => true,
                'class' => Usager::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (UsagerRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.label', 'ASC')
                    ->Where('s.supprimeLe is null');
                }
            ])
            ->add('domaine', EntityType::class, [
                'required' => false,
                'class' => Domaine::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (DomaineRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.label', 'ASC')
                    ->Where('s.supprimeLe is null');
                }
            ])
            ->add('description', TextareaType::class, [
                'required' => false
            ])
            ->add('exploitant', EntityType::class, [
                'required' => false,
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (ServiceRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.label', 'ASC')
                    ->where('s.supprimeLe is null')
                    ->andWhere('s.estServiceExploitant = true');
                }
            ])
            ->add('equipe', EntityType::class, [
                'required' => false,
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (ServiceRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.label', 'ASC')
                    ->Where('s.supprimeLe is null')
                    ->andWhere('s.estPilotageDme = true');
                }
            ])
            ->add('pilote', EntityType::class, [
                'required' => false,
                'class' => Pilote::class,
                'choice_label' => 'nomCompletCourt',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (PiloteRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.nom', 'ASC')
                    ->Where('s.supprimeLe is null');
                }
            ])
            ->add('piloteSuppleant', EntityType::class, [
                'required' => false,
                'class' => Pilote::class,
                'choice_label' => 'nomCompletCourt',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (PiloteRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.nom', 'ASC')
                    ->Where('s.supprimeLe is null');
                }
            ])
            ->add('typeElement', EntityType::class, [
                'required' => true,
                'class' => TypeElement::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (TypeElementRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.label', 'ASC')
                    ->Where('s.supprimeLe is null');
                }
            ])
            ->add('bureauRattachement', EntityType::class, [
                'required' => false,
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (ServiceRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.label', 'ASC')
                    ->Where('s.supprimeLe is null')
                    ->andWhere('s.estBureauRattachement = true');
                }
            ])
            ->add('plagesUtilisateur', CollectionType::class, [
                'entry_type' => PlageUtilisateurType::class,
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('annuaire', CollectionType::class, [
                'entry_type' => AnnuaireType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'data' => $composant->getAnnuaire(false)
            ])
            ->add('composantsImpactes', EntityType::class, [
                'class' => Composant::class,
                'choice_label' => 'label',
                'multiple' => true,
            ])
            ->add('impactesParComposants', EntityType::class, [
                'class' => Composant::class,
                'choice_label' => 'label',
                'multiple' => true,
            ])
        ;

        if ($composant !== null) {
            if ($composant->getId() !== null) {
                $builder->add('estArchive', CheckboxType::class, [
                    'required'  => false,
                    'mapped'    => false,
                ]);
            } else {
                $builder->add('impacteLuiMeme', CheckboxType::class, [
                    'required'  => false,
                    'mapped'    => false,
                ]);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Composant::class,

        ]);
    }
}
