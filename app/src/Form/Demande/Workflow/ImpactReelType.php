<?php

namespace App\Form\Demande\Workflow;

use App\Entity\Composant;
use App\Entity\Demande\ImpactReel;
use App\Entity\References\NatureImpact;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImpactReelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dateDebut', DateTimeType::class, [
                'widget'        => 'single_text',
                'format'        => 'dd/MM/yyyy HH:mm',
                'view_timezone' => 'Europe/Paris',
                'label'         => 'Date de début',
            ])
            ->add('dateFin', DateTimeType::class, [
                'widget'        => 'single_text',
                'format'        => 'dd/MM/yyyy HH:mm',
                'view_timezone' => 'Europe/Paris',
                'label'         => 'Date de fin',
            ])
            ->add('nature', EntityType::class, [
                'class'         => NatureImpact::class,
                'multiple'      => false,
                'expanded'      => false,
                'required'      => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('n')
                        ->where('n.supprimeLe IS NULL')
                        ->orderBy('n.label', 'ASC');
                },
                'label'         => 'Nature',
            ])
            ->add('composants', EntityType::class, [
                'class'         => Composant::class,
                'multiple'      => true,
                'expanded'      => false,
                'required'      => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->where('c.archiveLe IS NULL')
                        ->orderBy('c.label', 'ASC');
                },
                'label'         => 'Composants',
            ])
            ->add('commentaire', TextType::class, [
                'label'         => 'Commentaire',
            ])
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        //
        $resolver->setDefaults([
            'data_class' => ImpactReel::class,
        ]);
    }
}
