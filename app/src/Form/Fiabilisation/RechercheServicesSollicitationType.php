<?php

namespace App\Form\Fiabilisation;

use App\Entity\References\Mission;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityRepository;
use App\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class RechercheServicesSollicitationType extends AbstractType
{

    private $serviceRepository;

    public function __construct(ServiceRepository $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // On récupère les dates de dernières solicitations
        $datesDernieresSollicitation = $this->serviceRepository->datesDernieresSollicitation();
        $datesDernieresSollicitation = array_combine($datesDernieresSollicitation, $datesDernieresSollicitation);
        // On y ajoute la valeur jamais
        $datesDernieresSollicitation = array_merge(['Jamais sollicité' => 0], $datesDernieresSollicitation);

        // On construit notre formulaire
        $builder
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.supprimeLe IS NULL')
                        ->orderBy('s.label', 'ASC');
                },
            ])
            ->add('balf', TextType::class, [
                'required' => false,
            ])
            ->add('equipe', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.supprimeLe IS NULL')
                        ->andWhere('s.estPilotageDme = true')
                        ->orderBy('s.label', 'ASC');
                },
            ])
            ->add('mission', EntityType::class, [
                'class' => Mission::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('m')
                        ->where('m.supprimeLe IS NULL')
                        ->orderBy('m.label', 'ASC');
                },
            ])
            ->add('solliciteLe', ChoiceType::class, [
                'required' => false,
                'choices' => $datesDernieresSollicitation
            ])
            ->add('reset', ResetType::class)
            ->add('search', SubmitType::class)
        ;
    }
}
