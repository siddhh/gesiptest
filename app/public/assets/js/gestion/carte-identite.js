$(document).ready(function () {

    /**
     * Initialisations
     */
    let $modalHistorique = $('#modalHistoriqueCarteIdentite');
    let $modalHistoriqueTbody = $modalHistorique.find('tbody');
    let $modalTransmission = $('#modalTransmissionCarteIdentite');
    let services = ['Service Manager', 'Switch', 'Sinaps'];
    let pasDeTransmission = null;
    let $choixComposant = $('#visualiser_composant');

    /**
     * Si on sélectionne un nouveau composant à visualiser
     */
    $choixComposant.on('change', function() {
        window.location = $choixComposant.val();
    });

    /**
     * Supprime la carte d'identité
     */
    $('button[name="carte_identite_supprimer"]').on('click', function(event) {
        return confirm('Voulez-vous vraiment supprimer cette version de la carte d\'identité ?');
    });

    /**
     * Action sur le bouton "Historique"
     */
    $('.carte-identite-edition .affiche-historique').on('click', function() {
        let carteIdentiteId = $(this).parents('.carte-identite-courante').data('carte-identite-id');
        window.bigLoadingDisplay(true);
        $.ajax({
            url: '/ajax/carte-identite/historique/' + carteIdentiteId,
            method: 'GET',
        })
        .done(function(reponse) {
            $modalHistorique.find(".modal-title span").text(reponse.composant);
            $modalHistoriqueTbody.empty();
            if (reponse.historique.length == 0) {
                $modalHistoriqueTbody.html('<td colspan="4"><p class="pt-3 text-center">historique vide pour ce composant.</p></td>');
            } else {
                reponse.historique.forEach(function(evenement) {
                    $modalHistoriqueTbody.append('<tr><td>' + evenement.horodatage.substr(0, 10) + ' ' + evenement.horodatage.substr(10)
                        + '</td><td>' + evenement.service
                        + '</td><td style="text-align: left;">' + evenement.libelle + '</td>'
                        + '</td><td style="text-align: left;">' + (evenement.commentaire ? evenement.commentaire : '') + '</td></tr>'
                    );
                });
            }
            $modalHistorique.modal('show');
        })
        .fail(function() {
            alert('Impossible de récupérer l\'historique pour le moment.\nVeuillez réessayer plus tard.');
        })
        .always(function() {
            window.bigLoadingDisplay(false);
        });
    });


    /**
     * Si la fenetre modale de transmission est affichée, on récupère l'identifiant de carte d'identité
     */
    $('button.transmission-demarrer').on('click', function(){
        $modalTransmission.data('carte-identite-id', $(this).parents('tr').data('carte-identite-id'));
        $modalTransmission.data('carte-identite-composant-label', $(this).parents('tr').find('td:nth-child(2)').text());
    });

    /**
     * Fonction d'envoi au serveur de la demande de transmission
     */
    function transmettre() {
        let carteIdentiteId = $modalTransmission.data('carte-identite-id');
        window.bigLoadingDisplay(true);
        let transmettreA = [];
        services.forEach(function(service) {
            if (pasDeTransmission.indexOf(service) == -1) {
                transmettreA.push(service);
            }
        });
        $.ajax({
            url: '/ajax/carte-identite/transmission/' + carteIdentiteId,
            method: 'POST',
            dataType: 'json',
            data: {
                destinataires: transmettreA
            }
        })
        .done(function(reponse) {
            if (reponse['statut'] == 'ok') {
                window.location.reload();
            } else {
                window.afficherToast('ATTENTION : le traitement de votre demande a échoué ' + (reponse['message'] == null ? '' : '(' + reponse['message'] + ') ') + '!!!', 'danger');
                window.bigLoadingDisplay(false);
            }
        })
        .fail(function() {
            alert('Impossible de traiter votre demande pour le moment.\nVeuillez réessayer plus tard.');
            window.bigLoadingDisplay(false);
        });

        return;
    }

    /**
     * Action sur le bouton "Valider" de la modale
     */
    $("#modalTransmissionCarteIdentiteValider").on("click", function() {
        pasDeTransmission = [];
        if ($("#modalTransmissionCarteIdentiteCboxSma").is(":not(:checked)")) {
            pasDeTransmission.push("Service Manager");
        }
        if ($("#modalTransmissionCarteIdentiteCboxSwitch").is(":not(:checked)")) {
            pasDeTransmission.push("Switch");
        }
        if ($("#modalTransmissionCarteIdentiteCboxSinaps").is(":not(:checked)")) {
            pasDeTransmission.push("Sinaps");
        }

        if (pasDeTransmission.length >= 3) {
            // Si aucun service selectionné, on ferme simplement la fenêtre sans rien faire
            $modalTransmission.modal("hide");
        } else if (pasDeTransmission.length == 0) {
            // Si tous les services sélectionnés, on démarre la transmission directement
            transmettre();
            $modalTransmission.modal("hide");
        } else {
            // Sinon on affiche la fenêtre d'avertissement de transmission partielle
            let unSeul = (pasDeTransmission.length == 1);
            $modalTransmission.modal("hide");
            let modalBody = '<p>Le' + (unSeul ? '' : 's') + ' service' + (unSeul ? '' : 's') + '</p>';
            modalBody += '<ul>';
            pasDeTransmission.forEach(function(service) {
                modalBody += '<li>' + service + '</li>';
            });
            modalBody += '</ul>';
            modalBody += '<p>ne ser' + (unSeul ? 'a' : 'ont') + ' pas informé' + (unSeul ? '' : 's') + ' des modifications effectuées sur cette carte d’identité.</p><p>Voulez-vous néanmoins valider votre action ?</p>';
            $("#modalTransmissionPartielleCarteIdentite .modal-body").html(modalBody);
            $("#modalTransmissionPartielleCarteIdentite").modal("show");
        }
    });

    /**
     * Action sur le bouton "Oui" de la modal de confirmation de transmission partielle
     */
    $("#modalTransmissionPartielleCarteIdentiteValider").on("click", function() {
        if (pasDeTransmission.length < 3) {
            transmettre();
        }
        $("#modalTransmissionPartielleCarteIdentite").modal("hide");
    });

    // Retourne un objet / une structure contenant divers informations concernant le fichier fourni en paramètre
    function getFileInfos(fichier) {
        let ret = {
            'fileName': fichier.name,   // Nom complet du fichier (extension comprise)
            'name': '',                 // Nom sans l'extension
            'extension': '',            // Extension du fichier
            'taille': fichier.size,     // Taille du fichier en octets
            'mimeType': fichier.type    // Mime type du fichier
        };
        let dotIndex = fichier.name.lastIndexOf('.');
        if (dotIndex > -1) {
            ret.name = fichier.name.substring(0, dotIndex);
            ret.extension = fichier.name.substring(dotIndex);
        } else {
            ret.name = fichier.name;
        }
        return ret;
    }

    // Initialisation de quelques variables
    let $modaleAjoutModificationCarteIdentite = $("#modaleAjoutModificationCarteIdentite");
    let $carteIdentiteComposant = $('#carteIdentiteComposant');
    let $carteIdentiteFichier = $('#carteIdentiteFichier');
    let $inputAction = $modaleAjoutModificationCarteIdentite.find('#carte_identite_action');
    let $nouveauComposantLabel = $('#carte_identite_composantLabel');

    /**
     * Customize la modale en fonction de si on est en mode ajout ou modification
     */
    function switchAjoutMiseajour(action) {
        let $composantAjout = $modaleAjoutModificationCarteIdentite.find('.ajout');
        let $composantModification = $modaleAjoutModificationCarteIdentite.find('.modification');
        let $modalContent = $modaleAjoutModificationCarteIdentite.find('.modal-content');
        let $modalChampComposant = $modaleAjoutModificationCarteIdentite.find('.champ-composant');
        let $titreModale = $('.titre');
        if (!$modaleAjoutModificationCarteIdentite.hasClass('on-error')) {
            $modaleAjoutModificationCarteIdentite.find('form').trigger('reset');
        }
        if (action === 'ajout') {
            $composantAjout.show();
            $composantModification.hide();
            $modalChampComposant.append($composantAjout);
            $titreModale.text('Création d’une nouvelle carte d\'identité');
            $modaleAjoutModificationCarteIdentite.find('.telecharger.derniere-carte-identite').hide();
            $modaleAjoutModificationCarteIdentite.find('.telecharger.modele-carte-identite').show();
        } else if ($carteIdentiteComposant.data('admin') == 'oui') {
            // l'administrateur peut modifier le composant courant
            $composantAjout.show();
            $composantModification.hide();
            $modalChampComposant.append($composantAjout);
            $nouveauComposantLabel.val($(".carte-identite-courante").data('composant-label'));
            $titreModale.text('Modifier la carte d\'identité');
            $modaleAjoutModificationCarteIdentite.find('.telecharger.derniere-carte-identite').show();
            $modaleAjoutModificationCarteIdentite.find('.telecharger.modele-carte-identite').hide();
        } else {
            $composantAjout.hide();
            $composantModification.show();
            $modalContent.before($composantAjout);
            $titreModale.text('Modifier la carte d\'identité');
            $modaleAjoutModificationCarteIdentite.find('.telecharger.derniere-carte-identite').show();
            $modaleAjoutModificationCarteIdentite.find('.telecharger.modele-carte-identite').hide();
        }
        $inputAction.val(action);
    }

    // Si on clique sur le bouton modifier, on customize la modale pour la modification
    $('.modifier-carte-identite').on('click', function() {
        switchAjoutMiseajour('modification');
    });
    // Si on clique sur le bouton ajouter, on customize la modale pour l'ajout
    $('.ajouter-carte-identite').on('click', function() {
        switchAjoutMiseajour('ajout');
    });

    // Affiche la modale d'ajout / modification si des erreurs ont été détectées pendant la validation
    if ($modaleAjoutModificationCarteIdentite.hasClass('on-error')) {
        switchAjoutMiseajour($('#carte_identite_action').val());
        $modaleAjoutModificationCarteIdentite.modal('show');
    }

    /**
     * Gestion du champ permettant de récupérer les composants courants mais aussi de pouvoir en créer d'autres
     */

    // Initialisation
    let $composantSearchControl = $('.composant-custom-selection');
    let $composantTextSearch = $composantSearchControl.find('input');
    let $loadingSpinner = $composantSearchControl.find('.loader');
    let $tableResults = $composantSearchControl.find('table');
    let $composantGesip = $('#carte_identite_composant');
    let $composantInformation = $composantSearchControl.find('.information');
    let ajaxRechercheComposant = null;
    let carteComposant = null;

    // Événement lors de la saisie dans les champs Libellé et Code Carto
    $composantTextSearch.on('keypress', function(e) {

        // On récupère l'input et le début et fin de selection
        let $input = $(this);
        let selStart = $input.prop('selectionStart');
        let selEnd = $input.prop('selectionEnd');
        let char = null;

        // transformer une minuscule en majuscule
        if ((e.charCode > 96) && (e.charCode < 123)) {
            char = String.fromCharCode(e.charCode).toUpperCase();
        }
        // transformer un espace en underscore
        if (e.charCode === 32) {
            char = '_';
        }
        // accepter les chiffres
        if ((e.charCode >= 48) && (e.charCode <= 57)) {
            char = String.fromCharCode(e.charCode);
        }
        // accepter les majuscules et les caractères -_()[]\/
        if (((e.charCode > 64) && (e.charCode < 91)) || (e.charCode === 45) || (e.charCode === 95) || (e.charCode === 40) || (e.charCode === 41) || (e.charCode === 91) || (e.charCode === 93) || (e.charCode === 92) || (e.charCode === 47)) {
            char = String.fromCharCode(e.charCode);
        }

        // on ajoute notre caractère au champ
        if (char !== null) {
            // On récupère la saisie avant et après le curseur
            let debut = $input.val().slice(0, selStart);
            let fin = $input.val().slice(selEnd);
            // On modifie le champ pour concaténer le début, le caractère saisie et la fin
            $input.val(debut + char + fin);
            // On repositionne notre curseur
            $input.prop('selectionStart', (selStart + 1));
            $input.prop('selectionEnd', (selStart + 1));
        }

        // refuser les autres caractères
        $input.trigger('change');
        return false;
    });

    // Si l'utilisateur change la valeur du champ de recherche
    let timeoutQuery = null;
    $composantTextSearch.on('keyup', function() {
        if (timeoutQuery) {
            clearTimeout(timeoutQuery);
        }

        // Déselectionne la le composant gesip si il était selectionné
        $composantSearchControl.removeClass('composant-gesip-selectionne');
        $composantGesip.val('');
        $composantInformation.text('Carte identité sur un nouveau composant.');
        // Supprime les messages d'erreur
        $carteIdentiteComposant.find('.alert').remove();
        $carteIdentiteComposant.find('.form-errors').empty();
        // Cache la fenêtre des résultats
        $tableResults.hide();
        // Effectue la recherche si nombre de caractères mini atteint
        let labelSearch = $(this).val();
        if (labelSearch.length > 0) {
            $loadingSpinner.show();
            if (ajaxRechercheComposant != null) {
                ajaxRechercheComposant.abort();
            }
            timeoutQuery = setTimeout(function() {
                ajaxRechercheComposant = $.ajax({
                    url: '/ajax/carte-identite/recherche/label',
                    dataType: 'json',
                    data: {
                        label: labelSearch
                    }
                }).done(function(data) {
                    let $tbodyResults = $tableResults.find('tbody').empty();
                    let action = $inputAction.val();
                    $(data).each(function(){
                        if ((action == 'modification') || (this.carte == 'non')) {
                            let $tdResult = $('<td></td>').text(this.label);
                            let $trResult = $('<tr></tr>').data('composant-id', this.id).append($tdResult);
                            $tbodyResults.append($trResult);
                        }
                    });
                    if (data.length > 0) {
                        $tableResults.show();
                    }
                }).fail(function(erreur) {
                    if(erreur.status != 0) {
                        alert("Impossible de récupérer la liste des composants.");
                    }
                }).always(function() {
                    $loadingSpinner.hide();
                });
            }, 500);
        } else {
            $composantInformation.empty();
        }
    });

    // Si l'utilisateur sélectionne un composant on valide sa sélection
    $tableResults.find('tbody').on('mousedown', 'tr', function() {
        let composantLabel = $(this).find('td').text();
        let composantId = $(this).data('composant-id');
        $composantSearchControl.addClass('composant-gesip-selectionne');
        $composantTextSearch.val(composantLabel);
        $composantGesip.val(composantId);
        $composantInformation.text('Carte identité d\'un composant Gesip existant.');
        $tableResults.hide();
    });

    // Si l'utilisateur quitte le controle de recherche, on abandonne la requète et on cache tout !
    $composantSearchControl.on('focusout', function() {
        if (ajaxRechercheComposant != null) {
            ajaxRechercheComposant.abort();
            ajaxRechercheComposant = null;
        }
        $tableResults.hide();
        if ($composantGesip.val() == '') {
            // si l'utilisateur n'a pas sélectionné de composant, on vérifie si le label saisi correspond à un composant référencé ou non
            carteComposant = null;
            $composantGesip.children('option').each(function() {
                if ($composantTextSearch.val() == $(this).text()) {
                    carteComposant = $(this).data('carte');
                    $composantGesip.val($(this).val());
                    $composantInformation.text('Carte identité d\'un composant Gesip existant.');
                    return false;
                }
            });
        }
    });

    /*
     * Vérifie le formulaire avant d'envoyer
     */
    $modaleAjoutModificationCarteIdentite.find('form').on('submit', function(e) {
        // On initialise notre script
        let message = null;
        let erreursTrouvees = false;

        // Vérification du champ composant
        if ($inputAction.val() === 'ajout') {
            if ($composantTextSearch.val().length <= 0) {
                message = 'Le libellé d\'un composant ne peut être vide.';
                $carteIdentiteComposant.find('.form-errors').html($('<div></div>').text(message));
                erreursTrouvees = true;
            } else if (carteComposant === 'oui') {
                message = "Une carte d'identité existe déjà pour ce composant";
                $carteIdentiteComposant.find('.form-errors').html($('<div></div>').text(message));
                erreursTrouvees = true;
            }
        }

        // Vérification upload fichier
        let acceptAttribute = $carteIdentiteFichier.find('input[type="file"]').attr('accept');
        let mimeTypesString = $carteIdentiteFichier.find('input[type="file"]').data('mime-types');
        let mimeTypesAutorises = mimeTypesString.split(',');
        let tailleMaximumAutorisee = $carteIdentiteFichier.find('input[type="file"]').data('taille-maximum-autorisee');
        let maxFileSize = tailleMaximumAutorisee * Math.pow(1024, 2);
        let files = $('input[type="file"]')[0].files;
        let fileInfos = getFileInfos(files[0]);
        $carteIdentiteFichier.find('input[type="file"]').parent().find(".alert-danger").remove();
        if (fileInfos.taille > maxFileSize) {
            // teste si la taille du fichier est trop importante
            let message = 'Le fichier "' + fileInfos.fileName + '" ne peut pas être ajouté car il dépasse la taille maximum autorisée de ' + maxFileSize + ' Mo.';
            $carteIdentiteFichier.find('input[type="file"]').parent().append($('<div></div>').addClass('alert alert-danger').text(message));
            erreursTrouvees = true;
        } else if (mimeTypesAutorises.indexOf(fileInfos.mimeType) < 0) {
            // teste si le mime type du fichier est autorisé
            let message = 'Le fichier "' + fileInfos.fileName + '" ne peut pas être ajouté car son type "' + fileInfos.mimeType + '" ne correspond pas aux types de fichier autorisés (' + acceptAttribute.split(',').join(', ') + ').';
            $carteIdentiteFichier.find('input[type="file"]').parent().append($('<div></div>').addClass('alert alert-danger').text(message));
            erreursTrouvees = true;
        }
        if (erreursTrouvees) {
            return false;
        }
        window.bigLoadingDisplay(true);
    });
});
