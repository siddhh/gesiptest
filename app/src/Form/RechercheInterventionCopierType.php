<?php

namespace App\Form;

use App\Entity\Composant;
use App\Entity\References\MotifIntervention;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Service;
use Symfony\Component\Security\Core\Security;

class RechercheInterventionCopierType extends AbstractType
{
    /** @var Security */
    private $security;
    /** @var EntityManager */
    private $entityManager;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // On récupère le service courant
        /** @var Service $serviceCourant */
        $serviceCourant = $this->security->getUser();
        $filtreServices = [$serviceCourant->getId()];

        // Si l'utilisateur courant est une structure principale, alors on récupère les services associés
        //  (afin de les ajouter de le filtrage des composants)
        if ($serviceCourant->getEstStructureRattachement()) {
            $tmpServices = $this->entityManager->getRepository(Service::class)->findBy([
                'structurePrincipale' => $serviceCourant,
                'supprimeLe' => null,
            ]);
            /** @var Service $s */
            foreach ($tmpServices as $s) {
                $filtreServices[] = $s->getId();
            }
        }

        $builder
            ->add('demandePar', EntityType::class, [
                'class'         => Service::class,
                'choice_label'  => 'label',
                'multiple'      => false,
                'expanded'      => false,
                'required'      => true,
                'data'          => $this->security->isGranted(Service::ROLE_GESTION) ? null : $serviceCourant,
                'query_builder' => function (EntityRepository $er) use ($filtreServices) {
                    if ($this->security->isGranted(Service::ROLE_GESTION)) {
                        // On récupère tous les services ayant déjà créé une demande (pour les admins, et les DME)
                        return $er->createQueryBuilder('s')
                            ->join('s.demandesIntervention', 'd')
                            ->orderBy('s.label', 'ASC');
                    }
                    // Pour les autres services on affichera le service actuellement connecté à minima,
                    //  ainsi que les services ayant pour structure principale le service connecté si ce dernier est une structure principale.
                    return $er->createQueryBuilder('s')
                        ->where('s.id IN (:serviceIds)')
                        ->setParameter('serviceIds', $filtreServices)
                        ->orderBy('s.label', 'ASC');
                }
            ])
            ->add('composantConcerne', EntityType::class, [
                'class'         => Composant::class,
                'choice_label'  => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) use ($filtreServices) {
                    // Si l'utilisateur courant est ADMIN ou DME (aka ROLE_GESTION)
                    if ($this->security->isGranted(Service::ROLE_GESTION)) {
                        // On récupère tous les composants non archivé et trié par label
                        return $er->createQueryBuilder('c')
                            ->andWhere('c.archiveLe is null')
                            ->orderBy('c.label', 'ASC');
                    }
                    // Sinon, on renvoi les composants associés à l'utilisateur courant pour
                    //  des missions spécifiques
                    return $er->createQueryBuilder('c')
                        ->join('c.annuaire', 'a')
                        ->join('a.mission', 'm')
                        ->where('a.service IN (:services)')
                        ->andWhere('c.archiveLe is null')
                        ->andWhere('m.label IN (:missions)')
                        ->setParameter('services', $filtreServices)
                        ->setParameter('missions', [
                            'ES Exploitant Système',
                            'EA Exploitant Applicatif',
                            'MOA',
                            'MOA Associée',
                            'MOE',
                            'MOE Déléguée',
                            'ESI hebergeur',
                            'Développement',
                            'Scrum master',
                            'Product Owner',
                            'Dev Team',
                            'Equipe OPS'
                        ])
                        ->orderBy('LOWER(c.label)', 'ASC');
                }
            ])
            ->add('motifIntervention', EntityType::class, [
                'class' => MotifIntervention::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('m')
                        ->where('m.supprimeLe IS NULL')
                        ->orderBy('m.label', 'ASC');
                }
            ])
            ->add('search', SubmitType::class, [
                'label' => 'Rechercher',
            ]);
    }
}
