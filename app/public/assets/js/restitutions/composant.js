$(function () {

    /**
     * On initialise quelques variables ainsi que la pagination.
     */
    let $modalFluxDemandes = $('.modal-flux-demandes');
    Pagination.init();

    /**
     * Lorsque nous cliquons sur un bouton permettant de récupérer les demandes d'intervention des
     *  composants impactants et impactés.
     */
    $('.card-flux-gesip').click(function(e) {
        e.preventDefault();
        e.stopPropagation();
        const type = $(this).data('type');
        $modalFluxDemandes.modal('show');
        if (type === 'sortants') {
            $modalFluxDemandes.find('.modal-header .titre').html('Liste des demandes des composants impactés');
        } else if (type === 'entrants') {
            $modalFluxDemandes.find('.modal-header .titre').html('Liste des demandes des composants impactants');
        }
        appelServeurDemandesFlux(type);
        $modalFluxDemandes.data('type', type);
    });

    /**
     * Fonction permettant d'appeler le serveur pour récupérer les demandes d'interventions pour les flux
     *  entrants et sortants.
     */
    const appelServeurDemandesFlux = function(type, page = 1) {
        Pagination.purge();
        const url = window.location.href + '/interventions/' + type + '/' + page;
        $modalFluxDemandes.find('tr.item').remove();
        $modalFluxDemandes.find('.table-empty').hide();
        $modalFluxDemandes.find('.table-loading').show();

        $.ajax({
            url: window.location.href + '/interventions/' + type + '/' + page,
            method: 'GET'
        })
        .done(function(reponse) {
            Pagination.maj(reponse.pagination);
            if(reponse.pagination.total === 0) {
                $modalFluxDemandes.find('.table-empty').show();
            } else {
                for(var i = 0 ; i < reponse.donnees.length ; i++) {
                    let demande = reponse.donnees[i];
                    let $newDemande = $('<tr class="item"></tr>');
                    $newDemande
                        .append('<td><a href="' + encodeURI(demande.numeroLien) + '" target="_blank">' + demande.numero + '</a></td>')
                        .append('<td><a href="' + encodeURI(demande.composantLien) + '" target="_blank">' + demande.composantLabel + '</a></td>')
                        .append('<td>' + demande.etat + '</td>')
                        .append('<td>' + demande.nature + '</td>')
                        .append('<td>' + demande.motif + '</td>')
                        .append('<td>' + demande.description + '</td>')
                        .append('<td>' + demande.dateDebut + '</td>')
                        .append('<td><a href="' + encodeURI(demande.demandeParLien) + '" target="_blank">' + demande.demandeParLabel + '</a></td>');

                    $modalFluxDemandes.find('tbody').append($newDemande);
                }
            }
        })
        .fail(function(erreur) {
            if(erreur.status !== 0) {
                alert("Impossible de récupérer les données pour l'instant.");
            }
        })
        .always(function() {
            $modalFluxDemandes.find('.table-loading').hide();
        });
    }

    /**
     * Pagination
     */
    Pagination.changementDePage(function(page, $elt) {
        appelServeurDemandesFlux($modalFluxDemandes.data('type'), page);
    });

    /**
     * Permet de déployer les balfs de la restitution d'un composant
     */
    $('.restitution-composant-balf-toggle-on, .restitution-composant-balf-toggle-off').click(function(e) {
       e.preventDefault();
       $('.restitution-composant-balf-toggle-on, .restitution-composant-balf-toggle-off, .restitution-composant-balf').toggleClass('restitution-composant-balf__showed');
    });
});
