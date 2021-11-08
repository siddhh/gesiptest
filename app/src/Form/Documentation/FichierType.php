<?php

namespace App\Form\Documentation;

use App\Entity\Documentation\Fichier;
use App\Service\DocumentationService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class FichierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('ordre', HiddenType::class, [
                'required'  => true,
            ])
            ->add('label', TextType::class, [
                'required'  => true,
                'constraints' => [
                    new NotBlank(),
                    new Regex([
                        'pattern'       => '/[0-9a-zA-Z_-]{1,64}/',  // Restreint les caractères pouvant être utilisés pour nommer un fichier
                        'match'         => true,
                        'message'       => 'Seuls les caractères non-accentués sont autorisés (lettre, chiffres, caractères spéciaux _-.).',
                    ]),
                ]
            ])
            ->add('fichier', FileType::class, [
                'mapped'        => false,
                'required'      => false,
                'constraints'   => [
                    new File([
                        'maxSize'   => '64M',
                        'mimeTypes' => DocumentationService::mimeTypesAutorises(),
                        'mimeTypesMessage'  => 'Fichier invalide, seuls les types suivants sont acceptés: ' . implode(', ', DocumentationService::extensionsAutorisees()) . '.',
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Fichier::class,
        ]);
    }
}
