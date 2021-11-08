$(document).ready(function() {

    var menuGestionReferentielsVisible = false;

    /*
     * Ouvre/ferme le sous-menu "Gestion des référentiels"
     */
    $('#btn-menu-gestion-referentiels').on('click', function(e) {
        e.stopPropagation();
        if (menuGestionReferentielsVisible === false) {
            menuGestionReferentielsVisible = true;
            $(this).nextUntil("#fin-menu-gestion-referentiels").removeAttr("hidden");
            $(document).one('click', function() {
                menuGestionReferentielsVisible = false;
                $('#btn-menu-gestion-referentiels').nextUntil("#fin-menu-gestion-referentiels").attr("hidden", true);
            });
        }
        else {
            menuGestionReferentielsVisible = false;
            $('#btn-menu-gestion-referentiels').nextUntil("#fin-menu-gestion-referentiels").attr("hidden", true);
        }
    });

});
