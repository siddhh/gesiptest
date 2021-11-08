<?php

namespace App\Form\Calendrier;

use App\Entity\MepSsi;
use App\Entity\Composant;
use App\Entity\Service;
use App\Entity\Pilote;
use App\Entity\DemandeIntervention;
use App\Entity\References\GridMep;
use App\Entity\References\StatutMep;
use App\Form\Controls\SearchMultiSelectType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class MepSsiType extends AbstractType
{
    /** @var RouterInterface */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('palier', TextType::class, [
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('composants', SearchMultiSelectType::class, [
                'class' => Composant::class,
                'urlRecherche' => $this->router->generate('ajax-multi-select-type-composants'),
                'label' => 'Composants*',
                'constraints' => [
                    new Count([ 'min' => 1, 'minMessage' => "Vous devez sélectionner au moins un composant." ])
                ]
            ])
            ->add('visibilite', ChoiceType::class, [
                'placeholder' => '',
                'choices'   => [
                    'DME'   => 'DME',
                    'SI2'   => 'SI2A',
                    'SSI'   => 'SSI'
                ],
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('equipe', EntityType::class, [
                'placeholder' => '',
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                    ->orderBy('s.label', 'ASC')
                    ->Where('s.supprimeLe is null')
                    ->andWhere('s.estPilotageDme = true');
                },
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('pilotes', SearchMultiSelectType::class, [
                'class' => Pilote::class,
                'urlRecherche' => $this->router->generate('ajax-multi-select-type-pilotes'),
                'label' => 'Pilotes',
                'required' => false,
                'itemLabel' => 'nomCompletCourt',
            ])
            ->add('demandesInterventions', SearchMultiSelectType::class, [
                'class' => DemandeIntervention::class,
                'urlRecherche' => $this->router->generate('ajax-multi-select-type-demandes'),
                'label' => 'GESIP liés',
                'required' => false,
                'itemLabel' => 'numero',
            ])
            ->add('lep', DateType::class, [
                'widget'        => 'single_text',
                'format'        => 'dd/MM/yyyy',
                'view_timezone' => 'Europe/Paris',
                'required' => false,
            ])
            ->add('mepDebut', DateType::class, [
                'widget'        => 'single_text',
                'format'        => 'dd/MM/yyyy',
                'view_timezone' => 'Europe/Paris',
                'required' => false,
            ])
            ->add('mepFin', DateType::class, [
                'widget'        => 'single_text',
                'format'        => 'dd/MM/yyyy',
                'view_timezone' => 'Europe/Paris',
                'required' => false,
            ])
            ->add('mes', DateType::class, [
                'widget'        => 'single_text',
                'format'        => 'dd/MM/yyyy',
                'view_timezone' => 'Europe/Paris',
                'required' => true,
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->add('impacts', TextareaType::class, [
                'required' => false,
            ])
            ->add('risques', TextareaType::class, [
                'required' => false,
            ])
            ->add('motsClefs', TextType::class, [
                'required' => false,
            ])
            ->add('grids', EntityType::class, [
                'placeholder' => '',
                'class' => GridMep::class,
                'choice_label' => 'label',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->Where('s.supprimeLe is null')
                        ->orderBy('s.label', 'ASC');
                }
            ])
            ->add('statut', EntityType::class, [
                'required' => true,
                'class' => StatutMep::class,
                'placeholder' => '',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->andWhere('s.supprimeLe is null')
                        ->orderBy('s.label', 'ASC');
                },
                'constraints' => [
                    new NotBlank()
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MepSsi::class,
            'constraints' => [
                new Callback(['callback' => [$this, 'validate']]),
            ],
        ]);
    }

    /**
     * Fonction permettant de rajouter des contraintes de validation globale. (doit être déclarée dans configureOptions)
     *
     * @param MepSsi                     $mepSsi
     * @param ExecutionContextInterface $context
     */
    public function validate(MepSsi $mepSsi, ExecutionContextInterface $context): void
    {
        // Si mepFin est rempli et que mepDebut n'est pas rempli ou postérieur à mepFin
        if ($mepSsi->getMepFin() != null && ($mepSsi->getMepDebut() === null || $mepSsi->getMepDebut() > $mepSsi->getMepFin())) {
            // Alors on ajoute une erreur
            $context->buildViolation(null)->atPath('mepFin')->addViolation();
            $context->buildViolation("La période sélectionnée est incohérente. Merci de revoir votre saisie.")
                ->atPath('mepFin')
                ->addViolation();
        }
    }
}
