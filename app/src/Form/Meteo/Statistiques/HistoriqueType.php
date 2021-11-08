<?php

namespace App\Form\Meteo\Statistiques;

use App\Entity\Composant;
use App\Entity\Service;
use App\Repository\ComposantRepository;
use App\Repository\ServiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class HistoriqueType extends AbstractType
{
    /** @var Security */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * On construit notre formulaire pour la page de statistiques d'historique.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // On génère le nécessaire pour choisir une année entre Année en cours - 5 et Année en cours
        $anneeEnCours = date('Y');
        $choixAnnees = range($anneeEnCours, $anneeEnCours - 5);
        $choixAnnees = array_combine($choixAnnees, $choixAnnees);

        // Construit le formulaire
        $builder
            ->add('exploitant', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => true,
                'placeholder' => $this->security->isGranted(Service::ROLE_GESTION) ? '' : false,
                'data' => !$this->security->isGranted(Service::ROLE_GESTION) ? $this->security->getUser() : false,
                'query_builder' => function (ServiceRepository $er) {
                    $query = $er->createQueryBuilder('s')
                        ->andWhere('s.supprimeLe is null')
                        ->andWhere('s.estServiceExploitant = true')
                        ->orderBy('s.label', 'ASC');

                    if (!$this->security->isGranted(Service::ROLE_GESTION)) {
                        $query->andWhere('s = :serviceConnecte')
                            ->setParameter('serviceConnecte', $this->security->getUser());
                    }

                    return $query;
                },
                'constraints' => [
                    new NotBlank(),
                ]
            ])
            ->add('composant', EntityType::class, [
                'class' => Composant::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => true,
                'placeholder' => '',
                'query_builder' => function (ComposantRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->andWhere('c.meteoActive = true')
                        ->andWhere('c.exploitant IS NOT NULL')
                        ->orderBy('LOWER(c.label)', 'ASC');
                },
                'choice_attr' => function (Composant $composant) {
                    $exploitant = $composant->getExploitant();
                    $ids = [];

                    if ($exploitant) {
                        $ids[] = $exploitant->getId();

                        if ($exploitant->getStructurePrincipale()) {
                            $ids[] = $exploitant->getStructurePrincipale()->getId();
                        }
                    }

                    return ['data-exploitant-id' => implode('|', $ids)];
                },
                'constraints' => [
                    new NotBlank(),
                ]
            ])
            ->add('annee', ChoiceType::class, [
                'required' => true,
                'choices' => $choixAnnees,
                'data' => $anneeEnCours,
                'constraints' => [
                    new NotBlank(),
                ]
            ]);
    }

    /**
     * Fonction permettant de modifier les options du formulaire.
     * (ici, on supprime la prise en charge du jeton CSRF)
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'constraints' => [
                new Callback([ $this, 'validate' ]),
            ]
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
        // On va chercher les valeurs de $data pour faciliter l'écriture
        $composant = isset($data['composant']) ? $data['composant'] : null;
        $exploitant = isset($data['exploitant']) ? $data['exploitant'] : null;

        // Si nous avons une saisie de composant et d'exploitant
        if ($composant instanceof Composant && $exploitant instanceof Service) {
            // Si le composant ne correspond pas à l'exploitant saisie ou à la structure principale
            if ($composant->getExploitant() !== $exploitant && $composant->getExploitant()->getStructurePrincipale() !== $exploitant) {
                $context->buildViolation("L'exploitant sélectionné ne gère pas ce composant.")
                    ->atPath('composant')
                    ->addViolation();
            }
        }
    }
}
