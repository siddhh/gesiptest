/**
 * Change de page lorsque le bouton 'Oui' est cliqué dans la fenetre de confirmation de visualisation.
 */
$('#confirmationVisualisationModal .openView').on('click', function(event){
    location = $(this).data('url');
});

/**
 * Affiche la modale de confirmation de visualisation si nécessaire
 */
$('a[data-view-need-confirm]').on('click', function(event){
    event.preventDefault();
    $('#confirmationVisualisationModal .openView').data('url', $(this).attr('href'));
    $('#confirmationVisualisationModal').modal('show');
});

/**
 * Affiche la liste des demandes d'intervention par défaut cachées car associées à aucune équipe
 */
$('.montrerDemandesSansEquipe').on('click', function(){
    $('table.demandes-sans-equipe').removeClass('d-none');
    $(this).hide();
});
