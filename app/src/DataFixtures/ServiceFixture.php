<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Service;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ServiceFixture extends Fixture
{

    private $passwordEncoder;

    /**
     * Récupère le UserPasswordEncoder pour crypter les mots de passe
     */
    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * Permet de créer un service de test avec divers informations de bases
     *
     * @param string $label
     * @param string $email
     * @param array $roles
     * @param string $motdepasse
     * @return Service
     */
    private function serviceFactory(string $label, string $email, array $roles, string $motdepasse): Service
    {
        $service = new Service();
        $service->setLabel($label);
        $service->setEmail($email);
        $service->setRoles($roles);
        $motdepasseCrypte = $this->passwordEncoder->encodePassword($service, $motdepasse);
        $service->setMotdepasse($motdepasseCrypte);
        $service->setResetMotdepasse(false);
        $service->setEstServiceExploitant(false);
        $service->setEstBureauRattachement(false);
        $service->setEstStructureRattachement(false);
        $service->setEstPilotageDme(false);
        return $service;
    }

    /**
     * Génère des services en base de données pour effectuer des tests
     */
    public function load(ObjectManager $manager)
    {
        // Génère 4 services de base
        $manager->persist($this->serviceFactory("0 Service Administrateur", "gesip-admin@dgfip.local", ["ROLE_ADMIN"], "azerty"));
        $manager->persist($this->serviceFactory("0 Service DME", "dme@dgfip.local", ["ROLE_DME"], "azerty"));
        $manager->persist($this->serviceFactory("0 Service Intervenant", "intervenant@dgfip.local", ["ROLE_INTERVENANT"], "azerty"));
        $manager->persist($this->serviceFactory("0 Service invité", "invite@dgfip.local", ["ROLE_INVITE"], "azerty"));

        // Génère 16 services au hasard
        for ($i = 0; $i < 16; $i++) {
            $manager->persist($this->serviceFactory(
                "Service " . uniqid(),
                uniqid() . "@dgfip.finances.gouv.fr",
                ["ROLE_INTERVENANT"],
                "azerty"
            ));
        }

        // On envoie les nouveaux objets en base de données
        $manager->flush();
    }
}
