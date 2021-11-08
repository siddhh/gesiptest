$(document).ready(function() {

    /**
     * Lors d'un click sur le bouton de suppression d'une documentation
     */
    $('.suppression').click(function() {
        let $bouton = $(this);
        let documentationTitre = $(this).parents('.card').find('.card-header').text();
        if (confirm('Souhaitez-vous confirmer la suppression de la documentation "' + documentationTitre + '" ?')) {
            let urlQuery = '/ajax/documentation/supprimer/' + $(this).data('id');
            window.bigLoadingDisplay(true);
            $.post(urlQuery)
                .done(function() {
                    $bouton.closest('.card').remove();
                    window.afficherToast('Le document a bien été supprimé.', 'success');
                })
                .fail(function() {
                   alert("Une erreur est survenue lors de la suppression de la documentation. Merci de réessayer plus tard.");
                })
                .always(function() {
                   window.bigLoadingDisplay(false);
                });
        }
    });

})
