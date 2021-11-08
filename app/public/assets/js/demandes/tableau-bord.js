$(document).ready(function() {
    let $form = $('form');

    // Dès l'ouverture de la page si un filtre a été choisi, on recharge la page
    if ($form.serializeArray()[0].value !== '' || $form.serializeArray().length > 2) {
        $form.submit();
    }

    /**
     * En fonction de l'état, on affiche / cache certaines checkboxes
     */
    function onStatusChange() {
        let statusValue = $form.find('[name="recherche_demande_intervention[status]"]:checked').val();
        let $enRetardCheckBox = $form.find('.reponse-en-retard');
        if (['', 'EtatAccordee', 'EtatInterventionEnCours', 'EtatSaisirRealise'].indexOf(statusValue) >= 0) {
            $enRetardCheckBox.hide();
        } else {
            $enRetardCheckBox.show();
        }
        let $retourConsultationNegatifCheckBox = $form.find('.retour-consultation-negatif');
        if (statusValue == 'EtatAnalyseEnCours') {
            $retourConsultationNegatifCheckBox.hide();
        } else {
            $retourConsultationNegatifCheckBox.show();
        }
        $enRetardCheckBox.children('input').prop('checked', false);
        $retourConsultationNegatifCheckBox.children('input').prop('checked', false);
        $form.find('.demande-urgente input').prop('checked', false);
    }

    //A l'ouverture ou au rafraichissement de la page on gère l'affichage des filtres secondaires
    onStatusChange();

    //Evènement sur sélection
    $form.find('[name="recherche_demande_intervention[status]"]').on('change', onStatusChange);

     /**
     * Lors du changement de valeur, on envoie le formulaire
     */
    $form.find('input,select').on('change', function(){
        $form.submit();
    });

});
