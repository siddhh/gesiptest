$(document).ready(function () {

    /**
     * Interface de gestion des modèles de cartes d'identité
     */

    let $divGestionModeleCarteIdentite = $('.gestion-modele-carte-identite');

    // Si l'utilisateur choisi un autre modèle à activer
    $divGestionModeleCarteIdentite.find('input[name="modele-actif"]').click(function() {
        let $trCourant = $(this).parents('tr');
        let modeleLabel = $trCourant.find('td:nth-child(1)').text() + '  - ' + $trCourant.find('td:nth-child(2)').text();
        if (confirm('Souhaitez-vous vraiment activer ce modèle "' + modeleLabel + '" ?')) {
            window.bigLoadingDisplay(true);
            $.ajax({
                url: '/ajax/modele-carte-identite/activer/' + $(this).val(),
                method: 'PUT'
            })
            .done(function() {
                window.afficherToast('Modèle "' + modeleLabel + '" activé.', 'success');
            })
            .fail(function() {
                window.afficherToast('Impossible d\'activer ce modèle.\nVeuillez réessayer plus tard.', 'danger');
            })
            .always(function() {
                window.bigLoadingDisplay(false);
            });
        } else {
            return false;
        }
    });

    // Si l'utilisateur choisi un autre modèle à activer
    $divGestionModeleCarteIdentite.find('.supprimer-modele').click(function() {
        let $trCourant = $(this).parents('tr');
        let modeleLabel = $trCourant.find('td:nth-child(1)').text() + '  - ' + $trCourant.find('td:nth-child(2)').text();
        let confirmText = 'Souhaitez-vous vraiment supprimer ce modèle "' + modeleLabel + '" ?';
        if ($trCourant.find('input[name="modele-actif"]').is(':checked')) {
            confirmText = "Vous demandez à supprimer le modèle actuellement actif. Si vous poursuivez plus aucun modèle sera actif.\r\n" + confirmText;
        }
        if (confirm(confirmText)) {
            window.bigLoadingDisplay(true);
            $.ajax({
                url: '/ajax/modele-carte-identite/' + $(this).val(),
                method: 'DELETE'
            })
            .done(function() {
                $trCourant.remove();
                window.afficherToast('Modèle "' + modeleLabel + '" supprimé.', 'success');
            })
            .fail(function() {
                window.afficherToast('Impossible de supprimer ce modèle.\nVeuillez réessayer plus tard.', 'danger');
            })
            .always(function() {
                window.bigLoadingDisplay(false);
            });
        } else {
            return false;
        }
    });

});
