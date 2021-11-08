<?php

namespace App\Command;

use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class GestionServiceCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'gesip:service:gestion';

    /** @var EntityManagerInterface  */
    private $em;
    /** @var ValidatorInterface */
    private $validator;
    /** @var UserPasswordEncoderInterface */
    private $passwordEncoder;

    /**
     * Constructeur de la commande.
     * Permet notamment de récupérer dépendances
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em, ValidatorInterface $validator, UserPasswordEncoderInterface $passwordEncoder)
    {
        parent::__construct();
        $this->em = $em;
        $this->validator = $validator;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * Configure la commande
     */
    protected function configure()
    {
        $this
            ->setDescription('Permet la gestion des services Gesip via la ligne de commande.')
            ->addArgument('action', InputArgument::OPTIONAL, 'Détermine l\'action que la commande va réaliser.');
        ;
    }

    /**
     * Défini l'éxécution de la commande
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        switch ($input->getArgument('action')) {
            case 'lister':
                // listing des services existants
                return $this->listerServices($io);
            case 'ajouter':
                // ajouter un nouvel utilisateur
                return $this->ajouterService($io);
            default:
                return $this->afficherAide($io);
        }
    }

    /**
     * Retourne un message d'aide
     * @return int
     */
    private function afficherAide(SymfonyStyle $io): int
    {
        $io->writeln("Choisissez une action à réaliser:
    - lister: pour lister les services existants
    - ajouter: pour ajouter un nouveau service.");
        return 0;
    }

    /**
     * Liste les services existants
     * @return int
     */
    private function listerServices(SymfonyStyle $io): int
    {
        $services = $this->em->createQueryBuilder()
            ->select('s.id', 's.label', 's.email', 's.roles', 's.majLe', 's.supprimeLe')
            ->from(Service::class, 's')
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
        if (empty($services)) {
            $io->caution('Aucun service trouvé.');
        } else {
            $rows = [];
            foreach ($services as $service) {
                $serviceRow = array_values($service);
                $serviceRow[3] = implode(', ', $serviceRow[3]);
                $serviceRow[4] = !empty($serviceRow[4]) ? $serviceRow[4]->format('d/m/Y H:i:s'): ' - ';
                $serviceRow[5] = !empty($serviceRow[5]) ? $serviceRow[5]->format('d/m/Y H:i:s'): ' - ';
                $rows[] = $serviceRow;
            }
            $io->table(
                ['Id', 'Libellé', 'Adresse mail', 'Rôles', 'Ajouté / Modifié le', 'Supprimé le'],
                $rows
            );
        }
        return 0;
    }


    /**
     * Ajouter un nouveau service
     * @return int
     */
    private function ajouterService(SymfonyStyle $io): int
    {
        // phase de collecte d'information en mode interactif
        $service = new Service();
        do {
            // Création de l'objet service
            $service->setLabel($io->ask('Quel est le libellé du nouveau service ?', $service->getLabel(), function ($label) {
                if (empty($label)) {
                    throw new RuntimeException('Un libellé de service ne peut être vide !');
                }
                return $label;
            }));
            $service->setEmail($io->ask('Quelle est l\'adresse mail du service (fournissez une adresse mail valide) ?', $service->getEmail(), function ($mail) {
                $emailConstraint = new Assert\Email();
                $emailConstraint->message = 'Cette adresse n\'est pas une adresse mail valide.';
                $errors = $this->validator->validate($mail, $emailConstraint);
                if (count($errors) > 0) {
                    throw new RuntimeException($errors[0]->getMessage());
                }
                return $mail;
            }));
            $motdepasse = $io->askHidden('Quel devra-être le mot de passe de ce service pour se connecter ?', function ($mdp) {
                if (empty($mdp)) {
                    throw new RuntimeException('Les mots de passe vide ne sont pas acceptés.');
                } else {
                    $errors = $this->validator->validate($mdp, Service::motdepasseValidation());
                    if (count($errors) > 0) {
                        throw new RuntimeException($errors[0]->getMessage());
                    }
                }
                return $mdp;
            });
            $service->setMotdepasse($this->passwordEncoder->encodePassword($service, $motdepasse));
            //
            $roles = [
                'Administrateur'    => [Service::ROLE_ADMIN],
                'Dme'               => [Service::ROLE_DME],
                'Intervenant'       => [Service::ROLE_INTERVENANT],
                'Invité'            => [Service::ROLE_INVITE],
            ];
            $roleKey = $io->choice('Quelle est le rôle du service ?', array_keys($roles));
            $service->setRoles($roles[$roleKey]);
            // Validation
            $violations = $this->validator->validate($service);
            if (count($violations) > 0) {
                $errorMessages = [];
                foreach ($violations as $error) {
                    $errorMessages[] = PHP_EOL . ' - ' . $error->getMessage();
                }
                $io->error('Service invalide, veillez corriger les erreurs ci-dessous:' . implode('', $errorMessages));
            }
        } while (count($violations) > 0);

        // On demande confirmation
        $this->em->persist($service);
        if ($io->confirm("Confirmez-vous la création du service {$service->getLabel()} - {$service->getEmail()} [id={$service->getId()}] ?")) {
            // phase de création en base
            $this->em->flush();
            $io->success("Le service {$service->getLabel()} - {$service->getEmail()} [id={$service->getId()}] a été créé avec succès.");
        } else {
            $io->caution("Création du service annulée.");
        }
        return 0;
    }
}
