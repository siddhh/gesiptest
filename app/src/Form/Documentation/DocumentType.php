<?php

namespace App\Form\Documentation;

use App\Entity\Documentation\Document;
use App\Form\Documentation\FichierType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Count;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('titre', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank()
                ],
            ])
            ->add('date', DateType::class, [
                'widget'            => 'single_text',
                'required'          => true,
                'html5'             => false,
                'format'            => 'dd/MM/yyyy',
                'model_timezone'    => 'Europe/Paris'
            ])
            ->add('version', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank()
                ],
            ])
            ->add('destinataires', TextareaType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank()
                ],
            ])
            ->add('fichiers', CollectionType::class, [
                'entry_type'        => FichierType::class,
                'entry_options'     => ['label' => false],
                'allow_add'         => true,
                'allow_delete'      => true,
                'by_reference'      => false,
                'error_bubbling'    => false,
                'constraints'       => [
                    new Count(['min' => 1]),
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }
}
