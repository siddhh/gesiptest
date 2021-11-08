<?php

namespace App\Command\Cron;

use App\Entity\ActionHistory;
use App\Entity\Composant;
use App\Entity\Composant\Annuaire;
use App\Entity\Composant\PlageUtilisateur;
use App\Entity\Pilote;
use App\Entity\References\Domaine;
use App\Entity\References\Mission;
use App\Entity\References\TypeElement;
use App\Entity\References\Usager;
use App\Entity\Service;
use App\Repository\PiloteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Shapecode\Bundle\CronBundle\Annotation\CronJob;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @CronJob("0 3 * * *")
 * Sera exécuté 1 fois par jour à 3h00 du matin
 */
class ComposantEmailMiseAJourCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'gesip:cron:composant:emails-maj';

    /** @var EntityManagerInterface */
    private $em;
    /** @var MailerInterface */
    private $mailer;
    /** @var ParameterBagInterface */
    private $parameters;

    /** @var array */
    private $services;
    /** @var array */
    private $composants;
    /** @var array */
    private $annuaires;
    /** @var array */
    private $plageUtilisateurs;

    /** @var array */
    private $dateDesModifications;

    /**
     * Constructeur de la commande.
     * Permet notamment de récupérer dépendances
     *
     * @param EntityManagerInterface $em
     * @param MailerInterface $mailer
     */
    public function __construct(EntityManagerInterface $em, MailerInterface $mailer, ParameterBagInterface $parameters)
    {
        parent::__construct();
        $this->em = $em;
        $this->mailer = $mailer;
        $this->parameters = $parameters;
    }

    /**
     * Configure la commande
     */
    protected function configure()
    {
        $this
            ->setDescription('[CRON] Permet d\'envoyer un mail aux reponsables des composants avec les mises à jour effectuées dessus.')
            ->addOption('--with-interaction', '-i', InputOption::VALUE_NONE, 'Lorsque cette option n\'est pas utilisée, tous les pilotes sont sélectionnés et la date par défaut est la veille du lancement de la commande, sinon vous serez invité à saisir ces données')
            ->addOption('--dry-run', '-d', InputOption::VALUE_NONE, 'Nous n\'enverrons pas réellement les emails mais afficherons un tableau de debug')
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
        /**
         * On défini quelques variables utiles
         */
        $io = new SymfonyStyle($input, $output);
        $io->newLine();
        // Mode débug
        $debugMode = $input->getOption('dry-run');
        // Mode interactif
        $interactifMode = $input->getOption('with-interaction');
        //  Utile pour le script
        $colonnesAExclures = ['id', 'demandesIntervention', 'evenementsMeteo', 'codeCartoId', 'mepSsis', 'carteIdentites', 'carteIdentiteEvenements', 'majLe', 'ajouteLe', 'dureePlageUtilisateur', 'plagesUtilisateur'];

        // Récupération du jour par défaut utilisé pour récupérer les mises à jour
        $this->dateDesModifications = new \DateTime('yesterday');
        $this->dateDesModifications->setTimezone(new \DateTimeZone('Europe/Paris'));

        /**
         * Si on est en mode interactif, on demande la date des demandes
         */
        if ($interactifMode) {
            $newDate = $io->ask('Souhaitez-vous définir une date des modifications particulière ? (par défaut: ' . $this->dateDesModifications->format('d/m/Y') . ')');
            if ($newDate) {
                $this->dateDesModifications = \DateTime::createFromFormat('d/m/Y H:i:s', $newDate . '00:00:00');
                $this->dateDesModifications->setTimezone(new \DateTimeZone('Europe/Paris'));
            }
        }

        /**
         * On récupère la liste des données dont nous aurons besoin par la suite
         */
        $this->services = $this->em->getRepository(Service::class)->findAllInArray();
        $this->composants = $this->em->getRepository(Composant::class)->findAllInArray();
        $usagers = $this->em->getRepository(Usager::class)->findAllInArray();
        $domaines = $this->em->getRepository(Domaine::class)->findAllInArray();
        $typesElement = $this->em->getRepository(TypeElement::class)->findAllInArray();
        $missions = $this->em->getRepository(Mission::class)->findAllInArray();
        $pilotes = $this->em->getRepository(Pilote::class)->findAllInArray();
        $this->annuaires = $this->em->getRepository(Annuaire::class)->findAllInArray();
        $this->plageUtilisateurs = $this->em->getRepository(PlageUtilisateur::class)->findAllInArray();

        $referentielParChamps = [
            'usager' => [
                'donnees' => $usagers,
                'attribut' => 'label',
            ],
            'domaine' => [
                'donnees' => $domaines,
                'attribut' => 'label',
            ],
            'exploitant' => [
                'donnees' => $this->services,
                'attribut' => 'label',
            ],
            'equipe' => [
                'donnees' => $this->services,
                'attribut' => 'label',
            ],
            'pilote' => [
                'donnees' => $pilotes,
                'attribut' => 'nom',
            ],
            'piloteSuppleant' => [
                'donnees' => $pilotes,
                'attribut' => 'nom',
            ],
            'typeElement' => [
                'donnees' => $typesElement,
                'attribut' => 'label',
            ],
            'bureauRattachement' => [
                'donnees' => $this->services,
                'attribut' => 'label',
            ],
            'composantsImpactes' => [
                'donnees' => $this->composants,
                'attribut' => 'label',
            ],
            'impactesParComposants' => [
                'donnees' => $this->composants,
                'attribut' => 'label',
            ]
        ];

        /**
         * On requête la base de données pour récupérer toutes les actions loggées :
         * - Uniquement les Composants
         * - Uniquement les actions de type "modification"
         * - Uniquement les actions entre hier et aujourd'hui
         */
        $historique = $this->em->getRepository(ActionHistory::class)->listeActionsParEntite(
            [Composant::class],
            [ActionHistory::UPDATE],
            $this->dateDesModifications
        );

        /**
         * On parcourt toutes les actions afin de déterminer les changements
         * - Si on modifie plusieurs fois la valeur dans l'intervalle de temps, on récupère la première vieille valeur
         * ainsi que la dernière.
         * - Si l'ancienne valeur est la même qu'avant, alors on efface les modifications inutiles de la liste des
         * modifications.
         * - Si la valeur fait parti de la liste $referentielParChamps alors on récupère la valeur dans le référentiel
         * afin d'afficher le label.
         * - Si la valeur est un tableau de valeurs, alors on agit en conséquence.
         * - Si la valeur est une date ou booléenne, alors on agit en conséquence.
         */
        /** @var ActionHistory $actionHistory */
        $actionsEffectuesParComposants = [];
        foreach ($historique as $actionHistory) {
            $composantId = $actionHistory->getObjetId();
            $details = $actionHistory->getDetails();
            foreach ($details as $detailType => $detailValue) {
                foreach ($detailValue as $propertyName => $propertyValue) {
                    if (!in_array($propertyName, $colonnesAExclures)) {
                        if ($detailType !== "old" || !isset($actionsEffectuesParComposants[$composantId][$propertyName][$detailType])) {
                            if ($detailType === "new" && isset($actionsEffectuesParComposants[$composantId][$propertyName]['old'])
                                && $propertyValue === $actionsEffectuesParComposants[$composantId][$propertyName]['old']) {
                                unset($actionsEffectuesParComposants[$composantId][$propertyName]);
                            } else {
                                $serviceId = $actionHistory->getServiceId();
                                $this->initComposantStates($actionsEffectuesParComposants, $propertyName, $composantId, $serviceId);

                                if (in_array($propertyName, array_keys($referentielParChamps))) {
                                    $donnees = $referentielParChamps[$propertyName]['donnees'];
                                    $attribut = $referentielParChamps[$propertyName]['attribut'];

                                    if (is_array($propertyValue)) {
                                        $values = [];
                                        foreach ($propertyValue as $v) {
                                            $values[] = $donnees[$v][$attribut];
                                        }
                                        $actionsEffectuesParComposants[$composantId][$propertyName][$detailType] = $values;
                                    } else {
                                        $actionsEffectuesParComposants[$composantId][$propertyName][$detailType] = !empty($donnees[$propertyValue][$attribut]) ? $donnees[$propertyValue][$attribut] : "";
                                    }
                                } else {
                                    if ($propertyName === "archiveLe" && $propertyValue !== null) {
                                        $archiveLe = new \DateTime($propertyValue);
                                        $archiveLe->setTimezone(new \DateTimeZone("Europe/Paris"));
                                        $actionsEffectuesParComposants[$composantId][$propertyName][$detailType] = $archiveLe->format('d/m/Y H:i:s');
                                    } elseif (is_bool($propertyValue)) {
                                        $actionsEffectuesParComposants[$composantId][$propertyName][$detailType] = $propertyValue ? "Oui" : "Non";
                                    } else {
                                        $actionsEffectuesParComposants[$composantId][$propertyName][$detailType] = $propertyValue;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        /**
         * Liste les modifications effectuées sur les plages utilisateurs
         */
        $plageUtilisateursModifications = $this->em->getRepository(ActionHistory::class)->listeActionsParEntite(
            [PlageUtilisateur::class],
            [ActionHistory::CREATE, ActionHistory::UPDATE, ActionHistory::REMOVE],
            $this->dateDesModifications,
            ['actionDate' => 'desc', 'id' => 'desc']
        );

        // Si on trouve des modifications, on ajoute les modifications trouvées au tableau global
        if (!empty($plageUtilisateursModifications)) {
            $propertyName = 'plagesUtilisateur';
            foreach ($plageUtilisateursModifications as $i => $actionHistory) {
                $action = $actionHistory->getAction();
                $objectId = $actionHistory->getObjetId();
                $serviceId = $actionHistory->getServiceId();
                $details = $actionHistory->getDetails();
                $plageUtilisateur = isset($this->plageUtilisateurs[$objectId]) ? $this->plageUtilisateurs[$objectId] : null;
                $composantId = null;
                if (isset($details['new']['composant'])) {
                    $composantId = $details['new']['composant'];
                } elseif (isset($details['old']['composant'])) {
                    $composantId = $details['old']['composant'];
                } else {
                    $composantId = $plageUtilisateur->getComposant()->getId();
                }
                $this->initComposantStates($actionsEffectuesParComposants, $propertyName, $composantId, $serviceId);
                if (ActionHistory::CREATE === $action) {
                    // Création
                    if (empty($actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['new'])
                        && empty($actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['old'])) {
                        $actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['new'] = $details['new'];
                    }
                    unset($actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['old']);
                } elseif (ActionHistory::UPDATE === $action) {
                    // Mise à jour
                    if (empty($actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['new'])) {
                        $newState = null;
                        if (empty($actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['old'])) {
                            $newState = $this->getPlageUtilisateurStateArrayFromObject($objectId);
                            $actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['new'] = $newState;
                            $actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['old'] = $actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['new'];
                        } else {
                            $newState = $actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['old'];
                        }
                    }
                    $actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['old'] = array_replace($actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['old'], $details['old']);
                } elseif (ActionHistory::REMOVE === $action) {
                    // Suppression
                    if (empty($actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['old'])) {
                        $actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['old'] = $details['old'];
                    }
                    unset($actionsEffectuesParComposants[$composantId][$propertyName]['history'][$objectId]['new']);
                }
            }
        }

        /**
         * Mise en forme des modifications (récupération des libellés)
         */
        foreach ($actionsEffectuesParComposants as $composantId => $composantUpdates) {
            foreach ($composantUpdates as $fieldName => $fieldModifications) {
                if ('plagesUtilisateur' === $fieldName) {
                    $constants = new \ReflectionClass(PlageUtilisateur::class);
                    foreach ($fieldModifications['history'] as $plageUtilisateurId => $historyEntryPlageUtilisateur) {
                        foreach ($historyEntryPlageUtilisateur as $version => $arrayState) {
                            $jour = ucfirst(mb_strtolower(array_search($arrayState['jour'], $constants->getConstants())));
                            $debut = (new \DateTime($arrayState['debut']))->format('H:i:s');
                            $fin = (new \DateTime($arrayState['fin']))->format('H:i:s');
                            $actionsEffectuesParComposants[$composantId]['plagesUtilisateur'][$version][] = "{$jour} : de {$debut} à {$fin}";
                        }
                    }
                }
            }
        }

        /**
         * Supprime les doublons entre old et new (si une entrée apparait dans les 2 tableaux, c'est que cette entrée n'a pas été modifiée !)
         *  On en profite aussi pour supprimer les données inutiles et trier les tableaux de modifications
         */
        foreach ($actionsEffectuesParComposants as $composantId => $composantUpdates) {
            foreach (['annuaire', 'composantsImpactes', 'impactesParComposants', 'plagesUtilisateur'] as $fieldName) {
                // A ce stade nous n'avons plus besoin de l'historique
                if (isset($actionsEffectuesParComposants[$composantId][$fieldName]['history'])) {
                    unset($actionsEffectuesParComposants[$composantId][$fieldName]['history']);
                }
                // Pas besoin de supprimer les doublons si les tableaux old ou new sont vides !
                if (isset($composantUpdates[$fieldName]) && !empty($composantUpdates[$fieldName]['old']) && !empty($composantUpdates[$fieldName]['new'])) {
                    $actionsEffectuesParComposants[$composantId][$fieldName]['old'] = array_diff($composantUpdates[$fieldName]['old'], $composantUpdates[$fieldName]['new']);
                    $actionsEffectuesParComposants[$composantId][$fieldName]['new'] = array_diff($composantUpdates[$fieldName]['new'], $composantUpdates[$fieldName]['old']);
                }
                // A l'issue de cette opération, il se peut que ce champ ne comporte plus de modification, dans ce cas il faut retirer de la liste
                if (empty($actionsEffectuesParComposants[$composantId][$fieldName]['old']) && empty($actionsEffectuesParComposants[$composantId][$fieldName]['new'])) {
                    unset($actionsEffectuesParComposants[$composantId][$fieldName]);
                } else {
                    sort($actionsEffectuesParComposants[$composantId][$fieldName]['old']);
                    sort($actionsEffectuesParComposants[$composantId][$fieldName]['new']);
                }
                // Si composant ne comporte plus de champ modifié, on le retire de la liste des composants modifiés
                if (empty($actionsEffectuesParComposants[$composantId])) {
                    unset($actionsEffectuesParComposants[$composantId]);
                }
            }
        }

        // Récupère la liste des identifiants des composants modifiés
        $composantsImpactes = array_keys($actionsEffectuesParComposants);

        /**
         * Si nous sommes en mode interactif, on demande les pilotes et les équipes à notifier
         */
        $onlyPilotesIds = [];
        if ($interactifMode) {
            do {
                $tmp = $io->ask('Donnez l\'id du pilote à notifier (ne rien taper pour terminer la saisie)');
                if ($tmp !== null) {
                    $onlyPilotesIds[] = $tmp;
                }
            } while ($tmp !== null);

            if (count($onlyPilotesIds) === 0) {
                $confirm = $io->confirm("Confirmez-vous l'envoi des notifications à tous les pilotes de la base de données ?", false);
                if (!$confirm) {
                    $io->error("Annulation effectuée");
                    return 1;
                }
            }
        }

        $onlyEquipesIds = [];
        if ($interactifMode) {
            do {
                $tmp = $io->ask('Donnez l\'id de l\'équipe à notifier (ne rien taper pour terminer la saisie)');
                if ($tmp !== null) {
                    $onlyEquipesIds[] = $tmp;
                }
            } while ($tmp !== null);

            if (count($onlyEquipesIds) === 0) {
                $confirm = $io->confirm("Confirmez-vous l'envoi des notifications à tous les équipes de la base de données ?", false);
                if (!$confirm) {
                    $io->error("Annulation effectuée");
                    return 1;
                }
            }
        }

        /**
         * On récupère la liste des pilotes et les équipes à notifier ainsi que les composants dont ils ont la garde ainsi que
         * pour lequel ils sont suppléants.
         */
        /** @var PiloteRepository $pilotesRepository */
        $pilotesRepository = $this->em->getRepository(Pilote::class);
        $pilotes = $pilotesRepository->listePilotesParComposants($composantsImpactes)->getQuery()->getResult();
        $equipes = $this->em->getRepository(Service::class)->listeEquipesParComposants($composantsImpactes);
        $composantsParPilotes = [];
        $composantsParEquipes = [];

        /** @var Pilote $pilote */
        foreach ($pilotes as $pilote) {
            if (!$interactifMode ||
                $interactifMode && count($onlyPilotesIds) === 0 ||
                $interactifMode && in_array($pilote->getId(), $onlyPilotesIds)
            ) {
                $composantsParPilotes[$pilote->getId()] = ['pilote' => $pilote, 'composants' => []];
                $piloteComposants = array_merge($pilote->getComposants()->getValues(), $pilote->getSuppleantComposants()->getValues());

                /** @var Composant $composant */
                foreach ($piloteComposants as $composant) {
                    $composantsParPilotes[$pilote->getId()]['composants'][$composant->getId()] = $composant;
                }
            }
        }

        /** @var Equipe $equipe */
        foreach ($equipes as $equipe) {
            if (!$interactifMode ||
                $interactifMode && count($onlyEquipesIds) === 0 ||
                $interactifMode && in_array($equipe->getId(), $onlyEquipesIds)
            ) {
                $composantsParEquipes[$equipe->getId()] = ['equipe' => $equipe, 'composants' => []];
                $equipeComposants = $equipe->getComposantsEquipe()->getValues();
                foreach ($equipeComposants as $composant) {
                    $composantsParEquipes[$equipe->getId()]['composants'][$composant->getId()] = $composant;
                }
            }
        }

        $adminServices = $this->em->getRepository(Service::class)->getServicesParRoles(Service::ROLE_ADMIN);
        foreach ($adminServices as $service) {
            $composantsParEquipes[$service->getId()] = ['equipe' => $service, 'composants' => []];
            foreach ($composantsImpactes as $composantId) {
                $composant = $this->composants[$composantId];
                $composantsParEquipes[$service->getId()]['composants'][$composantId] = $composant;
            }
        }

        /**
         * Si nous sommes en mode interactif nous demandons confirmations
         */
        if ($interactifMode) {
            $debugTableDataPilotes = [];
            foreach ($composantsParPilotes as $piloteId => $infos) {
                /** @var Pilote $pilote */
                $pilote = $infos['pilote'];
                $composants = [];
                /** @var Composant $co */
                foreach ($infos['composants'] as $co) {
                    $composants[] = $co->getLabel() . ' (' . $co->getId() . ')';
                }

                $debugTableDataPilotes[] = [
                    $piloteId,
                    $pilote->getNomCompletCourt(),
                    $pilote->getBalp(),
                    implode("\n", $composants)
                ];
            }

            $io->table(
                ['ID', 'Nom du pilote', 'Bafp', 'Composants'],
                $debugTableDataPilotes
            );

            $debugTableDataEquipes = [];
            foreach ($composantsParEquipes as $equipeId => $infos) {
                $equipe = $infos['equipe'];
                $composants = [];
                foreach ($infos['composants'] as $co) {
                    $composants[] = $co['label'] . ' (' . $co['id'] . ')';
                }

                $debugTableDataEquipes[] = [
                    $equipeId,
                    $equipe->getLabel(),
                    $equipe->getEmail(),
                    implode("\n", $composants)
                ];
            }

            $io->table(
                ['ID', 'Nom équipe', 'Balf', 'Composants'],
                $debugTableDataEquipes
            );

            $confirm = $io->confirm("Confirmez-vous l'envoi des notifications aux personnes ci-dessus ?", false);
            if (!$confirm) {
                $io->error("Annulation effectuée");
                return 1;
            }
        }

        /**
         * On parcourt le tableau de composants par pilote afin de pouvoir générer nos emails
         */

        $addressFrom = new Address($this->parameters->get('noreply_mail'), $this->parameters->get('noreply_mail_label'));
        $adminServices = $this->em->getRepository(Service::class)->getServicesParRoles(Service::ROLE_ADMIN);

        $nbEmailPilotes = 0;
        $debugPilotes = [];
        $progressBar = new ProgressBar($output);
        foreach ($progressBar->iterate($composantsParPilotes) as $infos) {
            // On met en forme les modifications à afficher à la vue
            $listeDesModifications = [];
            foreach ($infos['composants'] as $composant) {
                if (isset($actionsEffectuesParComposants[$composant->getId()])) {
                    $listeDesModifications[$composant->getId()] = $actionsEffectuesParComposants[$composant->getId()];
                }
            }
            // On envoie le mail si il y a bien des modifications à afficher
            if (count($listeDesModifications) > 0) {
                /** @var Pilote $pilote */
                $pilote = $infos['pilote'];
                // Si le mode débug est désactivé nous envoyons véritablement les mails
                if (!$debugMode) {
                    $this->envoyerMail($pilote, $listeDesModifications);
                }
                $debugPilotes[$pilote->getId()] = [
                    'nom' => $pilote->getNomCompletCourt(),
                    'modifs' => $listeDesModifications
                ];
                $nbEmailPilotes++;
            }
        }

        $nbEmailEquipes = 0;
        $debugEquipes = [];
        foreach ($progressBar->iterate($composantsParEquipes) as $infos) {
            // On met en forme les modifications à afficher à la vue
            $listeDesModifications = [];
            foreach ($infos['composants'] as $composant) {
                $composantId = is_array($composant) ? $composant['id']: $composant->getId();
                if (isset($actionsEffectuesParComposants[$composantId])) {
                    $listeDesModifications[$composantId] = $actionsEffectuesParComposants[$composantId];
                }
            }
            // On envoie le mail si il y a bien des modifications à afficher
            if (count($listeDesModifications) > 0) {
                /** @var Equipe $equipe */
                $equipe = $infos['equipe'];
                // Si le mode débug est désactivé nous envoyons véritablement les mails
                if (!$debugMode) {
                    $this->envoyerMail($equipe, $listeDesModifications);
                }
                $debugEquipes[$equipe->getId()] = [
                    'nom' => $equipe->getLabel(),
                    'modifs' => $listeDesModifications
                ];
                $nbEmailEquipes++;
            }
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success(($nbEmailPilotes + $nbEmailEquipes) . ' mails ont été envoyés avec succès !');

        if ($debugMode) {
            $io->caution("Mode débug actif ! Aucun mail n'a été envoyé !");

            // Affichages des modifications des composants
            $io->title("Modifications par pilotes effectuées le " . $this->dateDesModifications->format('d/m/Y'));
            $debugTableData = [];
            $firstRow = true;
            foreach ($debugPilotes as $piloteId => $modifications) {
                if (count($debugTableData) > 0) {
                    $debugTableData[] = new TableSeparator();
                }
                $firstRow = true;
                foreach ($modifications['modifs'] as $composantId => $composantModifications) {
                    foreach ($composantModifications as $champ => $infosModifications) {
                        $debugTableData[] = [
                            ($firstRow ? $modifications['nom'] . ' (' . $piloteId .')' : ''),
                            $infosModifications['composantLabel'] . ' (' . $infosModifications['composantId'] .')',
                            $champ,
                            (is_array($infosModifications['old']) ? implode("\n", $infosModifications['old']) : $infosModifications['old']),
                            (is_array($infosModifications['new']) ? implode("\n", $infosModifications['new']) : $infosModifications['new'])
                        ];
                        $firstRow = false;
                    }
                }
            }
            foreach ($debugEquipes as $equipeId => $modifications) {
                if (count($debugTableData) > 0) {
                    $debugTableData[] = new TableSeparator();
                }
                $firstRow = true;
                foreach ($modifications['modifs'] as $composantId => $composantModifications) {
                    foreach ($composantModifications as $champ => $infosModifications) {
                        $debugTableData[] = [
                            ($firstRow ? $modifications['nom'] . ' (' . $equipeId .')' : ''),
                            $infosModifications['composantLabel'] . ' (' . $infosModifications['composantId'] .')',
                            $champ,
                            (is_array($infosModifications['old']) ? implode("\n", $infosModifications['old']) : $infosModifications['old']),
                            (is_array($infosModifications['new']) ? implode("\n", $infosModifications['new']) : $infosModifications['new'])
                        ];
                        $firstRow = false;
                    }
                }
            }
            $debugTable = new Table($output);
            $debugTable->setHeaders(['Pilote / Service', 'Composant', 'Champ', 'Ancienne valeur', 'Nouvelle valeur']);
            $debugTable->setColumnWidths([10, 15, 15, 50, 50]);
            $debugTable->setRows($debugTableData);
            $debugTable->render();
        }
        return 0;
    }

    /**
     * Envoi un mail à $recipient contenant la $listeDesModifications le concernant (composants dont il a la charge)
     *
     * @param Service|Pilote $recipient
     * @param array $listeDesModifications
     * @return void
     */
    private function envoyerMail($destinataire, array $listeDesModifications): void
    {
        $addressFrom = new Address($this->parameters->get('noreply_mail'), $this->parameters->get('noreply_mail_label'));
        $nomDesChamps = [
            'label' => 'Label',
            'codeCarto' => 'Code cartographie',
            'usager' => 'Usager',
            'domaine' => 'Domaine',
            'intitulePlageUtilisateur' => 'Intitulé de la plage utilisateur',
            'plagesUtilisateur' => 'Plages utilisateur',
            'exploitant' => 'Exploitant',
            'meteoActive' => 'Météo activée',
            'equipe' => 'Équipe',
            'pilote' => 'Pilote',
            'piloteSuppleant' => 'Pilote suppléant',
            'typeElement' => 'Type d\'élément',
            'estSiteHebergement' => 'Site hébergement',
            'bureauRattachement' => 'Bureau de rattachement',
            'annuaire' => 'Annuaire',
            'composantsImpactes' => 'Composants impactés',
            'impactesParComposants' => 'Impactés par',
            'archiveLe' => 'Archivé le'
        ];
        $context = [
            'nomsDesChamps'         => $nomDesChamps,
            'date'                  => $this->dateDesModifications,
            'listeDesModifications' => $listeDesModifications,
        ];
        $destinataireAddress = null;
        if ($destinataire instanceof Service) {
            $context['service'] = $destinataire;
            $destinataireAddress = new Address($destinataire->getEmail(), $destinataire->getLabel());
        } elseif ($destinataire instanceof Pilote) {
            $context['pilote'] = $destinataire;
            $destinataireAddress = $destinataire->getAddressObj();
        } else {
            throw new \Exception('Type de destinataire non-pris en charge');
        }
        $email = (new TemplatedEmail())
            ->from($addressFrom)
            ->to($destinataireAddress)
            ->subject("[GESIP: Modifications effectuées sur les Applications]")
            ->textTemplate('emails/composants/modifications.text.twig')
            ->htmlTemplate("emails/composants/modifications.html.twig")
            ->context($context)
        ;
        $this->mailer->send($email);
    }

    /**
     * Initialise l'état d'une propriété d'un composant dans le tableau des modifications
     *
     * @param array $actionsEffectuesParComposants
     * @param string $propertyName
     * @param int $composantId
     * @param int $serviceId
     * @return void
     */
    private function initComposantStates(array &$actionsEffectuesParComposants, string $propertyName, int $composantId, int $serviceId = null): void
    {
        if (!isset($actionsEffectuesParComposants[$composantId][$propertyName])) {
            $actionsEffectuesParComposants[$composantId][$propertyName]['composantId'] = $composantId;
            $actionsEffectuesParComposants[$composantId][$propertyName]['composantLabel'] = $this->composants[$composantId]['label'];
            $actionsEffectuesParComposants[$composantId][$propertyName]['majPar'] = !empty($this->services[$serviceId]['label']) ? $this->services[$serviceId]['label'] : '-';
            $actionsEffectuesParComposants[$composantId][$propertyName]['old'] = [];
            $actionsEffectuesParComposants[$composantId][$propertyName]['new'] = [];
            $actionsEffectuesParComposants[$composantId][$propertyName]['history'] = [];
        }
    }

    /**
     * Récupère l'état d'un annuaire sous forme de tableau
     *
     * @param int $annuaireId
     * @return array|null
     */
    private function getAnnuaireStateArrayFromObject(int $annuaireId): ?array
    {
        if (isset($this->annuaires[$annuaireId])) {
            $annuaire = $this->annuaires[$annuaireId];
            return [
                'composant'     => $annuaire->getComposant()->getId(),
                'mission'       => $annuaire->getMission()->getId(),
                'service'       => $annuaire->getService()->getId(),
                'balf'          => $annuaire->getBalf(),
                'majLe'         => $annuaire->getMajLe()->format('c'),
                'supprimeLe'    => $annuaire->getSupprimeLe() !== null ? $annuaire->getSupprimeLe()->format('c') : null,
            ];
        }
        return null;
    }

    /**
     * Récupère l'état d'une plage utilisateur sous forme de tableau
     *
     * @param int $plageUtilisateurId
     * @return array|null
     */
    private function getPlageUtilisateurStateArrayFromObject(int $plageUtilisateurId): ?array
    {
        if (isset($this->plageUtilisateurs[$plageUtilisateurId])) {
            $plageUtilisateur = $this->plageUtilisateurs[$plageUtilisateurId];
            return [
                'composant' => $plageUtilisateur->getComposant()->getId(),
                'jour'      => $plageUtilisateur->getJour(),
                'debut'     => $plageUtilisateur->getDebut()->format('c'),
                'fin'       => $plageUtilisateur->getFin()->format('c'),
                'ajouteLe'  => $plageUtilisateur->getAjouteLe()->format('c'),
                'majLe'     => $plageUtilisateur->getMajLe()->format('c'),
            ];
        }
        return null;
    }
}
