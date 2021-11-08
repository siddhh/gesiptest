<?php

namespace App\Form\Meteo\Statistiques;

use App\Entity\Composant;
use App\Entity\DemandeIntervention;
use App\Entity\References\MotifIntervention;
use App\Entity\References\NatureImpact;
use App\Entity\Service;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class InterventionsAvanceesType extends AbstractType
{

    /**
     * Permet de rendre une liste de choix (ou la clé par rapport à un paramètre donnée).
     *
     * @param string|null $value
     * @return false|string|string[]
     */
    public static function getChoix1(string $value = null)
    {
        $array = [
            "Demandeur" => 'demandeur',
            "Nature" => 'nature',
            "Composant" => 'composant',
            "Motif" => 'motif',
            "Exploitant" => 'exploitant',
            "Composant Impacté" => 'composant-impacte',
            "Impact prévisionnel" => 'impact-previsionnel',
            "Impact réel" => 'impact-reel',
            "Date d'intervention - Mois" => 'date-mois',
            "Date d'intervention - Trimestre" => 'date-trimestres',
            "Date d'intervention - Année" => 'date-annees',
        ];

        if ($value !== null) {
            return array_search($value, $array);
        }

        return $array;
    }

    /**
     * Permet de rendre une liste de choix (ou la clé par rapport à un paramètre donnée).
     *
     * @param string|null $value
     * @return false|string|string[]
     */
    public static function getChoix2(string $value = null)
    {
        $array = [
            "Nombre d'interventions" => 'nbr-interventions',
            "Durée moyenne prévisionnelle d'intervention (minutes)" => 'duree-moyenne-previsionnelle',
            "Durée moyenne réelle d'intervention (minutes)" => 'duree-moyenne-reelle',
            "Délai moyen de réponse DME (jours)" => 'delai-moyen-reponse-dme',
        ];

        if ($value !== null) {
            return array_search($value, $array);
        }

        return $array;
    }

    /**
     * Création du formulaire de saisie des filtres pour la page statistique
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // On génère notre tableau d'années
        $anneeEnCours = date('Y');
        $choixAnnees = range($anneeEnCours - 5, $anneeEnCours);
        $choixAnnees = array_combine($choixAnnees, $choixAnnees);

        // On déclare le contenu de notre formulaire
        $builder
            /** Partie filtres */
            // Champ "Demandeur"
            ->add('demandeur', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.supprimeLe IS NULL')
                        ->orderBy('s.label', 'ASC');
                }
            ])
            // Champ "Nature"
            ->add('nature', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Normal' => DemandeIntervention::NATURE_NORMAL,
                    'Urgent' => DemandeIntervention::NATURE_URGENT
                ],
                'placeholder' => '',
            ])
            // Champ "Composant"
            ->add('composant', EntityType::class, [
                'class' => Composant::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->andWhere('c.archiveLe IS NULL')
                        ->orderBy('LOWER(c.label)', 'ASC');
                }
            ])
            // Champ "motif"
            ->add('motif', EntityType::class, [
                'class' => MotifIntervention::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('m')
                        ->andWhere('m.supprimeLe IS NULL')
                        ->orderBy('m.label', 'ASC');
                }
            ])
            // Champ "Exploitant"
            ->add('exploitant', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.supprimeLe IS NULL')
                        ->andWhere('s.estServiceExploitant = true')
                        ->orderBy('s.label', 'ASC');
                }
            ])
            // Champ "Composant impacté"
            ->add('composantImpacte', EntityType::class, [
                'class' => Composant::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->andWhere('c.archiveLe IS NULL')
                        ->orderBy('LOWER(c.label)', 'ASC');
                }
            ])
            // Champ "Impact prévu"
            ->add('impactPrevu', EntityType::class, [
                'class' => NatureImpact::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('n')
                        ->andWhere('n.supprimeLe IS NULL')
                        ->orderBy('n.label', 'ASC');
                }
            ])
            // Champ "Impact réel"
            ->add('impactReel', EntityType::class, [
                'class' => NatureImpact::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('n')
                        ->andWhere('n.supprimeLe IS NULL')
                        ->orderBy('n.label', 'ASC');
                }
            ])
            // Champ "Décision DME"
            ->add('decisionDme', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Accord' => 'Accord',
                    'Refus' => 'Refus',
                    'En attente' => 'En attente',
                ],
                'placeholder' => '',
            ])
            // Champ "Période de" (défaut : année en cours)
            ->add('periodeDebut', ChoiceType::class, [
                'choices' => $choixAnnees,
                'data' => $anneeEnCours,
            ])
            // Champ "Période fin" (défaut : année en cours)
            ->add('periodeFin', ChoiceType::class, [
                'choices' => $choixAnnees,
                'data' => $anneeEnCours,
            ])

            /** Partie Tableau statistiques */
            // Champ "Recherche par"
            ->add('statistiquesRecherchePar', ChoiceType::class, [
                'required' => false,
                'placeholder' => '',
                'choices' => self::getChoix1(),
                'data' => 'demandeur',
                'constraints' => [
                    new NotBlank([ 'groups' => 'statistiques' ])
                ]
            ])
            // Champ "Statistique 1"
            ->add('statistiquesStat1', ChoiceType::class, [
                'required' => false,
                'placeholder' => '',
                'choices' => self::getChoix2(),
                'data' => 'nbr-interventions',
                'constraints' => [
                    new NotBlank([ 'groups' => 'statistiques' ])
                ]
            ])
            // Champ "Statistique 2"
            ->add('statistiquesStat2', ChoiceType::class, [
                'required' => false,
                'placeholder' => '',
                'choices' => self::getChoix2()
            ])
            // Bouton "Visualiser"
            ->add('statistiquesVisualiser', SubmitType::class, [
                'label' => 'Visualiser',
                'validation_groups' => [ 'filtres', 'statistiques' ]
            ])

            /** Partie Tableau dynamique croisé */
            // Champ "Ligne"
            ->add('croiseLigne', ChoiceType::class, [
                'required' => false,
                'placeholder' => '',
                'choices' => self::getChoix1(),
                'data' => 'demandeur',
                'constraints' => [
                    new NotBlank([ 'groups' => 'dynamique-croise' ])
                ]
            ])
            // Champ "Colonne"
            ->add('croiseColonne', ChoiceType::class, [
                'required' => false,
                'placeholder' => '',
                'choices' => self::getChoix1(),
                'data' => 'demandeur',
                'constraints' => [
                    new NotBlank([ 'groups' => 'dynamique-croise' ])
                ]
            ])
            // Champ "Valeur"
            ->add('croiseValeur', ChoiceType::class, [
                'required' => false,
                'placeholder' => '',
                'choices' => self::getChoix2(),
                'data' => 'nbr-interventions',
                'constraints' => [
                    new NotBlank([ 'groups' => 'dynamique-croise' ])
                ]
            ])
            // Bouton "Visualiser"
            ->add('croiseVisualiser', SubmitType::class, [
                'label' => 'Visualiser',
                'validation_groups' => [ 'filtres', 'dynamique-croise' ]
            ])
        ;

        $builder
            ->add('exportXLSX', SubmitType::class, [ 'attr' => [ 'style' => 'display: none;' ] ])
            ->add('exportPDF', SubmitType::class, [ 'attr' => [ 'style' => 'display: none;' ] ]);
    }

    /**
     * Fonction permettant de modifier les options du formulaire.
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'allow_extra_fields' => true,
            'csrf_protection' => false,
            'constraints'     => [
                new Callback(['callback' => [$this, 'validate'], 'groups' => 'filtres']),
            ],
        ]);
    }

    /**
     * Fonction permettant de rajouter des contraintes de validation globale. (doit être déclarée dans configureOptions)
     *
     * @param array                     $data
     * @param ExecutionContextInterface $context
     */
    public function validate(array $data, ExecutionContextInterface $context): void
    {
        // Si l'année de début est après l'année de fin
        if ($data['periodeDebut'] > $data['periodeFin']) {
            // Alors on ajoute une erreur
            $context->buildViolation(null)->atPath('periodeDebut')->addViolation();
            $context->buildViolation("La période sélectionnée est incohérente. Merci de revoir votre saisie.")
                ->atPath('periodeFin')
                ->addViolation();
        }
    }
}
