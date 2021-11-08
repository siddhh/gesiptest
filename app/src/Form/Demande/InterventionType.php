<?php

namespace App\Form\Demande;

use App\Entity\Composant;
use App\Entity\Composant\Annuaire;
use App\Entity\DemandeIntervention;
use App\Entity\References\MotifIntervention;
use App\Entity\Service;
use App\Form\Demande\ImpactType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class InterventionType extends AbstractType
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
            ->add('numero', HiddenType::class, [
                'required' => true,
            ])
            ->add('natureIntervention', ChoiceType::class, [
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'data' => DemandeIntervention::NATURE_NORMAL,
                'choices'  => [
                    'Urgente' => DemandeIntervention::NATURE_URGENT,
                    'Normale' => DemandeIntervention::NATURE_NORMAL,
                ],
            ])
            ->add('palierApplicatif', ChoiceType::class, [
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'choices'  => [
                    'Oui' => true,
                    'Non' => false,
                ]])
            ->add('description')
            ->add('solutionContournement')
            ->add('composant', EntityType::class, [
                'class' => Composant::class,
                'choice_label' => 'label',
                'placeholder' => '',
                'required' => true,
                'query_builder' => function (EntityRepository $er) use ($filtreServices) {
                    // Si l'utilisateur courant est ADMIN ou DME (aka ROLE_GESTION)
                    if ($this->security->isGranted(Service::ROLE_GESTION)) {
                        // On récupère tous les composants non archivé et trié par label
                        return $er->createQueryBuilder('c')
                            ->andWhere('c.archiveLe is null')
                            ->orderBy('LOWER(c.label)', 'ASC');
                    }
                    // Sinon, on renvoie les composants associés à l'utilisateur courant pour
                    //  des missions spécifiques
                    return $er->createQueryBuilder('c')
                        ->join('c.annuaire', 'a')
                        ->join('a.mission', 'm')
                        ->where('a.supprimeLe IS NULL')
                        ->andWhere('a.service IN (:services)')
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
                'class'         => MotifIntervention::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('m')
                        ->where('m.supprimeLe IS NULL')
                        ->orderBy('m.label', 'ASC');
                }
            ])
            ->add('services', EntityType::class, [
                'class'         => Annuaire::class,
                'choice_label'  => function ($annuaire) {
                    return $annuaire->getService()->getLabel() . ' - ' . $annuaire->getMission()->getLabel();
                },
                'multiple'      => true,
                'expanded'      => false,
                'required'      => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('a')
                        ->addSelect('a', 's', 'm')
                        ->join('a.service', 's')
                        ->join('a.mission', 'm')
                        ->where('a.supprimeLe IS NULL');
                },
            ])
            ->add('exploitantExterieurs', EntityType::class, [
                'class'         => Service::class,
                'multiple'      => true,
                'expanded'      => false,
                'required'      => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.supprimeLe IS NULL')
                        ->andWhere('s.estServiceExploitant = true')
                        ->orderBy('s.label', 'asc');
                },
            ])
            ->add('dateDebut', DateTimeType::class, [
                'widget'        => 'single_text',
                'format'        => 'dd/MM/yyyy HH:mm',
                'view_timezone' => 'Europe/Paris',
            ])
            ->add('dateFinMini', DateTimeType::class, [
                'widget'        => 'single_text',
                'format'        => 'dd/MM/yyyy HH:mm',
                'view_timezone' => 'Europe/Paris',
            ])
            ->add('dateFinMax', DateTimeType::class, [
                'widget'        => 'single_text',
                'format'        => 'dd/MM/yyyy HH:mm',
                'view_timezone' => 'Europe/Paris',
            ])
            ->add('dureeRetourArriere', IntegerType::class)
            ->add('impacts', CollectionType::class, [
                'entry_type' => ImpactType::class,
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('sendMail', CheckboxType::class, [
                'mapped' => false,
                'data' => true
            ])
            ->add('status', ChoiceType::class, [
                'required' => true,
                'expanded' => false,
                'multiple' => false,
                'choices'  => [
                    'Brouillon'                     => 'brouillon',
//                    'Demande en cours d\'analyse'   => 'Demande en cours d\'analyse',
                    'Analyse en cours'              => 'analyse-en-cours',
                ],
            ])
        ;
    }

    /**
     * Méthode de validation complémentaire
     * @param DemandeIntervention $demandeIntervention
     * @param ExecutionContextInterface $context
     * @return void
     */
    public function validate(DemandeIntervention $demandeIntervention, ExecutionContextInterface $context): void
    {
        // teste si le commentaire n'est pas vide en cas de fail
        if (count($demandeIntervention->getServices()) <= 0
            && count($demandeIntervention->getExploitantExterieurs()) <= 0) {
            $context
                ->buildViolation('Au moins un service doit être sélectionné comme exploitant.')
                ->atPath('services')
                ->addViolation()
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DemandeIntervention::class,
            'constraints'   => [
                new Callback([$this, 'validate']),
            ]
        ]);
    }
}
