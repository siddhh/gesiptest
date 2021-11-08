/**
 * Après avoir chargé la page...
 */
$(function() {

    /**
     * Gestion de la fenetre modale permettant de modifier les dates de début et de fin
     */
    // Au moment de la validation de la fenetre modale, on vérifie les dates et on les importe dans le tableau et dans le formulaire
    function periodeValidation($modal) {
        // Récupération des champs et des valeurs utiles
        let dateDebutSemaine = new Date($modal.data('debut-semaine'));
        let dateFinSemaine = new Date($modal.data('fin-semaine'));
        let $dateDebut = $modal.find('#txtDateDebut');
        let dateDebut = $dateDebut.data('DateTimePicker').viewDate();
        let $dateFin = $modal.find('#txtDateFin');
        let dateFin = $dateFin.data('DateTimePicker').viewDate();
        let $messages = $modal.find('.messages').empty();
        // On réinitialise l'affichage des champs et label en erreurs
        $modal.find('.form-control-error').removeClass('form-control-error');
        $modal.find('.form-label-error').removeClass('form-label-error');
        // Réalise différents tests pour vérifier la validité des informations
        let errors = [];
        if (dateFin < dateDebut) {
            $dateDebut.addClass('form-control-error');
            $dateDebut.parents('.form-group').find('label').addClass('form-label-error');
            $dateFin.addClass('form-control-error');
            $dateFin.parents('.form-group').find('label').addClass('form-label-error');
            errors.push('La date de début est postérieure à la date de fin.');
        }
        if (errors.length === 0 && (dateDebut > dateFinSemaine || dateFin < dateDebutSemaine)) {
            $dateDebut.addClass('form-control-error');
            $dateDebut.parents('.form-group').find('label').addClass('form-label-error');
            $dateFin.addClass('form-control-error');
            $dateFin.parents('.form-group').find('label').addClass('form-label-error');
            errors.push('Les dates saisies ne sont pas comprises dans la semaine Météo (du ' + dateDebutSemaine.toLocaleDateString('fr-FR') + ' 00:00 au ' + dateFinSemaine.toLocaleDateString('fr-FR') + ' 23:59).');
        }
        // On affiche les messages d'erreurs si trouvé et on rend la main
        if (errors.length > 0) {
            $(errors).each(function() {
                let $alert = $('<div></div>').addClass('alert alert-danger').attr('role', 'alert').text(this);
                $messages.append($alert);
            });
            return false;
        }
        return true;
    }

    // lorsque l'utilisateur clique sur le bouton Valider de la modale
    $('.periode-validation').on('click', function() {
        let $modal = $(this).parents('.modal');
        if (periodeValidation($modal)) {
            let $tr = $('#table_meteo_evenements tr.current-dates-update').removeClass('current-dates-update');
            $modal.find('.form-datetimepicker').each(function() {
                let value = $(this).val();
                $($(this).data('target')).text(value);
            });
            let dateDebut = $('#txtDateDebut').val();
            let dateFin = $('#txtDateFin').val();
            $tr.find('.date-debut').text(dateDebut);
            $tr.find('.date-fin').text(dateFin);
            let $tdDates = $tr.find('td:nth-child(1)');
            $tdDates.find('input[id$="debut"]').val(dateDebut);
            $tdDates.find('input[id$="fin"]').val(dateFin);
        } else {
            return false;
        }
    });


    /**
     * Gestion modification des evenements
     */

    // Récupère le prototype du formulaire fourni par Symfony en filtrant uniquement les champs qui nous interesse
    let $tableEvenements = $('#table_meteo_evenements');
    let $meteoModifier = $('.meteo-modifier');
    let formTemplate = $('#liste_evenements_evenements').empty().data('prototype');

    // Modifie une ligne existante pour remplacer le contenu de ces cellules par des champs modifiables
    function setManagedLine($tr, action) {
        if ($tr.hasClass('managed')) {
            // Si cette ligne est déjà managée, seule la valeur du champ action est à modifier
            let $tdDates = $tr.find('td:nth-child(1) input[id$="action"]').val(action);
            $tr.removeClass('creation edition suppression').addClass(action);
        } else {
            // Si cette ligne n'est pas encore managée, on contruit des champs modifiable à partir du code proposé par le prototype
            let tabKey = $tableEvenements.find('tr.managed').length;
            let $formEvenement = $(formTemplate.replace(/__name__/g, tabKey));
            $tr.addClass('managed ' + action);
            // Récupère l'id et l'action
            let $inputId = $formEvenement.find('#liste_evenements_evenements_' + tabKey + '_id');
            let $tdDates = $tr.find('td:nth-child(1)');
            $inputId.val($tr.data('evenement-id'));
            $tdDates.append($inputId);
            let $inputAction = $formEvenement.find('#liste_evenements_evenements_' + tabKey + '_action');
            $inputAction.val(action);
            $tdDates.append($inputAction);
            // Récupère les champs dates (début / fin)
            let $inputDebut = $formEvenement.find('#liste_evenements_evenements_' + tabKey + '_debut');
            $inputDebut.val($tdDates.find('.date-debut').text()).addClass('d-none');
            $tdDates.append($inputDebut);
            let $inputFin = $formEvenement.find('#liste_evenements_evenements_' + tabKey + '_fin');
            $inputFin.val($tdDates.find('.date-fin').text()).addClass('d-none');
            $tdDates.append($inputFin);
            let $buttonModifierDate = $('<button></button>').attr({type: 'button', title: 'Editer'}).addClass('btn modifier-dates')
                .append($('<i></i>').addClass('fa fa-edit'));
            $tdDates.append($buttonModifierDate);
            // Récupère le champ impacts
            let $inputImpact = $formEvenement.find('#liste_evenements_evenements_' + tabKey + '_impact');
            let $tdImpact = $tr.find('td:nth-child(2)');
            let impactId = $tr.data('impact-id');
            if (impactId != undefined) {
                $inputImpact.val(impactId);
            }
            $tdImpact.empty().append($inputImpact);
            // Récupère le champ motif
            let $inputTypeOperation = $formEvenement.find('#liste_evenements_evenements_' + tabKey + '_typeOperation');
            let $tdTypeOperation = $tr.find('td:nth-child(3)');
            let motifId = $tr.data('type-operation-id');
            if (motifId != undefined) {
                $inputTypeOperation.val(motifId);
            }
            $tdTypeOperation.empty().append($inputTypeOperation);
            // Récupère le champ description
            let $txtDescription = $formEvenement.find('#liste_evenements_evenements_' + tabKey + '_description');
            let $tdDescription = $tr.find('td:nth-child(4)');
            $txtDescription.val($tdDescription.text()).addClass('form-control');
            $tdDescription.empty().append($txtDescription);
            // Récupère le champ commentaire
            let $txtCommentaire = $formEvenement.find('#liste_evenements_evenements_' + tabKey + '_commentaire');
            let $tdCommentaire = $tr.find('td:nth-child(5)');
            $txtCommentaire.val($tdCommentaire.text()).addClass('form-control');
            $tdCommentaire.empty().append($txtCommentaire);
        }
    }

    // Désactive ou non le bouton d'enregistrement (et l'avertissement si on quitte la page), en fonction de si il n'y a rien à enregistrer
    function activeEnregistrementButton() {
        if ($tableEvenements.find('tr.managed').length > 0) {
            $('.buttonsbar button').prop('disabled', false);
            $(window).on('beforeunload', function() {
                return "Des modifications sont toujours en cours et ne seront pas enregistrées si vous quittez la page actuelle. Souhaitez-vous quand même quitter la page ?";
            });
        } else {
            $('.buttonsbar button').prop('disabled', true);
            $(window).off('beforeunload');
        }
    }
    activeEnregistrementButton();

    // Si l'utilisateur clique sur le bouton ajouter, on ajoute un évenement
    $meteoModifier.on('click', 'button.evenement-creer', function() {
        let $tr = $($tableEvenements.find('tr.template')[0].outerHTML);
        $tr.removeClass('template d-none');
        $tableEvenements.find('tr.toolsbar').before($tr);
        setManagedLine($tr, 'creation');
        activeEnregistrementButton();
    });

    // Si l'utilisateur clique sur le bouton edition, on transforme la ligne pour y insérer des champs modifiables
    $meteoModifier.on('click', 'button.evenement-editer', function() {
        let $tr = $(this).parents('tr');
        setManagedLine($tr, 'edition')
        $(this).remove();
        activeEnregistrementButton();
    });

    // Si l'utilisateur clique sur le bouton suppression, on défini simplement le champ d'action à suppression et on fait disparaitre la ligne
    $meteoModifier.on('click', 'button.evenement-supprimer', function() {
        let $tr = $(this).parents('tr');
        // On cache la ligne 'gentiment' !
        $tr.fadeOut(500, function() {
            if ($tr.hasClass('creation')) {
                // Si la ligne à supprimer est en cours de création, elle existe pas en base donc on la supprime la ligne
                $(this).remove();
            } else {
                // On rend la ligne managée et on détermine qu'elle doit être supprimée
                setManagedLine($tr, 'suppression');
            }
            activeEnregistrementButton();
        });
    });

    // Si l'utilisateur souhaite modifier les dates d'un évenement
    $meteoModifier.on('click', 'button.modifier-dates', function() {
        let $tr = $(this).parents('tr');
        let $modal = $('#modal_modifier_dates');
        $('#txtDateDebut').val($tr.find('.date-debut').text());
        $('#txtDateDebut').data('DateTimePicker').date($tr.find('.date-debut').text());
        $('#txtDateFin').val($tr.find('.date-fin').text());
        $('#txtDateFin').data('DateTimePicker').date($tr.find('.date-fin').text());
        $tr.addClass('current-dates-update');
        $modal.find('.form-datetimepicker').datetimepicker();
        $modal.find('.alert').remove();
        $modal.find('.form-label-error').removeClass('.form-label-error');
        $modal.find('.form-control-error').removeClass('.form-control-error');
        $modal.modal('show');
    });

    /**
     * Gestion du formulaire
     */
    $('form').submit(function(e) {
        // Si le bouton enregistré est cliqué, on désactive l'avertissement d'abandon de la page courante
        $(window).off('beforeunload');
        let $form = $(this);
        if (!$form.hasClass('form-disabled-ajax')) {
            e.preventDefault();
            window.bigLoadingDisplay(true);

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: $form.serializeArray(),
                success: function(data) {
                    $('#table_meteo_evenements').html($(data).find('#table_meteo_evenements').html());
                    $('#meteo-composant').html($(data).find('#meteo-composant').html());
                },
                error: function(error) {
                    alert("Une erreur est survenue lors de la récupération des informations. Merci de réessayer un peu plus tard.");
                },
                complete: function() {
                    window.bigLoadingDisplay(false);
                }
            });
        }
    });
});
