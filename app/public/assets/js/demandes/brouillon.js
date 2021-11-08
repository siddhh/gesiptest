$(document).ready(function () {

    /**
     * Lors d'un clic sur le bouton de suppression d'une demande (côté liste brouillons)
     */
    $(".btn-delete").on('click', function(){
        var $modal = $('#supprimerBrouillonModal');
        $modal.data('idASupprimer', $(this).data('id'));
        $modal.find('.modal-body p').html('La demande n°' + $(this).data('numero') +' sera définitivement supprimée. Voulez-vous confirmer?');
        $modal.modal('show');
    });

    /**
     * Lors d'un clic sur la modale de confirmation d'une suppression d'une demande
     */
    $("#btn-ouiSupprimer").on('click', function(){
        window.location = '/demandes/' + $('#supprimerBrouillonModal').data('idASupprimer') +'/supprimer';
    });

});
