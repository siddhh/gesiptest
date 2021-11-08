<?php

namespace App\Command;

use App\Entity\DemandeIntervention;
use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Entity\Fiabilisation\DemandeReferentielFlux;
use App\Entity\References\Usager;
use App\Entity\Service;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatRefusee;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\Etats\EtatSaisirRealise;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class ImportOldDataCommand extends Command
{

    /** @var string */
    protected static $defaultName = 'gesip:import:old-data';

    /** @var EntityManagerInterface  */
    private $em;

    /** @var EncoderFactoryInterface */
    private $encoderFactory;

    /**
     * Constructeur de la commande.
     * Permet notamment de récupérer dépendances
     *
     * @param EntityManagerInterface  $em
     * @param EncoderFactoryInterface $encoderFactory
     */
    public function __construct(EntityManagerInterface $em, EncoderFactoryInterface $encoderFactory)
    {
        parent::__construct();
        $this->em = $em;
        $this->encoderFactory = $encoderFactory;
    }

    /**
     * Configure la commande
     */
    protected function configure()
    {
        $this->setDescription('Permet d\'importer les données de l\'ancienne version de GESIP.');
    }

    /**
     * Défini l'éxécution de la commande
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        /* Initialisation */

        //region ------- Initialisation ------- ON
        /**
         * Initialisation
         */
        // On initialise notre date à partir de laquelle nous devons récupérer les données
        $dateReprise = '2018-01-01';
        // On récupère le jour actuel
        $now = new \DateTime();
        $tzUTC = new \DateTimeZone('UTC');
        // On démarre la stylisation I/O pour les commandes cli
        $io = new SymfonyStyle($input, $output);
        $io->newLine();
        // on établi une connexion avec nos bases de données mysql (ancienne données) / postgresql (nouvelles données)
        $oldDataBddGesip = DriverManager::getConnection([ 'url' => 'mysql://root:pass@dbmysql/gesip_2021-05-25' ]);
        $oldDataBddCmep = DriverManager::getConnection([ 'url' => 'mysql://root:pass@dbmysql/cmep_2021-05-25' ]);
        $newDataBdd = $this->em;
        // On fait d'autres trucs peu important ...
        $conversionBal = [];
        //endregion

        //region ------- Référentiels ------- ON
        /**
         * Ajout des tables de conversion
         */
        // Pour les missions
        $conversionMissions = [
            'AT' => 1, // Assistance
            'G2A' => 6, // EA Exploitant Applicatif
            'GTS' => 7, // EA Exploitant Système
            'MOA' => 11, // MOA
            'MOE' => 12, // MOE
            'DPE' => 15, // DME
            'EH' => 4, // ESI hébergeur
            'ASS' => 1, // Assistance
            'Moe-Deleg' => 14, // MOE Déléguée
            'Moa-Deleg' => 13, // MOA Déléguée
            'EXP' => 5, // Service (pour information)
            'DEV' => 3, // Développement
            'IA' => 8, // Intégration Applicative
            'IIA' => 9, // Intégration Inter-Applicative
            'INTEX' => 10, // Intégration de l'Exploitabilité
        ];
        // Pour les domaines
        $conversionDomaines = [
            'Fiscalite-ControlefiscaletCont' => 2, // Fiscalité – Contrôle fiscal et Contentieux
            'Transverse-Referentiels' => 21, // Transverse – Référentiels
            'Fiscalite-Professionnels' => 5, // Fiscalité – Professionnels
            'Pilotage-Communication' => 15, // Pilotage – Communication
            'Fiscalite-Particuliers' => 4, // Fiscalité – Particuliers
            'Gestionpublique-Gestioncomptab' => 10, // Gestion publique – Gestion comptable et financière
            'Transverse-BudgetMoyensetLogis' => 18, // Transverse – Budget, Moyens et Logistique
            'Pilotage-AuditRisquesetControl' => 14, // Pilotage – Audit, Risques et Contrôle de gestion
            'Gestionpublique-Valorisationet' => 13, // Gestion publique – Valorisation et conseil
            'Transverse-RH' => 20, // Transverse – RH
            'Fiscalite-Recouvrement' => 6, // Fiscalité – Recouvrement
            'Domaine-Gestiondudomaine' => 1, // Domaine – Gestion du domaine
            'Gestionpublique-DepensesdelEta' => 8, // Gestion publique – Dépenses de l'Etat et Paie
            'Gestionpublique-Comptabilite' => 7, // Gestion publique – Comptabilité
            'SSI-Outillage' => 17, // SSI – Outillage
            'SSI-Infrastructures' => 16, // SSI – Infrastructures
            'Fiscalite-FoncieretPatrimoine' => 3, // Fiscalité – Foncier et Patrimoine
            'Gestionpublique-Moyensdepaieme' => 11, // Gestion publique – Moyens de paiement
            'Gestionpublique-Fondsdeposes' => 9, // Gestion publique – Fonds déposés
            'Transverse-Outillage' => 19, // Transverse – Outillage
            'Gestionpublique-Retraitesetpen' => 12, //  Gestion publique – Retraites et pensions
        ];
        // Pour les usagers
        $conversionUsagers = [
            'EXTERNE' => 1, // Externe
            'EXTERNE/USAGERSPRO' => 5, // Externe / usagers pro
            'EXTERNE/USAGERSPART' => 4, // Externe / usagers part
            'EXTERNE/COLLOC' => 2, // Externe / Coloc
            'EXTERNE/PARTENAIRES' => 3, // Externe / Partenaires
            'INTERNE/AGENT' => 6, // Interne / Agent
            'MIXTE' => 7, // Mixte
        ];
        // Pour la nature des impacts
        $conversionNatureImpacts = [
            'AUCUN' => 1, // Aucun impact
            'FONCTRED' => 2, // Fonctionnalités réduites
            'INDISPPART' => 4, // Indisponibilité partielle
            'INDISPTOT' => 5, // Indisponibilité totale
            'INDISPMMA' => 3, // Impact Ponctuel MMA
        ];
        // Pour les types d'éléments (champ estAdmin dans Applis)
        $conversionTypeElement = [
            0 => 1, // Standard
            1 => 2, // Non MOA - Admin
            2 => 3, // Non MOA - Standard
        ];
        // Pour les motifs d'interventions
        $conversionMotifsIntervention = [
            'MAINTAPP' => 2, // Maintenance applicative
            'MAINTTEC' => 3, // Maintenance technique
            'OUVDROITS' => 6, // Ouverture de droits
            'OUVFLUX' => 7, // Ouverture de flux
            'RESOLINCID' => 8, // Résolution d'incident
            'OPEREXP' => 4, // Opération d'exploitation
            'OPTRSITE' => 5, // Opération de travaux sur site
        ];
        // Pour les motifs de refus
        $conversionMotifsRefus = [
            'CHANGEMENTIMPACT' => 1, // Changement d'impact
            'HORSPERIMETRE' => 4, // Saisie dans GESIP hors périmètre
            'REDACTION' => 3, // Rédaction
            'REPLANIFICATION' => 2, // Replanification de la date
        ];
        $labelsMotifsRefus = [
            1 => "Changement d'impact",
            4 => "Saisie dans GESIP hors périmètre",
            3 => "Rédaction",
            2 => "Replanification de la date",
        ];
        // Pour les motifs de renvoi
        $conversionMotifsRenvoi = [
            1 => 2, // Nature d'intervention
            2 => 3, // Motif d'interventions
            3 => 4, // Palier applicatif
            4 => 5, // Description
            5 => 6, // Intervention réalisée par
            6 => 8, // Période - Date Heure Intervention
            7 => 12, // Nature
            8 => 9, // Période - Date Heure de fin min
            9 => 10, // Période – Date Heure de fin max
            10 => 13, // Impact
            11 => 14, // Détail impact
            12 => 15, // Composants impactés
            13 => 16, // Ajouter un impact
            14 => 7, // Solution de contournement existante
        ];
        $labelsMotifsRenvoi = [
            2 => "Nature d'intervention",
            3 => "Motif d'interventions",
            4 => "Palier applicatif",
            5 => "Description",
            6 => "Intervention réalisée par",
            8 => "Période - Date Heure Intervention",
            12 => "Nature",
            9 => "Période - Date Heure de fin min",
            10 => "Période – Date Heure de fin max",
            13 => "Impact",
            14 => "Détail impact",
            15 => "Composants impactés",
            16 => "Ajouter un impact",
            7 => "Solution de contournement existante",
        ];
        // Pour la nature des impacts météo
        $conversionNatureImpactsMeteo = [
            'AUCUN' => 2, // Aucun impact
            'ACCESIMP' => 1, // Accès impossible
            'DEGRAD' => 4, // Fonctionnement dégradé
            'INDISPMMA' => 5, // Impact Ponctuel MMA
            'INDISPPART' => 6, // Indisponibilité partielle
            'INDISPPROG' => 7, // Indisponibilité programmée
            'INDISPTOT' => 8, // Indisponibilité totale
            'RETARDMAXMAJDO' => 9, // Retard majeur dans la mise à jour des données
            'RETARDMINMAJDO' => 10, //  Retard mineur dans la mise à jour des données
            'TRANSP' => 11, // Transparent pour les utilisateurs
        ];
        // Pour convertir les équipes de pilotage des MEPs SSI
        $conversionMepSsiEquipePilotage = [
            'CS1' => 'SI2DPEPPF',
            'CS2' => 'SI2DPEPOPE',
            'DME' => 'DPE',
        ];
        // Pour convertir les status des MEPs SSI
        $conversionMepSsiStatus = [
            'ARCHIVE' => 3,
            'CONFIRME' => 2,
            'ERREUR' => 4,
            'PROJET' => 1,
        ];
        // Pour convertir les grid mep des MEPs SSI
        $conversionMepSSiGridMep = [
           'BCP_ESI_CONCERNE' => 7,
           'FDR' => 2,
           'INDISPO' => 4,
           'INFRA_IMPACTANTE' => 5,
           'USAGERS' => 3,
           'HNO' => 6,
        ];
        //endregion

        /* Services & pilotes */

        //region ------- Conversion des Services (OK) ------- ON
        /**/
        $conversionServices = [];
        $conversionBal = [];
        $io->title("Conversion des services");
        // On récupère les services de l'ancienne base
        // # WHERE s.BAL IS NULL OR TRIM(s.BAL) = '' OR TRIM(b.Email) NOT REGEXP '^[a-zA-Z0-9._\\-]*@[a-zA-Z0-9._\\-]*.[a-zA-Z]{2,3}$'
        $services = $oldDataBddGesip->query("
            SELECT
                u.Utilisateur, u.Libelle as UtilisateurLibelle, s.Service, s.BAL, s.Libelle, b.Email, u.MotPasse,
                s.AccesDemande, s.AccesAnalyse, s.AccesConsultation, s.AccesReponse, s.AccesRealise, s.MeteoSaisie, s.MeteoValidation, s.MeteoStatistique,
                s.Rattachement, s.BALDateMaj
            FROM Services s
            LEFT JOIN Utilisateurs u ON u.Service = s.Service
            LEFT JOIN Bals b ON s.BAL = b.BAL
            WHERE s.Service <> 'VISITEUR'
                UNION
            SELECT u.Utilisateur, u.Libelle as UtilisateurLibelle, u.Utilisateur, s.BAL, u.libelle, '', u.MotPasse, '0', '0', '0', '0', '0', '0', '0', '0', '0', ''
            FROM Utilisateurs u
            LEFT JOIN Services s ON u.Service = s.Service
            LEFT JOIN Bals b ON s.BAL = b.BAL
            WHERE s.Libelle IS NULL
        ")->fetchAll();

        // On parcourt chaque ancien service pour le recréer côté nouvelle base
        $pgb = $io->createProgressBar(count($services));
        $pgb->start();
        $conversionUtilisateursEnServices = [];
        $servicesDejaPresent = [];
        $id = 0;
        $passwordEncoder = $this->encoderFactory->getEncoder(Service::class);
        foreach ($services as $service) {
            $label = $service['Libelle'];

            // Si le libellé est déjà présent, alors on indique dans la table de conversion la référence vers l'id du service
            if (isset($servicesDejaPresent[$label])) {
                $conversionServices[$service['Service']] = $servicesDejaPresent[$label];
                $conversionBal[$service['BAL']] = $servicesDejaPresent[$label];
            // Sinon, on crée le service
            } else {
                // On met un email par défaut si l'ancien email n'est pas valide
                $email = $service['Email'];
                if (!preg_match("/^[a-zA-Z0-9._\\-]*@[a-zA-Z0-9._\\-]*.[a-zA-Z]{2,3}$/", $email)) {
                    $email = "no-where@dgfip.finances.gouv.fr";
                }

                // On sélectionne le bon rôle pour ce service
                // Si tous les droits, alors admin
                if ($service['AccesDemande'] && $service['AccesAnalyse'] && $service['AccesConsultation'] && $service['AccesReponse'] && $service['AccesRealise'] && $service['MeteoSaisie'] && $service['MeteoValidation']) {
                    $role = Service::ROLE_ADMIN;
                    // Si AccesAnalyse
                } elseif ($service['AccesAnalyse']) {
                    $role = Service::ROLE_GESTION;
                    // Sinon, intervenant
                } else {
                    $role = Service::ROLE_INTERVENANT;
                }

                // On crée notre service
                $id++;
                $qb = $newDataBdd->getConnection()->prepare('
                    INSERT INTO service (
                        id, label, email, roles, motdepasse, reset_motdepasse, est_service_exploitant,
                        est_bureau_rattachement, est_structure_rattachement, est_pilotage_dme, date_validation_balf
                    ) VALUES (
                        nextval(\'service_id_seq\'), :label, :email, :roles, :motdepasse, :reset_motdepasse, :est_service_exploitant,
                        :est_bureau_rattachement, :est_structure_rattachement, :est_pilotage_dme, :date_validation_balf
                    );
                ');
                $qb->bindValue(':label', $label);
                $qb->bindValue(':email', $email);
                $qb->bindValue(':roles', json_encode([ $role ]));
                $qb->bindValue(':motdepasse', $passwordEncoder->encodePassword($service['MotPasse'] ? $service['MotPasse'] : uniqid(), null));
                $qb->bindValue(':reset_motdepasse', intval($label !== "Service GESIP"));
                $qb->bindValue(':est_service_exploitant', intval($role == Service::ROLE_INTERVENANT && $service['MeteoSaisie']));
                $qb->bindValue(':est_bureau_rattachement', intval($service['Rattachement'] == 1));
                $qb->bindValue(':est_structure_rattachement', 0);
                $qb->bindValue(':est_pilotage_dme', 0);
                $qb->bindValue(':date_validation_balf', $service['BALDateMaj'] ? $service['BALDateMaj'] : null);
                $qb->execute();

                // On sauvegarde le nouveau service dans les array de conversion
                $conversionServices[$service['Service']] = $id;
                $conversionBal[$service['BAL']] = $id;
                $servicesDejaPresent[$label] = $id;
                if ($service['Utilisateur']) {
                    $conversionUtilisateursEnServices[$service['Utilisateur']] = $id;
                }
            }
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        //region ------- Conversion des Pilotes (OK) ------- ON
        /**/
        $io->title("Conversion des pilotes");
        $conversionPilotes = [];
        $pilotes = [
            [ 'I. Lecomte', 'Isabelle', 'Lecomte', 'SI2DPEPOPE', 'isabelle.lecomte@dgfip.finances.gouv.fr' ],
            [ 'J. Gauthier', 'Jacques', 'Gauthier', 'SI2DPEPOPE', 'jacques.gauthier@dgfip.finances.gouv.fr' ],
            [ 'N. Simon', 'Nathalie', 'Simon', 'SI2DPEPPF', 'nathalie.simon@dgfip.finances.gouv.fr' ],
            [ 'Y. Barghout', 'Yasser', 'Barghout', 'SI2DPEPOPE', 'yasser.barghout@dgfip.finances.gouv.fr' ],
            [ 'D. Henry', 'Denis', 'Henry', 'SI2DPEPOPE', 'denis-m.henry@dgfip.finances.gouv.fr' ],
            [ 'D.Kuntz', 'Daniele', 'Kuntz', 'SI2DPEPPF', 'daniele.kuntz@dgfip.finances.gouv.fr' ],
            [ 'B. Riviere', 'Benoit', 'Riviere', 'SI2DPEPPF', 'dme-riviere.consultant@dgfip.finances.gouv.fr' ],
            [ 'N. Benderradji', 'Nordine', 'Benderradji', 'SI2DPEPPF', 'nordine.benderradji@dgfip.finances.gouv.fr' ],
            [ 'M. Boisson', 'Michel', 'Boisson', 'SI2DPEPOPE', 'michel.boisson@dgfip.finances.gouv.fr' ],
            [ 'C.Gatti', 'Claude', 'Gatti', 'SI2DPEPPF', 'claude.gatti@dgfip.finances.gouv.fr' ],
            [ 'M-C Monnier', 'Marie-Claude', 'Monnier', 'SI2DPEPPF', 'marie-claude.monnier@dgfip.finances.gouv.fr' ],
            [ 'F-DAMON', 'François', 'Damon', 'SI2DPEPOPE', 'dme-fdamon.consultant@dgfip.finances.gouv.fr' ],
            [ 'P. Bourmaud', 'Patrice', 'Bourmaud', 'SI2DPEPPF', 'patrice.bourmaud@dgfip.finances.gouv.fr' ],
            [ 'U.MOURTY', 'Uma', 'Mourty', 'SI2DPEPPF', 'uma.mourty@dgfip.finances.gouv.fr' ],
            [ 'M.-LARTIN', 'Mylène', 'Lartin', 'SI2DPESPE', 'mylene.lartin@dgfip.finances.gouv.fr' ],
            [ 'H.AZZOUZI', 'Housni', 'Azzouzi-Idrissi', 'SI2DPEPOPE', 'housni.azzouzi-idrissi@dgfip.finances.gouv.fr' ],
            [ 'P. Riou', 'Pierre', 'Riou', 'SI2DPEPOPE', 'pierre.riou@dgfip.finances.gouv.fr' ],
            [ 'C.Gosciniak', 'Christian', 'Gosciniak', 'SI2DPEPOPE', 'dpe-gosciniak.consultant@dgfip.finances.gouv.fr' ],
            [ 'D.-RADEGONDE', 'Didier', 'Radegonde', 'SI2DPEPPF', 'didier.radegonde@dgfip.finances.gouv.fr' ],
            [ 'A.-NASCIMENTO', 'Antonio', 'Nascimento', 'SI2DPESPE', 'antonio.nascimento@dgfip.finances.gouv.fr' ],
            [ 'C.Blanqui', 'Cédric', 'Blanqui', 'SI2DPEPPF', 'dme-blanqui.consultant@dgfip.finances.gouv.fr' ],
            [ 'G.-COUPERMANT', 'Gilles', 'Coupermant', 'SI2DPEPOPE', 'dme-coupermant.consultant@dgfip.finances.gouv.fr' ],
            [ 'T.MICHELIN', 'Thierry', 'Michelin', 'SI2DPEPPF', 'thierry.michelin@dgfip.finances.gouv.fr' ],
            [ 'P.PIOT', 'P', 'Piot', 'SI2DPEPPF', 'dme-piot.consultant@dgfip.finances.gouv.fr' ],
            [ 'S.COURTILLAT', 'Saadia', 'Tourliac', 'SI2DPEPOPE', 'saadia.courtillat@dgfip.finances.gouv.fr' ],
            [ 'V.TOURLIAC', 'Virginie', 'Tourliac', 'SI2DPESPE', 'virginie.tourliac@dgfip.finances.gouv.fr' ],
        ];
        // On crée nos pilotes
        $pgb = $io->createProgressBar(count($pilotes));
        $pgb->start();
        $id = 0;
        foreach ($pilotes as $pilote) {
            // On récupère le service équipe (et on le met en mode pilotage/dme)
            $equipe = self::getReferenceId($pilote[3], $conversionServices);
            if ($equipe) {
                $qb = $newDataBdd->getConnection()->prepare('UPDATE service SET est_pilotage_dme = true WHERE id = :id;');
                $qb->bindValue(':id', $equipe);
                $qb->execute();
            }

            // On crée le composant
            $id++;
            $qb = $newDataBdd->getConnection()->prepare('
                INSERT INTO pilote (
                    id, equipe_id, nom, prenom, balp
                ) VALUES (
                    nextval(\'pilote_id_seq\'), :equipe_id, :nom, :prenom, :balp
                );
            ');
            $qb->bindValue(':equipe_id', self::getReferenceId($pilote[3], $conversionServices));
            $qb->bindValue(':prenom', $pilote[1]);
            $qb->bindValue(':nom', $pilote[2]);
            $qb->bindValue(':balp', $pilote[4]);
            $qb->execute();
            $conversionPilotes[$pilote[0]] = $id;
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        /* Composants */

        //region ------- Conversion des Composants (appelé Applications dans l'ancien GESIP) (OK) ------- ON
        /**/
        $usagerDefault = self::getReference($newDataBdd, Usager::class, 'MIXTE', $conversionUsagers);
        $io->title("Conversion des composants");
        $conversionApplications = [];
        $conversionApplicationsExploitant = [];
        // On récupère les applications
        $applications = $oldDataBddGesip->query("
            SELECT a.*, a.Libelle as Libelle, p.Libelle as PlageLibelle
            FROM Applis a
            LEFT JOIN Plages p ON p.Plage = a.Plage
        ")->fetchAll();
        // On récupère les plages utilisateurs
        $conversionPlages = [];
        $plagesUtilisateurs = $oldDataBddGesip->query("
            SELECT *
            FROM PlagesJour
            ORDER BY Plage ASC
        ")->fetchAll();
        foreach ($plagesUtilisateurs as $plage) {
            $conversionPlages[$plage['Plage']][] = [
                'jour' => $plage['NumJour'],
                'debut' => $plage['HeureDeb'],
                'fin' => $plage['HeureFin']
            ];
        }
        // On crée nos applications
        $pgb = $io->createProgressBar(count($applications));
        $pgb->start();
        $id = 0;
        foreach ($applications as $application) {
            // On récupère le bureau de rattachement (et on le met en mode bureau de rattachement)
            $bureau = self::getReferenceId($application['Rattachement'], $conversionServices);
            if ($bureau) {
                $qb = $newDataBdd->getConnection()->prepare('UPDATE service SET est_bureau_rattachement = true WHERE id = :id;');
                $qb->bindValue(':id', $bureau);
                $qb->execute();
            }

            // On crée le composant
            $id++;
            $qb = $newDataBdd->getConnection()->prepare('
                INSERT INTO composant (
                    id, usager_id, domaine_id, exploitant_id, equipe_id, pilote_id, type_element_id,
                    bureau_rattachement_id, label, code_carto, intitule_plage_utilisateur, duree_plage_utilisateur, meteo_active,
                    est_site_hebergement, archive_le
                ) VALUES (
                    nextval(\'composant_id_seq\'), :usager_id, :domaine_id, :exploitant_id, :equipe_id, :pilote_id, :type_element_id,
                    :bureau_rattachement_id, :label, :code_carto, :intitule_plage_utilisateur, :duree_plage_utilisateur, :meteo_active,
                    :est_site_hebergement, :archive_le
                );
            ');
            $qb->bindValue(':usager_id', self::getReferenceId($application['Usager'], $conversionUsagers, $usagerDefault->getId()));
            $qb->bindValue(':domaine_id', self::getReferenceId($application['Domaine'], $conversionDomaines));
            $qb->bindValue(':exploitant_id', self::getReferenceId($application['CSI'], $conversionServices));
            $qb->bindValue(':equipe_id', self::getReferenceId($application['SuiviPar1'], $conversionServices));
            $qb->bindValue(':pilote_id', self::getReferenceId($application['SuiviPar2'], $conversionPilotes));
            $qb->bindValue(':type_element_id', self::getReferenceId($application['EstAdmin'], $conversionTypeElement));
            $qb->bindValue(':bureau_rattachement_id', $bureau);
            $qb->bindValue(':label', trim($application['Libelle']));
            $qb->bindValue(':code_carto', trim($application['CodeCarto']));
            $qb->bindValue(':intitule_plage_utilisateur', $application['PlageLibelle']);
            $qb->bindValue(':duree_plage_utilisateur', 0);
            $qb->bindValue(':meteo_active', intval($application['METEO'] == 1));
            $qb->bindValue(':est_site_hebergement', intval($application['Site'] == 1));
            $qb->bindValue(':archive_le', $application['Archive'] == 1 ? $now->format('Y-m-d H:i:s') : null);
            $qb->execute();
            $conversionApplications[$application['Application']] = $id;
            $conversionApplicationsExploitant[$application['Application']] = self::getReferenceId($application['CSI'], $conversionServices);

            // On ajoute les bonnes plages utilisateurs
            $dureeTotale = 0;
            $plages = isset($conversionPlages[$application['Plage']]) ? $conversionPlages[$application['Plage']] : [];
            foreach ($plages as $plage) {
                // On calcule la durée totale ainsi qu'un potentiel dépassement (en fin si > 24:00:00)
                $debut = explode(':', $plage['debut']);
                $fin = explode(':', $plage['fin']);
                $dureeTotale += (($fin[0] - $debut[0]) * 60) + ($fin[1] - $debut[1]);
                $depassement = ($fin[0] > 24 || $fin[0] == 24 && $fin[1] > 0);

                // On crée la plage utilisateur
                $qb = $newDataBdd->getConnection()->prepare('
                    INSERT INTO plage_utilisateur (id, composant_id, jour, debut, fin)
                    VALUES (nextval(\'plage_utilisateur_id_seq\'), :composant_id, :jour, :debut, :fin);
                ');
                $qb->bindValue(':composant_id', $id);
                $qb->bindValue(':jour', $plage['jour']);
                $qb->bindValue(':debut', $plage['debut']);
                $qb->bindValue(':fin', ($depassement || $plage['fin'] == '24:00:00' ? '00:00:00' : $plage['fin']));
                $qb->execute();

                // Si la fin dépasse 24h, on en crée une seconde avec le temps en plus sur la journée suivante
                if ($depassement) {
                    // On crée une nouvelle plage utilisateur (pour le jour suivant)
                    $qb = $newDataBdd->getConnection()->prepare('
                        INSERT INTO plage_utilisateur (id, composant_id, jour, debut, fin)
                        VALUES (nextval(\'plage_utilisateur_id_seq\'), :composant_id, :jour, :debut, :fin);
                    ');
                    $qb->bindValue(':composant_id', $id);
                    $qb->bindValue(':jour', $plage['jour'] + 1 == 8 ? 1 : $plage['jour'] + 1);
                    $qb->bindValue(':debut', '00:00:00');
                    $qb->bindValue(':fin', str_pad($fin[0] - 24, 2, '0', STR_PAD_LEFT) . ':' . $fin[1] . ':00');
                    $qb->execute();
                }
            }

            $qb = $newDataBdd->getConnection()->prepare('UPDATE composant SET duree_plage_utilisateur = :duree WHERE id = :id;');
            $qb->bindValue(':id', $id);
            $qb->bindValue(':duree', $dureeTotale);
            $qb->execute();

            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        //region ------- Conversion des flux composants (OK) ------- ON
        /**/
        $io->title("Conversion des flux composants");
        $fluxComposants = $oldDataBddGesip->query("
            SELECT a.Appli_mere, a.Appli_fille
            FROM Appli_Appli a
        ")->fetchAll();
        // On crée chaque flux dans la base
        $pgb = $io->createProgressBar(count($fluxComposants));
        $pgb->start();
        foreach ($fluxComposants as $flux) {
            $pere = isset($conversionApplications[$flux['Appli_mere']]) ? $conversionApplications[$flux['Appli_mere']] : null;
            $fils = isset($conversionApplications[$flux['Appli_fille']]) ? $conversionApplications[$flux['Appli_fille']] : null;
            if ($pere && $fils) {
                $qb = $newDataBdd->getConnection()->prepare('INSERT INTO composant_composant (composant_source, composant_target) VALUES (:pere, :fils);');
                $qb->bindValue(':pere', $pere);
                $qb->bindValue(':fils', $fils);
                $qb->execute();
            }
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        //region ------- Conversion des annuaires composants + MAJ Périmètre applicatif (OK) ------- ON
        /**/
        $io->title("Conversion des annuaires composants + MAJ Périmètre applicatif");
        $annuairesComposants = $oldDataBddGesip->query("
            SELECT a.Application, a.Structure, a.Service, sb.Email as ServiceEmail, ab.Email as AnnuaireEmail, a.Action, a.DateMaj
            FROM Annuaire a
            INNER JOIN Services s ON s.Service = a.Service
            LEFT JOIN Bals ab ON ab.Bal = a.Bal
            LEFT JOIN Bals sb ON sb.Bal = s.Bal
        ")->fetchAll();
        // On crée chaque flux dans la base
        $pgb = $io->createProgressBar(count($annuairesComposants));
        $pgb->start();
        $id = 0;
        $conversionAnnuaires = [];
        foreach ($annuairesComposants as $annuaire) {
            // On récupère les références
            $mission = self::getReferenceId($annuaire['Structure'], $conversionMissions);
            $service = self::getReferenceId($annuaire['Service'], $conversionServices);
            $composant = self::getReferenceId($annuaire['Application'], $conversionApplications);

            // Si la mission, le service et le composant existent bien en base
            if ($mission && $service && $composant) {
                // Si l'adresse email dans l'annuaire est la même que le service, ou si les adresses sont différentes mais invalides, on met la balf à null
                $email = $annuaire['AnnuaireEmail'];
                if ($annuaire['AnnuaireEmail'] === $annuaire['ServiceEmail'] || !preg_match("/^[a-zA-Z0-9._\\-]*@[a-zA-Z0-9._\\-]*.[a-zA-Z]{2,3}$/", $annuaire['AnnuaireEmail'])) {
                    $email = null;
                }

                // Nous ajoutons dans l'annuaire si l'action est tout sauf ajout
                if ($annuaire['Action'] != "ajout") {
                    // On insère les informations d'annuaire
                    $qb = $newDataBdd->getConnection()->prepare('
                        INSERT INTO annuaire (
                            id, mission_id, service_id, composant_id, balf
                        ) VALUES (
                            nextval(\'annuaire_id_seq\'), :mission_id, :service_id, :composant_id, :balf
                        );
                    ');
                    $qb->bindValue(':mission_id', self::getReferenceId($annuaire['Structure'], $conversionMissions));
                    $qb->bindValue(':service_id', self::getReferenceId($annuaire['Service'], $conversionServices));
                    $qb->bindValue(':composant_id', self::getReferenceId($annuaire['Application'], $conversionApplications));
                    $qb->bindValue(':balf', $email);
                    $qb->execute();
                    $id++;

                    // On ajoute dans l'annuaire le service
                    if (!isset($conversionAnnuaires[$annuaire['Service']])) {
                        $conversionAnnuaires[$annuaire['Service']] = [];
                    }
                    if (!isset($conversionAnnuaires[$annuaire['Service']][$annuaire['Application']])) {
                        $conversionAnnuaires[$annuaire['Service']][$annuaire['Application']] = [];
                    }
                    $conversionAnnuaires[$annuaire['Service']][$annuaire['Application']][] = $id;
                }

                // Si l'action est ajout ou suppr, alors on crée une demande de "Périmètre Applicatif"
                if ($annuaire['Action'] == "ajout" || $annuaire['Action'] == "suppr") {
                    // On insère les informations
                    $qb = $newDataBdd->getConnection()->prepare('
                        INSERT INTO demande_perimetre_applicatif (
                            id, service_demandeur_id, composant_id, mission_id, "type", ajoute_le
                        ) VALUES (
                            nextval(\'demande_perimetre_applicatif_id_seq\'), :service_demandeur_id, :composant_id, :mission_id, :type, :ajoute_le
                        );
                    ');
                    $qb->bindValue(':service_demandeur_id', self::getReferenceId($annuaire['Service'], $conversionServices));
                    $qb->bindValue(':composant_id', self::getReferenceId($annuaire['Application'], $conversionApplications));
                    $qb->bindValue(':mission_id', self::getReferenceId($annuaire['Structure'], $conversionMissions));
                    $qb->bindValue(':type', $annuaire['Action'] == "ajout" ? DemandePerimetreApplicatif::AJOUT : DemandePerimetreApplicatif::RETRAIT);
                    $qb->bindValue(':ajoute_le', $annuaire['DateMaj'] . ' 02:00:00');
                    $qb->execute();
                }
            }
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        $lastAnnuaireId = $id;
        /**/
        //endregion

        //region ------- Conversion des MAJ Référentiel des flux (OK) ------- ON
        /**/
        $io->title("Conversion des MAJ Référentiel des flux");
        $demandesReferentielFlux = $oldDataBddGesip->query("
            SELECT a.Appli_mere, a.Appli_fille, a.Action, a.DateMaj, a.Service
            FROM Appli_Appli a
            WHERE a.Action = 'ajout' OR a.Action = 'suppr'
        ")->fetchAll();
        // On crée chaque flux dans la base
        $pgb = $io->createProgressBar(count($demandesReferentielFlux));
        $pgb->start();
        foreach ($demandesReferentielFlux as $demande) {
            // On insère les informations
            $qb = $newDataBdd->getConnection()->prepare('
                        INSERT INTO demande_referentiel_flux (
                            id, service_demandeur_id, composant_source_id, composant_target_id, "type", ajoute_le
                        ) VALUES (
                            nextval(\'demande_referentiel_flux_id_seq\'), :service_demandeur_id,
                            :composant_source_id, :composant_target_id, :type, :ajoute_le
                        );
                    ');
            $qb->bindValue(':service_demandeur_id', self::getReferenceId($demande['Service'], $conversionServices));
            $qb->bindValue(':composant_source_id', self::getReferenceId($demande['Appli_mere'], $conversionApplications));
            $qb->bindValue(':composant_target_id', self::getReferenceId($demande['Appli_fille'], $conversionApplications));
            $qb->bindValue(':type', $demande['Action'] == "ajout" ? DemandeReferentielFlux::AJOUT : DemandeReferentielFlux::RETRAIT);
            $qb->bindValue(':ajoute_le', $demande['DateMaj'] . ' 02:00:00');
            $qb->execute();
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        /* Demandes d'interventions */

        //region ------- Récupération des consultations des interventions (OK) ------- ON
        /**/
        $io->title("Récupération des consultations des interventions");
        $consultations = $oldDataBddGesip->query("
            SELECT
                i.Numero, c.Service, c.DateReponse, c.Reponse, c.Commentaire
            FROM Interventions i
            INNER JOIN Consultes c ON i.Numero = c.Numero
            WHERE i.DateDemande >= '$dateReprise' AND i.Numero <> ''
        ")->fetchAll();
        $conversionConsultations = [];
        $pgb = $io->createProgressBar(count($consultations));
        $pgb->start();
        foreach ($consultations as $consultation) {
            if (!isset($conversionConsultations[$consultation['Numero']])) {
                $conversionConsultations[$consultation['Numero']] = [];
            }

            $conversionConsultations[$consultation['Numero']][] = [
                'service' => $consultation['Service'],
                'avis' => $consultation['Reponse'] !== null ? ($consultation['Reponse'] == 1 ? 'ok' : 'ko') : null,
                'date' => $consultation['DateReponse'] ? self::convertToUtc($consultation['DateReponse'] . ' 00:00:00') : null,
                'commentaire' => self::convertMisteryIntoUtf8($consultation['Commentaire'])
            ];
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        //region ------- Récupération des informations de renvoi des interventions (OK) ------- ON
        /**/
        $io->title("Récupération des informations de renvoi des interventions");
        $informationsRenvoi = $oldDataBddGesip->query("
            SELECT
                i.Numero, mi.MotifRenvoi, mi.Commentaire, mi.RenvoiDPE
            FROM MotifsInterventions mi
            LEFT JOIN Interventions i ON i.Numero = mi.Numero
            WHERE i.DateDemande >= '$dateReprise' AND i.Numero <> ''
            ORDER BY i.Numero ASC, mi.Numligne ASC
        ")->fetchAll();
        $conversionInformationsRenvoi = [];
        $pgb = $io->createProgressBar(count($informationsRenvoi));
        $pgb->start();
        foreach ($informationsRenvoi as $infoRenvoi) {
            if (!isset($conversionInformationsRenvoi[$infoRenvoi['Numero']])) {
                $conversionInformationsRenvoi[$infoRenvoi['Numero']] = [];
            }

            if (!isset($conversionInformationsRenvoi[$infoRenvoi['Numero']][$infoRenvoi['RenvoiDPE']])) {
                $conversionInformationsRenvoi[$infoRenvoi['Numero']][$infoRenvoi['RenvoiDPE']] = [];
            }

            $conversionInformationsRenvoi[$infoRenvoi['Numero']][$infoRenvoi['RenvoiDPE']][] = [
                'motif' => [
                    'id' => self::getReferenceId($infoRenvoi['MotifRenvoi'], $conversionMotifsRenvoi),
                    'label' => self::getReferenceLabel($infoRenvoi['MotifRenvoi'], $conversionMotifsRenvoi, $labelsMotifsRenvoi),
                ],
                'commentaire' => self::convertMisteryIntoUtf8($infoRenvoi['Commentaire'])
            ];
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        //region ------- Récupération des impacts des interventions (OK) ------- ON
        /**/
        $io->title("Récupération des impacts des interventions");
        $conversionImpactsPrevisionnel = [];
        $conversionImpactsReels = [];
        $datesInterventions = [];
        $impactsInterventions = $oldDataBddGesip->query("
            SELECT DISTINCT *
            FROM Interventions i
            INNER JOIN ImpactsInterventions ii ON i.Numero = ii.Numero
            WHERE i.DateDemande >= '$dateReprise' AND i.Numero <> ''
            ORDER BY i.Numero, ii.Numligne ASC
        ")->fetchAll();
        $pgb = $io->createProgressBar(count($impactsInterventions));
        $pgb->start();
        foreach ($impactsInterventions as $impact) {
            if (!isset($conversionImpactsPrevisionnel[$impact['Numero']])) {
                $conversionImpactsPrevisionnel[$impact['Numero']] = [];
            }

            if (!isset($datesInterventions[$impact['Numero']])) {
                $datesInterventions[$impact['Numero']] = [
                    'debut' => 0,
                    'fin_min' => 0,
                    'fin_max' => 0,
                    'retour_arriere' => 0
                ];
            }

            $iDateDebut = self::convertToUtcDate($impact['DateIntervention'] . ' ' . $impact['HeurePrevue']);
            $iDateFinMini = (clone $iDateDebut)->add(new \DateInterval('PT' . $impact['DureePrevueMin'] . 'M'));

            if ($impact['DureePrevueMax'] == 0) {
                $iDateFinMax = (clone $iDateFinMini);
            } else {
                $iDateFinMax = (clone $iDateDebut)->add(new \DateInterval('PT' . $impact['DureePrevueMax'] . 'M'));
            }

            $conversionImpactsPrevisionnel[$impact['Numero']][] = [
                'nature' => self::getReferenceId($impact['Impact'], $conversionNatureImpacts),
                'numero_ordre' => $impact['Numligne'],
                'certitude' => ($impact['Certain'] == 1),
                'commentaire' => self::convertMisteryIntoUtf8($impact['Operation']) . "\r\n" . self::convertMisteryIntoUtf8($impact['ImpactLibelle']),
                'date_debut' => $iDateDebut->format('Y-m-d H:i:s'),
                'date_fin_mini' => $iDateFinMini->format('Y-m-d H:i:s'),
                'date_fin_max' => $iDateFinMax->format('Y-m-d H:i:s'),
                'retour_arriere' => $impact['DureeArriere'],
                'composants' => array_map(function ($composant) {
                    return trim($composant);
                }, explode(',', $impact['ListeApplis']))
            ];

            if ($datesInterventions[$impact['Numero']]['debut'] == 0 || $iDateDebut->format('U') < $datesInterventions[$impact['Numero']]['debut']) {
                $datesInterventions[$impact['Numero']]['debut'] = $iDateDebut->format('U');
            }
            if ($iDateFinMini->format('U') > $datesInterventions[$impact['Numero']]['fin_min']) {
                $datesInterventions[$impact['Numero']]['fin_min'] = $iDateFinMini->format('U');
            }
            if ($iDateFinMax->format('U') > $datesInterventions[$impact['Numero']]['fin_max']) {
                $datesInterventions[$impact['Numero']]['fin_max'] = $iDateFinMax->format('U');
            }
            if ($impact['DureeArriere'] > $datesInterventions[$impact['Numero']]['retour_arriere']) {
                $datesInterventions[$impact['Numero']]['retour_arriere'] = $impact['DureeArriere'];
            }

            unset($iDateDebut);
            unset($iDateFinMini);
            unset($iDateFinMax);

            // Si l'impact est également réel
            if ($impact['DateInterventionReelle'] !== null) {
                if (!isset($conversionImpactsReels[$impact['Numero']])) {
                    $conversionImpactsReels[$impact['Numero']] = [];
                }

                if ($impact['DateInterventionReelle'] === "0000-00-00") {
                    $impact['DateInterventionReelle'] = $impact['DateIntervention'];
                }

                $iDateDebut = self::convertToUtcDate($impact['DateInterventionReelle'] . ' ' . $impact['HeureReelle']);
                $iDateFin = (clone $iDateDebut)->add(new \DateInterval('PT' . $impact['DureeReelle'] . 'M'));
                $conversionImpactsReels[$impact['Numero']][] = [
                    'nature' => self::getReferenceId($impact['ImpactReel'], $conversionNatureImpacts),
                    'numero_ordre' => $impact['Numligne'],
                    'commentaire' => self::convertMisteryIntoUtf8($impact['RealiseLibelle']),
                    'date_debut' => $iDateDebut->format('Y-m-d H:i:s'),
                    'date_fin' => $iDateFin->format('Y-m-d H:i:s'),
                    'composants' => array_map(function ($composant) {
                        return trim($composant);
                    }, explode(',', $impact['ListeApplis']))
                ];
                unset($iDateDebut);
                unset($iDateFin);
            }
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        //region ------- Conversion des interventions (global) (OK) ------- ON
        /**/
        $io->title("Conversion des interventions");
        $conversionInterventions = [];
        $interventions = $oldDataBddGesip->query("
            SELECT DISTINCT
                i.Numero, i.Service, i.Brique, i.Motif, CONCAT(i.DateDemande, ' ', i.HeureDemande) AS DateTimeDemande,
                i.Nature, i.Palier, i.Libelle, i.Contournement, i.CSI,
                i.DateDemande, i.DateConsultation, i.DateReponsePEN, i.AccordPEN, i.ForceAvisCDB,
                i.DateConsultationCDB, i.DateReponseCDB, i.AccordCDB, i.CommentaireConsultationCDB,
                i.BALReponseCDB, i.DateChgtEtatValid, i.EtatValid, i.DateRenvoi, i.DateAnnulation,
                i.Annule, i.Termine, i.CommentaireConsultation, i.MotifRefus, i.CommentaireAnnulation,
                i.Inabouti, i.CommentaireRealisation, i.RenvoiDPE
            FROM Interventions i
            WHERE i.DateDemande >= '$dateReprise' AND i.Numero <> ''
            ORDER BY i.DateDemande ASC
        ")->fetchAll();
        $pgb = $io->createProgressBar(count($interventions));
        $pgb->start();
        $lastImpactPrevisionnelId = 0;
        $lastSaisieRealiseId = 0;
        $lastImpactReelsId = 0;
        $conversionSaisieRealise = [];
        // On parcourt les résultat
        $id = 0;
        foreach ($interventions as $intervention) {
            // Si nous avons bien des dates pour ces interventions là
            if (isset($datesInterventions[$intervention['Numero']])) {
                // Si jamais nous avons des interventions liées à des services inexistant, on met le bon ...
                switch ($intervention['Service']) {
                    case 'CSIVERSAILLES':
                        $intervention['Service'] = "ESI_VERSAILLES";
                        break;
                    case 'SI2DCENRES':
                        $intervention['Service'] = "SI2DCEN";
                        break;
                }

                // On récupère les dates (UTC)
                $dateDebut = \DateTime::createFromFormat('U', $datesInterventions[$intervention['Numero']]['debut']);
                $dateFinMin = \DateTime::createFromFormat('U', $datesInterventions[$intervention['Numero']]['fin_min']);
                $dateFinMax = \DateTime::createFromFormat('U', $datesInterventions[$intervention['Numero']]['fin_max']);

                // On calcul le statut de la demande d'intervention
                // (Par défaut on est en analyse en cours)
                $statut = EtatAnalyseEnCours::class;
                $historiqueDemande = [[
                    'date' => self::convertToUtc($intervention['DateTimeDemande']),
                    'statut' => $statut,
                    'donnees' => []
                ]];
                // Si un renvoi
                if ($intervention['DateDemande'] && intval($intervention['RenvoiDPE']) > 0) {
                    $limit = intval($intervention['RenvoiDPE']) <= 4 ? intval($intervention['RenvoiDPE']) : 4;
                    for ($infoRenvoi = 1; $infoRenvoi <= $limit; $infoRenvoi++) {
                        // Si un renvoi est en cours
                        if ($infoRenvoi % 2 === 1 && isset($conversionInformationsRenvoi[$intervention['Numero']][$infoRenvoi])) {
                            $statut = EtatRenvoyee::class;
                            $historiqueDemande[] = [
                                'date' => self::convertToUtc($intervention['DateTimeDemande']),
                                'statut' => $statut,
                                'donnees' => $conversionInformationsRenvoi[$intervention['Numero']][$infoRenvoi]
                            ];

                        // Si un renvoi est terminé
                        } elseif ($infoRenvoi % 2 === 0 && $statut == EtatRenvoyee::class) {
                            $statut = EtatAnalyseEnCours::class;
                            $historiqueDemande[] = [
                                'date' => self::convertToUtc($intervention['DateTimeDemande']),
                                'statut' => $statut,
                                'donnees' => []
                            ];
                        }
                    }
                }
                // Si une demande de consultation
                if ($intervention['DateConsultation']) {
                    // Si il y a eu des consultations c'est une consultation, sinon une information
                    if (isset($conversionConsultations[$intervention['Numero']]) && count($conversionConsultations[$intervention['Numero']]) > 0) {
                        $statut = EtatConsultationEnCours::class;
                        $statutDonnes = [
                            'dateLimite' => self::convertToUtcDate($intervention['DateConsultation'] . ' 00:00:00', $intervention['DateTimeDemande'])
                                ->add(new \DateInterval('P7D'))->format('d/m/Y'),
                            'avecCdb' => ($intervention['ForceAvisCDB'] == 1),
                            'annuaires' => [ ],
                        ];
                        $statutDonnes['avis'] = [];

                        // On récupère les composants impactés
                        $composantsImpactesTotal = [$intervention['Brique']];
                        if (isset($conversionImpactsPrevisionnel[$intervention['Numero']])) {
                            foreach ($conversionImpactsPrevisionnel[$intervention['Numero']] as $impact) {
                                $composantsImpactesTotal = array_merge($composantsImpactesTotal, $impact['composants']);
                            }
                            $composantsImpactesTotal = array_unique($composantsImpactesTotal);
                        }

                        // On parcourt les consultations
                        foreach ($conversionConsultations[$intervention['Numero']] as $consultation) {
                            // Si l'exploitant existe
                            if (isset($conversionServices[$consultation['service']])) {
                                $annuairesService = [];
                                foreach ($composantsImpactesTotal as $composantImpacte) {
                                    if (isset($conversionAnnuaires[$consultation['service']]) && isset($conversionAnnuaires[$consultation['service']][$composantImpacte])) {
                                        $annuairesService = array_merge($annuairesService, $conversionAnnuaires[$consultation['service']][$composantImpacte]);
                                    }
                                }
                                $annuairesService = array_unique($annuairesService);

                                // Si l'exploitant n'est pas présent dans l'annuaire
                                if (count($annuairesService) === 0) {
                                    $qb = $newDataBdd->getConnection()->prepare('
                                        INSERT INTO annuaire (id, mission_id, service_id, composant_id, balf, supprime_le)
                                        VALUES (
                                            nextval(\'annuaire_id_seq\'),
                                            :mission_id, :service_id, :composant_id, null, :supprime_le
                                        );
                                    ');
                                    $qb->bindValue(':mission_id', 5);
                                    $qb->bindValue(':service_id', $conversionServices[$consultation['service']]);
                                    $qb->bindValue(':composant_id', self::getReferenceId($intervention['Brique'], $conversionApplications));
                                    $qb->bindValue(':supprime_le', $now->format('Y-m-d H:i:s'));
                                    $qb->execute();
                                    $lastAnnuaireId++;
                                    if (!isset($conversionAnnuaires[$consultation['service']])) {
                                        $conversionAnnuaires[$consultation['service']] = [];
                                    }
                                    if (!isset($conversionAnnuaires[$consultation['service']][$intervention['Brique']])) {
                                        $conversionAnnuaires[$consultation['service']][$intervention['Brique']] = [];
                                    }
                                    $conversionAnnuaires[$consultation['service']][$intervention['Brique']][] = $lastAnnuaireId;
                                    $annuairesService[] = $lastAnnuaireId;
                                }

                                // On ajoute son avis
                                $statutDonnes['annuaires'] = array_merge($statutDonnes['annuaires'], $annuairesService);

                                if ($consultation['date'] !== null) {
                                    $statutDonnes['avis'][$conversionServices[$consultation['service']]] = [
                                        'avis' => $consultation['avis'],
                                        'commentaire' => $consultation['commentaire'],
                                        'date' => $consultation['date'],
                                    ];
                                }
                            }
                        }
                    } else {
                        $statut = EtatInstruite::class;
                        $statutDonnes = [
                            'annuaires' => [ ],
                        ];
                    }

                    $historiqueDemande[] = [
                        'date' => self::convertToUtc($intervention['DateConsultation'] . ' 00:00:00', $intervention['DateTimeDemande']),
                        'statut' => $statut,
                        'donnees' => $statutDonnes
                    ];
                }
                // Si un renvoi après consultation
                if ($intervention['DateDemande'] && intval($intervention['RenvoiDPE']) > 4) {
                    for ($infoRenvoi = 5; $infoRenvoi <= intval($intervention['RenvoiDPE']); $infoRenvoi++) {
                        // Si un renvoi est en cours
                        if ($infoRenvoi % 2 === 1 && isset($conversionInformationsRenvoi[$intervention['Numero']][$infoRenvoi])) {
                            $statut = EtatRenvoyee::class;
                            $historiqueDemande[] = [
                                'date' => self::convertToUtc($intervention['DateRenvoi'] . ' 00:00:00', $intervention['DateTimeDemande']),
                                'statut' => $statut,
                                'donnees' => $conversionInformationsRenvoi[$intervention['Numero']][$infoRenvoi]
                            ];

                            // Si un renvoi est terminé
                        } elseif ($infoRenvoi % 2 === 0 && $statut == EtatRenvoyee::class) {
                            $statut = EtatAnalyseEnCours::class;
                            $historiqueDemande[] = [
                                'date' => self::convertToUtc($intervention['DateRenvoi'] . ' 00:00:00', $intervention['DateTimeDemande']),
                                'statut' => $statut,
                                'donnees' => []
                            ];
                        }
                    }
                }
                // Si une demande de consultation CDB
                if ($intervention['DateConsultationCDB']) {
                    $statut = EtatConsultationEnCoursCdb::class;
                    $statutDonnes = [
                        'commentaire' => '',
                    ];

                    // Et une réponse effectuée par le CDB
                    if ($intervention['DateReponseCDB']) {
                        $statutDonnes['CDB'] = [
                            'serviceId' => self::getReferenceId($intervention['BALReponseCDB'], $conversionBal, 18),
                            'avis' => ($intervention['AccordCDB'] == 1) ? 'ok' : 'ko',
                            'commentaire' => self::convertMisteryIntoUtf8($intervention['CommentaireConsultationCDB']),
                            'date' => self::convertToUtcDate($intervention['DateReponseCDB'] . ' 00:00:00', $intervention['DateTimeDemande'])->format('c')
                        ];
                    }

                    $historiqueDemande[] = [
                        'date' => self::convertToUtc($intervention['DateConsultationCDB'] . ' 00:00:00', $intervention['DateTimeDemande']),
                        'statut' => $statut,
                        'donnees' => $statutDonnes
                    ];
                }
                // Si une demande de consultation CDB terminée
                if ($intervention['DateConsultationCDB'] && $intervention['DateReponseCDB']) {
                    $statut = EtatInstruite::class;
                    $historiqueDemande[] = [
                        'date' => self::convertToUtc($intervention['DateReponseCDB'] . ' 00:00:00', $intervention['DateTimeDemande']),
                        'statut' => $statut,
                        'donnees' => []
                    ];
                }
                // Si un refus
                if ($intervention['DateReponsePEN'] && $intervention['AccordPEN'] == 0) {
                    $statut = EtatRefusee::class;
                    $historiqueDemande[] = [
                        'date' => self::convertToUtc($intervention['DateReponsePEN'] . ' 00:00:00', $intervention['DateTimeDemande']),
                        'statut' => $statut,
                        'donnees' => [
                            'commentaire' => self::convertMisteryIntoUtf8($intervention['CommentaireConsultation']),
                            'motif' => [
                                'id' => self::getReferenceId($intervention['MotifRefus'], $conversionMotifsRefus),
                                'label' => self::getReferenceLabel($intervention['MotifRefus'], $conversionMotifsRefus, $labelsMotifsRefus),
                            ]
                        ]
                    ];
                }
                // Si un accord
                if ($intervention['DateReponsePEN'] && $intervention['AccordPEN'] == 1) {
                    $statut = EtatAccordee::class;
                    $historiqueDemande[] = [
                        'date' => self::convertToUtc($intervention['DateReponsePEN'] . ' 00:00:00', $intervention['DateTimeDemande']),
                        'statut' => $statut,
                        'donnees' => [
                            'commentaire' => self::convertMisteryIntoUtf8($intervention['CommentaireConsultation'])
                        ]
                    ];
                }
                // Si une annulation
                if ($intervention['DateAnnulation'] || $intervention['Annule'] == 1 || $statut != EtatRefusee::class && $statut != EtatAccordee::class && $dateFinMax <= $now) {
                    $statut = EtatAnnulee::class;
                    $historiqueDemande[] = [
                        'date' => $intervention['DateAnnulation'] ?
                                self::convertToUtc($intervention['DateAnnulation'] . ' 00:00:00', $intervention['DateTimeDemande']) :
                                $now->setTimezone($tzUTC)->format('Y-m-d H:i:s'),
                        'statut' => $statut,
                        'donnees' => [
                            'commentaire' => self::convertMisteryIntoUtf8($intervention['CommentaireAnnulation'])
                        ]
                    ];
                }

                // On tri correctement l'historique par date puis par index dans le tableau
                $historiqueDemandeDates = array_column($historiqueDemande, 'date');
                $historiqueDemandeId = array_keys($historiqueDemande);
                array_multisort($historiqueDemandeDates, SORT_ASC, $historiqueDemandeId, SORT_ASC, $historiqueDemande);
                $statut = $historiqueDemande[count($historiqueDemande) - 1]['statut'];

                // Si Intervention en cours (si statut accord et si dans les dates d'intervention)
                if ($statut == EtatAccordee::class && $dateDebut <= $now) {
                    $statut = EtatInterventionEnCours::class;
                    $historiqueDemande[] = [
                        'date' => $dateDebut->format('Y-m-d H:i:s'),
                        'statut' => $statut,
                        'donnees' => []
                    ];
                }
                // Si Réalisé à saisir (si statut accord et si après les dates d'interventions)
                if ($statut == EtatInterventionEnCours::class && $dateFinMax <= $now) {
                    $statut = EtatSaisirRealise::class;
                    $historiqueDemande[] = [
                        'date' => $dateFinMax->format('Y-m-d H:i:s'),
                        'statut' => $statut,
                        'donnees' => []
                    ];
                }
                // Si nous devons fermer la saisie du réalisé
                $dateFermeture = (clone $dateFinMax)->add(new \DateInterval('P7D'));
                if ($statut == EtatSaisirRealise::class && $intervention['Inabouti'] != 1 && $dateFermeture <= $now) {
                    $statut = EtatInterventionReussie::class;
                    $historiqueDemande[] = [
                        'date' => $dateFermeture->format('Y-m-d H:i:s'),
                        'statut' => $statut,
                        'donnees' => []
                    ];
                    $conversionSaisieRealise[$intervention['Numero']] = [
                        'date' => $dateFermeture->format('Y-m-d H:i:s'),
                        'resultat' => 'ok',
                        'commentaire' => self::convertMisteryIntoUtf8($intervention['CommentaireRealisation'])
                    ];
                } elseif ($statut == EtatSaisirRealise::class && $intervention['Inabouti'] == 1) {
                    $statut = EtatInterventionEchouee::class;
                    $historiqueDemande[] = [
                        'date' => $dateFermeture->format('Y-m-d H:i:s'),
                        'statut' => $statut,
                        'donnees' => []
                    ];
                    $conversionSaisieRealise[$intervention['Numero']] = [
                        'date' => $dateFermeture->format('Y-m-d H:i:s'),
                        'resultat' => 'ko',
                        'commentaire' => self::convertMisteryIntoUtf8($intervention['CommentaireRealisation'])
                    ];
                }

                // On insère les informations d'une demande d'intervention
                $qb = $newDataBdd->getConnection()->prepare('
                    INSERT INTO demande_intervention (
                        id, demande_par_id, composant_id, motif_intervention_id, numero, demande_le,
                        nature_intervention, palier_applicatif, description, solution_contournement,
                        date_debut, date_fin_mini, date_fin_max, duree_retour_arriere, status, status_donnees,
                        ajoute_le
                    ) VALUES (
                        nextval(\'demande_intervention_id_seq\'),
                        :demande_par_id, :composant_id, :motif_intervention_id, :numero, :demande_le,
                        :nature_intervention, :palier_applicatif, :description, :solution_contournement,
                        :date_debut, :date_fin_mini, :date_fin_max, :duree_retour_arriere, :status, :status_donnees,
                        :ajoute_le
                    );
                ');
                $qb->bindValue(':demande_par_id', self::getReferenceId($intervention['Service'], $conversionServices));
                $qb->bindValue(':composant_id', self::getReferenceId($intervention['Brique'], $conversionApplications));
                $qb->bindValue(':motif_intervention_id', self::getReferenceId($intervention['Motif'], $conversionMotifsIntervention, 10));
                $qb->bindValue(':numero', $intervention['Numero']);
                $qb->bindValue(':demande_le', self::convertToUtc($intervention['DateTimeDemande']));
                $qb->bindValue(':nature_intervention', $intervention['Nature'] == "Urgence" ? DemandeIntervention::NATURE_URGENT : DemandeIntervention::NATURE_NORMAL);
                $qb->bindValue(':palier_applicatif', $intervention['Palier'] == 1 ? 1 : 0);
                $qb->bindValue(':description', self::convertMisteryIntoUtf8($intervention['Libelle']));
                $qb->bindValue(':solution_contournement', self::convertMisteryIntoUtf8($intervention['Contournement']));
                $qb->bindValue(':date_debut', $dateDebut->format('Y-m-d H:i:s'));
                $qb->bindValue(':date_fin_mini', $dateFinMin->format('Y-m-d H:i:s'));
                $qb->bindValue(':date_fin_max', $dateFinMax->format('Y-m-d H:i:s'));
                $qb->bindValue(':duree_retour_arriere', $datesInterventions[$intervention['Numero']]['retour_arriere']);
                $qb->bindValue(':status', $historiqueDemande[count($historiqueDemande) - 1]['statut']);
                $qb->bindValue(':status_donnees', json_encode($historiqueDemande[count($historiqueDemande) - 1]['donnees']));
                $qb->bindValue(':ajoute_le', self::convertToUtc($intervention['DateTimeDemande']));
                $qb->execute();
                $id++;
                $conversionInterventions[$intervention['Numero']] = $id;

                // On ajoute les exploitants
                $appli = $intervention['Brique'];
                $CSI = explode(',', $intervention['CSI']);
                foreach ($CSI as $exploitant) {
                    $exploitant = trim($exploitant);

                    // Si l'exploitant existe
                    if (isset($conversionServices[$exploitant])) {
                        // Si l'exploitant n'est pas présent dans l'annuaire
                        if (!isset($conversionAnnuaires[$exploitant]) || !isset($conversionAnnuaires[$exploitant][$appli]) || count($conversionAnnuaires[$exploitant][$appli]) === 0) {
                            $qb = $newDataBdd->getConnection()->prepare('
                                INSERT INTO annuaire (id, mission_id, service_id, composant_id, balf, supprime_le)
                                VALUES (
                                    nextval(\'annuaire_id_seq\'),
                                    :mission_id, :service_id, :composant_id, null, :supprime_le
                                );
                            ');
                            $qb->bindValue(':mission_id', 5);
                            $qb->bindValue(':service_id', $conversionServices[$exploitant]);
                            $qb->bindValue(':composant_id', self::getReferenceId($appli, $conversionApplications));
                            $qb->bindValue(':supprime_le', $now->format('Y-m-d H:i:s'));
                            $qb->execute();
                            $lastAnnuaireId++;
                            if (!isset($conversionAnnuaires[$exploitant])) {
                                $conversionAnnuaires[$exploitant] = [];
                            }
                            if (!isset($conversionAnnuaires[$exploitant][$appli])) {
                                $conversionAnnuaires[$exploitant][$appli] = [];
                            }
                            $conversionAnnuaires[$exploitant][$appli][] = $lastAnnuaireId;
                        }

                        // On ajoute l'annuaire
                        $qb = $newDataBdd->getConnection()->prepare('
                            INSERT INTO demande_intervention_annuaire (demande_intervention_id, annuaire_id)
                            VALUES (:demande_intervention_id, :annuaire_id);
                        ');
                        $qb->bindValue(':demande_intervention_id', $id);
                        $qb->bindValue(':annuaire_id', $conversionAnnuaires[$exploitant][$appli][0]);
                        $qb->execute();
                    }
                }

                // On ajoute en base de données l'historique de la demande
                foreach ($historiqueDemande as $histo) {
                    $qb = $newDataBdd->getConnection()->prepare('
                        INSERT INTO demande_historique_status (
                            id, demande_id, status, donnees, ajoute_le
                        ) VALUES (
                            nextval(\'demande_historique_status_id_seq\'),
                            :demande_id, :status, :donnees, :ajoute_le
                        );
                    ');
                    $qb->bindValue(':demande_id', $id);
                    $qb->bindValue(':status', $histo['statut']);
                    $qb->bindValue(':donnees', json_encode($histo['donnees']));
                    $qb->bindValue(':ajoute_le', $histo['date']);
                    $qb->execute();
                }

                // On ajoute les impacts prévisionnel de la demande en base de données, si il y en a
                if (isset($conversionImpactsPrevisionnel[$intervention['Numero']])) {
                    foreach ($conversionImpactsPrevisionnel[$intervention['Numero']] as $impact) {
                        $lastImpactPrevisionnelId++;
                        $qb = $newDataBdd->getConnection()->prepare('
                            INSERT INTO demande_impact (
                                id, nature_id, demande_id, numero_ordre, certitude, commentaire,
                                date_debut, date_fin_mini, date_fin_max
                            ) VALUES (
                                nextval(\'demande_impact_id_seq\'),
                                :nature_id, :demande_id, :numero_ordre, :certitude, :commentaire,
                                :date_debut, :date_fin_mini, :date_fin_max
                            );
                        ');
                        $qb->bindValue(':nature_id', $impact['nature']);
                        $qb->bindValue(':demande_id', $id);
                        $qb->bindValue(':numero_ordre', $impact['numero_ordre']);
                        $qb->bindValue(':certitude', $impact['certitude'] ? 1 : 0);
                        $qb->bindValue(':commentaire', $impact['commentaire']);
                        $qb->bindValue(':date_debut', $impact['date_debut']);
                        $qb->bindValue(':date_fin_mini', $impact['date_fin_mini']);
                        $qb->bindValue(':date_fin_max', $impact['date_fin_max']);
                        $qb->execute();

                        // On ajoute les composants associés
                        foreach ($impact['composants'] as $composant) {
                            if (isset($conversionApplications[$composant])) {
                                $qb = $newDataBdd->getConnection()->prepare('
                                    INSERT INTO demande_impact_composant (
                                        impact_id, composant_id
                                    ) VALUES (
                                        :impact_id, :composant_id
                                    );
                                ');
                                $qb->bindValue(':impact_id', $lastImpactPrevisionnelId);
                                $qb->bindValue(':composant_id', self::getReferenceId($composant, $conversionApplications));
                                $qb->execute();
                            }
                        }
                    }
                }

                // Si nous avons un réalisé à saisir
                if (isset($conversionSaisieRealise[$intervention['Numero']])) {
                    $lastSaisieRealiseId++;
                    $qb = $newDataBdd->getConnection()->prepare('
                        INSERT INTO demande_saisie_realise (
                            id, demande_id, service_id, resultat, commentaire, ajoute_le
                        ) VALUES (
                            nextval(\'demande_saisie_realise_id_seq\'),
                            :demande_id, :service_id, :resultat, :commentaire, :ajoute_le
                        );
                    ');
                    $qb->bindValue(':demande_id', $id);
                    $qb->bindValue(':service_id', null);
                    $qb->bindValue(':resultat', $conversionSaisieRealise[$intervention['Numero']]['resultat']);
                    $qb->bindValue(':commentaire', $conversionSaisieRealise[$intervention['Numero']]['commentaire']);
                    $qb->bindValue(':ajoute_le', $conversionSaisieRealise[$intervention['Numero']]['date']);
                    $qb->execute();

                    // On ajoute les impacts réels
                    if (isset($conversionImpactsReels[$intervention['Numero']])) {
                        foreach ($conversionImpactsReels[$intervention['Numero']] as $impact) {
                            $lastImpactReelsId++;
                            $qb = $newDataBdd->getConnection()->prepare('
                                INSERT INTO demande_impact_reel (
                                    id, saisie_realise_id, nature_id, numero_ordre, date_debut, date_fin, commentaire
                                ) VALUES (
                                    nextval(\'demande_impact_reel_id_seq\'),
                                    :saisie_realise_id, :nature_id, :numero_ordre, :date_debut, :date_fin, :commentaire
                                );
                            ');
                            $qb->bindValue(':saisie_realise_id', $lastSaisieRealiseId);
                            $qb->bindValue(':nature_id', $impact['nature']);
                            $qb->bindValue(':numero_ordre', $impact['numero_ordre']);
                            $qb->bindValue(':date_debut', $impact['date_debut']);
                            $qb->bindValue(':date_fin', $impact['date_fin']);
                            $qb->bindValue(':commentaire', $impact['commentaire']);
                            $qb->execute();

                            // On ajoute les composants associés
                            foreach ($impact['composants'] as $composant) {
                                if (isset($conversionApplications[$composant])) {
                                    $qb = $newDataBdd->getConnection()->prepare('
                                    INSERT INTO demande_impactreel_composant (
                                        impact_reel_id, composant_id
                                    ) VALUES (
                                        :impact_reel_id, :composant_id
                                    );
                                ');
                                    $qb->bindValue(':impact_reel_id', $lastImpactReelsId);
                                    $qb->bindValue(':composant_id', self::getReferenceId($composant, $conversionApplications));
                                    $qb->execute();
                                }
                            }
                        }
                    } elseif ($historiqueDemande[count($historiqueDemande) - 1]['statut'] === EtatInterventionReussie::class) {
                        foreach ($conversionImpactsPrevisionnel[$intervention['Numero']] as $impact) {
                            $lastImpactReelsId++;
                            $qb = $newDataBdd->getConnection()->prepare('
                                INSERT INTO demande_impact_reel (
                                    id, saisie_realise_id, nature_id, numero_ordre, date_debut, date_fin, commentaire
                                ) VALUES (
                                    nextval(\'demande_impact_reel_id_seq\'),
                                    :saisie_realise_id, :nature_id, :numero_ordre, :date_debut, :date_fin, :commentaire
                                );
                            ');
                            $qb->bindValue(':saisie_realise_id', $lastSaisieRealiseId);
                            $qb->bindValue(':nature_id', $impact['nature']);
                            $qb->bindValue(':numero_ordre', $impact['numero_ordre']);
                            $qb->bindValue(':date_debut', $impact['date_debut']);
                            $qb->bindValue(':date_fin', $impact['date_fin_max']);
                            $qb->bindValue(':commentaire', $impact['commentaire']);
                            $qb->execute();

                            // On ajoute les composants associés
                            foreach ($impact['composants'] as $composant) {
                                if (isset($conversionApplications[$composant])) {
                                    $qb = $newDataBdd->getConnection()->prepare('
                                    INSERT INTO demande_impactreel_composant (
                                        impact_reel_id, composant_id
                                    ) VALUES (
                                        :impact_reel_id, :composant_id
                                    );
                                ');
                                    $qb->bindValue(':impact_reel_id', $lastImpactReelsId);
                                    $qb->bindValue(':composant_id', self::getReferenceId($composant, $conversionApplications));
                                    $qb->execute();
                                }
                            }
                        }
                    }
                }
            }

            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        /* Météo */

        //region ------- Conversion des évènements météos (OK) -------- ON
        /**/
        $io->title("Conversion des évènements météos");
        $evenementsMeteo = $oldDataBddGesip->query("
            SELECT
                md.Semaine, md.Application, md.NbIncident, md.NbIntervTech, md.NbIntervAppl,
                CONCAT(md.DateDe, ' ', md.HeureDe, ':00') AS DateTimeDe,
                CONCAT(md.DateA, ' ', md.HeureA, ':00') AS DateTimeA,
                md.Description, md.Impact, md.LibSolutionCommentaire
            FROM METEO_Donnees md
            WHERE md.Semaine >= '$dateReprise' AND md.DateDe <> '0000-00-00' AND md.DateA <> '0000-00-00'
            ORDER BY md.Semaine ASC
        ")->fetchAll();
        $pgb = $io->createProgressBar(count($evenementsMeteo));
        $pgb->start();

        // On parcourt les évènements
        foreach ($evenementsMeteo as $eventMeteo) {
            // Si le composant existe bien en base
            if (isset($conversionApplications[$eventMeteo['Application']])) {
                // On calcul le type d'opération (Par défaut "Non communiqué")
                $typeOperationId = 9;
                // Si un incident
                if ($eventMeteo['NbIncident'] && !$eventMeteo['NbIntervTech'] && !$eventMeteo['NbIntervAppl']) {
                    $typeOperationId = 10;
                // Si maintenance technique
                } elseif (!$eventMeteo['NbIncident'] && $eventMeteo['NbIntervTech'] && !$eventMeteo['NbIntervAppl']) {
                    $typeOperationId = 3;
                // Si maintenance applicative
                } elseif (!$eventMeteo['NbIncident'] && !$eventMeteo['NbIntervTech'] && $eventMeteo['NbIntervAppl']) {
                    $typeOperationId = 2;
                }

                // On ajoute l'évènement dans la nouvelle base
                $qb = $newDataBdd->getConnection()->prepare('
                    INSERT INTO meteo_evenement (
                        id, composant_id, impact_id, type_operation_id, saisie_par_id, debut, fin, description, commentaire
                    ) VALUES (
                        nextval(\'meteo_evenement_id_seq\'), :composant_id, :impact_id, :type_operation_id,
                        :saisie_par_id, :debut, :fin, :description, :commentaire
                    );
                ');
                $qb->bindValue(':composant_id', self::getReferenceId($eventMeteo['Application'], $conversionApplications));
                $qb->bindValue(':impact_id', self::getReferenceId($eventMeteo['Impact'], $conversionNatureImpactsMeteo, 2));
                $qb->bindValue(':type_operation_id', $typeOperationId);
                $qb->bindValue(':saisie_par_id', isset($conversionApplicationsExploitant[$application['Application']]) && $conversionApplicationsExploitant[$application['Application']] ? $conversionApplicationsExploitant[$application['Application']] : 18);
                $qb->bindValue(':debut', self::convertToUtc($eventMeteo['DateTimeDe']));
                $qb->bindValue(':fin', self::convertToUtc($eventMeteo['DateTimeA']));
                $qb->bindValue(':description', str_ireplace(['<br>','<br />','<br/>'], "\r\n", self::convertMisteryIntoUtf8($eventMeteo['Description'])));
                $qb->bindValue(':commentaire', str_ireplace(['<br>','<br />','<br/>'], "\r\n", self::convertMisteryIntoUtf8($eventMeteo['LibSolutionCommentaire'])));
                $qb->execute();
            }
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        //region ------- Conversion des validations météos (OK) -------- ON
        /**/
        $io->title("Conversion des validations météos");
        $validationsMeteo = $oldDataBddGesip->query("
            SELECT mv.CSI, mv.Semaine
            FROM METEO_Validation mv
            WHERE mv.Semaine >= '$dateReprise'
            ORDER BY mv.Semaine ASC
        ")->fetchAll();
        $pgb = $io->createProgressBar(count($validationsMeteo));
        $pgb->start();

        // On parcourt les validations météo
        foreach ($validationsMeteo as $validationMeteo) {
            // Si l'exploitant existe bien en base
            if (isset($conversionServices[$validationMeteo['CSI']])) {
                // On calcule les dates
                $periodeDebut = \DateTime::createFromFormat('Y-m-d H:i:s', $validationMeteo['Semaine'] . ' 00:00:00');
                $periodeFin = (clone $periodeDebut)->add(new \DateInterval('P6D'))->setTime(23, 59, 59);
                $ajoutLe = (clone $periodeFin)->add(new \DateInterval('P1D'))->setTime(0, 0, 0);

                // On ajoute la validation dans la nouvelle base
                $qb = $newDataBdd->getConnection()->prepare('
                    INSERT INTO meteo_validation (
                        id, exploitant_id, periode_debut, periode_fin, ajoute_le
                    ) VALUES (
                        nextval(\'meteo_validation_id_seq\'), :exploitant_id, :periode_debut, :periode_fin, :ajoute_le
                    );
                ');
                $qb->bindValue(':exploitant_id', $conversionServices[$validationMeteo['CSI']]);
                $qb->bindValue(':periode_debut', $periodeDebut->format('Y-m-d H:i:s'));
                $qb->bindValue(':periode_fin', $periodeFin->format('Y-m-d H:i:s'));
                $qb->bindValue(':ajoute_le', $ajoutLe->format('Y-m-d H:i:s'));
                $qb->execute();
            }
            $pgb->advance();
        }
        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        /* CMEP */

        //region ------- Conversion des MEP SSI (OK) ------- ON
        /**/
        $io->title("Conversion des MEP SSI");
        $mepsSsi = $oldDataBddCmep->query("
            SELECT *
            FROM mep m
            WHERE
                m.date_lep >= '$dateReprise' OR
                m.date_mep_start >= '$dateReprise' OR
                m.date_mep_end >= '$dateReprise' OR
                m.date_gonogo >= '$dateReprise' OR
                m.date_mes >= '$dateReprise'
            ORDER BY m.cmep_id ASC
        ")->fetchAll();
        $pgb = $io->createProgressBar(count($mepsSsi));
        $pgb->start();
        $id = 0;
        // On parcourt les mep ssi
        foreach ($mepsSsi as $mep) {
            // On ajoute l'évènement dans la nouvelle base
            $qb = $newDataBdd->getConnection()->prepare('
                INSERT INTO mep_ssi (
                    id, equipe_id, statut_id, palier, visibilite, lep, mep_debut, mep_fin, mes, description,
                    impacts, risques, mots_clefs, ajoute_le, demande_par_id
                ) VALUES (
                    nextval(\'mep_ssi_id_seq\'), :equipe_id, :statut_id, :palier, :visibilite,
                    :lep, :mep_debut, :mep_fin, :mes, :description, :impacts, :risques, :mots_clefs, :ajoute_le,
                    :demande_par_id
                );
            ');
            $qb->bindValue(':equipe_id', self::getReferenceId(self::getReferenceId($mep['pilotage'], $conversionMepSsiEquipePilotage), $conversionServices));
            $qb->bindValue(':statut_id', self::getReferenceId($mep['status'], $conversionMepSsiStatus));
            $qb->bindValue(':palier', self::convertMisteryIntoUtf8($mep['palier']));
            $qb->bindValue(':visibilite', $mep['visible']);
            $qb->bindValue(':lep', $mep['date_lep'] ? self::convertToUtc($mep['date_lep'] . ' 00:00:00') : null);
            $qb->bindValue(':mep_debut', $mep['date_mep_start'] ? self::convertToUtc($mep['date_mep_start'] . ' 00:00:00') : null);
            $qb->bindValue(':mep_fin', $mep['date_mep_end'] ? self::convertToUtc($mep['date_mep_end'] . ' 00:00:00') : null);
            $qb->bindValue(':mes', $mep['date_mes'] ? self::convertToUtc($mep['date_mes'] . ' 00:00:00') : null);
            $qb->bindValue(':description', self::convertMisteryIntoUtf8($mep['description']));
            $qb->bindValue(':impacts', self::convertMisteryIntoUtf8($mep['impacts']));
            $qb->bindValue(':risques', self::convertMisteryIntoUtf8($mep['risques']));
            $qb->bindValue(':mots_clefs', self::convertMisteryIntoUtf8($mep['tags']));
            $qb->bindValue(':ajoute_le', self::convertMisteryIntoUtf8($mep['ts']));
            $qb->bindValue(':demande_par_id', 18);
            $qb->execute();
            $id++;

            // On lie les pilotes à notre mep_ssi
            $pilotes = explode(',', $mep['pilotes']);
            $pilotes = array_map('trim', $pilotes);
            foreach ($pilotes as $pilote) {
                // Si le pilote existe dans la nouvelle base
                if (isset($conversionPilotes[$pilote])) {
                    $qb = $newDataBdd->getConnection()->prepare('
                        INSERT INTO mep_ssi_pilote ( mep_ssi_id, pilote_id )
                        VALUES ( :mep_ssi_id, :pilote_id );
                    ');
                    $qb->bindValue(':mep_ssi_id', $id);
                    $qb->bindValue(':pilote_id', self::getReferenceId($pilote, $conversionPilotes));
                    $qb->execute();
                }
            }

            // On lie les composants à notre mep_ssi
            $composants = explode(',', $mep['domaine']);
            $composants = array_map('trim', $composants);
            foreach ($composants as $composant) {
                // Si le composant existe dans la nouvelle base
                if (isset($conversionApplications[$composant])) {
                    $qb = $newDataBdd->getConnection()->prepare('
                        INSERT INTO mep_ssi_composant ( mep_ssi_id, composant_id )
                        VALUES ( :mep_ssi_id, :composant_id );
                    ');
                    $qb->bindValue(':mep_ssi_id', $id);
                    $qb->bindValue(':composant_id', self::getReferenceId($composant, $conversionApplications));
                    $qb->execute();
                }
            }

            // On lie les grid à notre mep_ssi
            $grids = explode(',', $mep['grid']);
            $grids = array_map('trim', $grids);
            foreach ($grids as $grid) {
                // Si le grid existe dans la nouvelle base
                if (isset($conversionMepSSiGridMep[$grid])) {
                    $qb = $newDataBdd->getConnection()->prepare('
                        INSERT INTO mep_ssi_grid_mep ( mep_ssi_id, grid_mep_id )
                        VALUES ( :mep_ssi_id, :grid_mep_id );
                    ');
                    $qb->bindValue(':mep_ssi_id', $id);
                    $qb->bindValue(':grid_mep_id', self::getReferenceId($grid, $conversionMepSSiGridMep));
                    $qb->execute();
                }
            }

            $pgb->advance();
        }

        $pgb->finish();
        $io->newLine(2);
        /**/
        //endregion

        // This is the end!
        $io->newLine();
        $io->success("Migration effectuée !");
        return 0;
    }

    /**
     * Fonction permettant de renvoyer la durée en minutes entre une heure de début et une heure de fin.
     * (Format des heures : HH:MM:SS)
     *
     * @param string $debut
     * @param string $fin
     *
     * @return int
     */
    public static function getDureeMinutesAvecDebutFin(string $debut, string $fin) : int
    {
        $debut = explode(':', $debut);
        $fin = explode(':', $fin);
        return (($fin[0] - $debut[0]) * 60) + ($fin[1] - $debut[1]);
    }

    /**
     * Fonction permettant de renvoyer une référence d'un object par son ancien ID et une table de conversion.
     *
     * @param EntityManagerInterface $em
     * @param string                 $class
     * @param                        $id
     * @param array                  $conversion
     * @param null                   $default
     *
     * @return object|null
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getReference(EntityManagerInterface $em, string $class, $id, array $conversion, $default = null)
    {
        if ($id !== null && isset($conversion[$id])) {
            return $em->getReference($class, $conversion[$id]);
        }

        return $default;
    }

    /**
     * Fonction permettant de récupérer la nouvelle id via un tableau de conversion ou une valeur par défaut.
     *
     * @param       $id
     * @param array $conversion
     * @param null  $default
     *
     * @return mixed|null
     */
    public static function getReferenceId($id, array $conversion, $default = null)
    {
        if ($id !== null && isset($conversion[$id])) {
            return $conversion[$id];
        }
        return $default;
    }

    /**
     * Fonction permettant de récupérer un label du la nouvelle base par rapport à un vieux id.
     *
     * @param       $id
     * @param array $conversion
     * @param array $labels
     * @param null  $default
     *
     * @return mixed|null
     */
    public static function getReferenceLabel($id, array $conversion, array $labels, $default = null)
    {
        $id = self::getReferenceId($id, $conversion);

        if ($id !== null && isset($labels[$id])) {
            return $labels[$id];
        }
        return $default;
    }

    /**
     * Fonction permettant de récupérer la bonne conversion de données
     *
     * @param string|null $data
     *
     * @return string|null
     */
    public static function convertMisteryIntoUtf8(?string $data) : ?string
    {
        $test = preg_match("!!u", $data);
        $test2 = preg_match("/©/u", $data);

        if ($test && $test2) {
            $data = utf8_decode($data);
        } elseif (!$test && !$test2) {
            $data = utf8_encode($data);
        }

        $data = str_replace('§', '\'', $data);
        return $data;
    }

    /**
     * Fonction permettant de convertir une date Europe/Paris en UTC et de la renvoyée au format Y-m-d H:i:s.
     * (si l'on passe un second paramètre, on renvoie la date la plus récente entre celle passée en paramètre et la date
     * calculée)
     *
     * @param string $datetime
     *
     * @return string
     */
    public static function convertToUtc(string $datetime, string $datetimeCompare = null) : string
    {
        $newDate = self::convertToUtcDate($datetime);
        if ($datetimeCompare !== null && $newDate < $newDateCompare = self::convertToUtcDate($datetimeCompare)) {
            return $newDateCompare->format('Y-m-d H:i:s');
        }
        return self::convertToUtcDate($datetime)->format('Y-m-d H:i:s');
    }

    /**
     * Fonction permettant de convertir une date Europe/Paris en UTC et de renvoyer un DateTime.
     *
     * @param string $datetime
     *
     * @return \DateTime
     */
    public static function convertToUtcDate(string $datetime) : \DateTime
    {
        return \DateTime::createFromFormat('Y-m-d H:i:s', $datetime, new \DateTimeZone('Europe/paris'))
            ->setTimezone(new \DateTimeZone('UTC'));
    }
}
