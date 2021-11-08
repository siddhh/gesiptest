<?php

namespace App\Form\Demande\Workflow;

use App\Entity\Composant;
use App\Form\Demande\Workflow\Field\AnnuaireType;
use App\Workflow\MachineEtat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class LancerConsultationType extends AbstractType
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var MachineEtat $mae */
        $mae = $options['mae'];
        /** @var Composant[] $composantsAnnuaires */
        $composantsAnnuaires = [
            $mae->getDemandeIntervention()->getComposant()
        ];

        // On récupère les composants impactés non archivés.
        $idComposant = $mae->getDemandeIntervention()->getComposant()->getId();
        $idsComposantsHebergement = [];
        foreach ($mae->getDemandeIntervention()->getImpacts() as $impact) {
            foreach ($impact->getComposants() as $composant) {
                if (null === $composant->getArchiveLe()) {
                    $composantsAnnuaires[$composant->getId()] = $composant;
                    // Si le composant impacté est un site d'hébergement, on conserve son id
                    if (($composant->getId() != $idComposant) && ($composant->getEstSiteHebergement())) {
                        $idsComposantsHebergement[] = $composant->getId();
                    }
                }
            }
        }

        // Pour les composants impactés qui sont site d'hébergement, on récupère les composants impactés par ces composants
        if (count($idsComposantsHebergement) != 0) {
            $composantsImpactes = $this->em->getRepository(Composant::class)->createQueryBuilder('c')
                ->addSelect('c', 'ci')
                ->leftJoin('c.composantsImpactes', 'ci')
                ->where('c.id IN (:ids)')
                ->andWhere('c.archiveLe IS NULL')
                ->andWhere('ci.archiveLe IS NULL')
                ->setParameter(':ids', $idsComposantsHebergement)
                ->getQuery()
                ->getResult();
            foreach ($composantsImpactes as $composantsImpacte) {
                foreach ($composantsImpacte->getComposantsImpactes() as $composant) {
                    if (null === $composant->getArchiveLe()) {
                        $composantsAnnuaires[$composant->getId()] = $composant;
                    }
                }
            }
        }

        $builder
            ->add('envoyerMail', CheckboxType::class, [
                'data' => true,
                'label' => 'Avec envoi de mail',
                'required' => false
            ])
            ->add('avecCdb', CheckboxType::class, [
                'data' => false,
                'label' => 'Avec consultation du Chef De Bureau',
                'required' => false
            ])
            ->add('dateLimite', DateType::class, [
                'label' => 'Date limite de réponse',
                'format' => 'dd/MM/yyyy',
                'widget' => 'single_text',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual('today'),
                ]
            ])
            ->add('annuaires', AnnuaireType::class, [
                'composants'   => $composantsAnnuaires,
                'all_selected' => true,
                'block_prefix' => 'select_annuaires'
            ])
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        // On doit absolument passer une Machine à état dans une variable "mae"
        $resolver->setRequired('mae');
        $resolver->setAllowedTypes('mae', MachineEtat::class);
    }
}
