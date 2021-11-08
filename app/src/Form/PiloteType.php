<?php

namespace App\Form;

use App\Entity\Pilote;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Service;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Doctrine\ORM\EntityRepository;

class PiloteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, [
                'required' => true,
            ])
            ->add('prenom', TextType::class, [
                'required' => true,
            ])
            ->add('balp', EmailType::class, [
                'required' => true,
                'attr'  => [
                    'placeholder' => 'Recherche dans l\'annuaire LDAP ...',
                ],
            ])
            ->add('equipe', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'label' => 'Equipe :',
                'placeholder' => 'Saisir...',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.estPilotageDme = true')
                        ->orderBy('s.label', 'ASC');
                }
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Pilote::class,
        ]);
    }
}
