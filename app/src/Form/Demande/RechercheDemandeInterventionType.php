<?php

namespace App\Form\Demande;

use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Security\Core\Security;

class RechercheDemandeInterventionType extends AbstractType
{

    private $security;
    private $em;

    public function __construct(Security $security, EntityManagerInterface $em)
    {
        $this->security = $security;
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($this->security->isGranted(Service::ROLE_GESTION)
            || $this->security->isGranted(Service::ROLE_INTERVENANT)) {
            $builder
                ->add('status', ChoiceType::class, [
                    'required'  => true,
                    'multiple'  => false,
                    'expanded'  => true,
                    'choices'   => [
                        'Toutes Demandes'           => '',
                        'En cours d\'analyse'       => 'EtatAnalyseEnCours',
                        'Consultation en cours'     => 'EtatConsultationEnCours,EtatConsultationEnCoursCdb',
                        'Consultation instruite'    => 'EtatInstruite',
                        'Demandes renvoyées'        => 'EtatRenvoyee',
                        'Accord'                    => 'EtatAccordee',
                        'En cours'                  => 'EtatInterventionEnCours',
                        'Réalisé'                   => 'EtatSaisirRealise',
                    ],
                    'data' => ''
                ])
            ;
        }

        if ($this->security->isGranted(Service::ROLE_GESTION)) {
            // -- Filtrage par équipe
            // On crée nos options possibles dans le sélecteur, avec "Sans équipe" et les services DME
            $optionsEquipe = [ '' => '', 'Sans équipe associée' => 'sans-equipe-associee' ];
            $servicesDME = $this->em->getRepository(Service::class)
                ->createQueryBuilder('s')
                ->where('s.estPilotageDme = true')
                ->andWhere('s.supprimeLe is null')
                ->orderBy('s.label', 'ASC')
                ->getQuery()
                ->getResult();
            foreach ($servicesDME as $service) {
                $optionsEquipe[$service->getLabel()] = $service->getId();
            }
            // On ajoute le champ équipe
            $builder
                ->add('equipe', ChoiceType::class, [
                    'multiple' => false,
                    'expanded' => false,
                    'required' => false,
                    'label'    => 'Équipe',
                    'choices'  => $optionsEquipe,
                    'data'     =>
                        $this->security->isGranted(Service::ROLE_DME) && !$this->security->isGranted(Service::ROLE_ADMIN) ?
                            $this->security->getUser() :
                            null
                ]);

            // On ajoute les champs de Réponse en retard, Retour consultation négatif, ou demande urgente
            $builder
                ->add('reponseEnRetard', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('retourConsultationNegatif', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('demandeUrgente', CheckboxType::class, [
                    'required' => false,
                ])
            ;
        }
    }
}
