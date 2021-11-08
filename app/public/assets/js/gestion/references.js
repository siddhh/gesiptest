$(document).ready(function () {

    /**
     * Objet JS permettant de gérer les appels serveurs d'ajout / modfication / suppression
     */
    var API = {
        referenceType: '',
        baseUrl: '/ajax/reference/',
        init: function(referenceType) {
            referenceType = referenceType.split('\\').pop();
            referenceType = referenceType.replace(/[\w]([A-Z])/g, function(m) {
                return m[0] + "_" + m[1];
            }).toLowerCase();
            API.referenceType = referenceType;
            API.baseUrl += referenceType;
        },
        appelServeur: function(url, method, data, success, always) {
            $.ajax({
                url: url,
                method: method,
                transformRequest: function(obj) {
                    var str = [];
                    for(var p in obj)
                        str.push(encodeURIComponent(p) + '=' + encodeURIComponent(obj[p]));
                    return str.join('&');
                },
                data: data,
                complete: always,
                success: success,
                error: function(xhr) {
                    if (xhr.status === 422) {
                        var data = JSON.parse(xhr.responseText);
                        var $modal = $('#erreurServeurModal');
                        $modal.find('.modal-body p').html(data.errors.pop());
                        $modal.modal('show');
                    } else {
                        alert("Impossible d'effectuer cette opération pour le moment.")
                    }
                }
            });
        },
        formattageDonnees: function($reference) {
            let data = {};
            $reference.find('input, select').each(function(i){
                let fieldName = API.referenceType + "[" + $(this).attr('name') + "]";
                data[fieldName] = $(this).val();
            });
            return data;
        },
        ajout: function($reference, done, always) {
            var data = API.formattageDonnees($reference);
            this.appelServeur(API.baseUrl, 'POST', data, done, always);

        },
        modification: function(referenceId, $reference, done, always) {
            var data = API.formattageDonnees($reference);
            this.appelServeur(API.baseUrl + "/" + referenceId, 'PUT', data, done, always);
        },
        suppression: function(referenceId, done, always) {
            this.appelServeur(API.baseUrl + "/" + referenceId, 'DELETE', {}, done, always);
        }
    };

    /**
    * On initialise les variables dont nous avons besoin
    */
    var $templateDisplayItem = null;
    var $templateEditingItem = null;
    var $table = $('.table-data-reference');
    var $tableBody = $table.find('tbody');
    var referenceType = $table.data('reference-type');
    API.init(referenceType);

    /**
    * On récupère le template d'une ligne entière
    */
    $templateDisplayItem = $('<tr>').append($('.template-display-item').html());
    $templateEditingItem = $('<tr class="item-editing">').append($('.template-editing-item').html());
    $templateEditingItem.find('input, select').attr('id', null);

    /**
    * On fait un brin de toilette
    */
    $('.template-display-item').remove();
    $('.template-editing-item').remove();

    /**
    * Événement lors d'un clic sur le bouton d'ajout d'une entrée
    */
    $('.btn-add').click(function(e) {
        e.preventDefault();
        var $newEntry = $($templateEditingItem[0].outerHTML);
        $tableBody.append($newEntry);
        $newEntry.find('input:first').focus();
        window.resetFormLdap();
        formStateRefresh();
    });

    /**
     * Évènement lors d'un clic sur le bouton de modification d'une entrée
     */
    $tableBody.on('click', '.btn-edit', function(e) {
        e.preventDefault();
        var $entry = $(this).parents('tr');
        var $properties = $entry.find('td[data-property]');
        $editEntry = $($templateEditingItem[0].outerHTML);
        $properties.each(function() {
            var $property = $(this);
            var $field = $editEntry.find('*[name="' + $property.data('property') + '"]');

            if ($field.prop('tagName') === "INPUT") {
                $field.attr('value', $property.html());
            } else if ($field.prop('tagName') === "SELECT") {
                $field.find('option[value="' + $property.html() + '"]').attr('selected', 'selected');
            }
        });
        $entry.after($editEntry);
        $entry.addClass('d-none')
        $editEntry.attr('data-editing-id', $entry.attr('data-id'));
        $editEntry.find('input:first').focus();
        window.resetFormLdap();
        formStateRefresh();
    });

    /**
     * Évènement lors d'un clic sur le bouton d'annulation de modification ou d'ajout d'une entrée
     */
    $tableBody.on('click', '.btn-cancel', function(e) {
       e.preventDefault();
       var $editEntry = $(this).parents('tr');
       var $modal = $('#confirmationAnnulationModal');
       $modal.attr('data-row', $tableBody.find('tr').index($editEntry));
       $modal.modal('show');
    });
    $('#confirmationAnnulationModal').on('click', '.btn.btn-primary', function(e) {
        e.preventDefault();
        var $modal = $(this).parents('.modal');
        var rowId = $modal.attr('data-row');
        var editEntryId = $($tableBody.find('tr').get(rowId)).attr('data-editing-id');

        if (editEntryId !== undefined) {
           $tableBody.find('tr[data-id="' + editEntryId + '"]').removeClass('d-none');
        }
        $tableBody.find('tr').get(rowId).remove();
        formStateRefresh();
    });

    /**
     * Évènement lors d'un clic sur le bouton de suppression d'une entrée
     */
    $tableBody.on('click', '.btn-delete', function(e) {
       e.preventDefault();
        var $entry = $(this).parents('tr');
        var $modal = $('#confirmationSuppressionModal');
        $modal.attr('data-row', $tableBody.find('tr').index($entry));
        $modal.find('.label').html($entry.find('td.column-label').html());
        $modal.modal('show');
    });
    $('#confirmationSuppressionModal').on('click', '.btn.btn-primary', function(e) {
        e.preventDefault();
        var $modal = $(this).parents('.modal');
        var rowId = $modal.attr('data-row');
        var $entry = $($tableBody.find('tr').get(rowId));
        var entryId = $entry.attr('data-id');
        $entry.addClass('item-loading');
        $entry.find('input, select, button').prop('disabled', 'disabled');

        API.suppression(entryId, function(reponse) {
            $entry.remove();
            formStateRefresh();
        }, function() {
            $entry.removeClass('item-loading');
            $entry.find('input, select, button').prop('disabled', null);
        });
    });

    /**
     * Évènement lors d'un clic sur le bouton d'application de l'ajout ou modification d'une entrée
     */
    $tableBody.on('click', '.btn-apply', function(e) {
        e.preventDefault();
        var $editEntry = $(this).parents('tr');
        var editingId = $editEntry.data('editing-id');

        // Si ajout
        if (editingId === undefined) {
            $editEntry.addClass('item-loading');
            $editEntry.find('input, select, button').prop('disabled', 'disabled');
            API.ajout($editEntry, function(reponse) {
                $entry = $($templateDisplayItem[0].outerHTML);
                $entry.attr('data-id', reponse.data.nouvelId);
                var $properties = $editEntry.find('*[name]');
                $properties.each(function() {
                    var $property = $(this);
                    var $entryProperty = $entry.find('td[data-property="' + $property.attr('name') + '"]');
                    $entryProperty.html($property.val());
                });
                $editEntry.after($entry);
                $editEntry.remove();
                formStateRefresh();
            }, function() {
                $editEntry.removeClass('item-loading');
                $editEntry.find('input, select, button').prop('disabled', null);
            });

        // Sinon c'est une modification
        } else {
            var $entry = $('tr[data-id="' + editingId + '"]');
            $editEntry.addClass('item-loading');
            $editEntry.find('input, select, button').prop('disabled', 'disabled');
            API.modification(editingId, $editEntry, function(reponse) {
                var $properties = $editEntry.find('*[name]');
                $properties.each(function() {
                    var $property = $(this);
                    var $entryProperty = $entry.find('td[data-property="' + $property.attr('name') + '"]');
                    $entryProperty.html($property.val());
                });
                $editEntry.remove();
                $entry.attr('data-id', reponse.data.nouvelId);
                $entry.removeClass('d-none');
                formStateRefresh();
            }, function() {
                $editEntry.removeClass('item-loading');
                $editEntry.find('input, select, button').prop('disabled', null);
            });
        }

    });

    /**
     * Fonction permettant de définir le comportement lorsqu'une référence est en modification / ajout.
     * - Si nous avons encore des champs en édition :
     *      - Alors on demande confirmation si l'utilisateur souhaite fermer la fenêtre
     *      - On désactive les boutons d'ajout / modification d'autres lignes
     *      - On opacifie les autres lignes de références
     * - Sinon rien
     */
    function formStateRefresh() {
        var $autreReferences = $tableBody.children('tr:not(.item-editing)');
        var $btns = $('.btn-add, .btn-edit, .btn-delete');

        $(window).off('beforeunload');
        $btns.prop('disabled', null);
        $autreReferences.removeClass('item-transparency');

        if ($('.item-editing').length > 0) {
            $(window).on('beforeunload', function() {
                return "Des modifications sont toujours en cours et ne seront pas enregistrées si vous quittez la page actuelle. Souhaitez-vous quand même quitter la page ?";
            });
            $btns.prop('disabled', 'disabled');
            $autreReferences.addClass('item-transparency');
        }
    }
});
