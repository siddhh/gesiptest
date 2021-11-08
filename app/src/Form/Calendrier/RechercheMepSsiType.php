<?php

namespace App\Form\Calendrier;

use App\Entity\Composant;
use App\Entity\Pilote;
use App\Entity\Service;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RechercheMepSsiType extends AbstractType
{
    /** @var EntityManager $em */
    private $em;
    /** @var Security $security */
    private $security;

    /**
     * RechercheRestitutionComposantType constructor.
     *
     * @param EntityManagerInterface $em
     * @param Security               $security
     */
    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('periodeDebut', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'html5' => false,
                'format' => 'dd/MM/yyyy',
                'model_timezone' => 'Europe/Paris',
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('periodeFin', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'html5' => false,
                'format' => 'dd/MM/yyyy',
                'model_timezone' => 'Europe/Paris',
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('exploitants', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->andWhere('s.estServiceExploitant = true')
                        ->andWhere('s.supprimeLe IS NULL')
                        ->orderBy('s.label', 'ASC');
                }
            ])
            ->add('equipe', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->andWhere('s.estPilotageDme = true')
                        ->andWhere('s.supprimeLe IS NULL')
                        ->orderBy('s.label', 'ASC');
                }
            ])
            ->add('composants', EntityType::class, [
                'class' => Composant::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->where('c.archiveLe IS NULL')
                        ->orderBy('LOWER(c.label)', 'ASC');
                }
            ])
            ->add('pilotes', EntityType::class, [
                'class' => Pilote::class,
                'choice_label' => 'nomCompletCourt',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->andWhere('p.supprimeLe IS NULL')
                        ->orderBy('p.nom', 'ASC');
                }
            ])
            ->add('composantsImpactes', EntityType::class, [
                'class' => Composant::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->where('c.archiveLe IS NULL')
                        ->orderBy('LOWER(c.label)', 'ASC');
                }
            ])
            ->add('demandeur', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->andWhere('s.supprimeLe IS NULL')
                        ->orderBy('s.label', 'ASC');
                }
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'constraints' => [
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
        if ($data['periodeDebut'] > $data['periodeFin']) {
            // Alors on ajoute une erreur
            $context->buildViolation(null)->atPath('[periodeDebut]')->addViolation();
            $context->buildViolation("La période sélectionnée est incohérente. Merci de revoir votre saisie.")
                ->atPath('[periodeFin]')
                ->addViolation();
        }
    }
}
