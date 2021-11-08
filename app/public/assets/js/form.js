$(document).ready(function() {

    /**
     * Système générique de vidage de champ
     */
    $('.reset-field').each(function() {

        var $this = $(this);
        var $field = $this.parent().find('input');
        $this.hide();

        if ($field.val() != '') {
            $this.show();
        }

        $this.click(function() {
            $field.val('');
            $this.hide();
        });

        $field.on('keyup', function() {
            if ($field.val() != '') {
                $this.show();
            } else {
                $this.hide();
            }
        });
    });

    /**
     * Système d'intérogation de l'annuaire LDAP
     */
    window.resetFormLdap = function() {
        $('.form-ldap').each(function() {
            var $parent = $(this);
            var type = $parent.data('type') || 'structures';
            var $field = $parent.find('input.form-control');
            var $reset = $parent.find('.reset-field');
            var $loader = $parent.find('.fa-spinner');
            var timeoutRequest = null;
            var $ldap_resultat = $parent.find('.form-ldap-resultats');
            $field.prop('autocomplete', 'off');

            if($field.val() !== '') {
                $reset.show();
            }

            if($ldap_resultat.length === 0) {
                $ldap_resultat = $('<div class="form-ldap-resultats"></div>');
                $parent.append($ldap_resultat);
            }

            $ldap_resultat.html('');
            $ldap_resultat.append(
                "<table class=\"table table-bordered table-hover\">" +
                "   <thead>"+
                "       <tr>"+
                "           <th>Nom</th>"+
                "           <th>Adresse électronique</th>"+
                "       </tr>"+
                "   </thead>"+
                "   <tbody>"+
                "   </tbody>"+
                "</table>"
            );

            $reset.off('click');
            $reset.on('click', function() {
                $ldap_resultat.hide();
                $loader.hide();
                clearTimeout(timeoutRequest);
                $field.val('');
                $reset.hide();
            });

            $ldap_resultat.off('click');
            $ldap_resultat.on('click', 'tbody tr', function() {
                $field.val($(this).find('td:last').text());
                $ldap_resultat.hide();
            });

            $field.off('keyup');
            $field.on('keyup', function() {
                $reset.show();
                $loader.hide();
                clearTimeout(timeoutRequest);
                if ($field.val().length >= 3) {
                    $reset.hide();
                    $ldap_resultat.hide();
                    $loader.show();
                    timeoutRequest = setTimeout(function() {
                        $.ajax({
                            url: '/ajax/ldap/recherche/' + type + '?recherche=' + $field.val(),
                            method: 'GET',
                            dataType: 'json',
                            success: function (response) {
                                var len = response.donnees.length;
                                if (len > 0) {
                                    $ldap_resultat.find('tbody tr').remove();
                                    response.donnees.sort(function (a, b) {
                                        if (a.nom > b.nom) {
                                            return 1;
                                        }
                                        if (a.nom < b.nom) {
                                            return -1;
                                        }
                                        return 0;
                                    });
                                    for (var i = 0; i < len; i++) {
                                        $ldap_resultat.find('tbody').append("<tr><td>" + (response.donnees[i])['nom'] + "</td><td>" + (response.donnees[i])['mail'] + "</td></tr>");
                                    }
                                    $ldap_resultat.show();
                                    $ldap_resultat.scrollTop(0);
                                }
                            },
                            error: function (erreur) {
                                if (erreur.status !== 0) {
                                    alert("Impossible de récupérer les données de l'annuaire LDAP pour l'instant.");
                                }
                            },
                            complete: function () {
                                $loader.hide();
                                $reset.show();
                            }
                        });
                    }, 500);
                }
            });
        });
    };
    window.resetFormLdap();

    /**
     * Annuaire type
     */
    window.loadAnnuaireType = function () {
        $('.form-annuaire-type').each(function(e) {
            /**
             * Initialisation de variables pour nous faciliter la vie par la suite
             */
            let $formAnnuaireType = $(this);
            let $modale = $formAnnuaireType.find('.modal-annuaire-type');
            let $table = $formAnnuaireType.find('table');
            let $inputsContainer = $formAnnuaireType.find('.form-annuaire__inputs');

            /**
             * Lors d'un changement dans les filtres
             */
            $table.find('select').change(function(e) {
                e.preventDefault();
                let $this = $(this);
                let filtreType = $this.data('column');
                let valeurCherchee = $this.val();

                $table.find('.hide-' + filtreType).removeClass('hide-' + filtreType);

                if (valeurCherchee !== '') {
                    $table.find('tbody td[data-column="' + filtreType + '"]').each(function(i, row) {
                        let $row = $(row);
                        if ($row.html() !== valeurCherchee) {
                            $row.parents('tr').addClass('hide-' + filtreType)
                        }
                    });
                }
            });

            /**
             * Lors d'un clic sur le bouton permettant d'afficher la modale
             */
            $formAnnuaireType.find('button.open-modal').click(function(e) {
                e.preventDefault();
                // Récupère les valeurs selectionnées actuellement
                let checkedValues = [];
                $formAnnuaireType.find('.form-annuaire__inputs input').each(function(){
                    checkedValues.push($(this).val());
                });
                // Défini les cases correspondant aux valeurs actuellement selectionnées
                $table.find('tbody tr').each(function(){
                    let $tr = $(this);
                    let $inputCheckAllBox = $tr.find('.checkall-box');
                    let value = $inputCheckAllBox.val();
                    if (checkedValues.indexOf(value) >= 0) {
                        $inputCheckAllBox.prop('checked', 'checked');
                    } else {
                        $inputCheckAllBox.prop('checked', null);
                    }
                    $tr.toggleClass('checkall-box-checked', $inputCheckAllBox.is(':checked'));
                });
                // Coche les cases à cocher si nécessaire
                $table.find('.checkall-box-checked').find('input').prop('checked', 'checked');
                if ($table.find('.checkall-box').length === $table.find('.checkall-box-checked').length) {
                    $table.find('.checkall').prop('checked', 'checked');
                    $('.checkall').trigger('change');
                }
                $modale.modal({ 'backdrop': 'static', 'keyboard': false });
            });

            $modale.on('shown.bs.modal', function () {
                $modale.find('.modal-body').scrollTop(0);
            });

            $modale.find('.btn-cancel').click(function(e) {
                e.preventDefault();
                $modale.modal('hide');
            });

            $modale.find('.btn-apply').click(function(e) {
                e.preventDefault();
                let allowEmpty = $(this).data('allow-empty');
                let donneesSaisies = $modale.find('.checkall-container').data('checkedBoxes');
                let confirmMessage = 'Souhaitez-vous confirmer la saisie ?';
                if (allowEmpty == 1 && donneesSaisies.length === 0) {
                    confirmMessage = "Aucun des services figurant dans la liste de diffusion n'a été sélectionné. Ces services ne recevront pas le courriel d'information. \nVoulez-vous valider votre saisie ?";
                }
                if (confirm(confirmMessage)) {
                    $inputsContainer.html('');
                    donneesSaisies.each(function() {
                        $inputsContainer.append($('<input type="hidden" name="' + $inputsContainer.data('name') + '" value="' + $(this).val() + '" />'));
                    });
                    $formAnnuaireType.find('.nb').html(donneesSaisies.length);
                }
                $modale.modal('hide');
            });
        });
    };
    window.loadAnnuaireType();
});
