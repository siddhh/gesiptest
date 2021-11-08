/**
 * Script permettant de gérer la mise à jour des balfs de plusieurs services et annuaires à la fois
 * avec recherche préalable.
 */
$(document).ready(function() {

    /**
     * Initialisation
     */
    // On récupère l'objet jQuery représentant la div contenant les résultats
    let $ajaxResultat = $('#ajax-resultat');

    /**
     * Lorsque l'on clique sur le bouton de modification d'une entrée
     */
    $ajaxResultat.on('click', '.btn-saisie-balf', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // On récupère les éléments utiles
        let $this = $(this);
        let $parent = $this.parents('tr');
        let $inputClicked = $parent.find('input');
        if ($this.parents('tr').hasClass('checkall-box-checked')) {
            $parent = $ajaxResultat.find('.checkall-box-checked');
        }
        let $annulationBtn = $parent.find('.btn-annulation-saisie-balf');
        let $modificationBtn = $parent.find('.btn-saisie-balf');
        let $colonneBalf = $parent.find('.colonne-balf');
        let $input = $colonneBalf.find('input');

        // On agit sur la visibilité des éléments
        $colonneBalf.find('span').addClass('d-none');
        $input.removeClass('d-none');
        $annulationBtn.removeClass('d-none');
        $modificationBtn.addClass('d-none');

        // On donne le focus au champ
        $inputClicked.focus();
        $inputClicked.select();
    });

    /**
     * Lorsque l'on clique sur le bouton d'annulation d'une modification d'une entrée
     */
    $ajaxResultat.on('click', '.btn-annulation-saisie-balf', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // On récupère les éléments utiles
        let $this = $(this);
        let $parent = $this.parents('tr');
        let $spanClicked = $parent.find('span');
        if ($this.parents('tr').hasClass('checkall-box-checked')) {
            $parent = $ajaxResultat.find('.checkall-box-checked');
        }
        let $annulationBtn = $parent.find('.btn-annulation-saisie-balf');
        let $modificationBtn = $parent.find('.btn-saisie-balf');
        let $colonneBalf = $parent.find('.colonne-balf');
        let $span = $colonneBalf.find('span');
        let $input = $colonneBalf.find('input');

        // On agit sur la visibilité des éléments
        $input.addClass('d-none');
        $input.removeClass('form-control-error');
        $span.removeClass('d-none');
        $modificationBtn.removeClass('d-none');
        $annulationBtn.addClass('d-none');

        // On réinitialise le champ avec la valeur du span
        $input.each(function(e) {
            $(this).val($(this).parent().find('span').text());
        });
    });

    /**
     * Lorsque l'on clique sur l'input ou les liens, on stop la propagation de l'évènement à des couches supérieures.
     */
    $ajaxResultat.on('click', 'input, a', function(e) {
        e.stopPropagation();
    });

    /**
     * Lorsque l'on saisie quelque chose dans un champ de modification de balf.
     * Ceci est utilisé pour modifier toutes les balfs sélectionnées d'un coup.
     */
    $ajaxResultat.on('keyup', '.colonne-balf input', function(e) {
        let $input = $(this);
        if ($input.parents('tr').hasClass('checkall-box-checked')) {
            $ajaxResultat.find('.checkall-box-checked .colonne-balf input').val($input.val());
        }
    });

    /**
     * Fonction permettant de désactiver la modification des services, si ceux-ci sont des services par défauts
     */
    let desactivationModificationServices = function() {
        $ajaxResultat.find('.colonne-balf[data-service-id]').each(function() {
            let $colonne = $(this);
            if ($colonne.data('service-balf') === $colonne.find('span').text()) {
                $ajaxResultat.find('input[data-type="services"][data-id="' + $colonne.data('service-id') + '"]')
                    .parents('tr')
                    .find('.btn-saisie-balf')
                    .attr('disabled', 'disabled');
            }
        });
    };

    /**
     * Lorsque la page a été rafraichie suite à une recherche.
     */
    $('.form-ajax-replace').on('replaced', function (e) {
        desactivationModificationServices();
    });

    /**
     * Lorsque l'on clique sur le bouton de soumission des modifications
     */
    $ajaxResultat.on('click', '.btn-submit', function(e) {
        // On initialise notre action
        e.preventDefault();
        let $submitBtn = $(this);
        let data = { 'services': [], 'annuaires': [] };

        // On parcourt les modifications en attente
        $ajaxResultat.find('.form-control:not(.d-none)').each(function() {
            let $input = $(this);
            let idObject = $input.data('id');
            let typeObject = $input.data('type');
            let newValue = $input.val();
            let oldValue = $input.parent().find('span').text();

            // Si la nouvelle valeur est différente de l'ancienne, alors on l'ajoute à la requête de modifs
            if (newValue !== oldValue) {
                data[typeObject].push({ id: idObject, balf: $input.val() });
            }
        });

        // On active le bigloading screen
        window.bigLoadingDisplay(true);

        // On supprime le statut d'erreur sur les inputs
        $ajaxResultat.find('input.form-control-error').removeClass('form-control-error');

        // On envoi les modifications au serveur !
        $.ajax({
            url: $submitBtn.data('url-action'),
            method: "POST",
            contentType: "application/json; charset=utf-8",
            dataType: "json",
            data: JSON.stringify(data)
        })
        .done(function(retour) {
            // Si le serveur plante
            if (retour.success === false) {
                // On affiche un message d'erreur
                window.afficherToast(retour.message, 'danger');

                // On met en évidence les erreurs renvoyées par le serveur
                if (retour.errors) {
                    $.each(retour.errors['services'], function(idx, idService) {
                        $ajaxResultat.find('input[data-id="' + idService + '"]').addClass('form-control-error');
                    });
                }

            // Sinon, tout s'est bien passée
            } else {
                // On affiche un message de succès
                window.afficherToast(retour.message, 'success');

                // On met à jour l'ihm
                $ajaxResultat.find('.form-control:not(.d-none)').each(function() {
                    // On récupère les éléments que l'on va utiliser
                    let $input = $(this);
                    let $parent = $input.parent();
                    let $span = $parent.find('span');
                    let $annulationBtn = $input.parents('tr').find('.btn-annulation-saisie-balf');
                    let $modificationBtn = $input.parents('tr').find('.btn-saisie-balf');

                    // On traite notre ihm
                    $input.addClass('d-none');
                    $span.removeClass('d-none');
                    $span.text($input.val());
                    $annulationBtn.addClass('d-none');
                    $modificationBtn.removeClass('d-none');

                    // Si la modification est de type Annuaire :
                    if ($input.data('type') === "annuaires") {
                        // Si la balf saisie est la même que la balf par défaut, alors on change l'ihm en fonction
                        if ($parent.data('service-balf') === $input.val()) {
                            $span.css({'opacity': '0.5'});
                        } else {
                            $span.css({'opacity': '1'});
                        }
                    // Si la modification est de type Services :
                    } else if ($input.data('type') === "services") {
                        // On vient changer toutes les adresses annuaires par défaut récupérées dans la liste des annuaires
                        $ajaxResultat.find('.colonne-balf[data-service-id="' + $input.data('id') + '"]').each(function() {
                            let $colonne = $(this);
                            if ($colonne.data('service-balf') === $colonne.find('span').text()) {
                                $colonne.find('span').text($input.val());
                                $colonne.find('input').val($input.val());
                            }
                            $colonne.data('service-balf', $input.val());
                        });
                    }
                });
            }
        })
        .fail(function() {
            alert("Une erreur est survenue lors de la modification.\nMerci de réessayer plus tard.");
        })
        .always(function() {
            window.bigLoadingDisplay(false);
        });
    });

    /**
     * Lorsque l'on souhaite quitter la page, on vérifie que nous n'avions pas d'éléments en attente de modification.
     * Si tel était le cas, on demande confirmation auprès de l'utilisateur pour fermer la page.
     */
    $(window).on('beforeunload', function() {
        if ($ajaxResultat.find('.form-control:not(.d-none)').length > 0) {
            return "Des modifications sont toujours en cours et ne seront pas enregistrées si vous quittez la page actuelle. Souhaitez-vous quand même quitter la page ?";
        }
    });
});
