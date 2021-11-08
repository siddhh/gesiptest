$(document).ready(function() {
   /*
    * Suppression du service
    */
   $('.suppression-service').on('click', function () {
      var serviceId = $('#btSupprimerService').attr('data-service-id');
      window.location = '/gestion/services/' + serviceId + '/supprimer';
   });

   $('#btSupprimerService').on('click', function () {
       //initialisation
        var modal = $('#ServiceSupprimeModal');
        var serviceId = $('#btSupprimerService').attr('data-service-id');
        var nomService = $('#btSupprimerService').attr('data-service-label');
        titre = ('Le service '+nomService+' sera définitivement supprimé. ');
        tableauResultats = "";
        listeTableau = "";
        tableauFin = "";
        var pChargement = $('<p></p>')
            .append($('<img />').attr('src', '/assets/img/loadingcharte.gif'))
            .css('text-align', 'center');

        //affichage du chrono
        modal.find('.modal-body').empty().append(pChargement);
        modal.find('.modal-footer').hide();
        modal.show();

        //requête Ajax pour obtenir la liste des composants et missions rattachées au service
        requete_en_cours = $.ajax({
            url: '/ajax/service/composants/' + serviceId,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                var len = response.donnees.length;

                if (len > 0)
                {
                    tableauResultats = ("<div class=\"page-list\"><table id=\"tableau-resultats\" data-url=\"\" class=\"table\"><thead class=\"thead-dark\"><tr><th scope=\"col\">Composant</th><th scope=\"col\">Mission</th></tr></thead><tbody>");
                    tableauFin = ("</tbody></table></div>");
                    titre = ("Le service " +nomService+ " est associé dans l'annuaire GESIP aux éléments suivants:");
                    for(var i = 0 ; i < response.donnees.length ; i++) {
                        listeTableau =( listeTableau +"<tr><td>" +response.donnees[i].composant+ "</td><td>" +response.donnees[i].mission+"</td></tr>");
                    }
                    $("#lien-export-csv").removeAttr("hidden");
                    $("#lien-export-impression").removeAttr("hidden");
                }
            },
            error: function(erreur) {
                if(erreur.status != 0) {
                    alert("Impossible de récupérer les données de l'annuaire des composants pour l'instant.");
                }
            },
            complete: function() {
                modal.find('.modal-body').empty().append(
                    $('<p></p>').append(titre + tableauResultats+listeTableau+tableauFin+"Confirmez-vous la suppression de ce service?" )
                    )
                .show();
                modal.find('.modal-footer').show();
            }
        });

    });

 });
