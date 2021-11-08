$(document).ready(function() {

    /**
     * Initialisations
     */
    var requeteAjax = null;

    /**
     * Gestion des radiobox "multi-niveaux"
     */

    // Permet d'afficher / cacher les cases à cocher de second niveau lors d'un clic sur un radio de premier niveau
    $('.multiradio .mainradio').on('change', function() {
        let $mainRadio = $(this);
        $('.multiradio .mainradio').not($mainRadio).prop('checked', false);
        let $subradio = $mainRadio.parent().find('.subradios');
        $('.multiradio .subradios').not($subradio).hide();
        if ($subradio.length > 0) {
            $subradio.show();
            if ($subradio.find(':checked').length == 0) {
                $subradio.find('input:first').prop('checked', true);
            }
        }
    });

    /**
     * Rafraichissement de la vue lors du clic sur "Visualiser"
     */
    $('form').on('submit', function(event) {
        // Bloque l'envoi du formulaire au serveur
        event.preventDefault();
        // Récupère les données selectionnées dans le formulaire
        let annee = $('select[name="annee"]').val();
        let mode = $('input[name="mode"]:checked').val();
        // Initialise la récupération asynchrone
        let queryUrl = '/meteo/statistiques/interventions/' + mode + '/' + annee;
        window.bigLoadingDisplay(true);
        if (requeteAjax != null) {
            requeteAjax.abort();
        }
        // Lance la récupération asynchrone
        requeteAjax = $.ajax({
            url: queryUrl,
            method: 'GET',
        }).done(function(reponse) {
            // En cas de succès, on met à jour le(s) contenu(s) de la page devant être remplacé(s)
            let $html = $(reponse);
            $html.find('.ajax-replace-contents').each(function(){
                let jquerySelector = $(this).data('ajax-replace-selector');
                if (!jquerySelector) {
                    jquerySelector = '#' + $(this).attr('id');
                }
                $(jquerySelector).html($html.find(jquerySelector).html());
            });
        }).fail(function() {
            // En cas d'echec, on avertit l'utilisateur
            alert('Impossible de récupérer les demandes dans la même période concernée.');
        }).always(function() {
            // Termine la récupération asynchrone
            window.bigLoadingDisplay(false);
        });
    });

});
