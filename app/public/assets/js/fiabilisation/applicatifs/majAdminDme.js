$(document).ready(function () {

    /**
     * Initialisation
     */
    var $selectionService = $("#select-service");

    var $filtreComposants = $('#filtre-composants');
    var $selectionTousComposants = $("#selection-tous-composants");
    var $checkboxTousComposants = $("#checkbox-tous-composants");
    var $listeComposants = $("#liste-composants");
    var $loadingComposants = $listeComposants.find('.list-loading');
    var $videComposants = $listeComposants.find('.list-empty');

    var $selectionMission = $("#select-mission");

    var $perimetreApplicatifService = $("#perimetre-applicatif-service");
    var $tableauPerimetreApplicatifService = $("#tableau-perimetre-applicatif-service");
    var $loadingPerimetreApplicatifService = $perimetreApplicatifService.find('.list-loading');
    var $videPerimetreApplicatifService = $perimetreApplicatifService.find('.list-empty');

    $boutonEnregistrer = $("#btn-enregistrer");

    var filtre_composants = null;
    var requete_en_cours = null;
    var service = null;

    /**
     * tableau d'objets : 1 objet = 1 élémént (composant + mission) de périmètre applicatif
     *
     * attributs :  composantId
     *              composantLabel
     *              missionId
     *              missionLabel
     *              demandeType
     *              demandeId
     *              actionAdmin
     *
     *  types d'éléments :
     *
     *  - périmètre applicatif actuel du service : demandeType = null, actionAdmin = null|r(emove)
     *
     *  - demande du service : demandeType = a(dd)|r(emove), actionAdmin = null|r(emove)
     *
     *  - ajout manuel : demandeType = null, actionAdmin = a(dd)
     *
    */
    var perimetre_applicatif = [];
    var maj_perimetre = [];
    var maj_demandes = [];

    /**
     * Choix du service
     */
    $("#btn-service").on('click', function() {
        if ($selectionService.val() === "") {
            $selectionService.addClass("form-control-error");
            var $modal = $('#erreurSaisieModal');
            $modal.find('.modal-body p').html("Vous devez choisir un service.");
            $modal.modal('show');
            return false;
        }
        $selectionService.removeClass("form-control-error");
        if (service === null) { // premier service sélectionné
            service = $selectionService.val();
            $("#parametres-service").removeClass("d-none");
            ajax_liste_composants();
            ajax_perimetre_applicatif(service);
        }
        else {
            if ($selectionService.val() != service) { // changement de service sélectionné
                service = $selectionService.val();
                $filtreComposants.val("");
                ajax_liste_composants();
                $selectionMission.val("");
                $tableauPerimetreApplicatifService.empty();
                ajax_perimetre_applicatif(service);
            }
        }
    });

    /**
     * Choix des composants
     */
    // ajout d'un composant dans la liste des composants
    var ajout_liste_composant = function(composant) {
        $listeComposants.append(
            '<div class="form-check"><input class="form-check-input" type="checkbox" id="composant' + composant.id + '" value="'
            + composant.id + '"><label class="form-check-label" for="' + composant.id + '">' + composant.label + '</label></div>'
        );
    };

    // appel serveur pour récupérer la liste (filtrée) des composants
    var ajax_liste_composants = function(filtre = '') {
        $videComposants.hide();
        $selectionTousComposants.hide();
        $listeComposants.find('.form-check').remove();
        $loadingComposants.show();
        requete_en_cours = $.ajax({
                url: "/ajax/composant/recherche/label?label=" + encodeURIComponent(filtre),
                method: 'GET'
            })
            .done(function(reponse) {
                if(reponse.length === 0) {
                    $videComposants.show();
                }
                else {
                    $selectionTousComposants.show();
                    $checkboxTousComposants.prop("checked", false);
                    reponse.forEach(function(element) {
                        ajout_liste_composant(element);
                    });
                }
            })
            .fail(function(erreur) {
                if(erreur.status != 0) {
                    alert("Impossible de récupérer les données pour l'instant.");
                }
            })
            .always(function() {
                $loadingComposants.hide();
            });
    };

    // filtrage de la liste des composants
    $filtreComposants.on('keypress', function(e) {
        // transformer une minuscule en majuscule
        if ((e.charCode > 96) && (e.charCode < 123)) {
            $(this).val($(this).val() + String.fromCharCode(e.charCode).toUpperCase());
            return false;
        }
        // transformer un espace en underscore
        if (e.charCode === 32) {
            $(this).val($(this).val() + '_');
            return false;
        }
        // accepter les chiffres
        if ((e.charCode >= 48) && (e.charCode <= 57)) {
            char = String.fromCharCode(e.charCode);
        }
        // accepter les majuscules et les caractères -_()[]\/
        if (((e.charCode > 64) && (e.charCode < 91)) || (e.charCode === 45) || (e.charCode === 95) || (e.charCode === 40) || (e.charCode === 41) || (e.charCode === 91) || (e.charCode === 93) || (e.charCode === 92) || (e.charCode === 47)) {
            return true;
        }
        // refuser les autres caractères
        return false;
    });
    $filtreComposants.on('keyup', function() {
        // recharger la liste des composants si le filtre a été modifié
        if ($(this).val() != filtre_composants) {
            if (requete_en_cours) {
                requete_en_cours.abort();
            }
            filtre_composants = $(this).val();
            ajax_liste_composants(filtre_composants);
        }
    });
    $filtreComposants.nextAll('.reset-field').on('click', function() {
        // recharger la liste des composants si le filtre a été éffacé
        if (requete_en_cours) {
            requete_en_cours.abort();
        }
        filtre_composants = '';
        ajax_liste_composants();
    });

    // selection de tous les composants affichés
    $checkboxTousComposants.on('change', function() {
        if ($(this).is(':checked')) {
            $listeComposants.find("input").prop("checked", true);
        } else {
            $listeComposants.find("input").prop("checked", false);
        }
    });

    /**
     * Tableau du périmètre applicatif du service
     */

    // ajout d'un composant+mission dans le tableau du périmètre applicatif du service
    var ajout_tableau_perimetre = function(perimetre) {
        let composantMissionKey = perimetre.composantId + '-' + perimetre.missionId;
        if (perimetre.affichage !== null) {
            $tableauPerimetreApplicatifService.append(
                '<tr ' + (perimetre.affichage === 'v' ? 'class="text-success font-weight-bold"' : (perimetre.affichage === 'r' ? 'class="text-danger font-weight-bold"' : '' ) )
                + '><td class="text-center"><input class="checkbox" type="checkbox" value="' + composantMissionKey + '"></td><td>'
                + perimetre.composantLabel + '</td><td>' + perimetre.missionLabel + '</td></tr>'
            );
        }
    };

    // affichage du tableau du périmètre applicatif du service
    var affichage_tableau_perimetre = function() {
        // Génère un tableau à partir de notre hashtable pour pouvoir l'ordonner
        let perimetre_applicatif = [];
        for (let composantMissionKey in composantsMissionsHashTable) {
            let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
            perimetre_applicatif.push(composantMissionValue);
        }
        // Affichage de la liste des composants missions associés à ce service
        $tableauPerimetreApplicatifService.empty();
        if (perimetre_applicatif.length <= 0) {
            $videPerimetreApplicatifService.show();
        } else {
            $videPerimetreApplicatifService.hide();
            // tri du tableau par libellés de composants et de missions croissants
            perimetre_applicatif.sort(function(a, b) {
                if (a.composantLabel > b.composantLabel) { return 1; }
                if (a.composantLabel < b.composantLabel) { return -1; }
                if (a.missionLabel > b.missionLabel) { return 1; }
                if (a.missionLabel < b.missionLabel) { return -1; }
                return 0;
            });
            // génère une ligne par couplet composant - mission trouvés
            perimetre_applicatif.forEach(function(element) {
                ajout_tableau_perimetre(element);
            });
        }
    };

    // appel serveur pour récupérer le périmètre applicatif du service
    var ajax_perimetre_applicatif = function(service) {
        perimetre_applicatif = [];
        composantsMissionsHashTable = {};
        $videPerimetreApplicatifService.hide();
        $loadingPerimetreApplicatifService.show();
        $.ajax({
            url: "/ajax/service/composants/" + encodeURIComponent(service),
            method: 'GET'
        })
        .done(function(reponse) {
            reponse.donnees.forEach(function(element) {
                let composantMissionKey = element.composantId + '-' + element.missionId;
                composantsMissionsHashTable[composantMissionKey] = {
                    composantId: element.composantId,
                    composantLabel: element.composant,
                    missionId: element.missionId,
                    missionLabel: element.mission,
                    demandeType: null,
                    demandeId: null,
                    actionAdmin: null,
                    affichage: 'n'
                };
            });
            // appel serveur pour récupérer les demandes du service pour modifier son périmètre applicatif
            $.ajax({
                url: "/ajax/fiabilisation/applicatif/demandes/service/" + encodeURIComponent(service),
                method: 'GET'
            })
            .done(function(reponse) {
                reponse.donnees.forEach(function(demande) {
                    let composantMissionKey = demande.composantId + '-' + demande.missionId;
                    if (demande.type === 'add' && !composantsMissionsHashTable.hasOwnProperty(composantMissionKey)) {
                        composantsMissionsHashTable[composantMissionKey] = {
                            composantId: demande.composantId,
                            composantLabel: demande.composantLabel,
                            missionId: demande.missionId,
                            missionLabel: demande.missionLabel,
                            demandeType: 'a',
                            demandeId: demande.id,
                            actionAdmin: 'a',
                            affichage: 'v'
                        };
                    } else if (demande.type === 'remove' && composantsMissionsHashTable.hasOwnProperty(composantMissionKey)) {
                        composantsMissionsHashTable[composantMissionKey]['demandeType'] = 'r';
                        composantsMissionsHashTable[composantMissionKey]['actionAdmin'] = 'r';
                        composantsMissionsHashTable[composantMissionKey]['demandeId'] = demande.id;
                        composantsMissionsHashTable[composantMissionKey]['affichage'] = 'r';
                    }
                });
                affichage_tableau_perimetre();
            })
            .fail(function(erreur) {
                if(erreur.status != 0) {
                    alert("Impossible de récupérer les données pour l'instant.");
                }
            })
        })
        .fail(function(erreur) {
            if(erreur.status != 0) {
                alert("Impossible de récupérer les données pour l'instant.");
            }
        })
        .always(function() {
            $loadingPerimetreApplicatifService.hide();
        });
    };

    /**
     * Ajouter des éléments au tableau du périmètre applicatif du service
     */
    $("#btn-ajouter").on('click', function() {
        let selectedMissionId = $selectionMission.val();
        var $modal = $('#erreurSaisieModal');
        if (selectedMissionId === "" || ! $listeComposants.find("input").is(':checked')) {
            $modal.find('.modal-body p').html("La demande d'ajout d'un composant nécessite la sélection d'un composant ainsi que d'une mission.");
            $modal.modal('show');
            return false;
        }
        // Si cette entrée existe déjà et qu'elle est pas marquée pour être supprimée, on ne peut pas l'ajouter
        let allowContinue = true;
        let $composants = $listeComposants.find("input:checked");
        $composants.each(function() {
            let composantMissionKey = $(this).val() + '-' + selectedMissionId;
            if (composantsMissionsHashTable.hasOwnProperty(composantMissionKey)) {
                let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
                if (composantMissionValue.actionAdmin !== 'r' && composantMissionValue.affichage !== null) {
                    $modal.find('.modal-body p').html('Le composant "' + composantMissionValue.composantLabel + '" associé à la mission "'
                        + composantMissionValue.missionLabel + '" est déjà affecté à ce service.');
                    $modal.modal('show');
                    allowContinue = false;
                }
            }
        });
        // Si l'ajout est autorisé nous pouvons continuer
        if (allowContinue) {
            $composants.each(function() {
                let composantId = $(this).val();
                let composantMissionKey = composantId + '-' + selectedMissionId;
                if (composantsMissionsHashTable.hasOwnProperty(composantMissionKey)) {
                    // Si ce couplet existe déjà dans la liste c'est qu'il est actuellement marqué pour suppression
                    let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
                    if (composantMissionValue.actionAdmin === 'r') {
                        composantMissionValue.actionAdmin = null;
                        composantMissionValue.affichage = 'n';
                    } else {
                        composantMissionValue.actionAdmin = 'a';
                        composantMissionValue.affichage = 'v';
                    }
                } else {
                    // Sinon on l'ajoute
                    composantsMissionsHashTable[composantMissionKey] = {
                        composantId: composantId,
                        composantLabel: $(this).next().text(),
                        missionId: selectedMissionId,
                        missionLabel: $selectionMission.children(":selected").text().trim(),
                        demandeType: 'a',
                        demandeId: null,
                        actionAdmin: 'a',
                        affichage: 'v'
                    };
                }
            });
        }
        // Rafraichissement de l'affichage
        affichage_tableau_perimetre();
        $checkboxTousComposants.prop('checked', false);
        $composants.prop('checked', false);
        $selectionMission.val('');
    });

    /**
     * Retirer des éléments au tableau du périmètre applicatif du service
     */
    $("#btn-retirer").on('click', function() {
        if (! $perimetreApplicatifService.find("input").is(':checked')) {
            var $modal = $('#erreurSaisieModal');
            $modal.find('.modal-body p').html("Merci de sélectionner un composant à retirer.");
            $modal.modal('show');
            return false;
        }
        $perimetreApplicatifService.find('input:checked').each(function() {
            let composantMissionKey = $(this).val();
            let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
            if (composantMissionValue.demandeType === 'a') {
                composantMissionValue.actionAdmin = null;
                composantMissionValue.affichage = null;
            } else if (composantMissionValue.actionAdmin === 'a') {
                delete composantsMissionsHashTable[composantMissionKey];
            } else {
                composantMissionValue.actionAdmin = 'r';
                composantMissionValue.demandeType = 'r';
                composantMissionValue.affichage = 'r';
            }
        });
        affichage_tableau_perimetre();
    });

    /**
     * Enregistrer les modifications effectuées
     */
    $boutonEnregistrer.on('click', function() {
        maj_perimetre = [];
        maj_demandes = [];
        for (let composantMissionKey in composantsMissionsHashTable) {
            let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
            if (composantMissionValue.actionAdmin !== null) {
                maj_perimetre.push(composantMissionValue);
            }
            if (composantMissionValue.demandeId !== null) {
                maj_demandes.push({
                    demandeId: composantMissionValue.demandeId,
                    bilan: composantMissionValue.demandeType === composantMissionValue.actionAdmin ? 'a' : 'r'
                });
            }
        }

        if ((maj_perimetre.length === 0) && (maj_demandes.length === 0)) {
            var $modal = $('#erreurSaisieModal');
            $modal.find('.modal-body p').html("Vous n'avez effectué aucune modification.");
            $modal.modal('show');
        }
        else {
            $('#enregistrerModificationsModal').modal('show');
        }
    });

    /**
     * Si l'utilisateur valide l'enregistrement
     */
    $("#btn-ouiEnregister").on('click', function() {
        $('#enregistrerModificationsModal').modal('hide');
        $boutonEnregistrer.attr('disabled', true);
        // appel serveur pour envoyer les modifications du périmètre applicatif du service et de ses demandes de modification
        $.ajax({
            url: "/ajax/service/perimetre/modification",
            method: 'POST',
            dataType: 'json',
            data: {
                service: service,
                perimetre: maj_perimetre,
                demandes: maj_demandes
            },
            success: function() {
                $tableauPerimetreApplicatifService.empty();
                ajax_perimetre_applicatif(service);
                var $modal = $('#erreurSaisieModal');
                $modal.find('.modal-body p').html("Modifications enregistrées.");
                $modal.modal('show');
            },
            error: function() {
                alert('Impossible de soumettre les informations au serveur.');
            },
            complete: function() {
                $boutonEnregistrer.removeAttr('disabled');
            }
        });
    });
});
