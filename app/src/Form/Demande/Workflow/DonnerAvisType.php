<?php

namespace App\Form\Demande\Workflow;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class DonnerAvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('envoyerMail', CheckboxType::class, [
                'data' => true,
                'label' => 'Avec envoi de mail',
                'required' => false
            ])
            ->add('avis', ChoiceType::class, [
                'label'     => 'Avis',
                'choices'   => [
                    'Favorable'     => 'ok',
                    'Non favorable' => 'ko',
                ],
                'multiple'  => false,
                'expanded'  => true,
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('commentaire', TextareaType::class, [
                'required' => true,
            ])
        ;
    }

    /**
     * Méthode de validation complémentaire
     * @param array $data
     * @param ExecutionContextInterface $context
     * @return void
     */
    public function validate(array $data, ExecutionContextInterface $context): void
    {
        // teste si le commentaire n'est pas vide en cas d'avis défavorable
        if ($data['avis'] != 'ok' && empty($data['commentaire'])) {
            $context
                ->buildViolation('Si votre avis est défavorable précisez les raisons en commentaire.')
                ->atPath('commentaire')
                ->addViolation()
            ;
        }
    }

    /**
     * Ajoute une fonction de callback qui sera appelée lors de la validation.
     * @param OptionsResolver $resolver
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        // On doit absolument passer une Machine à état dans une variable "mae"
        $resolver->setDefaults([
            'constraints' => [
                new Callback([$this, 'validate']),
            ]
        ]);
    }
}
