<?php

namespace App\Form\Meteo\Statistiques;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class RepartitionType extends AbstractType
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
        // Création liste mois
        $tz = new \DateTimeZone('Europe/Paris');
        $listeMois = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre'
        ];

        // Création liste année
        $dateCourante = new \DateTime();
        $anneeCourante = $dateCourante->setTimezone($tz)->format('Y');
        $listeAnnees = range($anneeCourante, $anneeCourante - 4);
        $listeAnnees = array_combine($listeAnnees, $listeAnnees);

        // On récupère les informations de rôle ainsi que l'utilisateur courant pour plus tard
        $estGestion = $this->security->isGranted(Service::ROLE_GESTION);
        $serviceConnecte = $this->security->getUser();

        // On déclare le contenu de notre formulaire
        $builder
            // Champ "Exploitant"
            ->add('exploitant', EntityType::class, [
                'required'          => false,
                'data'              => !$estGestion ? $serviceConnecte : null,
                'class'             => Service::class,
                'choice_label'      => 'label',
                'multiple'          => false,
                'expanded'          => false,
                'placeholder'       => $estGestion ? '' : false,
                'disabled'          => !$estGestion,
                'query_builder'     => function (ServiceRepository $er) use ($estGestion, $serviceConnecte) {
                    // On commence notre requête simplement en prenant les services non supprimé et déclaré en tant qu'exploitant
                    $query = $er->createQueryBuilder('s')
                        ->orderBy('s.label', 'ASC')
                        ->where('s.supprimeLe is null')
                        ->andWhere('s.estServiceExploitant = true');

                    // Si nous ne sommes pas ROLE_GESTION, alors on force l'affichage du service connecté seulement
                    if (!$estGestion) {
                        $query->andWhere('s.id = :serviceConnecte')
                            ->setParameter('serviceConnecte', $serviceConnecte);
                    }

                    // On renvoi la requête pour que le formulaire la traite lors de l'affichage
                    return $query;
                },
            ])
            ->add('mois', ChoiceType::class, [
                'required'  => true,
                'choices'   => array_flip($listeMois),
                'data'      => date('n')
            ])
            ->add('annee', ChoiceType::class, [
                'required'  => true,
                'choices'   => $listeAnnees,
                'data'      => date('Y')
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
        ]);
    }
}
