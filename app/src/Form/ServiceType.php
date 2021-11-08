<?php

namespace App\Form;

use App\Entity\Service;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ServiceType extends AbstractType
{

    private $authChecker;

    public function __construct(AuthorizationCheckerInterface $authChecker)
    {
        $this->authChecker = $authChecker;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $service = $builder->getData();
        // met en forme la liste de choix des roles utilisée plus loin dans cette méthode
        $choixRoles = [];
        foreach (Service::listeRoles() as $role) {
            switch ($role) {
                case 'ROLE_ADMIN':
                    $choixRoles['Administrateur'] = $role;
                    break;
                case 'ROLE_DME':
                    $choixRoles['DME/Pilotage'] = $role;
                    break;
                case 'ROLE_INTERVENANT':
                    $choixRoles['Intervenant'] = $role;
                    break;
                case 'ROLE_INVITE':
                    $choixRoles['Invité'] = $role;
                    break;
                default:
                    $choixRoles[$role] = $role;
            }
        }
        // Génère une liste de champs constituant le formulaire
        $builder
            ->add('label', TextType::class, [
                'required' => true,
            ])
            ->add('estServiceExploitant', CheckboxType::class, [
                'required' => false,
            ])
            ->add('estBureauRattachement', CheckboxType::class, [
                'required' => false,
            ])
            ->add('estStructureRattachement', CheckboxType::class, [
                'required' => false,
            ])
            ->add('estPilotageDme', CheckboxType::class, [
                'required' => false,
            ])
            ->add('structurePrincipale', EntityType::class, [
                'class'         => Service::class,
                'choice_label'  => 'label',
                'multiple'      => false,
                'expanded'      => false,
                'required'      => false,
                'query_builder' => function (EntityRepository $er) use ($service) {
                    if ($service && !empty($service->getId())) {
                        return $er->createQueryBuilder('s')
                        ->orderBy('s.label', 'ASC')
                        ->where('s.id != ?1')
                        ->andWhere('s.supprimeLe is null')
                        ->setParameter(1, $service->getId());
                    } else {
                        return $er->createQueryBuilder('s')
                        ->orderBy('s.label', 'ASC');
                    }
                }
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => $choixRoles,
                'mapped' => true,
                'placeholder' => '',
                'multiple' => false,
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'attr'  => [
                    'placeholder' => 'Recherche dans l\'annuaire LDAP ...',
                ],
            ])
        ;
        // Le champ permettant de modifier le role_usurpateur est disponible uniquement si on est administrateur
        if ($this->authChecker->isGranted(Service::ROLE_ADMIN)) {
            $builder->add('estRoleUsurpateur', CheckboxType::class, [
                'required' => false,
            ]);
        }
        // Permet de convertir la valeur unique sélectionné dans la liste des roles en liste de roles utilisée par l'entité
        $builder->get('roles')->addModelTransformer(new CallbackTransformer(
            function (array $rolesArray) {
                return reset($rolesArray);
            },
            function (string $rolesString) {
                return [$rolesString];
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
}
