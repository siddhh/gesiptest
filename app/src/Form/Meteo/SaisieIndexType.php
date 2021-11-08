<?php

namespace App\Form\Meteo;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class SaisieIndexType extends AbstractType
{
    /** @var Security  */
    private $security;

    /**
     * SaisieIndexType constructor.
     *
     * @param Security $security
     */
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * On construit notre formulaire
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Si le service connecté n'est pas admin, alors on le sélectionne dans le champ exploitant
        $serviceSelectionne = !$this->security->isGranted(Service::ROLE_GESTION) ? $this->security->getUser() : null;

        $builder
            ->add('exploitant', EntityType::class, [
                'required' => false,
                'class' => Service::class,
                'choice_label' => 'label',
                'multiple' => false,
                'expanded' => false,
                'data' => $serviceSelectionne,
                'disabled' => $serviceSelectionne instanceof Service,
                'query_builder' => function (ServiceRepository $er) use ($serviceSelectionne) {
                    if ($serviceSelectionne !== null) {
                        return $er->createQueryBuilder('s')
                            ->where('s.id = :service')
                            ->setParameter('service', $serviceSelectionne);
                    }

                    return $er->createQueryBuilder('s')
                        ->orderBy('s.label', 'ASC')
                        ->andWhere('s.supprimeLe is null')
                        ->andWhere('s.estServiceExploitant = true');
                }
            ])
            ->add('periode', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'html5' => false,
                'constraints' => [
                    new NotBlank(),
                    new LessThan('today'),
                ]
            ])
        ;
    }
}
