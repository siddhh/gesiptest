$(document).ready(function() {

    /** Permet d'afficher ou non les opérations associées à un composant */
    $('.composant-item').click(function(e) {
        e.preventDefault();
        $(this).parents('li').find('ul').slideToggle(200);
        $(this).find('i').toggleClass('fa-rotate-90');
    });

    /** Permet d'afficher ou non les opérations en fonction au critère Aujourd'hui / Future */
    let affichageOperations = function() {
        // On récupère si nous souhaitons afficher les résultats dit "Aujourd'hui"
        let aujourdhui = $('*[name="choix-date"]:checked').val() === "aujourdhui";

        // On supprime la classe d-none
        $('.operation-item').removeClass('d-none');

        // Si on souhaite afficher les résultats dit "Aujourd'hui", on masque tous les autres
        if (aujourdhui) {
            $('.operation-item[data-aujourdhui="0"]').addClass('d-none');
        }

        // On masque les composants qui n'ont plus d'opérations à afficher
        $('.composant-item').each(function() {
            let $composantItem = $(this);
            $composantItem.removeClass('d-none');
            if ($composantItem.parent().find('.operation-item:not(.d-none)').length === 0) {
                $composantItem.addClass('d-none');
            }
        });

        // On masque les domaines qui n'ont plus de composants à afficher
        $('.domaine-item').each(function() {
            let $domaineItem = $(this);
            $domaineItem.removeClass('d-none');
            if ($domaineItem.find('.composant-item:not(.d-none)').length === 0) {
                $domaineItem.addClass('d-none');
            }
        });
    };
    // On lance dès l'ouverture de la page l'affichage des opérations
    affichageOperations();

    /** Lorsque l'on change le champ choix-data, pour filtrer les résultats */
    $('*[name="choix-date"]').change(function() {
        affichageOperations();
    });
});
