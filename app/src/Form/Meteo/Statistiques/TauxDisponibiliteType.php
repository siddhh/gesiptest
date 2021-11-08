<?php

namespace App\Form\Meteo\Statistiques;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TauxDisponibiliteType extends AbstractType
{
    /** @var Security */
    private $security;

    /**
     * TauxDisponibiliteType constructor.
     *
     * @param Security $security
     */
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Création du formulaire de saisie des filtres pour la page statistique
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // On définie notre timezone
        $tz = new \DateTimeZone('Europe/Paris');

        // On récupère les informations de rôle ainsi que l'utilisateur courant pour plus tard
        $estGestion = $this->security->isGranted(Service::ROLE_GESTION);
        $serviceConnecte = $this->security->getUser();

        // On déclare le contenu de notre formulaire
        $builder
            // Champ "Exploitant"
            ->add('exploitant', EntityType::class, [
                'required'          => false,
                'class'             => Service::class,
                'choice_label'      => 'label',
                'multiple'          => false,
                'expanded'          => false,
                'placeholder'       => $estGestion ? '' : false,
                'query_builder'     => function (ServiceRepository $er) use ($estGestion, $serviceConnecte) {
                    // On commence notre requête simplement en prenant les services non supprimé et déclaré en tant qu'exploitant
                    $query = $er->createQueryBuilder('s')
                        ->orderBy('s.label', 'ASC')
                        ->Where('s.supprimeLe is null')
                        ->AndWhere('s.estServiceExploitant = true');

                    // Si nous ne sommes pas ROLE_GESTION, alors on force l'affichage du service connecté seulement
                    if (!$estGestion) {
                        $query->andWhere('s.id = :serviceConnecte')
                            ->setParameter('serviceConnecte', $serviceConnecte);
                    }

                    // On renvoi la requête pour que le formulaire la traite lors de l'affichage
                    return $query;
                },
                'constraints' => [
                    new NotBlank(),
                ]
            ])
            // Champ "Période début" (défaut : premier jour du mois dernier)
            ->add('debut', DateType::class, [
                'widget'            => 'single_text',
                'required'          => false,
                'html5'             => false,
                'format'            => 'dd/MM/yyyy',
                'view_timezone'     => 'Europe/Paris',
                'data'              => new \DateTime('00:00:00 first day of previous month', $tz),
                'constraints'       => [
                    new NotBlank(),
                ]
            ])
            // Champ "Période fin"  (défaut : dernier jour du mois dernier)
            ->add('fin', DateType::class, [
                'widget'            => 'single_text',
                'required'          => false,
                'html5'             => false,
                'format'            => 'dd/MM/yyyy',
                'view_timezone'     => 'Europe/Paris',
                'data'              => new \DateTime('00:00:00 last day of previous month', $tz),
                'constraints'       => [
                    new NotBlank(),
                ]
            ])
        ;
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
            'constraints'     => [
                new Callback([$this, 'validate']),
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
        // Si la date de début est après la date de fin
        if ($data['debut'] > $data['fin']) {
            // Alors on ajoute une erreur
            $context->buildViolation(null)->atPath('[debut]')->addViolation();
            $context->buildViolation("La période sélectionnée est incohérente. Merci de revoir votre saisie.")
                ->atPath('[fin]')
                ->addViolation();
        }
    }
}
