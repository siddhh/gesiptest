<?php

namespace App\Form\Demande\Workflow;

use App\Form\Demande\Workflow\ImpactReelType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SaisieRealiseType2 extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('impactReels', CollectionType::class, [
                'entry_type'    => ImpactReelType::class,
                'allow_add'     => true,
                'allow_delete'  => true,
            ])
            ->add('resultat', ChoiceType::class, [
                'multiple'      => false,
                'expanded'      => true,
                'choices'       => [
                    'Réussie'   => 'ok',
                    'En échec'  => 'ko',
                ],
            ])
            ->add('commentaire', TextareaType::class, [
                'required' => false,
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
        // teste si le commentaire n'est pas vide en cas de fail
        if (!$data['resultat'] && empty($data['commentaire'])) {
            $context
                ->buildViolation('Si l\'intervention n\'est pas réussie, vous devez remplir le champ commentaire.')
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
        $resolver->setDefaults([
            'constraints'   => [
                new Callback([$this, 'validate']),
            ]
        ]);
    }
}
