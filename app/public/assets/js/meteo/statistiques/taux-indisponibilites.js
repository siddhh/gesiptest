$(function() {

    /**
     * Initialisations
     */
    let $form = $('form[name="taux_indisponibilites"]');
    let $equipesSelecteur = $('#taux_indisponibilites_equipe');
    let $pilotesSelecteur = $('#taux_indisponibilites_pilote');

    /**
     * Affiche ou cache certains choix lors de sélection de critères
     */

    // Si on change l'équipe, on selectionne les pilotes concernés
    $equipesSelecteur.on('change', function() {
        let $optionSelectionnee = $(this).find(':selected');
        if ($optionSelectionnee.val().length > 0) {
            let piloteIds = $optionSelectionnee.data('pilote-ids').toString().split(',');
            $pilotesSelecteur.find('option').each(function() {
                let value = $(this).val();
                if (value == '' || piloteIds.indexOf(value) >= 0) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            $pilotesSelecteur.find('option').show();
        }
        $pilotesSelecteur.selectpicker('refresh');
    });

    // Si on change le pilote, on sélectionne l'équipe associée.
    $pilotesSelecteur.on('change', function() {
        let $optionSelectionnee = $(this).find(':selected');
        if ($optionSelectionnee.val().length > 0) {
            let equipeId = $optionSelectionnee.data('equipe-id');
            $equipesSelecteur.find('option').each(function() {
                let value = $(this).val();
                if (value == '' || equipeId == value) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            $equipesSelecteur.find('option').show();
        }
        $equipesSelecteur.selectpicker('refresh');
    });

    /**
     * Modifie les liens des boutons d'export en fonction des critères sélectionnés
     */
    function updateExportButtons() {
        let urlPath = '/meteo/statistiques/taux-indisponibilites'
            + '/' + $('#taux_indisponibilites_moduleSource').val()
            + '/' + $('#taux_indisponibilites_frequence').val()
            + '/' + $('#taux_indisponibilites_periodeDebut').val()
            + '/' + $('#taux_indisponibilites_periodeFin').val();
        let equipeId = $('#taux_indisponibilites_equipe').val();
        let piloteId = $('#taux_indisponibilites_pilote').val();
        let exploitantId = $('#taux_indisponibilites_exploitant').val();
        let parameters = {};
        if (equipeId > 0 ) {
            parameters['equipe'] = equipeId;
        }
        if (piloteId > 0 ) {
            parameters['pilote'] = piloteId;
        }
        if (exploitantId > 0 ) {
            parameters['exploitant'] = exploitantId;
        }
        let queryString = '?' + $.param(parameters);
        if (queryString.length <= 1) {
            queryString = '';
        }
        $('.export-xlsx').attr('href', urlPath + '/xlsx' + queryString);
        $('.export-pdf').attr('href', urlPath + '/pdf' + queryString);
    }
    $form.find('select').on('change', function() {
        updateExportButtons();
    });
    updateExportButtons();

    /**
     * Lorsqu'un bouton d'export est cliqué, on valide le formulaire avant de suivre le lien
     */
    $('.export-xlsx, .export-pdf').on('click', function(event){
        let errors = validateForm();
        if (errors.length > 0) {
            return false;
        }
    })

    /**
     * Helper permettant de reconditionner les données lors de l'envoi de la requète
     */
    function serializeJson(data) {
        let o = {};
        $.each(data, function() {
            let name = this.name;
            let value = this.value || '';
            if (o[name]) {
                if (!Array.isArray( o[name])) {
                    o[name] = [o[name]];
                }
                o[name].push(value);
            } else {
                o[name] = value;
            }
        });
        return o;
    }

    /**
     * Gestion de la soumission du formulaire
     */

    // Effectue quelques controles de validation avant envoi...
    function validateForm() {
        let errorMessages = [];
        let $debutPeriode = $('#taux_indisponibilites_periodeDebut');
        let anneeDebut = $debutPeriode.val();
        let $finPeriode = $('#taux_indisponibilites_periodeFin');
        let anneeFin = $finPeriode.val();
        $form.find('.form-control-error').removeClass('form-control-error');
        $form.find('.form-label-error').removeClass('form-label-error');
        $form.find('.form-errors').html('');
        if (anneeDebut > anneeFin) {
            errorMessages.push({
                message: 'L\'année de fin ne peut être postérieure à l\'année de début.',
                fieldSelector: '#taux_indisponibilites_periodeFin'
            });
        }
        $.each(errorMessages, function() {
            let $fieldGroup = $form.find(this.fieldSelector).parents('.form-group');
            $fieldGroup.find('.form-control').addClass('form-control-error');
            $fieldGroup.find('label').addClass('form-label-error');
            $fieldGroup.find('.form-errors').append($('<div></div>').html(this.message))
         });
        return errorMessages;
    }

    // Intercepte l'envoi du formulaire classique pour récupérer et interpréter le résultat de facon asynchrone
    $form.on('submit', function(event){
        event.preventDefault();
        // Effectue quelques controles de validation avant envoi...
        let errors = validateForm();
        if (errors.length > 0) {
            return false;
        }
        // Récupère la réponse et l'affiche
        window.bigLoadingDisplay(true);
        $.ajax({
            url: '/ajax/meteo/statistiques/taux-indisponibilites',
            method: 'POST',
            data: serializeJson($form.serializeArray())
        }).done(function(response) {
            let data = response.data;
            if (data.composants.length > 0) {
                $('.meteo-resultat-vide').addClass('d-none');
                $('.meteo-resultat-tableau').removeClass('d-none');
                $('.composants-length').text(data.composants.length);
                let $tableHeaderLine = $('.meteo-resultat-tableau thead tr');
                let $tableBody = $('.meteo-resultat-tableau tbody');
                $tableHeaderLine.empty();
                $tableBody.empty();
                $tableHeaderLine.append($('<th></th>'));
                // pour chaque sous-périodes
                $(data.subperiodes).each(function() {
                    $th = $('<th></th>').text(this.periode.label);
                    $tableHeaderLine.append($th);
                });
                // pour chaque composants
                $(data.composants).each(function() {
                    $trComposant = $('<tr></tr>');
                    $trComposant.append($('<td></td>').text(this.label));
                    let composantId = this.id;
                    $(data.subperiodes).each(function() {
                        let tauxIndisponibilite = this.indisponibilite[composantId];
                        $trComposant.append($('<td></td>').text(tauxIndisponibilite + ' %'));
                    });
                    $tableBody.append($trComposant);
                });
            } else {
                $('.meteo-resultat-vide').removeClass('d-none');
                $('.meteo-resultat-tableau').addClass('d-none');
            }
        }).fail(function() {
            alert('Impossible de récupérer la liste des composants pour le moment. Merci de réessayer plus tard.');
        }).always(function() {
            window.bigLoadingDisplay(false);
        });
    });

});
