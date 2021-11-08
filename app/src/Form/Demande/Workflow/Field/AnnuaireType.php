<?php

namespace App\Form\Demande\Workflow\Field;

use App\Entity\Composant;
use App\Repository\Composant\AnnuaireRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class AnnuaireType extends AbstractType
{

    /** @var EntityManager $entityManager */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // On ajoute un champ qui n'est qu'un champ permettant de "cocher" les éléments
        $builder->add('ids', EntityType::class, [
            'class' => Composant\Annuaire::class,
            'multiple' => true,
            'choice_label' => 'id',
            'query_builder' => function (AnnuaireRepository $er) use ($options) {
                return $er->createQueryBuilder('a')
                    ->addSelect('m', 's', 'c')
                    ->join('a.mission', 'm')
                    ->join('a.service', 's')
                    ->join('a.composant', 'c')
                    ->where('a.composant IN (:composants)')
                    ->andWhere('a.supprimeLe IS NULL')
                    ->setParameter('composants', $options['composants'])
                    ->orderBy('c.label', 'ASC')
                    ->addOrderBy('s.label', 'ASC');
            },
            'constraints' => [
                new Count([
                    'min' => $options['min'],
                    'minMessage' => $options['minMessage']
                ])
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        // On doit absolument passer un tableau dans une variable "composants"
        $resolver->setRequired('composants');
        $resolver->setRequired('all_selected');
        $resolver->setRequired('min');
        $resolver->setRequired('minMessage');
        $resolver->setAllowedTypes('composants', 'array');
        $resolver->setAllowedTypes('all_selected', 'boolean');
        $resolver->setAllowedTypes('min', 'int');
        $resolver->setAllowedTypes('minMessage', 'string');

        // On autorise la possibilité de saisir un label pour le bouton
        $resolver->setDefaults([
            'btn_label' => 'Sélectionner les services',
            'all_selected' => false,
            'min' => 0,
            'minMessage' => "Un service doit au moins être sélectionné."
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        // On passe les variables à notre vue via l'objet directement
        $view->vars = array_merge($view->vars, [
            'btn_label' => $options['btn_label'],
            'all_selected' => $options['all_selected']
        ]);
    }
}
