/**
 * Recherche des composants
 */
$(document).ready(function () {

    /**
     * Initialisation
     */
    var url_donnees = '/ajax/composant/recherche/';
    var $tableau = $('#tableau-resultats')
    var $donnees = $tableau.find('tbody');

    /**
     * Méthodes utiles
     */
    // Ajout d'un composant dans le tableau de résultats
    var tableau_ajout_composant = function(composant) {
        $donnees.append(
            $('<tr class="item"></tr>').append(
                '<td><a href="/gestion/composants/' + composant.id + '/modifier">' + composant.label + '</a></td>',
                '<td>' + (composant.equipe !== undefined ? composant.equipe.label : '') + '</td>',
                '<td>' + (composant.pilote !== undefined ? composant.pilote.nom_complet_court : '') + '</td>',
                '<td>' + (composant.exploitant !== undefined ? composant.exploitant.label : '') + '</td>',
                '<td>' + composant.usager_id + '</td>',
                '<td>' + (composant.domaine !== undefined ? composant.domaine.label : '') + '</td>',
                '<td>' + (composant.archive_le ? "Oui" : "Non") + '</td>',
            )
        );
    };

    // Permet de faire un appel serveur
    $('.recherche-composant').on('click', function(event){
        event.preventDefault();
        $('.recherche-composant').prop('disabled', 'disabled');
        $('.recherche-composant').addClass('btn-loading');
        $.ajax({
            url: url_donnees,
            method: 'POST',
            data:{
                Label: $('#recherche_composant_label option:selected').text(),
                Equipe: $('#recherche_composant_equipe').val(),
                Pilote: $('#recherche_composant_pilote').val(),
                Exploitant: $('#recherche_composant_exploitant').val(),
                Usager: $('#recherche_composant_usager').val(),
                Domaine: $('#recherche_composant_domaine').val(),
                IsArchived: ($('#recherche_composant_is_archived').is(':checked') ? 1 : 0)
            }
        })
        .done(function(reponse) {
            $('#tableau-resultats tbody').empty();
            $('.page-list').show();
            if (reponse.donnees.length === 0){
                $tr=$('<tr><td colspan="7" font-size="1rem" ><b>Les critères sélectionnés ne corresponde à aucun composant.</td></tr>');
                $('#tableau-resultats tbody').append($tr);
            } else {
                $(reponse.donnees).each(function(){
                    tableau_ajout_composant(this);
                });
            }
            $('html, body').stop(true, true).animate({
                scrollTop: $("#tableau-resultats").offset().top
            }, 500);

            $('.recherche-composant').prop('disabled', null);
            $('.recherche-composant').removeClass('btn-loading');
        })
        .fail(function(erreur) {
            if (erreur.status !== 0) {
                alert("Impossible de récupérer les données pour l'instant.");
            }
        });
    });
});
