<?php

namespace App\Form;

use App\Entity\Service;
use App\Entity\DemandeIntervention;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Repository\ServiceRepository;
use Symfony\Component\Security\Core\Security;

class DemandeInterventionRenvoyeeType extends AbstractType
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

        // On construit le formulaire
        $builder
            ->add('demandePar', EntityType::class, [
                'required' => false,
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (ServiceRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->orderBy('s.label', 'ASC');
                }
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DemandeIntervention::class,
        ]);
    }
}
