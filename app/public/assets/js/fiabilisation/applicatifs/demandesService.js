$(document).ready(function () {

    /**
     * Initialisation
     */
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

    $boutonEnvoyer = $("#btn-envoyer");

    var filtre_composants = null;
    var requete_en_cours = null;

    /**
     * tableau d'objets : 1 objet = 1 élémént (composant + mission) de périmètre applicatif
     *
     * attributs :  composantId
     *              composantLabel
     *              missionId
     *              missionLabel
     *              dejaAffecte : true si element appartient au périmètre applicatif du service
     *              demandeId : si demande déjà entregistrée pour cet élément
     *              demandeType : null/a(jout)/r(etrait)
     *              affichage : null/n(ormal)/r(ouge)/v(ert)
     *
    */
    var perimetre_applicatif = [];
    let composantsMissionsHashTable = {};
    var maj_perimetre = [];
    var maj_demandes = [];
    var service = $("#perimetre-applicatif-demandes-modification").attr("data-service");

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
        $listeComposants.find("input").prop("checked", $(this).is(':checked'));
    });

    /**
     * Tableau du périmètre applicatif du service
     */

    // ajout d'un composant+mission dans le tableau du périmètre applicatif du service
    var ajout_tableau_perimetre = function(perimetre) {
        if (perimetre.affichage !== 'null') {
            let composantMissionKey = perimetre.composantId + '-' + perimetre.missionId;
            $tr = $('<tr></tr>');
            $tr.append($('<td class="text-center"><input class="checkbox" type="checkbox" value="' + composantMissionKey + '"></td>'));
            $tr.append($('<td></td>').text(perimetre.composantLabel));
            $tr.append($('<td></td>').text(perimetre.missionLabel));
            if (perimetre.affichage == 'v') {
                $tr.addClass('text-success font-weight-bold');
            } else if (perimetre.affichage == 'r') {
                $tr.addClass('text-danger font-weight-bold');
            }
            $tableauPerimetreApplicatifService.append($tr);
        }
    };

    // affichage du tableau du périmètre applicatif du service
    var affichage_tableau_perimetre = function() {
        // Génère un tableau à partir de notre hashtable pour pouvoir l'ordonner
        let perimetre_applicatif = [];
        for (let composantMissionKey in composantsMissionsHashTable) {
            let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
            if (composantMissionValue.affichage !== null) {
                perimetre_applicatif.push(composantMissionValue);
            }
        }
        $tableauPerimetreApplicatifService.empty();
        if (perimetre_applicatif.length <= 0) {
            $videPerimetreApplicatifService.show();
        }
        else {
            $videPerimetreApplicatifService.hide();
            perimetre_applicatif.sort(function(a, b) {
                if (a.composantLabel > b.composantLabel) { return 1; }
                if (a.composantLabel < b.composantLabel) { return -1; }
                if (a.missionLabel > b.missionLabel) { return 1; }
                if (a.missionLabel < b.missionLabel) { return -1; }
                return 0;
            });
            perimetre_applicatif.forEach(function(element) {
                ajout_tableau_perimetre(element);
            });
        }
    };

    // appel serveur pour récupérer le périmètre applicatif du service
    var ajax_perimetre_applicatif = function(service) {
        perimetre_applicatif = [];
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
                    affichage: 'n',
                    dejaAffecte: true
                };
            });
            // appel serveur pour récupérer les demandes du service enregistrées
            $.ajax({
                url: "/ajax/fiabilisation/applicatif/demandes/service/" + encodeURIComponent(service),
                method: 'GET'
            })
            .done(function(reponse) {
                reponse.donnees.forEach(function(demande) {
                    let composantMissionKey = demande.composantId + '-' + demande.missionId;
                    if (demande.type === 'add') {
                       composantsMissionsHashTable[composantMissionKey] = {
                            composantId: demande.composantId,
                            composantLabel: demande.composantLabel,
                            missionId: demande.missionId,
                            missionLabel: demande.missionLabel,
                            dejaAffecte: false,
                            demandeId: demande.id,
                            demandeType: "a",
                            affichage: "v",
                        };
                    } else if (demande.type === 'remove') {
                        composantsMissionsHashTable[composantMissionKey] = {
                            composantId: demande.composantId,
                            composantLabel: demande.composantLabel,
                            missionId: demande.missionId,
                            missionLabel: demande.missionLabel,
                            dejaAffecte: false,
                            demandeId: demande.id,
                            demandeType: "r",
                            affichage: "r",
                        };
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
        if (selectedMissionId === '' || ! $listeComposants.find('input').is(':checked')) {
            $modal.find('.modal-body p').html("La demande d'ajout d'un composant nécessite la sélection d'un composant ainsi que d'une mission.");
            $modal.modal('show');
            return false;
        }
        let allowContinue = true;
        let $composants = $listeComposants.find("input:checked");
        $composants.each(function() {
            let composantMissionKey = $(this).val() + '-' + selectedMissionId;
            if (composantsMissionsHashTable.hasOwnProperty(composantMissionKey)) {
                let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
                if (composantMissionValue.affichage === 'v' || composantMissionValue.affichage === 'n') {
                    $modal.find('.modal-body p').html('Le composant "' + composantMissionValue.composantLabel + '" associé à la mission "'
                        + composantMissionValue.missionLabel + '" est déjà affecté à ce service.');
                    $modal.modal('show');
                    allowContinue = false;
                }
            }
        });
        // on ajoute les éléments au tableau
        if (allowContinue) {
            $composants.each(function() {
                let composantId = $(this).val();
                let composantMissionKey = composantId + '-' + selectedMissionId;
                if (composantsMissionsHashTable.hasOwnProperty(composantMissionKey)) {
                    let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
                    if (composantMissionValue.dejaAffecte && composantMissionValue.demandeId === null) {
                        composantMissionValue.demandeType = null;
                        composantMissionValue.affichage = 'n';
                    } else if (composantMissionValue.demandeId === null || composantMissionValue.demandeType === 'r') {
                        composantMissionValue.demandeType = null;
                        composantMissionValue.affichage = 'n';
                    } else {
                        composantMissionValue.demandeType = 'a';
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
                        affichage: 'v',
                    };
                }
            });
        }
        // Rafraichissement de l'affichage
        affichage_tableau_perimetre();
        $checkboxTousComposants.prop("checked", false);
        $composants.prop("checked", false);
        $selectionMission.val("");
    });

    /**
     * Retirer des éléments au tableau du périmètre applicatif du service
     */
    $("#btn-retirer").on('click', function() {
        if (!$perimetreApplicatifService.find('input').is(':checked')) {
            var $modal = $('#erreurSaisieModal');
            $modal.find('.modal-body p').html('Merci de sélectionner un composant à retirer.');
            $modal.modal('show');
            return false;
        }
        $perimetreApplicatifService.find('input:checked').each(function() {
            let composantMissionKey = $(this).val();
            let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
            if (composantMissionValue.demandeType === 'a') {
                if (composantMissionValue.demandeId !== null) {
                    // Si une demande d'ajout a déjà été enregistrée, on ne l'affiche plus, mais on la conserve pour la supprimer
                    composantMissionValue.demandeType = null;
                    composantMissionValue.affichage = null;
                } else {
                    // Sinon on retire l'entrée de la hashtable
                    delete composantsMissionsHashTable[composantMissionKey];
                }
            } else {
                composantMissionValue.demandeType = 'r';
                composantMissionValue.affichage = 'r';
            }

        });
        affichage_tableau_perimetre();
    });

    /**
     * Enregistrer les modifications effectuées
     */
    $boutonEnvoyer.on('click', function() {
        maj_demandes = [];
        for (let composantMissionKey in composantsMissionsHashTable) {
            let composantMissionValue = composantsMissionsHashTable[composantMissionKey];
            if (
                (composantMissionValue.demandeId == null && composantMissionValue.demandeType != null)          // nouvelle demande
                    || (composantMissionValue.demandeId != null && composantMissionValue.demandeType == null)   // demande enregistrée à annuler
            ) {
                 maj_demandes.push({
                    composantId: composantMissionValue.composantId,
                    missionId: composantMissionValue.missionId,
                    demandeType: composantMissionValue.demandeType,
                    demandeId: composantMissionValue.demandeId,
                });
            }
        }
        if (maj_demandes.length === 0) {
            var $modal = $('#erreurSaisieModal');
            $modal.find('.modal-body p').html("Vous n'avez effectué aucune modification.");
            $modal.modal('show');
            return;
        }
        $boutonEnvoyer.attr('disabled', true);

        // appel serveur pour mettre à jour les demandes de modification
        $.ajax({
            url: "/ajax/fiabilisation/applicatif/demandes/service/maj",
            method: 'POST',
            dataType: 'json',
            data: {
                service: service,
                demandes: maj_demandes
            },
            success: function() {
                location.reload();
            },
            error: function() {
                alert('Impossible de soumettre les informations au serveur.');
            },
            complete: function() {
                $boutonEnvoyer.removeAttr('disabled');
            }
        });
    });

    /**
     * Chargement initial des tableaux
     */
    ajax_liste_composants();
    ajax_perimetre_applicatif(service);
});
