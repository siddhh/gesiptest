<?php

namespace App\Form\Meteo;

use App\Entity\Meteo\Evenement;
use App\Entity\References\ImpactMeteo;
use App\Entity\References\MotifIntervention;
use App\Repository\References\ImpactMeteoRepository;
use App\Repository\References\MotifInterventionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('id', HiddenType::class, [
                'required'          => false,
                'mapped'            => false,
            ])
            ->add('action', HiddenType::class, [
                'required'          => false,
                'mapped'            => false,
            ])
            ->add('debut', DateType::class, [
                'widget'            => 'single_text',
                'required'          => true,
                'html5'             => false,
                'format'            => 'dd/MM/yyyy HH:mm',
                'view_timezone'     => 'Europe/Paris',
            ])
            ->add('fin', DateType::class, [
                'widget'            => 'single_text',
                'required'          => true,
                'html5'             => false,
                'format'            => 'dd/MM/yyyy HH:mm',
                'view_timezone'     => 'Europe/Paris',
            ])
            ->add('impact', EntityType::class, [
                'required'          => true,
                'class'             => ImpactMeteo::class,
                'choice_label'      => 'label',
                'multiple'          => false,
                'expanded'          => false,
                'query_builder'     => function (ImpactMeteoRepository $er) {
                    return $er->createQueryBuilder('i')
                    ->orderBy('i.label', 'ASC')
                    ->Where('i.supprimeLe is null');
                }
            ])
            ->add('typeOperation', EntityType::class, [
                'required'          => true,
                'class'             => MotifIntervention::class,
                'choice_label'      => 'label',
                'multiple'          => false,
                'expanded'          => false,
                'query_builder'     => function (MotifInterventionRepository $er) {
                    return $er->createQueryBuilder('m')
                    ->orderBy('m.label', 'ASC')
                    ->Where('m.supprimeLe is null');
                }
            ])
            ->add('description', TextareaType::class, [
                'required'          => false,
            ])
            ->add('commentaire', TextareaType::class, [
                'required'          => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
