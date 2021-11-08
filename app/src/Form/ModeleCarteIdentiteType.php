<?php

namespace App\Form;

use App\Entity\ModeleCarteIdentite;
use App\Service\CarteIdentiteService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\File;

class ModeleCarteIdentiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Construit le formulaire
        $builder
            ->add('fichier', FileType::class, [
                'mapped'        => false,
                'required'      => true,
                'constraints'   => [
                    new File([
                        'maxSize'           => CarteIdentiteService::getTailleMaximumFichier() . 'M',
                        'mimeTypes'         => CarteIdentiteService::mimeTypesAutorises(),
                        'mimeTypesMessage'  => 'Fichier invalide, seuls les types suivants sont acceptés: ' . implode(', ', CarteIdentiteService::extensionsAutorisees()) . '.',
                    ])
                ],
            ])
            ->add('commentaire', TextareaType::class)
            ->add('actif', CheckboxType::class, [
                'required' => false,
            ])
            ->add('publier', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ModeleCarteIdentite::class,
        ]);
    }
}
