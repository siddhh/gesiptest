$(document).ready(function() {

    var modificationEnCours = $('.page-label').hasClass('composant-modification');

    /**
    *initialisation de jQuery Smart Wizard
    **/
    var wizard = $('#smartwizard').smartWizard({
        theme: 'arrows',
        keyboardSettings: {
            keyNavigation: false,
        },
        toolbarSettings: {
            showNextButton: false,
            showPreviousButton: false,
        },
        enableURLhash: false,
    });

    // Récupère les données précédemment entrées pour faire la synthèse
    etape1Formattage = function() {
        $([
            'label',
            'codeCarto',
            'description'
        ]).each(function(){
            let fieldName = this;
            let fieldValue = $('#etape-1 .' + fieldName).val();
            if (fieldName.toString() === 'description') {
                fieldValue = fieldValue.replace(/&/g, '&amp;')
                    .replace(/>/g, '&gt;').replace(/</g, '&lt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&apos;');
                $('#etape-5 .' + fieldName).html(fieldValue.replaceAll("\n", "<br/>"));
            } else {
                $('#etape-5 .' + fieldName).text(fieldValue);
            }
        });
        $([
            'usager',
            'domaine',
            'equipe',
            'pilote',
            'piloteSuppleant',
            'typeElement',
            'estSiteHebergement',
            'bureauRattachement',
            'exploitant',
            'meteoActive'
        ]).each(function(){
            let fieldName = this;
            $('#etape-5 .' + fieldName).text($('#etape-1 .' + fieldName + ' option:selected').text());
        });
        $('#etape-5 .meteo tbody').empty();
        $('#etape-1 .table-data-reference tbody tr').each(function(){
            let trHoraires = $('<tr></tr>');
            $(this).find('td').each(function(index){
                let tdValue;
                let valeurInitiale = $(this).attr('data-valeur-initiale');
                if (index < 1) {
                    tdValue = $(this).find('option:selected').text();
                    valeur = $(this).find('option:selected').attr('value');
                } else if (index < 3) {
                    tdValue = $(this).find('input').val();
                    valeur = tdValue;
                } else {
                    return false;
                }
                if (tdValue != '') {
                    if (valeur != valeurInitiale ) {
                        trHoraires.append($('<td class = modif></td>').text(tdValue));
                    } else {
                        trHoraires.append($('<td></td>').text(tdValue));
                    }
                } else {
                    return false;
                }
            });
            $('#etape-5 .meteo tbody').append(trHoraires);
        });
    };

    // Récupère l'annuaire lorsque l'étape 2 est quittée (met à jour l'étape 5 et le formulaire)
    etape2Formattage = function() {
        $('#etape-5 .annuaire tbody').empty();
        $('#etape-2 #tableau-resultats tbody tr:not(.deleted, .need-deletion)').each(function(){
            let trAnnuaire = $('<tr></tr>');
            $(this).find('td').each(function(index){
                let tdValue;
                let valeurInitiale = $(this).attr('data-valeur-initiale');
                if (index < 2) {
                    tdValue = $(this).find('option:selected').text();
                    valeur = $(this).find('option:selected').val();
                } else if (index === 2) {
                    tdValue = $(this).find('input').val();
                    valeur = tdValue;
                } else {
                    return false;
                }
                if (tdValue != '') {
                    if (valeur != valeurInitiale ) {
                        trAnnuaire.append($('<td></td>').addClass('modif').text(tdValue));
                    } else {
                        trAnnuaire.append($('<td></td>').text(tdValue));
                    }
                } else {
                    return false;
                }
            });
            $('#etape-5 .annuaire tbody').append(trAnnuaire);
        });
    };
    etape3Formattage = function() {
        $('#etape-5 .flux-entrants').empty();
        $('#etape-3 .sel-composants li.visible label').each(function(){
            valeurInitiale = $(this).parent().attr('data-valeur-initiale');
            if (valeurInitiale == 1) {
                liFlux = $('<li></li>').text($(this).text());
            } else {
                liFlux = $('<li></li>').addClass('modif').text($(this).text());
            }
            $('#etape-5 .flux-entrants').append(liFlux);
        });
    };
    etape4Formattage = function() {
        $('#etape-5 .flux-sortants').empty();
        $('#etape-4 .sel-composants li.visible label').each(function(){
            valeurInitiale = $(this).parent().attr('data-valeur-initiale');
            if (valeurInitiale == 1) {
                liFlux = $('<li></li>').text($(this).text());
            } else {
                liFlux = $('<li></li>').addClass('modif').text($(this).text());
            }
            $('#etape-5 .flux-sortants').append(liFlux);
        });
    };
    // Réintégration de la météo
    function updatePlagesUtilisateur(templateEditingItem)
    {
        $('#composant_plagesUtilisateur>div>div').each(function(i) {
            let iterationName = $(this).attr('id');
            let trPlagesUtilisateur = $(templateEditingItem).clone();
            let day = $(this).find('div:nth-child(1) input').val();
            let beginHour = $(this).find('div:nth-child(2) select:nth-child(1) option:selected').val().padStart(2, '0')
                + ':' + $(this).find('div:nth-child(2) select:nth-child(2) option:selected').val().padStart(2, '0');
            let endHour = $(this).find('div:nth-child(3) select:nth-child(1) option:selected').val().padStart(2, '0')
                + ':' + $(this).find('div:nth-child(3) select:nth-child(2) option:selected').val().padStart(2, '0');
            trPlagesUtilisateur.find('.jour option[value="' + day + '"]').prop('selected', true).parents('td').attr('data-valeur-initiale',day);
            trPlagesUtilisateur.find('.heure-debut').val(beginHour).parents('td').attr('data-valeur-initiale',beginHour);
            trPlagesUtilisateur.find('.heure-fin').val(endHour).parents('td').attr('data-valeur-initiale',endHour);
            $('#plages-utilisateur').append(trPlagesUtilisateur);
        });
        $('#etape-1 .btn-validate').trigger('click');
        $('.timepicker').datetimepicker({
            locale: 'fr',
            format: 'LT',
            useCurrent: false,
        });
    }

    // Met à jour les résumés (encart en haut) des autres étapes à partir de l'étape 1
    function majResumeEtape1() {
        let composantLabel = $('#etape-1 #composant_label').val();
        $('.abstract .composant-label').text(composantLabel);
        $('.abstract .composant-exploitant').text($('#etape-1 #composant_exploitant option:selected').text());
        $('.abstract .composant-equipe').text($('#etape-1 #composant_equipe option:selected').text());
        $('.abstract .composant-pilote').text($('#etape-1 #composant_pilote option:selected').text());
        // Met à jour le nom du composant dans les flux-sortants
        $('.flux-composant-selection li.composant-courant').each(function() {
            $(this).find('span').text(' ' + composantLabel);
        });
    };

    // Teste la validité de l'annuaire
    function remplissageAnnuaire(){
        let rowCompletedCount = 0;
        $('#etape-2 tbody tr').each(function(i){
            let colCompletedCount = 0;
            $(this).find('td select[name="missionId"], td select[name="serviceId"], td input[name="label"]').each(function(){
                if ($(this).val() != '') {
                    colCompletedCount++;
                } else {
                    return false;
                }
            });
            if (colCompletedCount >= 3) {
                rowCompletedCount++;
            } else {
                $(this).addClass('error');
            }
        });
        if (rowCompletedCount < $('#etape-2 tbody tr').length) {
            alert("Certaines lignes dans le tableau sont incomplètes.");
            return false;
        }
        return true;
    };

    $('.composant-modification .sw ul.nav a').addClass('done');


    // A chaque fois qu'on change d'étape, on controle les champs mis à jour
    $("#smartwizard").on('leaveStep', function(e, anchorObject, currentStepIndex,nextStepIndex) {
        switch(currentStepIndex) {
            case 0:
                majResumeEtape1();
                etape1Formattage();
                etape2Formattage();
                etape3Formattage();
                etape4Formattage();
                return controlePlageUtilisateur();
            case 1:
                etape2Formattage();
                return remplissageAnnuaire();
            case 2:
                etape3Formattage();
            case 3:
                etape4Formattage();
        }
    });

    /**
     * Récupération des élements à partir des données contenues dans le formulaire (cas d'erreur lors de la création ou de la modification)
     */
    // Réintégration des flux entrants / sortants (appelé après la récupération de la liste des composants)
    function updateSelectedComposantsFromForm()
    {
        $('#composant_impactesParComposants option:selected').each(function() {
            $('#etape-3 .sel-composants input[value="' + $(this).val() + '"]').parents('li').addClass('visible').attr('data-valeur-initiale','1');
        });
        $('#composant_composantsImpactes option:selected').each(function() {
            $('#etape-4 .sel-composants input[value="' + $(this).val() + '"]').parents('li').addClass('visible').attr('data-valeur-initiale','1');
        });
    }

    updateSelectedComposantsFromForm();


    // Réintégration de l'annuaire à partir des données de formulaire cachées
    $('#composant_annuaire>div>div').each(function(i) {
        let iterationName = $(this).attr('id');
        let trAnnuaire = $('#etape-2 tr.ligne-saisie:last');
        if ($('#etape-2 tr.ligne-saisie').length <= i) {
            trAnnuaire = trAnnuaire.clone();
            $('#etape-2 table tbody').append(trAnnuaire);
        }
        let missionId = $(this).find('#' + iterationName + '_mission option:selected').val();
        let serviceId = $(this).find('#' + iterationName + '_service option:selected').val();
        let balf = $(this).find('#' + iterationName + '_balf').val();
        trAnnuaire.find('td:nth-child(1) select').val(missionId);
        trAnnuaire.find('td:nth-child(1) select').parent().attr('data-valeur-initiale',missionId);
        trAnnuaire.find('td:nth-child(2) select').val(serviceId);
        trAnnuaire.find('td:nth-child(2) select').parent().attr('data-valeur-initiale',serviceId);
        trAnnuaire.find('td:nth-child(3) input').val(balf);
        trAnnuaire.find('td:nth-child(3) input').parent().attr('data-valeur-initiale',balf);
    });


    /**
     * Etape 1 (données générales)
     */

    // initialisations
    $("#etape-1 #composant_label").focus();
    if ($('#smartwizard.isSubmitted').length == 0) {
        $("#composant_usager").prepend('<option value="" selected></option>');
        $("#composant_typeElement").prepend('<option value="" selected></option>');
    }
    var $templateEditingItem = null;
    $templateEditingItem = $('<tr class="item-editing">').append($('.template-editing-item').html());
    $('.template-editing-item').remove();
    var plagesHoraires = [];

    // Événement lors de la saisie dans les champs Libellé et Code Carto
    $("#etape-1 #composant_label, #etape-1 #composant_codeCarto").on('keypress', function(e) {

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

    // Événement lors d'un clic sur le bouton d'ajout d'une plage utilisateur
    $('#etape-1 .btn-add').on('click', function(e) {
        e.preventDefault();
        var $newEntry = $($templateEditingItem[0].outerHTML);
        $("#plages-utilisateur").append($newEntry);
        $('.timepicker').datetimepicker({
            locale: 'fr',
            format: 'LT',
            useCurrent: false,
        });
    });

    // Évènement lors d'un clic sur le bouton de suppression d'une plage utilisateur
    $("#etape-1 #plages-utilisateur").on('click', '.btn-cancel', function(event) {
        event.preventDefault();
        if (confirm('Confirmez-vous la suppression de cette plage ?')) {
            $(this).parents('tr').remove();
        }
    });

    // Événement lors d'un clic sur le bouton d'annulation de saisie des plages utilisateur
    $('#etape-1 .btn-delete').on('click', function(e) {
        e.preventDefault();
        if (confirm('Confirmez-vous la suppression de toutes les plages ?') === true) {
            $("#plages-utilisateur").html("");
            plagesHoraires = [];
        }
    });
    function controlePlageUtilisateur() {
        plagesHoraires = [];
        var message = "";
        var erreur = false;
        $("#plages-utilisateur").find("tr").each(function(indice) {
            var jour = $(this).find("select").val();
            var debut = $(this).find("input:first").val();
            var fin = $(this).find("input:last").val();
            if ((jour === "") || (debut === "") || (fin === "")) {
                message = 'La plage ' + (indice +1) + ' est incomplète.';
                erreur = true;
                return false;
            }
            if (jour < 1 || jour > 7) {
                message = 'Plage ' + (indice +1) + ' erronée : jour incorrect.';
                erreur = true;
                return false;
            }
            if (/(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]/.test(debut) === false) {
                message = 'Plage ' + (indice +1) + ' erronée : heure de début incorrecte.';
                erreur = true;
                return false;
            }
            if (/(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]/.test(fin) === false) {
                message = 'Plage ' + (indice +1) + ' erronée : heure de fin incorrecte.';
                erreur = true;
                return false;
            }
            var debutHeure = parseInt(debut.substr(0, 2));
            var debutMinute = parseInt(debut.substr(3, 2));
            var finHeure = parseInt(fin.substr(0, 2));
            var finMinute = parseInt(fin.substr(3, 2));

            if (finHeure === 0 && finMinute === 0) {
                finHeure = 24;
            }

            if ((debutHeure > finHeure) || ((debutHeure === finHeure) && (debutMinute >= finMinute))) {
                message = 'Plage ' + (indice +1) + ' erronée : l\'heure de début doit être supérieure à l\'heure de fin.';
                erreur = true;
                return false;
            }
            for (var indicePlage = 0; indicePlage < plagesHoraires.length; indicePlage++) {
                if ((jour === plagesHoraires[indicePlage]['jour']) && (((debutHeure * 60) + debutMinute) < ((plagesHoraires[indicePlage]['heure_de_fin'] * 60) + plagesHoraires[indicePlage]['minute_de_fin']))
                && (((finHeure * 60) + finMinute) > ((plagesHoraires[indicePlage]['heure_de_debut'] * 60) + plagesHoraires[indicePlage]['minute_de_debut']))) {
                    message = 'Les plages ' + (indicePlage + 1) + ' et ' + (indice + 1) + ' se chevauchent.';
                    erreur = true;
                return false;
                }
            }
            plagesHoraires.push({
                jour: jour,
                heure_de_debut : debutHeure,
                minute_de_debut : debutMinute,
                heure_de_fin : finHeure,
                minute_de_fin : finMinute,
            })
        });
        if (erreur === true) {
            plagesHoraires = [];
            var $modal = $('#erreurSaisieModal');
            $modal.find('.modal-body p').html(message);
            $modal.modal('show');
            return false;
        }
        else {
            var totalMinute = 0;
            plagesHoraires.forEach(function(plage) {
                totalMinute += ((plage['heure_de_fin'] - plage['heure_de_debut']) * 60) + plage['minute_de_fin'] - plage['minute_de_debut'];
            });
            var totalHeure = Math.floor(totalMinute / 60);
            totalMinute = totalMinute % 60;
            $("#total-plages-horaires").val(totalHeure + " heure" + (totalHeure > 1 ? "s " : " ") + totalMinute + " minute" + (totalMinute > 1 ? "s" : ""));
            return true;
        }
    };

    // Événement lors d'un clic sur le bouton de validation de saisie des plages utilisateur
    $('#etape-1 .btn-validate').on('click', function(e) {
        e.preventDefault();
        controlePlageUtilisateur();
    });
    updatePlagesUtilisateur($templateEditingItem);

    // Evenement lors d'un clic sur le bouton annuler
    $('#etape-1 .clear-step').on('click', function(){
        window.location = $(this).attr('data-url');
    });

    // Événement lors d'un clic sur le bouton 'Suivant'
    $('#etape-1 .next-step').on('click', function(e) {
        e.preventDefault();
        var message = "Les données obligatoires suivantes sont manquantes : ";
        var erreur = false;
        if ($("#composant_label").val() === "") {
            $("#composant_label").addClass("form-control-error");
            erreur = true;
            message += "Libellé";
        }
        else {
            $("#composant_label").removeClass("form-control-error");
        }
        if ($("#composant_intitulePlageUtilisateur").val() === "" && plagesHoraires.length != 0) {
            $("#composant_intitulePlageUtilisateur").addClass("form-control-error");
            if (erreur === false) {
                erreur = true;
            }
            else {
                message += " / ";
            }
            message += "Intitulé Plages Utilisateur";
        }
        else {
            $("#composant_intitulePlageUtilisateur").removeClass("form-control-error");
        }
        if ($("#composant_usager").val() === "") {
            $("#composant_usager").addClass("form-control-error");
            if (erreur === false) {
                erreur = true;
            }
            else {
                message += " / ";
            }
            message += "Usager";
        }
        else {
            $("#composant_usager").removeClass("form-control-error");
        }
        if ($("#composant_meteoActive").val() === "0") {
            $("#composant_meteoActive").addClass("form-control-error");
            if (erreur === false) {
                erreur = true;
            }
            else {
                message += " / ";
            }
            message += "Suivi dans Météo";
        }
        else {
            $("#composant_meteoActive").removeClass("form-control-error");
        }
        if ($("#composant_typeElement").val() === "") {
            $("#composant_typeElement").addClass("form-control-error");
            if (erreur === false) {
                erreur = true;
            }
            else {
                message += " / ";
            }
            message += "Type Element";
        }
        else {
            $("#composant_typeElement").removeClass("form-control-error");
        }
        if (erreur === true) {
            var $modal = $('#erreurSaisieModal');
            $modal.find('.modal-body p').html(message);
            $modal.modal('show');
        } else {
            majResumeEtape1();
            // passe à l'étape suivante
            wizard.smartWizard('next');
        }
    });

    /**
     * Etape 2
     */

    // Ajout d'une nouvelle ligne
    let templateLineStep2 = $('#etape-2 .template-line').remove().removeClass('template-line')[0].outerHTML;
    $('#etape-2 .template-line').remove();
    $('#etape-2 .btn-add').click(function() {
        let $trNewAnnuaireLine = $(templateLineStep2);
        let $selectNewLineService = $trNewAnnuaireLine.find('td:nth-child(2) select');
        $selectNewLineService.addClass('select-picker');
        $selectNewLineService.selectpicker({
            'hideDisabled': true,
            'liveSearch': true,
            'style': '',
            'styleBase': 'form-control'
        });
        $('#etape-2 tbody').append($($trNewAnnuaireLine));

    });

    // Suppression d'une ligne
    $('#etape-2 tbody').on('click', '.btn-delete',function() {
        if (confirm("Confirmez-vous la suppression de la ligne ?")) {
            let trAnnuaire = $(this).parents('tr');
            if (trAnnuaire.attr('data-id')) {
                trAnnuaire.addClass('d-none need-deletion');
            } else {
                trAnnuaire.remove();
            }
        }
    });

    // Suppression de toutes les lignes (réinitialisation)
    $('#etape-2 .clear-step').on("click",function(){
        if (confirm("Confirmez-vous la suppression de toutes les lignes ?")){
            $('#etape-2 tbody tr.ligne-saisie').remove();
            $('#etape-2 tbody tr.need-deletion').removeClass('d-none need-deletion');
            $('#etape-2 tbody td[data-valeur-initiale]').each(function() {
                let $td = $(this);
                let initialValue = $td.attr('data-valeur-initiale');
                $td.find('input, select').val(initialValue);
            });
        }
    });

    // Récupération de la balf service
    $('#etape-2 tbody').on('change', '.serviceSaisi', function() {
        let tr = $(this).parents('tr');
        tr.removeClass('error');
        let balfService = '';
        if ($(this).find(':selected').val()) {
            balfService = $(this).find(':selected').attr('data-balf');
        }
        tr.find('.balfServiceSaisi').val(balfService);
    });

    // définition des évenements sur les boutons étape précedente / suivante
    $('#etape-2 .prev-step, #etape-4 .prev-step').on('click', function(){
        wizard.smartWizard('prev');
    });

    // passage à l étape 3
    $('#etape-2 .next-step').on('click', function(event) {
        event.preventDefault();
        let erreurMessages = [];
        $('#etape-2 .balfServiceSaisi:invalid').each(function() {
            let $tr = $(this).parents('tr');
            let missionLabel = $tr.find('td:nth-child(1) :selected').text();
            let serviceLabel = $tr.find('td:nth-child(2) :selected').text();
            let balf = $(this).val();
            erreurMessages.push('La balf "' + balf + '" associée à la mission "'  + missionLabel + '" et au service "' + serviceLabel + '" est invalide.');
        });
        if (erreurMessages.length > 0) {
            alert(erreurMessages.join("\r\n"));
        } else {
            wizard.smartWizard('next');
        }
    });

    /**
     * Etape 3 et 4 (flux composant)
     */

    // permet de cocher / décocher
    $('#etape-3 .checkAll, #etape-4 .checkAll').on('click', function() {
        let chkAll = $(this);
        let divChkAll = chkAll.parent();
        let inputs = divChkAll.parent().find('ul input');
        if (chkAll.is(':checked')) {
            inputs.each(function(){
                if ($(this).not(':checked')) {
                    $(this).prop('checked', true);
                }
            });
        } else {
            inputs.each(function(){
                if ($(this).is(':checked')) {
                    $(this).prop('checked', false);
                }
            });
        }
    });
    // permet de mettre à jour l'état de la case à cocher globale en fonction de l'état de celles de la liste
    function refreshCheckAll(liste) {
        let allChecked = true;
        let allUnchecked = true;
        liste.find('li.visible input').each(function(){
            if ($(this).is(':checked')) {
                allUnchecked = false;
            } else {
                allChecked = false;
            }
        });
        if (!allChecked) {
            liste.parents('div.info').find('.checkAll').prop('checked', false);
        } else if(!allUnchecked) {
            liste.parents('div.info').find('.checkAll').prop('checked', true);
        }
    }
    $('#etape-3 ul, #etape-4 ul').on('change', 'input', function() {
        let liste = $(this).parents('ul');
        refreshCheckAll(liste);
    });

    // On masque les composants déjà saisies dans les flux entrants comme sortants
    const cacherComposantsDejaSaisies = function() {
        $('ul.sel-composants').each(function() {
            let $preselListe = $(this).parents('.flux-composant-selection').find('ul.presel-composants')
            let $selListe = $(this).find('li.visible');
            $selListe.each(function() {
                let value = $(this).find('input').val();
                $preselListe.find('li input[value="' + value + '"]').parents('li').removeClass('visible');
            });
        });
    };
    cacherComposantsDejaSaisies();

    // permet de déplacer les composants d'une liste à l'autre
    $('#etape-3 .add, #etape-4 .add').on('click', function(){
        let divTabPane = $(this).parents('div.tab-pane');
        let preselListe = divTabPane.find('ul.presel-composants');
        let selListe = divTabPane.find('ul.sel-composants');
        preselListe.find('li.visible input:checked').each(function(){
            let input = $(this);
            selListe.find('li input[value="' + input.val() + '"]').parents('li').addClass('visible');
            input.parents('li').removeClass('visible');
            input.prop('checked', '');
        });
    });
    $('#etape-3 .remove, #etape-4 .remove').on('click', function(){
        let divTabPane = $(this).parents('div.tab-pane');
        let preselListe = divTabPane.find('ul.presel-composants');
        let selListe = divTabPane.find('ul.sel-composants');
        selListe.find('li.visible input:checked').each(function(){
            let input = $(this);
            preselListe.find('li input[value="' + input.val() + '"]').parents('li').addClass('visible');
            input.parents('li').removeClass('visible');
            input.prop('checked', '');
        });
    });

    // définition des évenements sur les boutons étape précedente / suivante
    $('#etape-3 .prev-step').on('click', function(){
        wizard.smartWizard('prev');
    });
    $('#etape-4 .prev-step').on('click', function(){
        wizard.smartWizard('goto', 3);
    });
    $('#etape-3 .next-step').on('click', function(){
        wizard.smartWizard('next');
    });
    $('#etape-4 .next-step').on('click', function(){
        etape1Formattage();
        etape2Formattage();
        etape3Formattage();
        // passe à la dernière étape
        wizard.smartWizard('next');
    });

    // définition de l'évenement réinitialisation de l'étape
    $('#etape-3 .clear-step, #etape-4 .clear-step').on('click', function() {
        let divTabPane = $(this).parents('div.tab-pane');
        var modal = divTabPane.find('.clear-step-modal').modal();
        modal.find('.step-reinit').on('click', function(){
            divTabPane.find('ul.sel-composants li').removeClass('visible');
            divTabPane.find('ul.sel-composants li[data-valeur-initiale="1"]').addClass('visible');
            modal.hide();
        });
        modal.show();
    });

    // ajoute un composant dans la liste de préselection
    function refreshComposants(divTabPane, labelSearch, refreshCache) {
        let preselListe = divTabPane.find('ul.presel-composants');
        let selListe = divTabPane.find('ul.sel-composants');
        $.ajax({
            url: '/ajax/composant/recherche/label',
            dataType: 'json',
            data: {
                label: labelSearch
            },
            success: function(data) {
                if (refreshCache) {
                    preselListe.text($(divTabPane).attr('id'));
                    preselListe.empty();
                    $(data).each(function() {
                        let chk = $('<input />').attr({type: 'checkbox'}).addClass('form-check-input').val(this.id);
                        let label = $('<label></label>').addClass('form-check-label').text(this.label).prepend(chk);
                        let li = $('<li></li>').addClass('form-check visible').append(chk, label)
                        preselListe.append(li);
                        selListe.append(li.clone().removeClass('visible'));
                        updateSelectedComposantsFromForm();
                    });
                } else {
                    preselListe.find('li').each(function(){
                        let liId = $(this).find('input').val();
                        let visible = false;
                        $(data).each(function() {
                            if (this.id == liId) {
                                visible = true;
                                return false;
                            }
                        });
                        if (visible) {
                            $(this).addClass('visible');
                        } else {
                            $(this).removeClass('visible');
                        }
                    });
                    refreshCheckAll(preselListe);
                }
                cacherComposantsDejaSaisies();
            },
            error: function() {
                alert('Impossible de récupérer la liste des composants.');
            }
        });
    }

    //pour savoir si la liste initiale est modifiée
    $('#etape-3 .sel-composants li.visible label').each(function(){
        if ($(this).parents('li').attr('data-valeur-initiale') != "1") {
            $(this).addClass('modif');
        }
    });
    $('#etape-4 .sel-composants li.visible label').each(function(){
        if ($(this).parents('li').attr('data-valeur-initiale') != "1") {
            $(this).addClass('modif');
        }
    });

    // permet de rafraichir la liste des composants disponibles dans la liste de sélection à partir du libellé
    $('#etape-3 .label-search, #etape-4 .label-search').on('input', function() {
        let divTabPane = $(this).parents('div.tab-pane');
        refreshComposants(divTabPane, $(this).val());
    });

    /**
     * Etape 5 SYNTHESE
     */

    // définition des évenements sur les boutons étape précedente / suivante
    $('#etape-5 .prev-step').on('click', function(){
        wizard.smartWizard('prev');
    });

    // Annulation complete
    $('#etape-5 .clear-step').click(function(){
        if (confirm("Confirmez-vous la suppression de toute votre saisie ?")) {
            window.location = "/gestion/composants/";
        }
    });

    // Affichage de la modale en cas d'archivage
    let unarchivingWaintingConfirmation = false;
    $('.unarchiving button').on('click', function(event) {
        if (!unarchivingWaintingConfirmation) {
            event.preventDefault();
            unarchivingWaintingConfirmation = true;
            if (confirm("Souhaitez-vous désarchiver ce composant et le rendre de nouveau actif ?")) {
                $(this).trigger('click');
            } else {
                unarchivingWaintingConfirmation = false;
            }
        }
    });

    // Soumission du formulaire au serveur
    function setFormData()
    {
        // récupère les données de base (étape 1)
        let divPlagesUtilisateurForms = $('#composant_plagesUtilisateur');
        divPlagesUtilisateurForms.find('>div').addClass('need-deletion');
        $('#etape-1 tbody tr.item-editing').each(function(lineNumber) {
            let plageUtilisateurBaseId = '#composant_plagesUtilisateur_' + lineNumber;
            let divPlagesUtilisateurForm = $(plageUtilisateurBaseId);
            if (divPlagesUtilisateurForm.length < 1) {
                divPlagesUtilisateurForm = $(divPlagesUtilisateurForms.attr('data-prototype').replace(/__name__/g, lineNumber));
                divPlagesUtilisateurForms.append(divPlagesUtilisateurForm);
            }
            let trPlagesUtilisateur = $(this);
            let day = parseInt(trPlagesUtilisateur.find('.jour').val());
            let selHeureDebut = trPlagesUtilisateur.find('.heure-debut').val();
            let selHeureFin = trPlagesUtilisateur.find('.heure-fin').val();
            let heureDebut = selHeureDebut.split(':');
            let heureFin = selHeureFin.split(':');
            divPlagesUtilisateurForm.find(plageUtilisateurBaseId + '_jour').val(day);
            divPlagesUtilisateurForm.find(plageUtilisateurBaseId + '_debut_hour option[value="' + parseInt(heureDebut[0]) + '"]').prop('selected', true);
            divPlagesUtilisateurForm.find(plageUtilisateurBaseId + '_debut_minute option[value="' + parseInt(heureDebut[1]) + '"]').prop('selected', true);
            divPlagesUtilisateurForm.find(plageUtilisateurBaseId + '_fin_hour option[value="' + parseInt(heureFin[0]) + '"]').prop('selected', true);
            divPlagesUtilisateurForm.find(plageUtilisateurBaseId + '_fin_minute option[value="' + parseInt(heureFin[1]) + '"]').prop('selected', true);
            divPlagesUtilisateurForm.parent().removeClass('need-deletion');
        });
        divPlagesUtilisateurForms.find('>div.need-deletion').remove();
        // récupère l'annuaire (étape 2)
        let divAnnuaireForms = $('#composant_annuaire');
        $('#etape-2 tbody tr').each(function(lineNumber) {
            let trAnnuaire = $(this);
            let annuaireId = trAnnuaire.attr('data-id');
            let divAnnuaireForm = $(divAnnuaireForms).find('input[value="' + annuaireId + '"]').parent().parent();
            if (divAnnuaireForm.length < 1) {
                let newAnnuaireForm = $(divAnnuaireForms.attr('data-prototype').replace(/__name__/g, lineNumber));
                divAnnuaireForms.append(newAnnuaireForm);
                divAnnuaireForm = newAnnuaireForm.find('div');
            }
            if (trAnnuaire.hasClass('need-deletion')) {
                divAnnuaireForm.remove();
            } else {
                let annuaireBaseId = '#' + $(divAnnuaireForm).attr('id');
                divAnnuaireForm.find(annuaireBaseId + '_mission').val(trAnnuaire.find('select[name="missionId"]').val());
                divAnnuaireForm.find(annuaireBaseId + '_service').val(trAnnuaire.find('select[name="serviceId"]').val());
                divAnnuaireForm.find(annuaireBaseId + '_balf').val(trAnnuaire.find('input[name="label"]').val());
            }
        });
        // récupère la liste des flux entrants (étape 3)
        $('#composant_impactesParComposants option:selected').prop('selected', false);
        $('#etape-3 .sel-composants li.visible input').each(function(){
            $('#composant_impactesParComposants option[value="' + $(this).val() + '"]').prop('selected', true);
        });
        // récupère la liste des flux sortants (étape 4)
        $('#composant_composantsImpactes option:selected').prop('selected', false);
        $('#composant_impacteLuiMeme').prop('checked', false);
        $('#etape-4 .sel-composants li.visible input').each(function() {
            let composantId = $(this).val();
            if (composantId != '') {
                $('#composant_composantsImpactes option[value="' + composantId + '"]').prop('selected', true);
            } else {
                $('#composant_impacteLuiMeme').prop('checked', true);
            }
        });
    }

    // Soumission de l'ajout du composant au serveur
    $('#confirmationModal .archivage-composant').on('click', function(){
        $('form[name="composant"]').submit();
    });
    $('#etape-5 .next-step').click(function(event){
        event.preventDefault();
        setFormData();
        if ($('#composant_estArchive').is(':checked')){
            var $modal = $('#confirmationModal');
            $modal.modal('show');
        } else {
            $('form[name="composant"]').submit();
        }
    });
    flagUneFois = false;
    if (modificationEnCours === true) {
        $('#etape-5 .archivage').text('non');
        if (($('#composant_estArchive').is(':checked')) && (flagUneFois == false)) {
            flagUneFois = true;
            majResumeEtape1();
            $('.prev-step').remove();
            $('.next-step').remove();
            etape1Formattage();
            etape2Formattage();
            etape3Formattage();
            etape4Formattage();
            $('#smartwizard').smartWizard("goToStep", 4);
            $('#smartwizard').smartWizard("stepState", [0,1,2,3], "disable");
            $('#etape-5 .archivage').text('oui');
        }
        //création de la zone de saisie Archivage
        $('#composant_estArchive').on('change', function(){
            if ($(this).is(':checked')){
                $('#etape-5 .archivage').text('oui');
            } else {
                $('#etape-5 .archivage').text('non');
            }

        });
        //pour les select, input et textarea (hors tableaux) : soulignement des valeurs modifiées
        $('select, input, textarea').change(function(){
            if (($(this).val()) != ($(this).attr('data-valeur-initiale'))) {
                $('#etape-5 .'+($(this).attr('data-cible'))).addClass('modif');
            } else {
                $('#etape-5 .'+($(this).attr('data-cible'))).removeClass('modif');
            }
        });
    }

    window.bigLoadingDisplay(false);
});
