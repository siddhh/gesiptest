<?php

namespace App\Form;

use App\Entity\Composant;
use App\Entity\Pilote;
use App\Entity\Service;
use App\Entity\References\MotifIntervention;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatRefusee;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Etats\EtatTerminee;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RechercheInterventionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('numero', TextType::class, [
                'required' => false,
            ])
            ->add('demandePar', EntityType::class, [
                'class' => Service::class,
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.supprimeLe IS NULL')
                        ->orderBy('s.label', 'ASC');
                }
            ])
            ->add('exploitant', EntityType::class, [
                'class' => Service::class,
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.supprimeLe IS NULL')
                        ->orderBy('s.label', 'ASC');
                },
            ])
            ->add('composantConcerne', EntityType::class, [
                'class' => Composant::class,
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->where('c.archiveLe IS NULL')
                        ->orderBy('LOWER(c.label)', 'ASC');
                },
            ])
            ->add('composantImpacte', EntityType::class, [
                'class' => Composant::class,
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->where('c.archiveLe IS NULL')
                        ->orderBy('LOWER(c.label)', 'ASC');
                },
            ])
            ->add('motifIntervention', EntityType::class, [
                'class' => MotifIntervention::class,
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('m')
                        ->where('m.supprimeLe IS NULL')
                        ->orderBy('m.label', 'ASC');
                }
            ])
            ->add('pilote', EntityType::class, [
                'class' => Pilote::class,
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->where('p.supprimeLe IS NULL')
                        ->orderBy('p.nom', 'ASC')
                        ->addOrderBy('p.prenom', 'ASC');
                }
            ])
            ->add('periodeDateDebut', DateType::class, [
                'widget'            => 'single_text',
                'required'          => false,
                'html5'             => false,
                'format'            => 'dd/MM/yyyy',
                'model_timezone'    => 'Europe/Paris',
            ])
            ->add('periodeDateFin', DateType::class, [
                'widget'            => 'single_text',
                'required'          => false,
                'html5'             => false,
                'format'            => 'dd/MM/yyyy',
                'model_timezone'    => 'Europe/Paris',
            ])
            ->add('demandeLe', DateType::class, [
                'widget'            => 'single_text',
                'required'          => false,
                'html5'             => false,
                'format'            => 'dd/MM/yyyy',
                'model_timezone'    => 'Europe/Paris',
            ])
            ->add('status', ChoiceType::class, [
                'choices'           => self::getStatusChoices(),
                'empty_data'        => ''
            ])
            ->add('interventionsActives', CheckboxType::class, [
                'mapped'            => false,
                'required'          => false,
                'data'              => true
            ])
            ->add('reset', ResetType::class)
            ->add('search', SubmitType::class)
        ;
    }

    /**
     * M??thode de validation compl??mentaire
     * @param array $data
     * @param ExecutionContextInterface $context
     * @return void
     */
    public function validate(array $data, ExecutionContextInterface $context): void
    {
        // teste si les dates de d??but / fin d'intervention sont valides
        if (!empty($data['periodeDateDebut']) && !empty($data['periodeDateFin'])
            && $data['periodeDateDebut'] > $data['periodeDateFin']) {
            $context
                ->buildViolation('Si les 2 crit??res sont pr??cis??es, la date de d??but d\'intervention ne peut ??tre post??rieure ?? sa date de fin.')
                ->atPath('[periodeDateDebut]')
                ->addViolation()
            ;
        }
    }

    /**
     * Ajoute une fonction de callback qui sera appel??e lors de la validation.
     * @param OptionsResolver $resolver
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'constraints' => [
                new Callback([$this, 'validate']),
            ]
        ]);
    }

    /**
     * Liste des choix possibles pour le crit??re de recherche "statut"
     */
    private static function getStatusChoices() :array
    {
        return [
            'Toutes les demandes' => [
                'Toutes les demandes' => '',
            ],
            'Interventions renvoy??es au demandeur' => [
                'Demande renvoy??e apr??s analyse'            => EtatRenvoyee::class . '<' . EtatAnalyseEnCours::class,
                'Demande renvoy??e apr??s consultation'       => EtatRenvoyee::class . '<' . EtatInstruite::class,
                'Demande renvoy??e apr??s accord'             => EtatRenvoyee::class . '<' . EtatAccordee::class,
            ],
            'Interventions annul??es' => [
                'Demande annul??e'                           => EtatAnnulee::class,
                'Demande annul??e apr??s accord'              => EtatAnnulee::class . '<' . implode(',', [EtatAccordee::class, EtatSaisirRealise::class, EtatInterventionEnCours::class]),
            ],
            'Interventions refus??es' => [
                'Demande refus??e'                           => EtatRefusee::class,
            ],
            'Interventions en cours d\'analyse' => [
                'Demande en cours d\'analyse'               => EtatAnalyseEnCours::class,
            ],
            'Interventions en attente de r??ponse' => [
                'Demande instruite'                         => EtatInstruite::class,
                'Consultation en cours du CDB'              => EtatConsultationEnCoursCdb::class,
                'Consultation en cours'                     => EtatConsultationEnCours::class,
            ],
            'Interventions accept??es' => [
                'Demande accord??e'                          => EtatAccordee::class,
                'Intervention en cours'                     => EtatInterventionEnCours::class,
                'Intervention termin??e'                     => implode(',', [EtatInterventionReussie::class, EtatInterventionEchouee::class, EtatSaisirRealise::class, EtatTerminee::class])
            ],
            'Interventions termin??es et r??ussies' => [
                'Intervention r??ussie'                      => EtatInterventionReussie::class,
            ],
            'Interventions termin??es et en ??chec' => [
                'Intervention en ??chec'                     => EtatInterventionEchouee::class,
            ],
        ];
    }
}
