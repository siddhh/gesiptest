<?php

namespace App\Form\Meteo\Statistiques;

use App\Entity\Pilote;
use App\Entity\Service;
use App\Repository\PiloteRepository;
use App\Repository\ServiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TauxIndisponibilitesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Génère la liste des années selectionnables
        $annees = [];
        for ($i = date('Y') - 5; $i <= date('Y'); $i++) {
            $annees[$i] = $i;
        }
        // Construit le formulaire
        $builder
            ->add('moduleSource', ChoiceType::class, [
                'required' => true,
                'choices'  => [
                    'Interventions programmées' => 'interventions',
                    'Évènements Météo'          => 'evenements',
                ],
            ])
            ->add('frequence', ChoiceType::class, [
                'required' => true,
                'choices'  => [
                    'Mensuel'       => 'P1M',
                    'Trimestriel'   => 'P3M',
                    'Semestriel'    => 'P6M',
                    'Annuel'        => 'P1Y',
                ],
            ])
            ->add('periodeDebut', ChoiceType::class, [
                'required'  => true,
                'choices'   => $annees,
                'data'      => date('Y'),
            ])
            ->add('periodeFin', ChoiceType::class, [
                'required'  => true,
                'choices'   => $annees,
                'data'      => date('Y'),
            ])
            ->add('equipe', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (ServiceRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->addSelect('p')
                        ->leftJoin('s.pilotes', 'p')
                        ->where('s.supprimeLe is null')
                        ->andWhere('s.estPilotageDme = true')
                        ->orderBy('s.label', 'ASC');
                },
                'choice_attr' => function ($choice, $key, $value) {
                    return ['data-pilote-ids' => implode(',', array_column($choice->getPilotes()->toArray(), 'id'))];
                }
            ])
            ->add('pilote', EntityType::class, [
                'class' => Pilote::class,
                'choice_label' => 'nomCompletCourt',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (PiloteRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->addSelect('s')
                        ->leftJoin('p.equipe', 's')
                        ->where('p.supprimeLe is null')
                        ->orderBy('p.nom', 'ASC');
                },
                'choice_attr' => function ($choice, $key, $value) {
                    $equipe = $choice->getEquipe();
                    return ['data-equipe-id' => !empty($equipe) ? $equipe->getId() : ''];
                }
            ])
            ->add('exploitant', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (ServiceRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.supprimeLe is null')
                        ->where('s.estServiceExploitant = true')
                        ->orderBy('s.label', 'ASC');
                }
            ])
        ;
    }

    /**
     * Méthode de validation complémentaire
     * @param array $data
     * @param ExecutionContextInterface $context
     * @return void
     */
    public function validate(array $data, ExecutionContextInterface $context): void
    {
        // teste si les dates sont valides
        $anneeMaxFin = date('Y');
        $anneeMinDebut = $anneeMaxFin - 5;
        if (empty($data['periodeDebut']) || $data['periodeDebut'] < $anneeMinDebut) {
            $context
                ->buildViolation("Une année de début de période valide doit être indiquée (minimum: {$anneeMinDebut}).")
                ->atPath('periodeDebut')
                ->addViolation()
            ;
        } elseif (empty($data['periodeFin']) || $data['periodeFin'] > $anneeMaxFin) {
            $context
                ->buildViolation("Une année de fin de période valide doit être indiquée (maximum: {$anneeMaxFin}).")
                ->atPath('periodeFin')
                ->addViolation()
            ;
        } elseif ($data['periodeDebut'] > $data['periodeFin']) {
            $context
                ->buildViolation('L\'année de début ne peut pas être supérieure à l\'année de fin.')
                ->atPath('periodeFin')
                ->addViolation()
            ;
        }
    }

    /**
     * Ajoute une fonction de callback qui sera appelée lors de la validation.
     * @param OptionsResolver $resolver
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'constraints'   => [
                new Callback([$this, 'validate']),
            ]
        ]);
    }
}
