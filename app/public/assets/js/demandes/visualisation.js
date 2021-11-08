$(function() {

    /**
     * Initialisation
     */
    let $body = $('body');
    let demandeId = $('.page-header h2[data-demande-id]').data('demande-id');
    let $action_vue = $('.action-vue');
    let $action_btns = $('.card-actions');

    /**
     * Permet de cacher les impacts si le nombre est supérieur à deux composants.
     */
    $('.btn-composants-impactes-toggle-hide').click(function(e) {
        $(this).parents('.composants-impactes').toggleClass('hide');
    });

    /**
     * Lorsque l'on clique sur un bouton d'action, on doit charger la vue, on lancer l'action.
     */
    $('.card-actions button').click(function(e) {
        e.preventDefault();
        // On récupère l'action
        let action = $(this).data('action');

        // On supprime l'action en cours, si il y en a une
        $action_vue.html('');

        // On affiche le "chargement en cours..."
        window.bigLoadingDisplay(true);

        // On va chercher la vue
        $.ajax({
            url: '/demandes/' + demandeId + '/vue-action/' + action,
            method: 'GET'
        })
        .done(function(reponse) {
            if (reponse !== '') {
                $action_btns.hide();
                $action_vue.html(reponse);
                $('html, body').stop(1, 1).animate({
                    scrollTop: ($action_vue.offset().top)
                }, 250);

                // On initialise les time pickers si il y en a dans le formulaire d'action chargé.
                $action_vue.find('.timepicker').datetimepicker({
                    locale: 'fr',
                    format: 'L',
                    useCurrent: false,
                });

                if (action === "ActionRenvoyer") {
                    actionRenvoyerInit();
                }

                if (action === "ActionSaisirRealise") {
                    actionSaisirRealiseInit();
                }

                if (action === "ActionLancerInformation" || action === "ActionLancerConsultation") {
                    window.loadAnnuaireType();
                }

            } else {
                lancerAction(action);
            }
        })
        .fail(function(erreur) {
            alert("Une erreur s'est produite lors de la récupération du formulaire de l'action. Merci de réessayer plus tard.");
        })
        .always(function() {
            window.bigLoadingDisplay(false);
        });
    });

    /**
     * Lors d'un clic sur le bouton "Annuler" d'un formulaire d'action
     */
    $body.on('click', '.card-action-formulaire button[type=reset]', function(e) {
        $action_vue.html('');
        $action_btns.show();
    });

    /**
     * Lors d'un clic sur le bouton "Valider" d'un formulaire d'action
     */
    $body.on('submit', '.card-action-formulaire form', function(e) {
        e.preventDefault();
        lancerAction($($(this).parents('.card-action-formulaire').get(0)).data('action'));
    });

    /**
     * Lors d'un clic sur le bouton "Suivi des consultations" du bloc "Historique"
     */
    $('.btn-suivi-consultations').click(function(e) {
        // On supprime le comportement par défaut du bouton, et on récupère la fenêtre modale ainsi que l'id de la demande.
        e.preventDefault();
        let $modale = $('#suiviConsultationModale');

        // On affiche la modale, en mode "Chargement en cours..."
        $modale.find('.modal-loading').show();
        $modale.find('.modal-body').hide();
        $modale.modal('show');

        // On lance la requête de récupération des avis enregistrés
        $.ajax({
            url: '/ajax/demandes/' + demandeId + '/consultations',
            method: 'GET',
        })
        .done(function(reponse) {

            // On vide les tableaux (uniquement les données et pas les entêtes)
            $modale.find('table tbody tr').remove();

            // On parcours la liste des avis du CDB renvoyés par le serveur
            $.each(reponse.CDB, function(i, avis) {
                let dateAvis = new Date(avis.dateAvis);
                let dateConsultation = new Date(avis.dateConsultation);
                let $nouvelleLigne = $('<tr></tr>').append($('<td>' + dateConsultation.toLocaleDateString() + '<br/><small>' + dateConsultation.toLocaleTimeString() + '</small></td>'));
                if (dateAvis > 0) {
                    $nouvelleLigne.append($('<td>' + dateAvis.toLocaleDateString() + '<br/><small>' + dateAvis.toLocaleTimeString() + '</small></td>'))
                } else {
                    $nouvelleLigne.append($('<td></td>'))
                }
                if (avis.avis.length > 0) {
                    $nouvelleLigne.append($('<td class="' + (avis.avis === 'ok' ? 'avis-favorable' : 'avis-defavorable') + '">' + (avis.avis === 'ok' ? 'Favorable' : 'Défavorable') + '</td>'))
                } else {
                    $nouvelleLigne.append($('<td></td>'))
                }
                $nouvelleLigne.append($('<td class="column-commentaire">' + avis.commentaire + '</td>'));
                $modale.find('table.table-cdb tbody').append($nouvelleLigne);
            });

            // On parcours la liste des avis des services renvoyés par le serveur
            $.each(reponse.services, function(i, avis) {
                let $nouvelleLigne = $('<tr></tr>');
                $nouvelleLigne.append($('<td>' + avis.serviceLabel + '</td>'));
                $nouvelleLigne.append($('<td>' + avis.nbConsultation + '</td>'));

                if (avis.date === '') {
                    $nouvelleLigne.append($('<td></td>'));
                } else {
                    let dateAvis = new Date(avis.date);
                    $nouvelleLigne.append($('<td>' + dateAvis.toLocaleDateString() + '<br/><small>' + dateAvis.toLocaleTimeString() + '</small></td>'));
                }

                if (avis.avis === '') {
                    $nouvelleLigne.append($('<td></td>'));
                } else {
                    $nouvelleLigne.append($('<td class="' + (avis.avis === 'ok' ? 'avis-favorable' : 'avis-defavorable') + '">' + (avis.avis === 'ok' ? 'Favorable' : 'Défavorable') + '</td>'));
                }

                $nouvelleLigne.append($('<td class="column-commentaire">' + avis.commentaire + '</td>'));

                $modale.find('table.table-services tbody').append($nouvelleLigne);
            });

            // On désactive le chargement, et on affiche le corps de la modale
            $modale.find('.modal-loading').hide();
            $modale.find('.modal-body').show();
        })
        .fail(function(erreur) {
            alert("Problème de récupération des informations. Merci de réessayer plus tard.");
            $modale.modal('hide');
        });
    });

    /**
     * Fonction permettant de lancer une action au serveur.
     */
    let lancerAction = function(action) {
        // On récupère le bouton de l'action pour y récupérer le titre, ainsi que $formulaire
        let $btnAction = $('.card-actions button[data-action="' + action + '"]');
        let $cardFormulaire = $('.card-action-formulaire[data-action="' + action + '"]');
        let $formulaire = $cardFormulaire.find('form');
        let texteConfirmation = '';

        // Si l'action est "Lancer Consultation" alors on fait attention à la date limite de réponse !
        if (action === "ActionLancerConsultation" && $('#dateLimiteDecisionDme').length > 0) {
            var dateLimiteDecision = moment($('#dateLimiteDecisionDme').data('date') + ' 23:59:59');
            var dateSaisie = $('#lancer_consultation_dateLimite').data('DateTimePicker').viewDate();
            // Si la date saisie est supérieure à la date limite, on en informe l'utilisateur.
            if (dateSaisie > dateLimiteDecision) {
                texteConfirmation += "ATTENTION : La date limite de consultation est supérieure à la date de réponse attendue !\n";
            }
        }

        // Si l'utilisateur n'est pas habilité, on le prévient qu'il n'est pas habilité mais il peut quand même traiter
        //  la demande si besoin
        if ($btnAction.data('habilite') === 0) {
            texteConfirmation += "\nVous n'êtes pas habilités à traiter cette demande.";
            texteConfirmation += "\nVoulez-vous, malgré tout, effectuer l'action \"" + $btnAction.html().trim() +"\" ?";
        } else {
            texteConfirmation += "\nVoulez-vous effectuer l'action \"" + $btnAction.html().trim() +"\" ?";
        }

        // Si l'action existe dans la page, et l'utilisateur acccepte la confirmation d'éxécuter de l'action
        if ($btnAction.length === 1 && confirm(texteConfirmation)) {

            // On traite les données du formulaire de l'action
            let donnees = $formulaire.serializeArray().reduce(function(obj, item) {
                let name = item.name.replace($formulaire.attr('name') + '[', '').replace(']', '');

                // Si le nom représente un tableau de valeurs, alors on met en forme un vrai tableau : name => [value, ..]
                if (name.lastIndexOf('[]') === (name.length - 2)) {
                    name = name.replace('[]', '');
                    if (obj[name] === undefined) {
                        obj[name] = [];
                    }
                    obj[name].push(item.value);
                    // Sinon, c'est une donnée normale qui est représentée sous la forme : name => value
                } else {
                    obj[name] = item.value;
                }
                return obj;
            }, {});

            // On ajoute l'action dans les données à envoyer au serveur
            donnees['action'] = action;

            // On passe en chargement, et on supprime les indications d'erreurs potentiellement déjà présentes.
            window.bigLoadingDisplay(true);
            $cardFormulaire.removeClass('card-action-formulaire__erreur');
            $cardFormulaire.find('.form-control-error').removeClass('form-control-error');
            $cardFormulaire.find('.form-group.form-errors').removeClass('form-errors');
            $cardFormulaire.find('.form-errors').html('');

            // On lance la requête de l'exécution de l'action sur la demande d'intervention
            $.ajax({
                url: '/ajax/demandes/' + demandeId + '/action',
                method: 'POST',
                data: donnees
            })
            .done(function(reponse) {
                document.location.reload(true);
            })
            .fail(function(erreur) {
                console.log(erreur);
                if (erreur.status === 500) {
                    if (erreur.responseJSON && erreur.responseJSON.message) {
                        alert(erreur.responseJSON.message);
                    } else {
                        alert("Une erreur s'est produite lors de l'éxécution de l'action. Merci de réessayer plus tard.");
                    }
                } else if (erreur.status === 422) {
                    window.afficherToast("Les champs en rouge n'ont pas été saisis correctement. Merci de revoir votre saisie.", "danger");
                    $cardFormulaire.addClass('card-action-formulaire__erreur');

                    let erreurs = erreur.responseJSON.form;
                    $.each(erreurs, function(champ, message) {
                        let $champ = $('*[name="' + champ + '"]');
                        let $formGroup = $champ.parents('.form-group');
                        $champ.addClass('form-control-error');
                        $formGroup.addClass('form-errors');
                        $formGroup.addClass('form-errors');
                        $formGroup.find('.form-errors').append($('<div>' + message + '</div>'));
                    });
                }

                window.bigLoadingDisplay(false);
            });
        }
    };

    /************************************************************************************************
     * Formulaires particuliers des actions
     ************************************************************************************************/

    /**
     * Action "Donner son avis"
     */
    // Si un avis favorable est donné, il n'y a pas lieu, l'envoi de mail est désactivé.
    $body.on('change', '#donner_avis_avis input', function() {
        if ($('#donner_avis_avis input:checked').val() == 'ok') {
            $('#donner_avis_envoyerMail').prop('disabled', true);
            $('#donner_avis_envoyerMail').prop('checked', false);
        } else {
            $('#donner_avis_envoyerMail').prop('disabled', false);
            $('#donner_avis_envoyerMail').prop('checked', true);
        }
    });

    /**
     * Action "Renvoyer"
     */
    let actionRenvoyerInit = function() {
        $('.card-action-formulaire[data-action="ActionRenvoyer"]').each(function() {
            /**
             * On récupère quelques éléments pour nous faciliter la tâche par la suite
             */
            let $cardFormulaire = $(this);
            let $formMotifs = $cardFormulaire.find('.form-motifs');
            let $motifs = $cardFormulaire.find('.form-motifs__motifs');
            let motifPrototype = $motifs.data('prototype');

            /**
             * Initialisation (si pas de motif déjà dans le formulaire, on en ajoute un directement)
             */
            if ($motifs.find('.form-motifs__motif').length === 0) {
                $motifs.append($(motifPrototype.replace(/__name__/g, 0)));
            }
            $motifs.data('index', $motifs.find('.form-motifs__motif').length);

            if ($motifs.find('.form-motifs__motif').length === 1) {
                $motifs.find('.btn-delete').hide();
            }

            /**
             * Lors d'un clic sur le bouton d'ajout d'un motif
             */
            $formMotifs.find('.btn-add').click(function(e) {
                e.preventDefault();
                let index = $motifs.data('index');
                $motifs.append($(motifPrototype.replace(/__name__/g, index)));
                $motifs.data('index', (index + 1));
                $motifs.find('.btn-delete').show();
            });

            /**
             * Lors d'un clic sur le bouton de suppression d'un motif
             */
            $motifs.on('click', '.btn-delete', function(e) {
                e.preventDefault();
                $(this).parents('.form-motifs__motif').remove();
                if ($motifs.find('.form-motifs__motif').length === 1) {
                    $motifs.find('.btn-delete').hide();
                }
            });
        });
    };

    /**
     * Action "Saisir le réalisé"
     */
    let actionSaisirRealiseInit = function() {
        $('.card-action-formulaire[data-action="ActionSaisirRealise"]').each(function() {

            /**
             * Initialisation de quelques variables
             */
            let $impactsContainer = $('.demande-creation-impacts');

            /**
             * Gestion des impacts réels
             */

            /**
             * On enregistre les templates de doublons
             */
            $('.demande-composants__item.clone-base .demande-composants-item__delete').hide();
            let $templateComposantItemFull = $('.demande-composants__item').clone();
            let $templateComposantItem = $('.demande-composants__item.clone-base').clone();
            let $templateImpactItem = $('.demande-impact:nth-child(1)').clone();
            $templateImpactItem.find('option[value="12"]').prop('selected', true);
            $templateImpactItem.find('option[value="11"]').prop('selected', true);

            /**
             * Fonction permettant de parcourir une collection et de placer l'id dans une propriété du DOM de l'élement
             * @param id
             * @param prop
             * @param $collection
             * @param oldId
             * @private
             */
            let _setIdInProp = function(id, prop, $collection, oldId = '#') {
                $collection.each(function() {
                    let $this = $(this);
                    let propValue = $this.prop(prop);
                    if (propValue !== '') {
                        $this.prop(prop, propValue.replace(oldId, id));
                    }
                });
            };

            /**
             * Fonction permettant d'ajouter l'id dans l'impact passé en paramètre
             * @param id
             * @param $impact
             * @param oldId
             */
            let ajoutIdImpact = function(id, $impact, oldId = '#') {
                $impact.find('.demande-impact__numero').html(id);
                _setIdInProp(id, 'for', $impact.find('label'), oldId);
                _setIdInProp(id, 'id', $impact.find('input, select, textarea, div'), oldId);
                _setIdInProp(id, 'name', $impact.find('input'), oldId);
            };

            /**
             * Fonction permettant d'ajouter un impact à la liste des impacts
             */
            let ajoutImpact = function() {
                // On clone les templates
                let $nouvelImpact = $templateImpactItem.clone();
                let $nouvelleSaisieComposant = $templateComposantItemFull.clone();
                $templateImpactItem
                // On initialise l'impact
                ajoutIdImpact($('.demande-creation-impacts .demande-impact').length + 1, $nouvelImpact);
                $nouvelImpact.find('.demande-composants').html($nouvelleSaisieComposant);

                // On initialise les date time picker
                $nouvelImpact.find('.form-datetimepicker').datetimepicker();
                // On initialise le premier sélecteur de composant
                $nouvelleSaisieComposant.find('select').selectpicker({
                    'liveSearch': true,
                    'style': '',
                    'styleBase': 'form-control'
                });

                // On ajout le nouvel impact
                $impactsContainer.append($nouvelImpact);

                // On met à jour le numéro d'ordre des impacts saisis
                majOrdreImpact();
            };

            /**
             * Fonction permettant d'ajouter un composant à la liste des composants d'un impact
             */
            let ajoutImpactComposant = function($parentContainer) {
                let $nouvelleSaisieComposant = $templateComposantItem.clone();
                $nouvelleSaisieComposant.find('.demande-composants-item__delete').hide();
                $parentContainer.append($nouvelleSaisieComposant);
                $nouvelleSaisieComposant.find('select').selectpicker({
                    'liveSearch': true,
                    'style': '',
                    'styleBase': 'form-control'
                });
            };

            /**
             * Fonction permettant de mettre à jour l'ordre des impacts
             */
            let majOrdreImpact = function() {
                // Si nous avons qu'un seul impact, nous désactivons la possibilité de supprimer un impact
                let $impacts = $('.demande-impact');
                if($impacts.length === 1) {
                    $impacts.find('.demande-impact__delete').hide();
                } else {
                    $impacts.find('.demande-impact__delete').show();
                }

                // On met à jour les id, for, name, des champs de l'impact
                $impactsContainer.find('.demande-impact').each(function(i) {
                    let $this = $(this);
                    ajoutIdImpact(i+1, $this, $this.find('.demande-impact__numero').html());
                });
            };

            /**
             * Récupération des éléments de l'impact
             * @param $impact
             */
            let recuperationDonneesImpact = function($impact) {
                // On initialise les variables
                let donnees = {};
                let composants = [];

                // On va récupérer la liste des composants et on récupère l'id à chaque fois
                $impact.find('.demande-composants__item').each(function() {
                    let value = $(this).find('select').val();

                    if (value !== '') {
                        composants.push(parseInt($(this).find('select').val()));
                    }
                });

                // On récupère les autres valeurs du formulaires que l'on injecte dans l'objet données
                donnees.nature      = $impact.find('[data-name=nature]').val() ? parseInt($impact.find('[data-name=nature]').val()) : null;
                donnees.commentaire = $impact.find('[data-name=commentaire]').val();
                donnees.dateDebut   = $impact.find('[data-name=datedebut]').val() ? $impact.find('[data-name=datedebut]').data("DateTimePicker").viewDate() : null;
                donnees.dateFin     = $impact.find('[data-name=datefin]').val() ? $impact.find('[data-name=datefin]').data("DateTimePicker").viewDate() : null;
                donnees.composants  = composants;

                // Force les secondes et les millisecondes à 0
                if (donnees.dateDebut) {
                    donnees.dateDebut._d.setSeconds(0,0);
                }
                if (donnees.dateFin) {
                    donnees.dateFin._d.setSeconds(0,0);
                }

                // On renvoi les données
                return donnees;
            };

            /**
             * Fonction permettant de récupérer les données de tous les impacts
             * @returns {[]}
             */
            let recuperationDonnesImpacts = function() {
                let donnees = [];
                $impactsContainer.find('.demande-impact').each(function() {
                    donnees.push(recuperationDonneesImpact($(this)));
                });
                return donnees;
            };

            let validationSaisie = function() {
                let erreurDetectee = false;

                // Récupère les dates encadrant la demande dintervention
                let $impactsContainer = $('.demande-creation-impacts');
                let interventionDateDebut = new Date($impactsContainer.data('date-debut-intervention'));
                let interventionDateFin = new Date($impactsContainer.data('date-fin-intervention'));

                // Retire les champs en erreurs précédemment
                $('.saisie-realise-form').find('.form-control-error').removeClass('form-control-error');
                $('.saisie-realise-form').find('.form-label-error').removeClass('form-label-error');

                $impactsContainer.find('.demande-impact').each(function(i) {
                    let $impact = $(this);
                    let impactNumber = i+1;
                    let donnees = recuperationDonneesImpact($impact);
                    let aAucunImpact = (donnees.nature !== null && $impact.find('[data-name=nature] option[value=' + donnees.nature + ']').html() !== "Aucun impact");

                    $impact.find('.form-control-error').removeClass('form-control-error');
                    $impact.find('.form-label-error').removeClass('form-label-error');

                    if (donnees.nature === null) {
                        $impact.find('[data-name=nature]').addClass('form-control-error');
                        $impact.find('[data-name=nature]').parents('.form-group').find('label').addClass('form-label-error');
                        window.afficherToast('La nature de l\'impact ' + impactNumber + ' est manquante.', 'danger');
                        erreurDetectee = true;
                    }

                    // Si "Aucun Impact", n'est pas sélectionné, l'utilisateur doit remplir les autres champs (dates + composants)
                    if (aAucunImpact) {
                        if (donnees.composants.length === 0) {
                            $impact.find('.demande-composants .form-control').addClass('form-control-error');
                            $impact.find('.demande-composants').parents('.form-group').find('label').addClass('form-label-error');
                            window.afficherToast('Veuillez sélectionner les composants impactés ou modifier la nature de l\'impact réel ' + impactNumber + ' .', 'danger');
                            erreurDetectee = true;
                        }
                        if (donnees.dateDebut === null) {
                            $impact.find('[data-name=datedebut]').addClass('form-control-error');
                            $impact.find('[data-name=datedebut]').parents('.form-group').find('label').addClass('form-label-error');
                            window.afficherToast('La date/heure de début d\'impact ' + impactNumber + ' est manquante.', 'danger');
                            erreurDetectee = true;
                        }
                        if (donnees.dateFin === null) {
                            $impact.find('[data-name=datefin]').addClass('form-control-error');
                            $impact.find('[data-name=datefin]').parents('.form-group').find('label').addClass('form-label-error');
                            window.afficherToast('La date/heure de fin d\'impact' + impactNumber + ' est manquante.', 'danger');
                            erreurDetectee = true;
                        } else if (donnees.dateFin <= donnees.dateDebut) {
                            $impact.find('[data-name=datefin]').addClass('form-control-error');
                            $impact.find('[data-name=datefin]').parents('.form-group').find('label').addClass('form-label-error');
                            window.afficherToast('La date/heure de fin d\'impact ' + impactNumber + ' est antérieure ou égale à la date/heure de début d\'impact.', 'danger');
                            erreurDetectee = true;
                        }
                    }
                });

                // test du dernier bloc (saisie réalisé)
                if ($('input[name="saisie_realise[resultat]"]:checked').length <= 0) {
                    $('input[name="saisie_realise[resultat]"]').addClass('form-control-error');
                    $('input[name="saisie_realise[resultat]"]').parents('.form-group').find('label').addClass('form-label-error');
                    window.afficherToast('Vous devez indiquer si l\'intervention est réussie ou a échoué.', 'danger');
                    erreurDetectee = true;
                } else if ($('input[name="saisie_realise[resultat]"]:checked').val() == 'ko'
                    && $('textarea[name="saisie_realise[commentaire]"]').val().length <= 0) {
                    $('textarea[name="saisie_realise[commentaire]').addClass('form-control-error');
                    $('textarea[name="saisie_realise[commentaire]').parents('.form-group').find('label').addClass('form-label-error');
                    window.afficherToast('Vous devez remplir le champ commentaire si l\'intervention n\'est pas réussie.', 'danger');
                    erreurDetectee = true;
                }

                return erreurDetectee;
            };

            /**
             * ----- Modification des composants impactés -----
             */
            const $modaleSaisieComposants = $('#saisieComposantsImpactes');
            $impactsContainer.on('click', '.demande-composants__item button', function(e) {
                // On défini quelques variables
                let $modaleSel = $modaleSaisieComposants.find('.modal-saisie-composants__sel');

                // On réinitialise notre modale
                $modaleSaisieComposants.find('.modal-saisie-composants-search__input').val('');
                $modaleSel.find('ul').empty();
                $modaleSel.find('input:checked').prop('checked', false);
                $modaleSel.trigger('CheckboxesChange', [ 0, [] ]);
                $modaleSel.data('checkedBoxes', []);

                // On parcourt tous les composants déjà saisie
                let $listeComposants = $(this).parents('.card-content').find('ul');
                $listeComposants.find('li:not(.aucun-composant)').each(function(i, composant) {
                    let $composant = $(composant);
                    let id = $composant.find('input').val();
                    let label = $composant.find('span.label').html();

                    // On crée notre composant
                    let $li = $('<li>' +
                        '   <label class="composant-item">' +
                        '       <input type="checkbox" class="checkall-box" value="' + id + '" />' +
                        '       <span class="label">' + label + '</span>' +
                        '   </label>' +
                        '</li>');

                    // On l'ajoute dans la sélection de la modale
                    $modaleSel.find('ul').append($li);
                });

                // On va chercher les composants en base de données et on affiche la modale
                rechercheComposant();
                $modaleSaisieComposants.modal('show');

                // On défini le comportement lors d'une validation de la modale
                $modaleSaisieComposants.unbind('saisieValidee');
                $modaleSaisieComposants.on('saisieValidee', function(e, composants) {
                    // On vide la liste des composants
                    $listeComposants.empty();
                    // Si la liste des composants sélectionnées est vide
                    if (composants.length === 0) {
                        // On affiche "Aucun composant sélectionné"
                        $listeComposants.append('<li class="aucun-composant">Aucun composant sélectionné</li>');

                        // Sinon, on parcourt les composants sélectionnés
                    } else {
                        $.each(composants, function(i, composant) {
                            // Pour chaque, on crée un élément
                            let $li = $('<li class="mt-2 mb-2 text-center">' +
                                '   <span class="label">' + composant.label + '</span> ' +
                                '   <input type="hidden" class="form-control" value="' + composant.id + '">' +
                                '</li>');

                            // On l'ajoute dans la sélection de la modale
                            $listeComposants.append($li);
                        });
                    }

                    // On masque la modale
                    $modaleSaisieComposants.modal('hide');
                });
            });

            /**
             * Fonction permettant de chercher un composant dans la base de données GESIP
             * @param typeSearch
             */
            const rechercheComposant = function(typeSearch) {
                // On prépare quelques variables
                let $sel = $('.modal-saisie-composants__sel');
                let $presel = $('.modal-saisie-composants__presel');

                // On initialise notre liste
                $presel.find('.card-body-loading').show();
                $presel.find('ul').empty();
                $presel.find('input:checked').prop('checked', false);
                $presel.trigger('CheckboxesChange', [ 0, [] ]);
                $presel.data('checkedBoxes', []);

                // On effectue la requête
                $.ajax({
                    url: '/ajax/composant/recherche/label',
                    dataType: 'json',
                    data: {
                        label: typeSearch
                    },
                    success: function(data) {
                        // On parcourt les composants trouvés
                        $.each(data, function(idx, composant) {
                            // On crée notre composant
                            let $li = $('<li>' +
                                '   <label class="composant-item">' +
                                '       <input type="checkbox" class="checkall-box" value="' + composant.id + '" />' +
                                '       <span class="label">' + composant.label + '</span>' +
                                '   </label>' +
                                '</li>');

                            // Si le composant est déjà dans la liste à droite, on le masque à gauche.
                            if ($sel.find('input[value="' + composant.id + '"]').length > 0) {
                                $li.addClass('d-none');
                            }

                            // On ajoute le nouveau composant à la liste
                            $presel.find('ul').append($li);
                        });
                    },
                    complete: function() {
                        $presel.find('.card-body-loading').hide();
                    },
                    error: function() {
                        alert('Impossible de récupérer la liste des composants.');
                    }
                });
            };

            /**
             * Lorsque l'on cherche un composant via le champ de recherche
             */
            let bufferModaleSaisieComposants = null;
            $modaleSaisieComposants.on('keyup', '.modal-saisie-composants-search__input', function(e) {
                let typeSearch = $(this).val();
                if (bufferModaleSaisieComposants !== null) {
                    clearTimeout(bufferModaleSaisieComposants);
                }
                bufferModaleSaisieComposants = setTimeout(function() {
                    rechercheComposant(typeSearch);
                }, 500);
            });

            /**
             * Lorsque l'on clique sur le bouton d'ajout de la liste de droite.
             */
            $modaleSaisieComposants.on('click', '.btn-add', function(e) {
                e.preventDefault();
                let $liste = $('.modal-saisie-composants__sel ul');
                let $presel = $('.modal-saisie-composants__presel');
                let selection = $presel.data('checkedBoxes');

                selection.each(function(i, ckb) {
                    let id = $(ckb).val();
                    let label = $(ckb).parent().find('span.label').html();
                    $(ckb).parents('li').addClass('d-none');

                    if ($liste.find('input[value="' + id + '"]').length === 0) {
                        $liste.append(
                            $('<li>' +
                                '   <label class="composant-item">' +
                                '       <input type="checkbox" class="checkall-box" value="' + id + '" />' +
                                '       <span class="label">' + label + '</span>' +
                                '   </label>' +
                                '</li>')
                        );
                    }
                });

                $presel.find('input:checked').prop('checked', false);
                $presel.trigger('CheckboxesChange', [ 0, [] ]);
                $presel.data('checkedBoxes', []);
            });

            /**
             * Lorsque l'on clique sur le bouton de suppression de la liste de droite.
             */
            $modaleSaisieComposants.on('click', '.btn-remove', function(e) {
                e.preventDefault();
                let $liste = $('.modal-saisie-composants__presel ul');
                let $sel = $('.modal-saisie-composants__sel');
                let selection = $sel.data('checkedBoxes');

                selection.each(function(i, ckb) {
                    let id = $(ckb).val();
                    $(ckb).parents('li').remove();
                    $liste.find('input[value="' + id + '"]').parents('li').removeClass('d-none');
                });

                $sel.find('input:checked').prop('checked', false);
                $sel.trigger('CheckboxesChange', [ 0, [] ]);
                $sel.data('checkedBoxes', []);
            });

            /**
             * Lorsque l'on clique sur le bouton de validation de la saisie
             */
            $modaleSaisieComposants.on('click', '.btn-validate', function(e) {
                // On initialise quelques variables utiles
                let composantsSelectionnes = [];
                let $listeSelections = $('.modal-saisie-composants__sel li');

                // On parcourt la sélection faite par l'utilisateur
                $listeSelections.each(function(i, selection) {
                    composantsSelectionnes.push({
                        id: $(selection).find('input').val(),
                        label: $(selection).find('span.label').html()
                    });
                });

                // On déclenche un évènement avec la sélection de l'utilisateur
                $modaleSaisieComposants.trigger('saisieValidee', [ composantsSelectionnes ]);
            });

            /**
             * Lors d'un clic sur le bouton "Ajouter un impact"
             */
            $('.demande-impact__add').click(function(e) {
                e.preventDefault();
                ajoutImpact();
            });

            /**
             * Lors d'un clic sur le bouton de suppression d'un impact
             */
            $impactsContainer.on('click', '.demande-impact__delete', function(e) {
                e.preventDefault();
                let $impactItem = $(this).parents('.demande-impact');
                $impactItem.remove();
                majOrdreImpact();
            });

            /**
             * On initialise les éléments (date time picker et select picker) déjà en place
             */
            $impactsContainer.find('.form-datetimepicker').datetimepicker();
            $impactsContainer.find('select').selectpicker({
                'liveSearch': true,
                'style': '',
                'styleBase': 'form-control'
            });
            majOrdreImpact();

            /**
             * Si un champ est modifié, on retire les surbrillance erreur
             */
            $('.saisie-realise-form input, .saisie-realise-form textarea, .saisie-realise-form select').on('change', function(){
                $(this).removeClass('form-control-error');
                $(this).parents('.form-group').find('label').removeClass('form-label-error');
            });

            /**
             * Récupère les impacts réels et les enregistres dans le formulaire
             */
            let natureAucunImpactId = 6;
            let $formImpacts = $('#saisie_realise_impactReels');
            let templateFormImpact = $formImpacts.data('prototype');
            function setFormData() {
                $formImpacts.empty();
                $('.demande-impact').each(function(lineNumber){
                    let $formImpact = $(templateFormImpact.replace(/__name__/g, lineNumber));
                    let impactNumber = lineNumber + 1;
                    let nature = $('#nature-' + impactNumber).val();
                    let commentaire = $('#commentaire-' + impactNumber).val();
                    let dateDebut = $('#datedebut-' + impactNumber).val();
                    let dateFin = $('#datefin-' + impactNumber).val();
                    $formImpact.find('#saisie_realise_impactReels_' + lineNumber + '_nature').val(nature);
                    $formImpact.find('#saisie_realise_impactReels_' + lineNumber + '_commentaire').val(commentaire);
                    $formImpact.find('#saisie_realise_impactReels_' + lineNumber + '_dateDebut').val(dateDebut);
                    $formImpact.find('#saisie_realise_impactReels_' + lineNumber + '_dateFin').val(dateFin);
                    $formImpact.find('#saisie_realise_impactReels_' + lineNumber + '_composants :selected').prop('selected', false);
                    if (nature !== natureAucunImpactId) {
                        $('#demande-composants-' + impactNumber + ' li:not(.aucun-composant) input').each(function() {
                            let composantId = $(this).val();
                            $formImpact.find('#saisie_realise_impactReels_' + lineNumber + '_composants option[value="' + composantId + '"]').prop('selected', true);
                        });
                    } else {
                        $formImpact.find('#saisie_realise_impactReels_' + lineNumber + '_composants :selected').prop('selected', false);
                    }
                    $formImpacts.append($formImpact);
                });
            }

            /**
             * Récupération des données à partir du formulaire (lorsque le service a déjà effectué une première saisie)
             */
            function importFormData()
            {
                $('#saisie_realise_impactReels>div>div').each(function(lineNumber) {
                    let impactNumber = lineNumber + 1;
                    if (lineNumber > 0) {
                        ajoutImpact();
                    }
                    $('#nature-' + impactNumber).val($('#saisie_realise_impactReels_' + lineNumber + '_nature').val()).selectpicker('refresh');
                    $('#commentaire-' + impactNumber).val($('#saisie_realise_impactReels_' + lineNumber + '_commentaire').val());
                    let $impactComposantContainer = $('#demande-composants-' + impactNumber + ' ul');
                    $('#saisie_realise_impactReels_' + lineNumber + '_composants :selected').each(function() {
                        let $li = $('<li class="mt-2 mb-2 text-center">' +
                            '   <span class="label">' + $(this).html() + '</span> ' +
                            '   <input type="hidden" class="form-control" value="' + $(this).val() + '">' +
                            '</li>');
                        $impactComposantContainer.append($li);
                        $impactComposantContainer.find('li.aucun-composant').remove();
                    });
                    $('#datedebut-' + impactNumber).data('DateTimePicker').date($('#saisie_realise_impactReels_' + lineNumber + '_dateDebut').val());
                    $('#datefin-' + impactNumber).data('DateTimePicker').date($('#saisie_realise_impactReels_' + lineNumber + '_dateFin').val());
                });
            }
            importFormData();

            /**
             * Teste la validité du formulaire avant envoi
             */
            $('.valide-saisie-realise').click(function(event) {
                event.preventDefault();
                if (!validationSaisie()) {
                    setFormData();
                    lancerAction($($('.card-action-formulaire form').parents('.card-action-formulaire').get(0)).data('action'));
                }
            });

            /**
             * Affiche le formulaire si l'utilisateur souhaite continuer malgré l'avertissement
             */
            $('.showForm').on('click', function() {
                $('.saisie-realise-form').removeClass('d-none');
                $('.saisie-realise-warning').hide();
            });

        });
    };
});
