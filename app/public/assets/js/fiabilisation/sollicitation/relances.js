$(function() {

    /**
     * On initialise le nécessaire pour le bon fonctionnement du script
     */
    // On déclare quelques variables
    let actionUrl = $('.page-list form').attr('action');
    let actionRequest = null;
    let $checkboxesContainer = $('.checkall-container');
    let $actionsBtn = $('.btn.submit');
    let $copieMail = $('input[name="copieMail"]')
    let $modaleConfirmation = $('.modal-confirm');
    let $resetBtn = $('button[type="reset"]');
    // On désactive le bouton d'action si jamais lors de l'actualisation de la page ils sont toujours actifs
    $actionsBtn.prop('disabled', 'disabled');

    /**
     * Activation / Désactivation du bouton "Relancer les services" en fonction du nombre de cases cochées
     */
    $checkboxesContainer.on('CheckboxesChange', function(e, nbr) {
        $actionsBtn.prop('disabled', (nbr > 0) ? '' : 'disabled');
    });

    /**
     * Déclaration de quelques fonctions utiles
     */
    // Effectue la requête vers l'api pour relancer les services
    let submitSollicitation = function() {
        // On affiche le big loading
        window.bigLoadingDisplay(true);
        // Si une requête est déjà en cours, on l'annule
        if (actionRequest !== null) {
            actionRequest.abort();
        }
        //
        let servicesIds = [];
        $checkboxesContainer.data('checkedBoxes').each(function(){
            servicesIds.push($(this).val());
        });
        actionRequest = $.ajax({
            type: 'post',
            url: actionUrl,
            dataType: 'json',
            data: {
                servicesIds: servicesIds,
                copyMail: $copieMail.is(':checked') ? 1 : 0
            },
            success: function(data) {
                // // On renvoie le formulaire de recherche pour forcer le rechargement de la page...
                $('form[name="recherche"]').submit();
            },
            error: function(error) {
                // Si nous ne sommes pas dans le cas d'une requête annulée
                if (error.status !== 0) {
                    alert('Impossible de poursuivre l\'action demandée. Le traitement a été annulé.');
                    window.bigLoadingDisplay(false);
                }
            }
        });
    };

    /**
     * Lors d'un clic sur le bouton de "Reset"
     */
    $resetBtn.click(function(e) {
        e.preventDefault();
        var $filtres = $('form[name="recherche"]').find('select');
        $filtres.val("");
        $filtres.trigger('change');
    });

    /**
     * Lors d'un clic sur le bouton "Relancer les services"
     * On demande confirmer, et on lance une requête ajax afin de pouvoir solliciter les services sélectionnés
     */
    $actionsBtn.click(function(e) {
        e.preventDefault();
        // On affiche la modale de confirmation
        $modaleConfirmation.modal('show');
    });

    /**
     * Lors d'un clic sur le bouton de confirmation de la fenêtre modale
     */
    $modaleConfirmation.find('.confirm').click(function(e) {
        e.preventDefault();
        submitSollicitation();
    });
});
