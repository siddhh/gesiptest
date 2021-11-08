$(document).ready(function() {

    // Initialisation
    let $filtresContainer = $('.page-filters');
    let urlAction = $filtresContainer.data('url');
    let $selectExploitant = $filtresContainer.find('#historique_exploitant');
    let $selectComposant = $filtresContainer.find('#historique_composant');
    let $filtresForm = $filtresContainer.find('form');

    // Si on retourne en arrière, on recharge la page
    $(window).on("popstate", function(e) {
        window.bigLoadingDisplay(true);
        location.reload();
    });

    // Fonction permettant de rafraichir la liste des composants en fonction de l'exploitant saisie
    const refreshComposantsParExploitant = function () {
        let idExploitant = $selectExploitant.val();
        $selectComposant.find('option').addClass('d-none');
        $selectComposant.find('option[data-exploitant-id]').each(function() {
            let $option = $(this);
            let ids = $option.data('exploitant-id').toString().split('|');
            if ($.inArray(idExploitant, ids) !== -1) {
                $option.removeClass('d-none');
            }
        });
        $selectComposant.selectpicker('refresh');
    };
    refreshComposantsParExploitant();

    // Lorsque nous changeons d'exploitant dans le formulaire de filtres
    $selectExploitant.change(function(e) {
        e.preventDefault();
        $selectComposant.val('');
        refreshComposantsParExploitant();
    });

    // Lorsque l'on clique sur la soumission du formulaire
    $filtresForm.submit(function(e) {
        e.preventDefault();

        // On remet à zéro pour les erreurs
        $filtresForm.find('.form-control-error').removeClass('form-control-error');
        $filtresForm.find('.form-label-error').removeClass('form-label-error');
        $filtresForm.find('.form-errors').html('');

        // On récupère les données du formulaire
        const data = $(this).serializeArray().reduce(function(obj, item) {
            obj[item.name.replace('historique[', '').replace(']', '')] = item.value;
            return obj;
        }, {});

        // Si tous les champs nécessaires ont été saisie
        let erreursDetectees = [];
        // Si le champ Exploitant est vide
        if (data['exploitant'] === '') {
            erreursDetectees.push({ 'champ': 'exploitant', 'message': 'Le champ est obligatoire.' });
        }
        // Si le champ Période début est vide
        if (data['composant'] === '') {
            erreursDetectees.push({ 'champ': 'debut', 'message': 'Le champ est obligatoire.' });
        }
        // Si le champ Période fin est vide
        if (data['annee'] === '') {
            erreursDetectees.push({ 'champ': 'fin', 'message': 'Le champ est obligatoire.' });
        }
        // Si il n'y a pas d'erreur
        if (erreursDetectees.length === 0) {
            // on formate l'url d'action et on va chercher les informations
            let urlQuery = urlAction
                .replace('%23SE%23', data['exploitant'])
                .replace('%23CO%23', data['composant'])
                .replace('%23AN%23', data['annee']);

            // On lance la requête
            window.bigLoadingDisplay(true);
            $.get(urlQuery)
                .done(function(response) {
                    $('#donnees').html($(response).find('#donnees').html());
                    history.pushState(null, null, urlQuery);
                })
                .fail(function() {
                    alert("Un erreur est survenue lors de la récupération des informations. Merci de réessayer plus tard.");
                })
                .always(function() {
                    window.bigLoadingDisplay(false);
                });

        // Sinon, on affiche les erreurs
        } else {
            $.each(erreursDetectees, function(i, d) {
                let $fieldGroup = $filtresForm.find('*[name="historique[' + d['champ'] + ']"]').parents('.form-group');
                $fieldGroup.find('.form-control').addClass('form-control-error');
                $fieldGroup.find('label').addClass('form-label-error');
                $fieldGroup.find('.form-errors').append($('<div></div>').html(d['message']))
            });
        }
    });
})
