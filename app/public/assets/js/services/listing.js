/**
 * Listing des services
 */
$(document).ready(function () {

    /**
     * Initialisation
     */
    var url_donnees = '/ajax/services/listing/';
    var $tableau = $('#tableau-resultats');
    var $donnees = $tableau.find('tbody');
    var $loading = $tableau.find('.table-loading');
    var $vide = $tableau.find('.table-empty');
    var $filtre = $('.filtre');
    var requete_en_cours = null;
    Pagination.init();

    /**
     * Méthodes utiles
     */
    // Ajout d'un service dans le tableau de résultats
    var tableau_ajout_service = function(service) {
        let $trService = $('<tr class="item"></tr>').append(
            '<td><a href="/gestion/services/' + service.id + '/modifier">' + service.label + '</a></td>',
            '<td>' + (service.estServiceExploitant ? "Oui" : "Non") + '</td>',
            '<td>' + (service.estStructureRattachement ? "Oui" : "Non") + '</td>',
            '<td><a href="mailto:' + service.email + '">' + service.email + '</a></td>'
        );
        if ($tableau.hasClass('switch-user-allowed') && service.id !== $tableau.data('user-id')) {
            $trService.append('<td><a class="btn btn-sm btn-success" href="/?_switch_user=' + service.id + '" title="Se connecter en tant que"><i class="fa fa-address-card-o "></i></a></td>')
        }
        $donnees.append($trService);
    };
    // Purge des services dans le tableau de résultats
    var tableau_purge = function() {
        $donnees.find('tr.item').remove();
    };
    // Permet de faire un appel serveur
    var appel_serveur = function(filtre = '', page = 1) {
        Pagination.purge();
        $vide.hide();
        $loading.show();
        tableau_purge();
        requete_en_cours = $.ajax({
                url: url_donnees + page + "?filtre=" + encodeURIComponent(filtre),
                method: 'GET'
            })
            .done(function(reponse) {
                Pagination.maj(reponse.pagination);
                if(reponse.pagination.total === 0) {
                    $vide.show();
                } else {
                    for(var i = 0 ; i < reponse.donnees.length ; i++) {
                        tableau_ajout_service(reponse.donnees[i]);
                    }
                }
            })
            .fail(function(erreur) {
                if(erreur.status != 0) {
                    alert("Impossible de récupérer les données pour l'instant.");
                }
            })
            .always(function() {
                $loading.hide();
            });
    };

    /**
     * Récupération des informations
     */
    appel_serveur($filtre.val());

    /**
     * Filtrage
     */
    $filtre.on('keyup', function() {
        if(requete_en_cours) {
            requete_en_cours.abort();
        }
        appel_serveur($(this).val(), 1);
    });

    /**
     * Pagination
     */
    Pagination.changementDePage(function(page) {
        appel_serveur($filtre.val(), page);
    });


});
