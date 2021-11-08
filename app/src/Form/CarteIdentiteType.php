<?php

namespace App\Form;

use App\Entity\CarteIdentite;
use App\Entity\ComposantCarteIdentite;
use App\Entity\Composant;
use App\Service\CarteIdentiteService;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CarteIdentiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Charge l'entité manager à partir des options fournies
        $em = $options['em'];
        // Construit le formulaire
        $builder
            ->add('action', HiddenType::class, [
                'required'      => true,
                'mapped'        => false,
                'data'          => 'ajout',
            ])
            ->add('composantLabel', TextType::class, [
                'required'      => false,
                'mapped'        => false,
                'constraints' => [
                    new Callback(function ($label, ExecutionContextInterface $context, $payload) use ($em) {
                        $action = $context->getRoot()->get('action')->getData();
                        $composantId = $context->getRoot()->get('composant')->getData();
                        if ($action === 'ajout') {
                            if (empty($composantId) && empty($label)) {
                                $context
                                    ->buildViolation('Choisissez ou fournissez un libellé de composant à créer.')
                                    ->addViolation();
                            } elseif (!empty($label)) {
                                $cci =  $em->getRepository(ComposantCarteIdentite::class)->libelleComposantDejaUtilise($label);
                                if (!empty($cci)) {
                                    $context
                                        ->buildViolation('Composant carte identité déjà existant.')
                                        ->addViolation();
                                }
                            }
                        }
                    }),
                ],
            ])
            ->add('composant', EntityType::class, [
                'required'      => false,
                'class'         => Composant::class,
                'choice_label'  => 'label',
                'choice_attr'   => function (Composant $composant) {
                    return ['data-carte' => (count($composant->getCarteIdentites()) == 0 ? 'non' : 'oui')];
                },
                'multiple'      => false,
                'placeholder'   => '',
                'expanded'      => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('co')
                        ->addSelect('ca')
                        ->leftJoin('co.carteIdentites', 'ca')
                        ->where('co.archiveLe IS NULL')
                        ->orderBy('UPPER(co.label)', 'ASC')
                    ;
                }
            ])
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
            ->add('commentaire', TextareaType::class, [
                'required'      => false,
                'mapped'        => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('em');
        $resolver->setAllowedTypes('em', EntityManagerInterface::class);
        $resolver->setDefaults([
            'data_class' => CarteIdentite::class,
        ]);
    }
}
