$(document).ready(function(){

    //Sélection d'un composant -> bouton Valider renseigné de la sélection
    $('#selectionDeComposant').on('change', function(){
        let page_composant = "/gestion/synthese-composant/" + $(this).val();
        $('#composantSelectionne').data('chemin', page_composant);
    });

    //Validation du choix d'un composant -> page correspondante appelée
    $('#composantSelectionne').on('click',function(){
        let href_selectionne = $(this).data('chemin');
        if (href_selectionne !== undefined) {
            document.location.href = href_selectionne;
        }
    })

});
