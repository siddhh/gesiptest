$(document).ready(function() {
    /*
     * Suppression du pilote
     */
    $('.suppression-pilote').on('click', function () {
       console.log('fonction suppression-pilote');
       var piloteId = $('#btSupprimerPilote').attr('data-pilote-id');
       window.location = '/gestion/pilotes/' + piloteId + '/supprimer';
    });
  });
