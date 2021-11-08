$(document).ready(function() {
    /**
     * Initialisation
     */

    $("#service_label").focus();

    // activer champ "Structure Principale" si, et seulement si, case "Structure de rattachement" décochée
    function onChangeStructureRattachement() {
        if ($(this).is(':checked'))
        {
            $("#service_idStructurePrincipale, #service_structurePrincipale").attr("disabled", true);
            $("#service_idStructurePrincipale option, #service_structurePrincipale option").removeAttr('selected');
        }
        else
        {
            $("#service_idStructurePrincipale, #service_structurePrincipale").attr("disabled", false);
        }
    }
    $("#service_estStructureRattachement").on('change', onChangeStructureRattachement);
    $("#service_estStructureRattachement").change();

    // charger dans champ "Structure Principale" la liste des services déclarés dans Gesip
    $("#service_idStructurePrincipale").on('focus', function() {
        var select = this;
        $.ajax({
            url: '/ajax/service',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $(select).empty();
                $(select).append('<option value=""></option>');
                var len = response.donnees.length;
                for(var i = 0; i < len; i++) {
                    $(select).append("<option value='" + (response.donnees[i])['id'] + "'>" + (response.donnees[i])['label'] + "</option>");
                }
            },
            error: function() {
                alert("Impossible de récupérer les données pour l'instant.");
            }
        });
    });

    // afficher une croix à droite du champ "BALF" à la moindre saisie dans celui-ci
    $("#service_email").on('input', function() {
        if ($(this).val() === '')
        {
            $("#efface-balf").attr('hidden', true);
        }
        else
        {
            $("#efface-balf").attr('hidden', false);
        }
    });

    // effacer champ "BALF" en cliquant sur la croix le jouxtant
    $("#efface-balf").on('click', function() {
        $("#service_email").val('');
        $(this).attr('hidden', true);
    });

    // afficher le tableau des BALF présentes dans le LDAP en cas de recherche demandée (bouton "Rechercher")
    $("#recherche-balf").on('click', function() {
        var chaine = $("#service_email").val();
        if (chaine.length > 2)
        {
            $.ajax({
                url: '/ajax/ldap/recherche/structures?recherche=' + chaine,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $("#table-balf").attr('hidden', true);
                    var len = response.donnees.length;
                    if (len > 0)
                    {
                        $("tr").remove();
                        response.donnees.sort(function(a, b) {
                            if (a.nom > b.nom) { return 1; }
                            if (a.nom < b.nom) { return -1; }
                            return 0;
                        });
                        for(var i = 0; i < len; i++) {
                            $("#table-balf").append("<tr><td>" + (response.donnees[i])['nom'] + "</td><td>" + (response.donnees[i])['mail'] + "</td></tr>");
                        }
                        $("#table-balf").attr('hidden', false);
                        // copier dans le champ "BALF" la BALF de la ligne du tableau cliquée
                        $("tr").on('click', function() {
                            $("#service_email").val($(this).find("td:last").text());
                            $("#table-balf").attr('hidden', true);
                        });
                    }
                },
                error: function() {
                    alert("Impossible de récupérer les données pour l'instant.");
                }
            });
        }
    });

    // supprimer le formatage et le message d'erreur quand le champ en erreur est modifié
    $("#service_label").on('input', function() {
        $("#erreur-label").empty();
        $("#service_label").removeClass('erreur');
    });
    $("#service_email").on('input', function() {
        $("#erreur-email").empty();
        $("#service_email").removeClass('erreur');
    });

    /**
     * Affiche un message d'avertissement si l'utilisateur modifie ses propres roles (avant déconnexion)
     */
    let $divForm = $('.page-form');
    if ($divForm.data('current-user-id') === $divForm.data('update-user-id')) {
        let mainRole = $('#service_roles').val();
        let secondRole = $('#service_estRoleUsurpateur').is(':checked');
        $('form[name="service"]').on('submit', (event) => {
            if (
                (mainRole !== $('#service_roles').val() || secondRole !== $('#service_estRoleUsurpateur').is(':checked')) 
                    && !confirm('Vous êtes sur le point de changer vos propres droits. Vous serez déconnecté automatiquement pour prendre en compte ces changements. Souhaitez-vous continuer ?')) {
                event.preventDefault();
            }
        });
    }

});

