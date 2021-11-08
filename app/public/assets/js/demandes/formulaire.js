$(document).ready(function() {

    /**
     * Initialisation de quelques variables
     */
    let $impactsContainer = $('.demande-creation-impacts');
    let $interventionForm = $('form[name="intervention"]');
    let $dateDebutIntervention = null;
    let $dateFinMinIntervention = null;
    let $dateFinMaxIntervention = null;
    let dateDebutPicker = null;
    let dateFinMinPicker = null;
    let dateFinMaxPicker = null;

    /**
     *initialisation de jQuery Smart Wizard
     **/
    let wizard = $('#smartwizard').smartWizard({
        selected: 0,
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

    /**
     * On fixe pour chaque champ datetimepicker le datetime minimal saisissable via le widget
     */
    $("#dateDebut").on("dp.show", function () {
        $(this).data("DateTimePicker").minDate(new Date());
    });
    $("#dateFinMini").on("dp.show", function () {
        if ($("#dateDebut").val() == '') {
            $(this).data("DateTimePicker").minDate(new Date());
        } else {
            $(this).data("DateTimePicker").minDate($("#dateDebut").data('DateTimePicker').viewDate());
        }
    });
    $("#dateFinMax").on("dp.show", function () {
        if ($("#dateFinMini").val() != '') {
            $(this).data("DateTimePicker").minDate($("#dateFinMini").data('DateTimePicker').viewDate());
        } else if ($("#dateDebut").val() != '') {
            $(this).data("DateTimePicker").minDate($("#dateDebut").data('DateTimePicker').viewDate());
        } else {
            $(this).data("DateTimePicker").minDate(new Date());
        }
    });
    $(".demande-creation-impacts").on("dp.show", "input[data-name='datedebut']", function () {
        $(this).data("DateTimePicker").minDate($("#dateDebut").data('DateTimePicker').viewDate());
    });
    $(".demande-creation-impacts").on("dp.show", "input[data-name='datefinmin']", function () {
        if ($(".demande-impact").last().find("input[data-name='datedebut']").data('DateTimePicker').viewDate() == '') {
            $(this).data("DateTimePicker").minDate($("#dateDebut").data('DateTimePicker').viewDate());
        } else {
            $(this).data("DateTimePicker").minDate(moment($(".demande-impact").last().find("input[data-name='datedebut']").data('DateTimePicker').viewDate()));
        }
    });
    $(".demande-creation-impacts").on("dp.show", "input[data-name='datefinmax']", function () {
        if ($(".demande-impact").last().find("input[data-name='datefinmin']").data('DateTimePicker').viewDate() == '') {
            $(this).data("DateTimePicker").minDate($("#dateDebut").data('DateTimePicker').viewDate());
        } else {
            $(this).data("DateTimePicker").minDate(moment($(".demande-impact").last().find("input[data-name='datefinmin']").data('DateTimePicker').viewDate()));
        }
    });

    /**
     * On fixe la valeur maximale du retour arrière
     */
    $('#dureeRetourArriere').on('click', function(){
        if ($("#dateFinMini").val() !== '' && $("#dateFinMax").val() !== '') {
            let dateFinMini = moment($("#dateFinMini").data('DateTimePicker').viewDate()).format('X');
            let dateFinMax = moment($("#dateFinMax").data('DateTimePicker').viewDate()).format('X');
            if (dateFinMax >= dateFinMini) {
                $(this).attr('max', (dateFinMax - dateFinMini) / 60);
            }
        }
    });

    /**
     * On aménage le formulaire pour le traitement des demandes renvoyées
     */
    if ($('#bloc-demande-renvoyee').length) {
        $('#btn-enregistrer').remove();
    }

    /**
     * ---------------- ETAPE 1 -----------------------------------
     */
    /**
     * On initialise quelques variables utiles
     */
    let ajaxRecuperationAnnuaireRequete = null;
    let ajaxRecuperationComposantsImpactesRequete = null;
    let cacheResponseComposantImpactes = null;
    let $demandeServicesAnnuaire = $('.demande-services');
    let $templateServiceRowItem = $('<div class="demande-service row"><div class="col-4 demande-service__mission"></div><div class="col-8 demande-service__services"></div></div>');

    /**
     * Fonction permettant de récupérer les informations
     */
    let ajaxRecuperationAnnuaire = function() {
        let idComposant = $('#intervention_composant').val();

        if (ajaxRecuperationAnnuaireRequete !== null) {
            ajaxRecuperationAnnuaireRequete.abort();
        }
        if (ajaxRecuperationComposantsImpactesRequete !== null) {
            ajaxRecuperationComposantsImpactesRequete.abort();
        }

        if (idComposant > 0) {
            $demandeServicesAnnuaire.empty();
            $demandeServicesAnnuaire.removeClass('loading');
            $demandeServicesAnnuaire.addClass('loading');

            // Requête de recherche de l'annuaire
            ajaxRecuperationAnnuaireRequete = $.ajax({
                url: '/ajax/composant/' + idComposant + '/annuaire',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    // On ajoute une nouvelle ligne pour chaque mission trouvée, ainsi que les services corresponsants
                    $.each(response, function(missionLabel, services) {
                        let $newRow = $templateServiceRowItem.clone();
                        $newRow.find('.demande-service__mission').html('<label><input type="checkbox"> ' + missionLabel + '</label>');

                        $.each(services, function(idAnnuaire, service) {
                            $newRow.find('.demande-service__services')
                                .append('<label data-service-id=' + service.id + '><input type="checkbox" class="demande-service__annuaire" value="' + idAnnuaire + '" disabled> ' + service.label + '</label>')
                                .addClass('disabled');
                        });
                        $demandeServicesAnnuaire.append($newRow);
                    });

                    // Si nous avons des services à afficher, on les affiche.
                    if ($demandeServicesAnnuaire.find('.demande-service__annuaire').length === 0) {
                        $demandeServicesAnnuaire.append($('<div class="aucun-service text-center">Il n\'existe actuellement dans l\'annuaire GESIP aucun service associé à ce composant susceptible de réaliser l\'intervention.<br/>L\'annuaire du composant doit être mis à jour.</div>'))
                    }

                    // On réinitialise la liste des services exploitants externes
                    initSelectList();
                },
                error: function(erreur) {
                    if(erreur.status !== 0) {
                        alert("Impossible de récupérer l'annuaire du composant sélectionné pour l'instant.");
                    }
                },
                complete: function() {
                    $demandeServicesAnnuaire.removeClass('loading');
                    importFormData();
                }
            });

            // Requête de recherche des composants impactés
            if (!$('#smartwizard').hasClass('copie') && !$('#smartwizard').hasClass('modification')) {
                ajaxRecuperationComposantsImpactesRequete = $.ajax({
                    url: '/ajax/composant/' + idComposant + '/flux-sortants',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        let $firstImpact = $('#demande-composants-1');
                        $firstImpact.find('ul').empty();
                        $firstImpact.find('ul').append($('<li class="aucun-composant">Aucun composant sélectionné</li>'));
                        $.each(response, function(i, composant) {
                            $firstImpact.find('ul').append(
                                $('<li class="mt-2 mb-2 text-center">' +
                                '   <span class="label">' + composant.label + '</span> ' +
                                '   <input type="hidden" class="form-control" value="' + composant.id + '">' +
                                '</li>')
                            );
                            $firstImpact.find('li.aucun-composant').remove();
                        });
                        // On aura besoin de la liste des composants impactés si l'utilisateur
                        //  ajoute de nouveaux impacts en etape 3 donc on met en cache la réponse
                        cacheResponseComposantImpactes = response;
                    },
                    error: function(erreur) {
                        if(erreur.status !== 0) {
                            alert("Impossible de récupérer les composants impactés du composant sélectionné pour l'instant.");
                        }
                    },
                    complete: function() {
                        $demandeServicesAnnuaire.removeClass('loading');
                    }
                });
            }
        } else {
            $demandeServicesAnnuaire.empty();
            $demandeServicesAnnuaire.append($('<div class="aucun-service text-center">Merci de sélectionner un composant pour pouvoir sélectionner un service réalisant l\'intervention.</div>'))
        }
    };

    /**
     * Fonction permettant de récupérer les annuaires sélectionnés
     */
    let recuperationAnnuairesSelectionnes = function() {
        let liste = [];
        $('.demande-service__services input:checked').each(function() {
            liste.push(parseInt($(this).val()));
        });
        return liste;
    };

    /**
     * Fonction permettant de valider l'étape 1
     */
    let validationEtape1 = function() {
        // On initialise les variables
        let erreurDetectee = [];
        let $etape1 = $('.etape-1');
        let $description = $etape1.find('#intervention_description');
        let $composant = $etape1.find('#intervention_composant');
        let $palierApplicatif = $etape1.find('#intervention_palierApplicatif');
        let $motifIntervention = $etape1.find('#intervention_motifIntervention');
        let $natureIntervention = $etape1.find('#intervention_natureIntervention');

        // On réinitialise l'affichage des champs et label en erreurs
        $etape1.find('.form-control-error').removeClass('form-control-error');
        $etape1.find('.form-label-error').removeClass('form-label-error');

        // Si la description est vide
        if ($description.val() === '') {
            erreurDetectee.push('Description');
            $description.parents('.form-group').find('.form-control').addClass('form-control-error');
            $description.parents('.form-group').find('label').addClass('form-label-error');
        }

        // Si le composant est vide
        if ($composant.val() === '') {
            erreurDetectee.push('Composant');
            $composant.parents('.form-group').find('.form-control').addClass('form-control-error');
            $composant.parents('.form-group').find('label').addClass('form-label-error');
        }

        // Si pallier applicatif n'a pas été coché
        if ($palierApplicatif.find('input:checked').length === 0) {
            erreurDetectee.push('Palier applicatif');
            $palierApplicatif.parents('.form-group').find('label').addClass('form-label-error');
        }

        // Si motif d'intervention est vide
        if ($motifIntervention.val() === '') {
            erreurDetectee.push('Motif d\'intervention');
            $motifIntervention.addClass('form-control-error');
            $motifIntervention.parents('.form-group').find('label').addClass('form-label-error');
        }

        // Si nature intervention n'a pas été coché
        if ($natureIntervention.find('input:checked').length === 0) {
            erreurDetectee.push('Nature d\'intervention');
            $natureIntervention.parents('.form-group').find('label').addClass('form-label-error');
        }

        // Si aucun annuaire sélectionnés
        if (recuperationAnnuairesSelectionnes().length === 0 && $('#intervention_exploitantExterieurs :selected').length <= 0) {
            erreurDetectee.push('Service réalisant l\'intervention');
            $demandeServicesAnnuaire.parents('.editable').find('.title').addClass('form-label-error');
            $demandeServicesAnnuaire.parents('.editable').find('.selection-visibility').parents('label').addClass('form-label-error');
            $demandeServicesAnnuaire.find('label').addClass('form-label-error');
        }

        // Si un champ vide, on affiche un message
        if (erreurDetectee.length > 0) {
            window.afficherToast(
                'Le(s) champ(s) : ' + erreurDetectee.join(', ') + ' sont invalides.<br/> Merci de revoir votre saisie.',
                'danger'
            );
            // Des erreurs détectés, on envoi true
            return true;
        }

        // Pas d'erreur, on renvoi false
        return false;
    };

    /**
     * Lors d'un changement de valeur au niveau du champ "Composant concerné"
     */
    ajaxRecuperationAnnuaire();
    $('#intervention_composant').change(function() {
        ajaxRecuperationAnnuaire();
    });

    /**
     * Lorsque l'on clique sur une checkbox d'une mission, on active ou désactive tous les services
     */
    $demandeServicesAnnuaire.on('change', '.demande-service__mission input', function(e) {
        e.preventDefault();
        let $this = $(this);
        let $parent = $this.parents('.demande-service');

        if (!$this.is(':checked')) {
            $parent.find('.demande-service__services').addClass('disabled');
            $parent.find('.demande-service__services input').prop('disabled', 'disabled');
            $parent.find('.demande-service__services input').prop('checked', '');
        } else {
            $parent.find('.demande-service__services').removeClass('disabled');
            $parent.find('.demande-service__services input').prop('disabled', '');
            $parent.find('.demande-service__services input').prop('checked', 'checked');
        }
    });

    /**
     * Si tous les services d'une mission sont désactivés, on décoche la mission concernée
     */
    $demandeServicesAnnuaire.on('change', '.demande-service__annuaire', function(e) {
        e.preventDefault();
        let $annuaireRow = $(this).parents('.demande-service.row');
        if ($annuaireRow.find('.demande-service__services :checked').length <= 0) {
            $annuaireRow.find('.demande-service__services input').prop('disabled', true)
            $annuaireRow.find('.demande-service__mission input').prop('checked', false);
        }
    });

    /**
     * Récupère les champs entrés lorsque l'on quitte l'étape 1
     *  (complète le résumé, la synthèse, et rafraichi les services sélectionnées dans le formulaire)
     */
    function onLeaveStep1() {
        $('.demande-numero').text($('#smartwizard').data('demande-numero'));
        $('.demande-demandePar-label').text($('#smartwizard').data('demande-demandepar-label'));
        $('.demande-composant-label').text($('#intervention_composant :selected').text());
        $('.demande-motifIntervention-label').text($('#intervention_motifIntervention :selected').text());
        $('.demande-description').text($('#intervention_description').val());
        let servicesLabels = [];
        $('#intervention_services option').prop('selected', false);
        $('.demande-service__services :checked').each(function() {
            let serviceLabel = $(this).parent().text();
            let annuaireId = $(this).val();
            if (servicesLabels.indexOf(serviceLabel) < 0) {
                servicesLabels.push(serviceLabel);
            }
            $('#intervention_services option[value="' + annuaireId + '"]').prop('selected', true);
        });
        $('#intervention_exploitantExterieurs :selected').each(function() {
            servicesLabels.push($(this).text());
        });
        servicesLabels.sort();
        $('.demande-services-label').text(servicesLabels.join(', '));
    }

    /**
     * ---------------- ETAPE 2 -----------------------------------
     */
    /**
     * Fonction permettant de valider l'étape 2
     */
    let validationEtape2 = function() {
        // On initialise les variables
        let dateCourante = moment();
        let erreurDetectee = false;
        let $etape2 = $('.etape-2');
        let $dateDebut = $etape2.find('#dateDebut');
        let dateDebut = $dateDebut.data('DateTimePicker').viewDate();
        let $dateFinMin = $etape2.find('#dateFinMini');
        let dateFinMin = $dateFinMin.data('DateTimePicker').viewDate();
        let $dateFinMax = $etape2.find('#dateFinMax');
        let dateFinMax = $dateFinMax.data('DateTimePicker').viewDate();
        let $dureeRetourArriere = $etape2.find('#dureeRetourArriere');

        // On réinitialise l'affichage des champs et label en erreurs
        $etape2.find('.form-control-error').removeClass('form-control-error');
        $etape2.find('.form-label-error').removeClass('form-label-error');

        // Si dateDebut est vide
        if ($dateDebut.val() === '') {
            erreurDetectee = true;
            $dateDebut.addClass('form-control-error');
            $dateDebut.parents('.form-group').find('label').addClass('form-label-error');
        }

        // Si dateFinMin est vide
        if ($dateFinMin.val() === '') {
            erreurDetectee = true;
            $dateFinMin.addClass('form-control-error');
            $dateFinMin.parents('.form-group').find('label').addClass('form-label-error');
        }

        // Si dateFinMax est vide
        if ($dateFinMax.val() === '') {
            erreurDetectee = true;
            $dateFinMax.addClass('form-control-error');
            $dateFinMax.parents('.form-group').find('label').addClass('form-label-error');
        }

        // Si dureeRetourArriere est vide
        if ($dureeRetourArriere.val() === '' || $dureeRetourArriere.val() < 0) {
            erreurDetectee = true;
            $dureeRetourArriere.addClass('form-control-error');
            $dureeRetourArriere.parents('.form-group').find('label').addClass('form-label-error');
        }

        // Si un champ vide, on affiche un message
        if (erreurDetectee) {
            window.afficherToast('Des anomalies ont été détectées et mises en évidence en rouge lors de la saisie des périodes et durées.', 'danger');
            return erreurDetectee;

        // Si pas d'erreur pour l'instant, on test les l'intervales
        } else {

            // Si l'intervention commence avant la date courante
            if (dateDebut <= dateCourante) {
                erreurDetectee = true;
                $dateDebut.addClass('form-control-error');
                $dateDebut.parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date d\'intervention est antérieure à la date du jour. Veuillez modifier votre saisie.', 'danger');
                return erreurDetectee;
            }

            // Si la date de fin maxi est inférieure à la date de fin mini
            if (dateFinMax < dateFinMin) {
                erreurDetectee = true;
                $dateFinMin.addClass('form-control-error');
                $dateFinMin.parents('.form-group').find('label').addClass('form-label-error');
                $dateFinMax.addClass('form-control-error');
                $dateFinMax.parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de fin maximale est antérieure à la date/heure de fin minimale. Veuillez modifier votre saisie.', 'danger');
                return erreurDetectee;
            }

            // Si la date de fin mini est inférieure à la date de début
            if (dateFinMin < dateDebut) {
                erreurDetectee = true;
                $dateFinMin.addClass('form-control-error');
                $dateFinMin.parents('.form-group').find('label').addClass('form-label-error');
                $dateDebut.addClass('form-control-error');
                $dateDebut.parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de fin minimale est incohérente avec la date/heure d\'intervention. Veuillez modifier votre saisie.', 'danger');
                return erreurDetectee;
            }

            // Si la durée de retour arrière est supérieure à la différence entre la date de fin maximale d'intervention et date de fin minimal d'intervention
            if (moment(dateFinMin).add($dureeRetourArriere.val(), 'minutes').format('X') > moment(dateFinMax).format('X')) {
                erreurDetectee = true;
                $dateFinMin.addClass('form-control-error');
                $dateFinMin.parents('.form-group').find('label').addClass('form-label-error');
                $dateFinMax.addClass('form-control-error');
                $dateFinMax.parents('.form-group').find('label').addClass('form-label-error');
                $dureeRetourArriere.addClass('form-control-error');
                $dureeRetourArriere.parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La durée du retour arrière est supérieure à la différence entre la date de Fin maximale et la date de fin minimale. Veuillez modifier votre saisie.', 'danger');
                return erreurDetectee;
            }
        }
    };

    /**
     * Récupère les champs entrés lorsque l'on quitte l'étape 2
     *  (complète le résumé et la synthèse, et rafraichi les champs du formulaire)
     */
    function onLeaveStep2() {
        $(['dateDebut', 'dateFinMini', 'dateFinMax', 'dureeRetourArriere']).each(function() {
            let value = $('#' + this ).val();
            $('.demande-' + this).text(value);
            $('#intervention_' + this).val(value);
        });

        $dateDebutIntervention = $('#dateDebut').val();
        dateDebutPicker = $('#dateDebut').data("DateTimePicker").viewDate();
        if ($('#datedebut-1').val() === '') {
            $('#datedebut-1').val($dateDebutIntervention);
            $('#datedebut-1').data("DateTimePicker").date(dateDebutPicker);
        }
        $dateFinMinIntervention = $('#dateFinMini').val();
        dateFinMinPicker = $('#dateFinMini').data("DateTimePicker").viewDate();
        if ($('#datefinmin-1').val() === '') {
            $('#datefinmin-1').val($dateFinMinIntervention);
            $('#datefinmin-1').data("DateTimePicker").date(dateFinMinPicker);
        }
        $dateFinMaxIntervention = $('#dateFinMax').val();
        dateFinMaxPicker = $('#dateFinMax').data("DateTimePicker").viewDate();
        if ($('#datefinmax-1').val() === '') {
            $('#datefinmax-1').val($dateFinMaxIntervention);
            $('#datefinmax-1').data("DateTimePicker").date(dateFinMaxPicker);
        }

        // Met à jour les impacts existants
        if ($('#smartwizard').hasClass('copie') || $('#smartwizard').hasClass('renvoyee')) {
            $('.demande-impact').each(function() {
                $(this).find('[data-name="datedebut"]').val($dateDebutIntervention).trigger('change');
                $(this).find('[data-name="datefinmin"]').val($dateFinMinIntervention).trigger('change');
                $(this).find('[data-name="datefinmax"]').val($dateFinMaxIntervention).trigger('change');
            });
        }
    }

    /**
     * ---------------- ETAPE 3 -----------------------------------
     */
    /**
     * On enregistre les templates de doublons
     */
    let $templateComposantItem = $('.demande-composants__item:nth-child(1)').clone();
    let $templateImpactItem = $('.demande-impact:nth-child(1)').clone();
    $templateImpactItem.find('textarea, select, input[type=text]').val('');
    $templateImpactItem.find('input[data-name=certitude][value=0]').prop('checked', false);
    $templateImpactItem.find('input[data-name=certitude][value=1]').prop('checked', true);

    /**
     * Fonction permettant de parcourir une collection et de placer l'id dans une propriété du DOM de l'élement
     * @param id
     * @param prop
     * @param $collection
     * @param oldId
     * @private
     */
    let _setIdInProp = function(id, prop, $collection, oldId = '#') {
        $collection.each(function() {
            let $this = $(this);
            let propValue = $this.prop(prop);
            if (propValue !== '') {
                $this.prop(prop, propValue.replace(oldId, id));
            }
        });
    };

    /**
     * Fonction permettant d'ajouter l'id dans l'impact passé en paramètre
     * @param id
     * @param $impact
     * @param oldId
     */
    let ajoutIdImpact = function(id, $impact, oldId = '#') {
        $impact.find('.demande-impact__numero').html(id);
        _setIdInProp(id, 'for', $impact.find('label'), oldId);
        _setIdInProp(id, 'id', $impact.find('input, select, textarea, div'), oldId);
        _setIdInProp(id, 'name', $impact.find('input'), oldId);
    };

    /**
     * Fonction permettant d'ajouter un impact à la liste des impacts
     */
    let ajoutImpact = function() {
        // On clone les templates
        let $nouvelImpact = $templateImpactItem.clone();
        let $nouvelleSaisieComposant = $templateComposantItem.clone();

        // On initialise l'impact
        $nouvelleSaisieComposant.find('.demande-composants-item__delete').hide();
        ajoutIdImpact($('.demande-creation-impacts .demande-impact').length + 1, $nouvelImpact);
        $nouvelImpact.find('.demande-composants').html($nouvelleSaisieComposant);

        // On ajoute les composants impactés par défaut pour ce nouvel impact (à partir du cache enregistré à l'étape 1 au moment de la selection du composant)
        $.each(cacheResponseComposantImpactes, function(i, composant) {
            $nouvelImpact.find('ul').append(
                $('<li class="mt-2 mb-2 text-center">' +
                '   <span class="label">' + composant.label + '</span> ' +
                '   <input type="hidden" class="form-control" value="' + composant.id + '">' +
                '</li>')
            );
            $nouvelImpact.find('li.aucun-composant').remove();
        });

        // On initialise les date time picker
        $nouvelImpact.find('.form-datetimepicker').datetimepicker();
        $nouvelImpact.find("input[data-name='datedebut']").val($dateDebutIntervention);
        $nouvelImpact.find("input[data-name='datedebut']").data("DateTimePicker").date(dateDebutPicker);
        $nouvelImpact.find("input[data-name='datefinmin']").val($dateFinMinIntervention);
        $nouvelImpact.find("input[data-name='datefinmin']").data("DateTimePicker").date(dateFinMinPicker);
        $nouvelImpact.find("input[data-name='datefinmax']").val($dateFinMaxIntervention);
        $nouvelImpact.find("input[data-name='datefinmax']").data("DateTimePicker").date(dateFinMaxPicker);
        // On initialise le premier sélecteur de composant
        $nouvelleSaisieComposant.find('select').selectpicker({
            'liveSearch': true,
            'style': '',
            'styleBase': 'form-control'
        });

        // On ajout le nouvel impact
        $impactsContainer.append($nouvelImpact);

        // On met à jour le numéro d'ordre des impacts saisis
        majOrdreImpact();
    };

    /**
     * Fonction permettant de mettre à jour l'ordre des impacts
     */
    let majOrdreImpact = function() {
        // Si nous avons qu'un seul impact, nous désactivons la possibilité de supprimer un impact
        let $impacts = $('.demande-impact');
        if($impacts.length === 1) {
            $impacts.find('.demande-impact__delete').hide();
        } else {
            $impacts.find('.demande-impact__delete').show();
        }

        // On met à jour les id, for, name, des champs de l'impact
        $impactsContainer.find('.demande-impact').each(function(i) {
            let $this = $(this);
            ajoutIdImpact(i+1, $this, $this.find('.demande-impact__numero').html());
        });
    };

    /**
     * Récupération des éléments de l'impact
     * @param $impact
     */
    let recuperationDonneesImpact = function($impact) {
        // On initialise les variables
        let donnees = {};
        let composants = [];

        // On va récupérer la liste des composants et on récupère l'id à chaque fois
        $impact.find('.demande-composants__item').each(function() {
            let value = $(this).find('input').val();

            if (value !== '') {
                composants.push(parseInt($(this).find('input').val()));
            }
        });

        // On récupère les autres valeurs du formulaires que l'on injecte dans l'objet données
        donnees.nature      = $impact.find('[data-name=nature]').val() ? parseInt($impact.find('[data-name=nature]').val()) : null;
        donnees.certitude   = ($impact.find('[data-name=certitude]:checked').val() === '1');
        donnees.commentaire = $impact.find('[data-name=commentaire]').val();
        donnees.dateDebut   = $impact.find('[data-name=datedebut]').val() ? $impact.find('[data-name=datedebut]').data("DateTimePicker").viewDate() : null;
        donnees.dateFinMin  = $impact.find('[data-name=datefinmin]').val() ? $impact.find('[data-name=datefinmin]').data("DateTimePicker").viewDate() : null;
        donnees.dateFinMax  = $impact.find('[data-name=datefinmax]').val() ? $impact.find('[data-name=datefinmax]').data("DateTimePicker").viewDate() : null;
        donnees.composants  = composants;

        // On renvoi les données
        return donnees;
    };

    let validationSaisieImpacts = function() {
        let erreurDetectee = false;

        // Récupère les dates précédentes
        let $etape2 = $('.etape-2');
        let $dateDebut = $etape2.find('#dateDebut');
        let dateDebut = $dateDebut.data('DateTimePicker').viewDate();
        let $dateFinMin = $etape2.find('#dateFinMini');
        let dateFinMin = $dateFinMin.data('DateTimePicker').viewDate();
        let $dateFinMax = $etape2.find('#dateFinMax');
        let dateFinMax = $dateFinMax.data('DateTimePicker').viewDate();

        $impactsContainer.find('.demande-impact').each(function(i) {
            let $impact = $(this);
            let impactNumber = i+1;
            let donnees = recuperationDonneesImpact($impact);
            let aAucunImpact = (donnees.nature !== null && $impact.find('[data-name=nature] option[value=' + donnees.nature + ']').html() !== "Aucun impact");

            $impact.find('.form-control-error').removeClass('form-control-error');
            $impact.find('.form-label-error').removeClass('form-label-error');

            if (donnees.nature === null) {
                $impact.find('[data-name=nature]').addClass('form-control-error');
                $impact.find('[data-name=nature]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La nature de l\'impact ' + impactNumber + ' est manquante.', 'danger');
                erreurDetectee = true;
            }

            if (donnees.dateDebut === null) {
                $impact.find('[data-name=datedebut]').addClass('form-control-error');
                $impact.find('[data-name=datedebut]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de début d\'impact ' + impactNumber + ' est manquante.', 'danger');
                erreurDetectee = true;
            } else if (donnees.dateDebut < dateDebut) {
                $impact.find('[data-name=datedebut]').addClass('form-control-error');
                $impact.find('[data-name=datedebut]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de début d\'impact ' + impactNumber + ' est antérieure à la date/heure de début de l\'intervention.', 'danger');
                erreurDetectee = true;
            } else if (dateFinMin < donnees.dateDebut) {
                $impact.find('[data-name=datedebut]').addClass('form-control-error');
                $impact.find('[data-name=datedebut]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de début d\'impact ' + impactNumber + ' est postérieure à la date/heure de fin minimum d\'intervention.', 'danger');
                erreurDetectee = true;
            }

            if (donnees.dateFinMin === null) {
                $impact.find('[data-name=datefinmin]').addClass('form-control-error');
                $impact.find('[data-name=datefinmin]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de fin minimum d\'impact' + impactNumber + ' est manquante.', 'danger');
                erreurDetectee = true;
            } else if (donnees.dateFinMin < donnees.dateDebut) {
                $impact.find('[data-name=datefinmin]').addClass('form-control-error');
                $impact.find('[data-name=datefinmin]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de fin minimum d\'impact ' + impactNumber + ' est antérieure à la date/heure de début d\'impact.', 'danger');
                erreurDetectee = true;
            } else if (donnees.dateFinMin < dateDebut) {
                $impact.find('[data-name=datefinmin]').addClass('form-control-error');
                $impact.find('[data-name=datefinmin]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de fin minimum d\'impact ' + impactNumber + ' est antérieure à la date/heure de début de l\'intervention.', 'danger');
                erreurDetectee = true;
            } else if (dateFinMin < donnees.dateFinMin) {
                $impact.find('[data-name=datefinmin]').addClass('form-control-error');
                $impact.find('[data-name=datefinmin]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de fin d\'impact ' + impactNumber + ' est postérieure à la date/heure de fin minimum d\'intervention.', 'danger');
                erreurDetectee = true;
            }

            if (donnees.dateFinMax === null) {
                $impact.find('[data-name=datefinmax]').addClass('form-control-error');
                $impact.find('[data-name=datefinmax]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de fin maximum d\'impact' + impactNumber + ' est manquante.', 'danger');
                erreurDetectee = true;
            } else if (donnees.dateFinMax < donnees.dateFinMin) {
                $impact.find('[data-name=datefinmax]').addClass('form-control-error');
                $impact.find('[data-name=datefinmax]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de fin maximum d\'impact ' + impactNumber + ' est antérieure à la date/heure de fin minimum d\'impact.', 'danger');
                erreurDetectee = true;
            } else if (donnees.dateFinMax > dateFinMax) {
                $impact.find('[data-name=datefinmax]').addClass('form-control-error');
                $impact.find('[data-name=datefinmax]').parents('.form-group').find('label').addClass('form-label-error');
                window.afficherToast('La date/heure de fin maximum d\'impact ' + impactNumber + ' est postérieure à la date/heure de fin maximum de l\'intervention.', 'danger');
                erreurDetectee = true;
            }

            // Si "Aucun Impact", n'est pas sélectionné, l'utilisateur doit remplir les autres champs (composants)
            if (aAucunImpact) {
                if (donnees.composants.length === 0) {
                    $impact.find('.demande-composants .form-control').addClass('form-control-error');
                    $impact.find('.demande-composants').parents('.form-group').find('label').addClass('form-label-error');
                    window.afficherToast('Aucun composant impacté par l\'impact ' + impactNumber + ' défini.', 'danger');
                    erreurDetectee = true;
                }
            }

        });
        return erreurDetectee;
    };

    /**
     * Lors d'un clic sur le bouton "Ajouter un impact"
     */
    $('.demande-impact__add').click(function(e) {
        e.preventDefault();
        ajoutImpact();
    });

    /**
     * ----- Modification des composants impactés -----
     */
    const $modaleSaisieComposants = $('#saisieComposantsImpactes');
    $impactsContainer.on('click', '.demande-composants__item button', function(e) {
        // On défini quelques variables
        let $modaleSel = $modaleSaisieComposants.find('.modal-saisie-composants__sel');

        // On réinitialise notre modale
        $modaleSaisieComposants.find('.modal-saisie-composants-search__input').val('');
        $modaleSel.find('ul').empty();
        $modaleSel.find('input:checked').prop('checked', false);
        $modaleSel.trigger('CheckboxesChange', [ 0, [] ]);
        $modaleSel.data('checkedBoxes', []);

        // On parcourt tous les composants déjà saisie
        let $listeComposants = $(this).parents('.card-content').find('ul');
        $listeComposants.find('li:not(.aucun-composant)').each(function(i, composant) {
            let $composant = $(composant);
            let id = $composant.find('input').val();
            let label = $composant.find('span.label').html();

            // On crée notre composant
            let $li = $('<li>' +
                '   <label class="composant-item">' +
                '       <input type="checkbox" class="checkall-box" value="' + id + '" />' +
                '       <span class="label">' + label + '</span>' +
                '   </label>' +
                '</li>');

            // On l'ajoute dans la sélection de la modale
            $modaleSel.find('ul').append($li);
        });

        // On va chercher les composants en base de données et on affiche la modale
        rechercheComposant();
        $modaleSaisieComposants.modal('show');

        // On défini le comportement lors d'une validation de la modale
        $modaleSaisieComposants.unbind('saisieValidee');
        $modaleSaisieComposants.on('saisieValidee', function(e, composants) {
            // On vide la liste des composants
            $listeComposants.empty();
            // Si la liste des composants sélectionnées est vide
            if (composants.length === 0) {
                // On affiche "Aucun composant sélectionné"
                $listeComposants.append('<li class="aucun-composant">Aucun composant sélectionné</li>');

            // Sinon, on parcourt les composants sélectionnés
            } else {
                $.each(composants, function(i, composant) {
                    // Pour chaque, on crée un élément
                    let $li = $('<li class="mt-2 mb-2 text-center">' +
                        '   <span class="label">' + composant.label + '</span> ' +
                        '   <input type="hidden" class="form-control" value="' + composant.id + '">' +
                        '</li>');

                    // On l'ajoute dans la sélection de la modale
                    $listeComposants.append($li);
                });
            }

            // On masque la modale
            $modaleSaisieComposants.modal('hide');
        });
    });

    /**
     * Fonction permettant de chercher un composant dans la base de données GESIP
     * @param typeSearch
     */
    const rechercheComposant = function(typeSearch) {
        // On prépare quelques variables
        let $sel = $('.modal-saisie-composants__sel');
        let $presel = $('.modal-saisie-composants__presel');

        // On initialise notre liste
        $presel.find('.card-body-loading').show();
        $presel.find('ul').empty();
        $presel.find('input:checked').prop('checked', false);
        $presel.trigger('CheckboxesChange', [ 0, [] ]);
        $presel.data('checkedBoxes', []);

        // On effectue la requête
        $.ajax({
            url: '/ajax/composant/recherche/label',
            dataType: 'json',
            data: {
                label: typeSearch
            },
            success: function(data) {
                // On parcourt les composants trouvés
                $.each(data, function(idx, composant) {
                    // On crée notre composant
                    let $li = $('<li>' +
                                '   <label class="composant-item">' +
                                '       <input type="checkbox" class="checkall-box" value="' + composant.id + '" />' +
                                '       <span class="label">' + composant.label + '</span>' +
                                '   </label>' +
                                '</li>');

                    // Si le composant est déjà dans la liste à droite, on le masque à gauche.
                    if ($sel.find('input[value="' + composant.id + '"]').length > 0) {
                        $li.addClass('d-none');
                    }

                    // On ajoute le nouveau composant à la liste
                    $presel.find('ul').append($li);
                });
            },
            complete: function() {
                $presel.find('.card-body-loading').hide();
            },
            error: function() {
                alert('Impossible de récupérer la liste des composants.');
            }
        });
    };

    /**
     * Lorsque l'on cherche un composant via le champ de recherche
     */
    let bufferModaleSaisieComposants = null;
    $modaleSaisieComposants.on('keyup', '.modal-saisie-composants-search__input', function(e) {
        let typeSearch = $(this).val();
        if (bufferModaleSaisieComposants !== null) {
            clearTimeout(bufferModaleSaisieComposants);
        }
        bufferModaleSaisieComposants = setTimeout(function() {
            rechercheComposant(typeSearch);
        }, 500);
    });

    /**
     * Lorsque l'on clique sur le bouton d'ajout de la liste de droite.
     */
    $modaleSaisieComposants.on('click', '.btn-add', function(e) {
        e.preventDefault();
        let $liste = $('.modal-saisie-composants__sel ul');
        let $presel = $('.modal-saisie-composants__presel');
        let selection = $presel.data('checkedBoxes');

        selection.each(function(i, ckb) {
            let id = $(ckb).val();
            let label = $(ckb).parent().find('span.label').html();
            $(ckb).parents('li').addClass('d-none');

            if ($liste.find('input[value="' + id + '"]').length === 0) {
                $liste.append(
                    $('<li>' +
                    '   <label class="composant-item">' +
                    '       <input type="checkbox" class="checkall-box" value="' + id + '" />' +
                    '       <span class="label">' + label + '</span>' +
                    '   </label>' +
                    '</li>')
                );
            }
        });

        $presel.find('input:checked').prop('checked', false);
        $presel.trigger('CheckboxesChange', [ 0, [] ]);
        $presel.data('checkedBoxes', []);
    });

    /**
     * Lorsque l'on clique sur le bouton de suppression de la liste de droite.
     */
    $modaleSaisieComposants.on('click', '.btn-remove', function(e) {
        e.preventDefault();
        let $liste = $('.modal-saisie-composants__presel ul');
        let $sel = $('.modal-saisie-composants__sel');
        let selection = $sel.data('checkedBoxes');

        selection.each(function(i, ckb) {
            let id = $(ckb).val();
            $(ckb).parents('li').remove();
            $liste.find('input[value="' + id + '"]').parents('li').removeClass('d-none');
        });

        $sel.find('input:checked').prop('checked', false);
        $sel.trigger('CheckboxesChange', [ 0, [] ]);
        $sel.data('checkedBoxes', []);
    });

    /**
     * Lorsque l'on clique sur le bouton de validation de la saisie
     */
    $modaleSaisieComposants.on('click', '.btn-validate', function(e) {
        // On initialise quelques variables utiles
        let composantsSelectionnes = [];
        let $listeSelections = $('.modal-saisie-composants__sel li');

        // On parcourt la sélection faite par l'utilisateur
        $listeSelections.each(function(i, selection) {
            composantsSelectionnes.push({
                id: $(selection).find('input').val(),
                label: $(selection).find('span.label').html()
            });
        });

        // On déclenche un évènement avec la sélection de l'utilisateur
        $modaleSaisieComposants.trigger('saisieValidee', [ composantsSelectionnes ]);
    });

    /**
     * Lors d'un clic sur le bouton de suppression d'un impact
     */
    $impactsContainer.on('click', '.demande-impact__delete', function(e) {
        e.preventDefault();
        $(this).parents('.demande-impact').remove();
        majOrdreImpact();
    });

    /**
     * On initialise les éléments (date time picker et select picker) déjà en place
     */
    $impactsContainer.find('.form-datetimepicker').datetimepicker();
    majOrdreImpact();

    /**
     * Récupère les champs entrés lorsque l'on quitte l'étape 3
     *  (complète la synthèse, et rafraichi les champs du formulaire)
     */
    function onLeaveStep3() {
        let natureAucunImpactId = 6;
        let $formImpacts = $('#intervention_impacts').empty();
        let $tableImpacts = $('.demande-impacts tbody').empty();
        $('.demande-impact').each(function(lineNumber){
            let $formImpact = $($formImpacts.attr('data-prototype').replace(/__name__/g, lineNumber));
            $formImpacts.append($formImpact);
            let impactNumber = lineNumber + 1;
            let $trImpact = $('<tr></tr>');
            $trImpact.append($('<td></td>').text(impactNumber));
            let nature = $('#nature-' + impactNumber).val();
            let commentaire = $('#commentaire-' + impactNumber).val();
            let dateDebut = $('#datedebut-' + impactNumber).val();
            let dateFinMini = $('#datefinmin-' + impactNumber).val();
            let dateFinMax = $('#datefinmax-' + impactNumber).val();
            $formImpact.find('#intervention_impacts_' + lineNumber + '_nature').val(nature);
            $formImpact.find('#intervention_impacts_' + lineNumber + '_certitude').val($('#certitude-' + impactNumber + '-oui').prop('checked') ? 1 : 0);
            $formImpact.find('#intervention_impacts_' + lineNumber + '_commentaire').val(commentaire);
            $formImpact.find('#intervention_impacts_' + lineNumber + '_dateDebut').val(dateDebut);
            $formImpact.find('#intervention_impacts_' + lineNumber + '_dateFinMini').val(dateFinMini);
            $formImpact.find('#intervention_impacts_' + lineNumber + '_dateFinMax').val(dateFinMax);
            let $details = $('<p></p>');
            $details.append(document.createTextNode('Du ' + dateDebut + ' au ' + dateFinMini + ' voire jusqu\'au ' + dateFinMax), $('<br />'));
            $details.append($('#nature-' + impactNumber + ' :selected').text(), $('<br />'));
            $details.append(document.createTextNode(commentaire));
            $trImpact.append($('<td></td>').append($details));
            $formImpact.find('#intervention_impacts_' + lineNumber + '_composants :selected').prop('selected', false);
            let composantLabels = [];
            if (nature != natureAucunImpactId) {
                $('#demande-composants-' + impactNumber + ' li:not(.aucun-composant)').each(function() {
                    let $li = $(this);
                    let composantId = $li.find('input').val();
                    let composantLabel = $li.find('span.label').html();
                    $formImpact.find('#intervention_impacts_' + lineNumber + '_composants option[value="' + composantId + '"]').prop('selected', true);
                    if (composantLabel != '') {
                        composantLabels.push(composantLabel);
                    }
                });
            } else {
                let $composants = $('#demande-composants-' + impactNumber + ' ul').empty();
                $composants.append($('<li class="aucun-composant">Aucun composant sélectionné</li>'));
                $formImpact.find('#intervention_impacts_' + lineNumber + '_composants :selected').prop('selected', false);
            }
            $trImpact.append($('<td></td>').text(composantLabels.join(', ')));
            $tableImpacts.append($trImpact);
            $formImpacts.append($formImpact);
        });
    }

    // A chaque fois qu'on passe à l'étape suivante, on controle les champs mis à jour
    $('#smartwizard').on('leaveStep', function(e, anchorObject, currentStepIndex, nextStepIndex) {
        if (currentStepIndex < nextStepIndex) {
            switch(currentStepIndex) {
                case 0:
                    return !validationEtape1() && onLeaveStep1();
                    break;
                case 1:
                    return !validationEtape2() && onLeaveStep2();
                    break;
                case 2:
                    return !validationSaisieImpacts() && onLeaveStep3();
                    break;
            }
        }
    });

    /**
     * définition des évenements sur les boutons étape précedente / suivante
     */
    $('.prev-step').on('click', function(){
        wizard.smartWizard('prev');
    });
    $('.next-step').on('click', function(){
        wizard.smartWizard('next');
    });

    /**
     * Récupération des données à partir du formulaire (utile lors du chargement d'une demande déjà postée ou enregistrée)
     */
    function importFormData(complete)
    {
        // import étape 1 (annuaires)
        $('#intervention_services option:selected').each(function() {
            let $annuaireService = $('.demande-service__annuaire[value="' + $(this).val() + '"]');
            $annuaireService.prop('checked', true);
            let $annuaireRow = $annuaireService.parents('.demande-service.row');
            $annuaireRow.find('.demande-service__services').removeClass('disabled');
            $annuaireRow.find('.demande-service__mission input').prop('checked', true);
            $annuaireRow.find('input').prop('disabled', false);
        });
        if (complete) {
            // import étape 2
            $(['dateDebut', 'dateFinMini', 'dateFinMax', 'dureeRetourArriere']).each(function() {
                let $field = $('#' + this );
                if ($field.hasClass('form-datetimepicker')) {
                    $field.data('DateTimePicker').date($('#intervention_' + this).val());
                } else {
                    $field.val($('#intervention_' + this).val());
                }
            });
            // import étape 3 (impacts)
            $('#intervention_impacts>div>div').each(function(lineNumber) {
                let impactNumber = lineNumber + 1;
                if (lineNumber > 0) {
                    ajoutImpact();
                }
                $('#nature-' + impactNumber).val($('#intervention_impacts_' + lineNumber + '_nature').val()).selectpicker('refresh');
                if (parseInt($('#intervention_impacts_' + lineNumber + '_certitude').val()) > 0) {
                    $('#certitude-' + impactNumber + '-oui').prop('checked', true);
                } else {
                    $('#certitude-' + impactNumber + '-non').prop('checked', true);
                }
                $('#commentaire-' + impactNumber).val($('#intervention_impacts_' + lineNumber + '_commentaire').val());
                let $impactComposantContainer = $('#demande-composants-' + impactNumber + ' ul');
                $('#intervention_impacts_' + lineNumber + '_composants :selected').each(function() {
                    let $li = $('<li class="mt-2 mb-2 text-center">' +
                        '   <span class="label">' + $(this).html() + '</span> ' +
                        '   <input type="hidden" class="form-control" value="' + $(this).val() + '">' +
                        '</li>');
                    $impactComposantContainer.append($li);
                    $impactComposantContainer.find('li.aucun-composant').remove();
                });
                $('#datedebut-' + impactNumber).data('DateTimePicker').date($('#intervention_impacts_' + lineNumber + '_dateDebut').val());
                $('#datefinmin-' + impactNumber).data('DateTimePicker').date($('#intervention_impacts_' + lineNumber + '_dateFinMini').val());
                $('#datefinmax-' + impactNumber).data('DateTimePicker').date($('#intervention_impacts_' + lineNumber + '_dateFinMax').val());
            });
        }
    }
    importFormData(true);

    /**
     * Permet de lancer la procédure de check si d'autres demandes seraient dans la même période
     */
    function startCheckOtherDemandes() {
        let $modal = $('#confirmationModal');
        let $modalTbody = $modal.find('tbody');
        let dateDebut = $('#intervention_dateDebut').val();
        let dateFin = $('#intervention_dateFinMax').val();
        let demandeId = $('#smartwizard').data('demande-id');
        window.bigLoadingDisplay(true);
        $.ajax({
            url: $modalTbody.data('url'),
            method: 'GET',
            data: {
                start: dateDebut,
                end: dateFin,
                excludeId: demandeId
            }
        })
        .done(function(reponse) {
            // Si le webservice ne retourne pas de data
            if (reponse.data === undefined) {
                alert('Impossible de récupérer les demandes dans la même période concernée.');
            }
            // Si il n'y a pas de superposition avec d'autres demandes
            else if(reponse.data.length === 0) {
                $interventionForm.submit();
            }
            // Sinon on liste les demandes concernées dans la modale prévue à cet effet
            else {
                $modalTbody.empty();
                reponse.data.forEach(function(demande) {
                    let $trDemande = $('<tr></tr>');
                    $tdNumero = $('<td></td>').append($('<a></a>')
                        .attr('href', demande.showDemandeLink)
                        .attr('target', '_blank')
                        .text(demande.numero));
                    $tdDateDemande = $('<td></td>').text(demande.demandeLe);
                    $tdStatus = $('<td></td>').text(demande.status);
                    $tdDemandePar = $('<td></td>').text(demande.demandePar);
                    $tdNature = $('<td></td>').text(demande.nature);
                    $tdComposant = $('<td></td>').text(demande.composant);
                    let exploitants = [];
                    demande.exploitants.forEach(function(exploitant){
                        if (exploitants.indexOf(exploitant.service) < 0) {
                            exploitants.push(exploitant.service);
                        }
                    });
                    $tdExploitants = $('<td></td>').text(exploitants.join(', '));
                    $tdMotif = $('<td></td>').text(demande.motif);
                    $tdPalier = $('<td></td>').text(demande.palier ? 'Oui': 'Non');
                    $tdDescription = $('<td></td>').text(demande.description);
                    $tdDebut = $('<td></td>').text(demande.dateDebut);
                    $trDemande.append($tdNumero, $tdDateDemande, $tdStatus, $tdDemandePar, $tdNature, $tdComposant,
                        $tdExploitants, $tdMotif, $tdPalier, $tdDescription, $tdDebut);
                    $modalTbody.append($trDemande);
                });
                if ($(window).width() >= 1200) {
                    $modal.find('.modal-lg').css('max-width', '1200px');
                }
                $modal.modal('show');
            }
            window.bigLoadingDisplay(false);
        })
        .fail(function(erreur) {
            alert('Impossible de récupérer les demandes dans la même période concernée.');
            window.bigLoadingDisplay(false);
        });
    }
    $('.valid-demande').on('click', function(){
        $interventionForm.submit();
    });

    /**
     * Affiche une demande de confirmation avant d'envoyer la demande d'intervention
     */
    $('.save-demande').click(function(event) {
        event.preventDefault();
        $('#intervention_status').val('brouillon');
        startCheckOtherDemandes();
    });
    $('.send-demande').click(function(event) {
        event.preventDefault();
        $('#intervention_status').val('analyse-en-cours');
        startCheckOtherDemandes();
    });

    /**
     * Action des boutons / liens (bouton Annuler)
     */
    $('button[data-url]').on('click', function() {
        window.location = $(this).data('url');
    });

    /**
     * Réinitialisation de la demande
     */
    $interventionForm.on('reset', function(e) {
        // On demande confirmation pour réinitialiser le formulaire de demande de création
        if (!confirm("Voulez-vous vraiment réinitialiser cette demande ?\nToutes les informations saisies seront alors perdues.")) {
            // La réponse est "non" alors on empêche la réinitialisation du formulaire
            e.preventDefault();
        } else {
            // Pour resetter le formulaire, le plus simple c'est de recharger la page
            window.bigLoadingDisplay(true);
            location.reload();
        }

        // On réinitialise le wizard
        wizard.smartWizard('reset');
        setTimeout(function() {
            ajaxRecuperationAnnuaire();
        }, 250);
    });
});
