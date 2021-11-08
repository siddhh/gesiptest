<?php

namespace App\Form\Demande;

use App\Entity\Composant;
use App\Entity\Demande\Impact;
use App\Entity\References\NatureImpact;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImpactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nature', EntityType::class, [
                'class' => NatureImpact::class,
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('n')
                        ->where('n.supprimeLe IS NULL')
                        ->orderBy('n.label', 'ASC');
                }
            ])
            ->add('certitude', ChoiceType::class, [
                'choices'           => [
                    'Non'   => 0,
                    'Oui'   => 1,
                ],
                'empty_data' => 0
            ])
            ->add('commentaire', TextType::class)
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
            ->add('composants', EntityType::class, [
                'class' => Composant::class,
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->where('c.archiveLe IS NULL')
                        ->orderBy('LOWER(c.label)', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Impact::class,
        ]);
    }
}
