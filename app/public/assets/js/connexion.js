$(document).ready(function() {

     /**
     * Faire apparaître/disparaître la ligne de modification de mot de passe si invité
     */
    function permuteAffichage() {
        var input = $('#form_label').val();
        if (input === "invite") {
            $('.pasInvite').hide();
        } else {
            $('.pasInvite').show();
        }
    }
    $('#form_label').on('change', permuteAffichage);
    permuteAffichage();

    function passagePamaretres() {
        var serviceSelectionne = $('#form_label').val();
        $("a.motdepasse-modifie").attr('href', '/modifierMotdepasse/' +serviceSelectionne);
    }

    passagePamaretres();
    $('#form_label').on('change', passagePamaretres);

    /**
     * Faire apparaître/disparaître le masquage du mot de passe
     */
    $('.toggle-password').on('click', function() {
        $(this).toggleClass("fa-eye fa-eye-slash");
        var input = $("#form_motdepasse");
        if (input.attr("type") === "password") {
            input.attr("type", "text");
        } else {
            input.attr("type", "password");
        }
    });

    /*
     * Lorsque le bouton "Oui" est cliqué, on lance la réinitialisation proprement dite
     */
    function onServiceMotdepasseReinitialisation() {
        var modal = $('#MotdepasseOublieModal');
        var serviceId = modal.attr('data-service-id');
        console.log('Appel du serveur pour demander la réinitialisation du mot de passe...');
        var pChargement = $('<p></p>')
            .append($('<img />').attr('src', '/assets/img/loadingcharte.gif'))
            .css('text-align', 'center');
        modal.find('.modal-body').empty().append(pChargement);
        modal.find('.modal-footer').hide();
        // appel webservice de réinitialisation de mot de passe service
        $.post("/ajax/service/motdepasse/reinitialise/" + serviceId, function() {
            console.log(' > Mot de passe réinitialisé.');
            modal.find('.modal-body').empty().append(
                $('<p></p>').append(
                    'Nous venons d\'envoyer un e-mail contenant des paramètres de connexion temporaire à l\'application GESIP sur la BALF ',
                    $('<strong></strong>').text(modal.attr('data-service-email')),
                    '.'
                )
            );
            modal.find('.modal-footer').empty().append(
                $('<button></button>')
                    .attr({"type": "button", "data-dismiss": "modal"})
                    .addClass('btn btn-secondary')
                    .text('Fermer la fenêtre')
            ).show();
        }).fail(function() {
            console.log(' > Erreur lors de la réinitialisation du mot de passe.');
        });
    }

    /*
     * Réinitialise le mot de passe
     */
    $('.motdepasse-oublie').on('click', function () {
        console.log(' > Récupération des infos du service à réintialiser.');
        var serviceId = $('#form_label').val();
        $.get('/ajax/service/' + serviceId, function(service) {
            console.log('Ouverture popin réinitialisation du mot de passe d\'un service.');
            var modal = $('#MotdepasseOublieModal');
            modal.attr({
                "data-service-id": service.id,
                "data-service-email": service.email,
                "data-service-label": service.label,
            });
            var pAdvert = $('<p></p>').append(
                'Le service ',
                $('<strong></strong>').text(service.label),
                ' va recevoir ses paramètres de connexion sur la BALF ',
                $('<a></a>').attr('href', 'mailto:' + service.email).text(service.email)
            );
            modal.find('.modal-body').empty().append(
                pAdvert,
                $('<p></p>').text('Souhaitez-vous confirmer ?')
            );
            modal.find('.modal-footer').empty().append(
                $('<button></button>')
                    .attr({"type": "button", "data-dismiss": "modal"})
                    .addClass('btn btn-secondary')
                    .text('Non'),
                $('<button></button>')
                    .attr({"type": "button"})
                    .addClass('btn btn-primary reinitialise-motdepasse')
                    .text('Oui')
                    .on('click', onServiceMotdepasseReinitialisation)
            );
            modal.modal('show');
        }).fail(function(){
            console.log(' > La récupération des informations du service a échoué.');
        });
    });

});
