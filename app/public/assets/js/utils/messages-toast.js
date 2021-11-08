$(function () {

    /**
     * Affichage des toasts déjà présent dans la page
     */
    // On récupère les toasts déjà présent dans la page (généré par twig du coup)
    let $toasts = $('.toast');
    // On les affiche
    $toasts.toast('show');
    // On supprime les toasts inutiles dans le DOM après leur affichage
    $toasts.on('hidden.bs.toast', function () {
        $(this).remove();
    });

    /**
     * On affiche un toast dans la page
     * (Si message est un array, alors chaque ligne est dans un paragraphe.)
     * (Les types de toast possibles : https://getbootstrap.com/docs/4.3/components/alerts/)
     *
     * @param message
     * @param type
     * @param temps
     */
    window.afficherToast = function(message, type, temps) {
        // On défini les valeurs par défaut
        temps = temps || 10000;
        type = type || 'success';

        // On formate notre nouveau toast
        var $toast = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="' + temps + '">');
        var $toastBody = $('<div class="toast-body alert alert-' + type + '">');
        var $btnClose = $('<button type="button" class="close" data-dismiss="toast" aria-label="Close"><span aria-hidden="true">&times;</span></button>');

        // Si le message est un tableau, alors pour chaque ligne du tableau on ajoute un paragraphe
        if (Array.isArray(message)) {
            $.each(message, function() {
                $toastBody.append($('<p>' + this + '</p>'));
            });
        // Sinon, nous n'ajoutons qu'un seul paragraphe avec la valeur
        } else {
            $toastBody.append($('<p>' + message + '</p>'));
        }

        // On ajoute le bouton close
        $toastBody.append($btnClose);
        // On ajoute le corps du toast dans le toast
        $toast.append($toastBody);
        // On ajoute le toast dans le container de toast
        $('.toast-container').append($toast);

        // On affiche le loast !
        $toast.toast('show');
        // On le supprime du DOM quand celui-ci est caché dans la page après affichage
        $toast.on('hidden.bs.toast', function () {
            $(this).remove();
        });
    };

});
