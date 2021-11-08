/**
 * Listing des Répartition des évènements et des indisponibilités
 */
$(document).ready(function () {

    let $tableauResultat = $('#tableau-resultats');

    /**
     * Renvoie une url
     */
    function getServiceUrl(exploitant, mois, annee)
    {
        return $tableauResultat.data('url')
        + '/' + (exploitant != '' ? exploitant : 0)
        + '/' + mois
        + '/' + annee;
    }

    function setExportUrl() {
        let exploitant = $('#repartition_exploitant').val();
        let mois = $('#repartition_mois').val();
        let annee = $('#repartition_annee').val();
        let serviceUrl = getServiceUrl(exploitant, mois, annee);
        $('.exports a').each(function() {
            $(this).attr('href', serviceUrl + '/' + $(this).data('export-type'));
        });
    }

    /**
     * Sur changement de valeur de formulaire
     */
    $('select').on('change', function() {
        // Rafraichissement du contenu du tableau en fonction des valeurs du formulaire
        let exploitant = $('#repartition_exploitant').val();
        let mois = $('#repartition_mois').val();
        let annee = $('#repartition_annee').val();
        // On lance la requête
        window.bigLoadingDisplay(true);
        $.ajax({
            url: getServiceUrl(exploitant, mois, annee),
            method: 'GET',
        })
        .done(function(response) {
            $tableauResultat.html($(response).find('#tableau-resultats').html());
        })
        .fail(function() {
            alert("Un erreur est survenue lors de la récupération des informations. Merci de réessayer plus tard.");
        })
        .always(function() {
            window.bigLoadingDisplay(false);
        });

        // Changement des urls des boutons d'export
        setExportUrl();
    });
    setExportUrl();
});
