$(document).ready(function() {

    /**
     * Initialisation
     */
    let $body = $('body');

    /**
     * Gestion de la fenêtre d'informations
     */
    // On affiche ou non la fenêtre d'informations au clic sur le bouton associé
    $('.window-informations .btn-informations').click(function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).parent('.window-informations').toggleClass('is-opened');
    });
    // On masque la fenêtre d'information au clic ailleurs
    $('html').click(function (e) {
        if ($(e.target).parents('.window-informations').length === 0) {
            $('.window-informations.is-opened').removeClass('is-opened');
        }
    });

    /**
     * Initialisation des composants impliquant une initialisation globale
     */
    // On met en place les select-picker présents dans la page
    $.fn.selectpicker.Constructor.BootstrapVersion = '4';
    $('.select-picker').selectpicker({
        'hideDisabled': true,
        'liveSearch': true,
        'style': '',
        'styleBase': 'form-control'
    });
    // Lors d'un reset de formulaire, on rafraîchi l'affichage des select-pickers
    $('form').on('reset', function() {
        setTimeout(function() {
            $('.select-picker').selectpicker('refresh');
        });
    });
    // On initialise les timepickers en place dans la page
    // @deprecated
    $('.datepicker').datetimepicker({
        locale: 'fr',
        format: 'LT',
        useCurrent: false,
    });
    // On initialise les timepickers en place dans la page
    // @deprecated
    $('.timepicker').datetimepicker({
        locale: 'fr',
        format: 'L',
        useCurrent: false,
    });
    // On initialise les datetimepicker
    $('.form-datetimepicker').datetimepicker();
    // On initialise les datepicker
    $('.form-datepicker').datetimepicker({
        locale: 'fr',
        format: 'L',
    });

    /**
     * Gestion des "Tout sélectionner" par conteneur
     * .checkall-container          => Conteneur d'où l'événement "CheckboxesChange" est lancé, à capturer dans le script
     *      .checkall               => Case "Tout"
     *
     *      .checkall-box-handle    => (Facultatif) Au clic sur cet élément, on coche / décoche la case enfant (pratique pour cocher une case associée à une ligne entière)
     *          .checkall-box       => Case "Item unique"
     *
     *      .checkall-box-handle
     *          .checkall-box
     *
     *      .checkall-box-handle
     *          .checkall-box
     *
     *      (...)
     */
    // On décoche tout en arrivant sur la page et on initialise les containers
    $('.checkall, .checkall-box').prop('checked', '');
    $('.checkall-container').data('checkedBoxes', $([]));
    // Lors d'un changement de valeur au niveau de la case "Tout selectionner"
    $body.on('change', '.checkall', function (e) {
        e.preventDefault();
        // On initialise quelques variables
        let $this = $(this);
        let $checkboxesContainer = $this.parents('.checkall-container');
        let $checkboxes = $checkboxesContainer.find('.checkall-box');
        let $checkboxesHandler = $checkboxesContainer.find('.checkall-box-handle');

        // Si on est coché, alors on coche toutes les cases à cocher
        if ($this.is(':checked')) {
            $checkboxes.prop('checked', 'checked');
            $checkboxesHandler.addClass('checkall-box-checked');
        // Sinon, on décoche toutes les cases à cocher
        } else {
            $checkboxes.prop('checked', null);
            $checkboxesHandler.removeClass('checkall-box-checked');
        }

        // On récupère toutes les cases cochées du container
        let $checkboxesChecked = $checkboxesContainer.find('.checkall-box:checked');

        // On émet un événement depuis le container afin de pouvoir le capturer par la suite, on lui passe également
        // le nombre de case cochées ainsi que les cases cochées.
        $checkboxesContainer.trigger('CheckboxesChange', [
            $checkboxesChecked.length,
            $checkboxesChecked
        ]);
        $checkboxesContainer.data('checkedBoxes', $checkboxesChecked);
    });
    // Lors d'un changement de valuer au niveau d'une case ".checkall-box"
    $body.on('change', '.checkall-box', function (e) {
        e.preventDefault();
        // On initialise quelques variables
        let $this = $(this);
        let $checkboxeHandler = $this.parents('.checkall-box-handle');
        let $checkboxesContainer = $this.parents('.checkall-container');
        let $checkboxes = $checkboxesContainer.find('.checkall-box');
        let $checkboxesChecked = $checkboxesContainer.find('.checkall-box:checked');
        let $checkAll = $checkboxesContainer.find('.checkall');

        // On ajoute / retire la classe "checkall-box-checked" du handler
        $checkboxeHandler.toggleClass('checkall-box-checked', $this.is(':checked'));

        // Si le nombre total de case à coché du container est égal au nombre déjà coché, alors on coche "Tout sélectionner"
        if ($checkboxes.length === $checkboxesChecked.length) {
            $checkAll.prop('checked', 'checked');
        // Sinon, on le décoche
        } else {
            $checkAll.prop('checked', null);
        }

        // On émet un événement depuis le container afin de pouvoir le capturer par la suite, on lui passe également
        // le nombre de case cochées ainsi que les cases cochées.
        $checkboxesContainer.trigger('CheckboxesChange', [
            $checkboxesChecked.length,
            $checkboxesChecked
        ]);
        $checkboxesContainer.data('checkedBoxes', $checkboxesChecked);
    });
    // Lors d'un clic sur un élément ".checkall-box-handle"
    $body.on('click', '.checkall-box-handle', function(e) {
        let $ckb = $(this).find('.checkall-box');
        if (e.target !== $ckb.get(0)) {
            e.preventDefault();
            e.stopPropagation();
            $ckb.prop('checked', $ckb.is(':checked') ? '' : 'checked');
            $ckb.trigger('change');
        }
    });

    /**
     * Gestion du "big loading" : affichage d'un système de chargement global
     * - status: true - on affiche le chargement
     * - status: false - on masque le chargement
     */
    window.bigLoadingDisplay = function(status, callback) {
        let $bigLoading = $('.big-loading');

        if (status) {
            $bigLoading.stop(1, 1).fadeIn(200);
        } else {
            $bigLoading.stop(1, 1).fadeOut(200);
        }
    };

    /**
     * Réinitialise les champs d'un formulaire automatiquement
     *  Si plusieurs formulaires existent dans votre page, utilisez data-form-reset-selector.
     */
    $('.form-reset').on('click', function(e) {
        e.preventDefault();
        let $forms;
        if($(this).data('form-reset-selector')) {
            $forms = $($(this).data('form-reset-selector'));
        } else {
            $forms = $('form');
        }
        $forms.find('input, textarea').each(function() {
            switch($(this).attr('type')) {
                case 'checkbox': case 'radio':
                    $(this).prop('checked', false);
                    break;
                default:
                    $(this).val('');
            }
        });
        $forms.find('select').each(function(){
            $(this).val('');
            if ($(this).hasClass('select-picker')) {
                $(this).selectpicker('refresh');
            }
        });
    });

    /**
     * Lors d'un changement d'un filtre select avec redirection.
     */
    $('.select-redirect').on('change', function (e) {
        e.preventDefault();
        if ($(this).val() !== '') {
            document.location.href = $(this).val();
        }
    });

    $('.form-ajax-replace .btn-export').click(function(e) {
        e.preventDefault();
        let $btn = $(this);
        let $form = $btn.parents('.form-ajax-replace');
        let saveAction = window.location.href;
        $form.attr('target', '_blank');
        $form.attr('action', saveAction + '?export=' + $btn.data('export-type'));
        $form.addClass('form-disabled-ajax');
        $form.submit();
        $form.removeClass('form-disabled-ajax');
        $form.attr('action', saveAction);
    });

    $('.form-ajax-replace').submit(function(e) {
        let $form = $(this);
        if (!$form.hasClass('form-disabled-ajax')) {
            e.preventDefault();
            let eltSelector = $form.data('ajax-replace-id');
            window.bigLoadingDisplay(true);

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: $form.serializeArray(),
                success: function(data) {
                    $(eltSelector).html($(data).find(eltSelector).html());
                    $form.trigger('replaced');
                },
                error: function(error) {
                    alert("Une erreur est survenue lors de la récupération des informations. Merci de réessayer un peu plus tard.");
                },
                complete: function() {
                    $('html, body').scrollTop($(eltSelector).offset().top);
                    window.bigLoadingDisplay(false);
                }
            });
        }
    });

    /**
     * Météo DateTimePicker
     */
    // On parcourt tous les datetimepicker
    $('.meteopicker').each(function() {
        // On initialise quelques variables
        let $meteopicker = $(this);
        let dtOptions = {
            locale: 'fr',
            format: 'YYYY-MM-DD',
            useCurrent: false,
            inline: true,
        };

        // Fonction permettant d'initialiser le datetimepicker
        let initDateTimePicker = function(options) {
            // Si le datetimepicker a déjà initialisé
            if ($meteopicker.data('DateTimePicker')) {
                $meteopicker.data('DateTimePicker').destroy();
            }
            // On initialise
            $meteopicker.datetimepicker({
                ...dtOptions,
                ...options
            });
            // On supprime le comportement de la recréation intégrale du datetimepicker quand on clique une nouvelle fois sur une date
            $meteopicker.find('.datepicker-days').on('click', 'td.day.active, td.day.old, td.day.new', function(e) {
                return false;
            });
        };

        // Fonction permettant de colorier les semaines du calendrier (de jeudi à mercredi) en fonction d'un td
        let colorierCalendrier = function($td, activeClasse) {
            var $trCurrentWeek = $td.parents('tr');
            if ($td.index() >= 3) {
                $td.addClass(activeClasse);
                $trCurrentWeek.find('td.day:nth-child(n+4)').addClass(activeClasse);
                $trCurrentWeek.next().find('td.day:nth-child(-n+3)').addClass(activeClasse);
            } else {
                $td.addClass(activeClasse);
                $trCurrentWeek.find('td.day:nth-child(-n+3)').addClass(activeClasse);
                $trCurrentWeek.prev().find('td.day:nth-child(n+4)').addClass(activeClasse);
            }
        };

        // Fonction permettant de récupérer les dates valides
        let ajaxDatesValides = function() {
            // On récupère l'url d'action, le mois et l'année en cours d'affichage sur le datetimepicker
            let actionUrl = $meteopicker.data('ajax-url');
            let viewDate = $meteopicker.data('DateTimePicker').viewDate();
            let month = (viewDate.month() + 1);
            let year = viewDate.year();
            $($meteopicker.data('input')).val('');

            // On lance la requête
            $meteopicker.addClass('loading');
            $.get(actionUrl, { month: month, year: year })
            .done(function(reponse) {

                // On crée notre array de date à rendre actif
                let dates = ['1900-01-01'];

                // On parcourt notre réponse
                $.each(reponse, function(i, date) {
                    dates.push(date);
                });

                // On réinitilise notre datetimepicker
                initDateTimePicker({
                    viewDate: viewDate,
                    enabledDates: dates
                });
            })
            .fail(function() {
                alert("Impossible de récupérer les informations pour le moment. Merci de réessayer plus tard.");
                initDateTimePicker({ enabledDates: ['1900-01-01'] })
            })
            .always(function() {
                $meteopicker.removeClass('loading');
            });
        };

        // Lorsqu'une date a été saisie, on met à jour l'affichage de la semaine météo ainsi que la valeur dans le champ associé
        $meteopicker.on('dp.change', function(e) {
            // On colorie notre calendrier
            var $tdDay = $meteopicker.find('td.day.active');
            colorierCalendrier($tdDay, 'active-hover');

            // On calcule la date de début de la période
            var date = e.date;
            date = date.day(date.day() >= 4 ? 4 : -3);

            // On met à jour le champ avec la date de début de période au bon format
            $($meteopicker.data('input')).val(date.format('YYYY-MM-DD'));
        });

        // Lorsque l'on navigue dans les mois / années
        $meteopicker.on('dp.update', function(e) {
            if ($meteopicker.data('DateTimePicker') !== undefined) {
                // On colorie notre calendrier
                var $tdDay = $meteopicker.find('td.day.active');
                colorierCalendrier($tdDay, 'active-hover');

                // On va chercher les informations sur le serveur
                ajaxDatesValides();
            }
        });

        // Lorsque l'on survole les dates, on colorie notre calendrier
        $meteopicker.on('mouseenter', 'td.day:not(.disabled)', function(e) {
            $meteopicker.addClass('hovering');
            colorierCalendrier($(this), 'hover');
        });

        // Lorsque l'on arrête le survole des dates, on supprime le coloriage de la semaine
        $meteopicker.on('mouseleave', 'td.day', function(e) {
            $meteopicker.removeClass('hovering');
            $meteopicker.find('td.day.hover').removeClass('hover');
        });

        // On lance notre datetimepicker
        initDateTimePicker();
        ajaxDatesValides();
    });

    /**
     * Mise en place du système de tri de colonne en JQ de tableau.
     * Il suffit d'indiquer la classe `table-tri` au niveau de la colonne que l'on souhaite trier.
     * Le script fait le reste !
     */
    $('.table-tri').each(function() {
        let $this = $(this);
        if ($this.parents('table').find('tbody tr').length <= 2) {
            $this.removeClass('table-tri');
            $this.removeClass('table-tri__active');
        }
    });
    $body.on('click', '.table-tri', function(e) {
        e.preventDefault();
        let $headColonne = $(this);
        let $table = $headColonne.parents('table');
        let $tbody = $table.find('tbody');
        let thIndex = $headColonne.index();

        if ($headColonne.hasClass('table-tri__active')) {
            $headColonne.toggleClass('table-tri__inverse');
        }
        const inverse = $headColonne.hasClass('table-tri__inverse');
        $table.find('.table-tri__active').removeClass('table-tri__active');
        $table.find('.table-tri__inverse').not($headColonne).removeClass('table-tri__inverse');
        $headColonne.addClass('table-tri__active');

        $tbody.find('td').filter(function() {
            return $(this).index() === thIndex;
        }).sortElements(function(a, b){

            // On récupère les textes des lignes A et B
            let textA = $.text([a]);
            let textB = $.text([b]);

            // Si une valeur tri-value est indiquée, le tri sera alors fondé sur celle-ci
            if (
                $(a).data('tri-value') !== undefined ||
                $(b).data('tri-value') !== undefined
            ) {
                textA = $(a).data('tri-value');
                textB = $(b).data('tri-value');
            } else {
                // Si les contenus ressemblent à des dates, alors on les formate pour pouvoir les utiliser façon US
                //   (pour permettre un ordre alphabétique correct)
                let dateTextA = textA.toString().match(/^(\d{2})\/(\d{2})\/(\d{4})(\ \d{2}\:\d{2})?$/);
                let dateTextB = textB.toString().match(/^(\d{2})\/(\d{2})\/(\d{4})(\ \d{2}\:\d{2})?$/);
                if (dateTextA !== null || dateTextB !== null) {
                    textA = dateTextA[3] + '-' + dateTextA[2] + '-' + dateTextA[1];
                    textB = dateTextB[3] + '-' + dateTextB[2] + '-' + dateTextB[1];
                    if (undefined !== dateTextA[4]) {
                        textA += dateTextA[4];
                    }
                    if (undefined !== dateTextB[4]) {
                        textB += dateTextB[4];
                    }
                }
            }

            // On tri !
            if( textA === textB )
                return 0;
            return textA > textB ?
                inverse ? -1 : 1
                : inverse ? 1 : -1;

        }, function() {
            // parentNode is the element we want to move
            return this.parentNode;
        });
    });

    /**
     * Permet de plier ou déplier une card bootstrap.
     * (On traite uniquement lors d'un clic sur `.card-deploy`.)
     */
    $body.on('click', '.card-deploy .card-header', function(e) {
        e.preventDefault();
        $(this).parents('.card-deploy').toggleClass('card-deploy-deployed');
    });

    /**
     * Gestion du control "MultiSelectType"
     */
    $('.form-multi-select-type').each(function() {
        // On va récupérer les informations dont nous avons besoin pour le fonctionnement du champ personnalisé
        const $field = $(this);
        const itemLabel = $field.data('label');
        const url = $field.data('url');
        const prototype = $field.data('prototype');
        const $itemsSelected = $field.find('.form-multi-select-type__inner-wrapper');
        const $search = $itemsSelected.find(".form-multi-select-type__search");
        const $searchInput = $search.find("input");
        const $searchLoader = $search.find(".form-multi-select-type__loader");
        const $results = $field.find(".form-multi-select-type__search-result");
        const $resultsTable = $field.find(".form-multi-select-type__search-result table");

        // On initialise quelques variables dont nous aurons besoin un peu plus bas
        let queryInProgress = null;
        let timeoutRequest = null;
        let timeoutResultHide = null;

        // Lorsque l'on clique dans la zone de saisie, on donne le focus au champ de recherche
        $field.click(function() {
            $searchInput.focus();
        });

        // Lorsque l'on tape quelque chose dans notre champ de saisie de recherche
        $searchInput.keyup(function() {
            // On montre notre spinner de loading, on masque les résultats que l'on vide entièrement
            $searchLoader.show();
            $results.hide();
            $resultsTable.empty();

            // Si une requête est déjà prévue dans 500ms, on l'annule
            clearTimeout(timeoutRequest);
            // De même que pour le masquage automatique des résultats
            clearTimeout(timeoutResultHide);

            // Si il y a une saisie qui a au moins 2 caractères
            if ($searchInput.val().length >= 2) {

                // Si une requête est en cours, on l'annule.
                if (queryInProgress !== null) {
                    queryInProgress.abort();
                }

                // On planifie l'envoi de la requête de recherche pour dans 500ms
                timeoutRequest = setTimeout(searchRequest, 500);
            } else {
                $searchLoader.hide();
            }
        });

        // Fonction permettant d'appeler une url prédéfinie pour effectuer la recherche
        const searchRequest = function() {
            // On montre notre spinner de loading, on masque les résultats que l'on vide entièrement
            $searchLoader.show();
            $results.hide();
            $resultsTable.empty();
            clearTimeout(timeoutResultHide);

            // On effectue la requête vers le serveur
            queryInProgress = $.get(url, { 'search': $searchInput.val() })
            // Si la requête est ok
            .done(function(data) {

                // On crée l'entête du tableau de résultat
                let colspan = 0;
                $thead = $('<thead></thead>').addClass('table-dark');
                $tr = $('<tr></tr>');
                $.each(data[0], function(key, label) {
                    $tr.append($('<th></th>').html(label));
                    colspan++;
                });
                $thead.append($tr);
                $resultsTable.append($thead);

                // Si il y a des résultats, on les met en forme pour l'affichage
                $tbody = $('<tbody></tbody>');

                // On parcourt les résultats que l'on ajoute au tableau de résultats en comptant le nombre
                //  (si celui-ci n'a pas déjà été sélectionné)
                let nbrResultats = 0;
                $.each(data.slice(1), function(i, obj) {
                    if ($itemsSelected.find('input[value="' + obj['id'] + '"]').length === 0) {
                        $tr = $('<tr></tr>')
                            .data('id', obj['id'])
                            .data('label', obj[itemLabel]);
                        $.each(data[0], function (key) {
                            $tr.append($('<td></td>').html(obj[key]));
                            colspan++;
                        });
                        $tbody.append($tr);
                        nbrResultats++;
                    }
                });

                // Si il n'y eu aucun résultat, alors on affiche un message "Aucun résultats"
                if (nbrResultats === 0) {
                    $tbody.append(
                        $('<tr></tr>').append(
                            $('<td></td>')
                                .html('Votre recherche n\'a donné aucun résultat.')
                                .attr({ 'colspan': colspan })
                        ).addClass('no-results')
                    );
                }
                $resultsTable.append($tbody);

                // On affiche les résultats
                $results.show();
            })
            // Si la requête est en erreur
            .fail(function(erreur) {
                if (erreur.status !== 0) {
                    alert("Impossible de récupérer les informations pour l'instant.\nMerci de réitérer plus tard.")
                }
            })
            // Et dans tous les cas, on masque le loader ...
            .always(function() {
                $searchLoader.hide();
            });
        };

        // Lorsque l'on clique sur un résultat du tableau de résultats
        $resultsTable.on('click', 'tbody tr:not(.no-results)', function(e) {
            e.preventDefault();
            // On récupère les informations du résultat cliqué
            const id = $(this).data('id');
            const label = $(this).data('label');

            // On ajoute la nouvelle entrée (si elle n'a pas déjà été sélectionnée)
            if ($itemsSelected.find('input[value="' + id + '"]').length === 0) {
                $newSelected = $(prototype);
                $newSelected.find('input').attr('value', id);
                $newSelected.find('.form-multi-select-type-item__title').html(label);
                $newSelected.insertBefore($search);
            }

            // On supprime le contenu du champ de recherche et on le masque
            $results.stop(0, 0).fadeOut(250, function() {
                clearTimeout(timeoutResultHide);
            });

            // On réinitialise la recherche
            $searchInput.val('');
        });

        // Lorsque la souris survol le champ, on retire notre demande de masquage des résultats
        $field.on('mouseenter', function(e) {
            e.preventDefault();
            clearTimeout(timeoutResultHide);
        });

        // Lorsque la souris ne survol plus le champ, on demande le masquage des résultats dans 750ms
        $field.on('mouseleave', function(e) {
            e.preventDefault();
            timeoutResultHide = setTimeout(function() {
                $results.stop(0, 0).fadeOut(250);
            }, 750);
        });

        // Lorsque l'on clique sur la croix des éléments sélectionnés, on supprime ce même élément
        $itemsSelected.on('click', '.form-multi-select-type-item__remove', function(e) {
            e.preventDefault();
            $(this).parents('.form-multi-select-type__item').remove();
        });

        //
        $searchInput.on('focus', function(e) {
            $searchInput.parents('.form-multi-select-type').addClass('focus');
        });

        $searchInput.on('blur', function(e) {
            $searchInput.parents('.form-multi-select-type').removeClass('focus');
        });
    });

    /**
     * Système d'affichage du changelog de l'application liée au projet GitLab
     */
    // Initialisation de quelques variables utiles pour l'affichage du changelog
    let changelog_page = 1;
    let changelog_end_reached = false;
    let $changelog_open = $('.open-modal-changelog');
    let $modal_changelog = $('.modal-changelog');
    let $modal_changelog_body = $modal_changelog.find('.modal-body');
    let $modal_changelog_loader = $modal_changelog.find('.table-loading');

    // Lors que l'on clique sur le lien permettant d'ouvrir la modale de changelog
    $changelog_open.click(function(e) {
        e.preventDefault();
        $modal_changelog.modal('show');
        changelog_page = 1;
        changelog_end_reached = false;
        changelogLoad(1, true);
    });

    // Lors que nous scrollons dans les versions, et lorsque l'on arrive vers la fin de la liste, on charge la page
    // suivante.
    $modal_changelog_body.scroll(function() {
        if (!changelog_end_reached && !$modal_changelog_loader.hasClass('show')) {
            var scrollHeight = $modal_changelog_body.prop('scrollHeight');
            var scrollPos = $modal_changelog_body.height() + $modal_changelog_body.scrollTop();

            if (((scrollHeight - 200) >= scrollPos) / scrollHeight === 0) {
                changelog_page++;
                changelogLoad(changelog_page);
            }
        }
    });

    // Fonction permettant de charger une page dans le tableau présentant les versions du changelog
    let changelogLoad = function(page, withReset = false) {
        let $modal_title_small = $modal_changelog.find('.modal-title small');
        $modal_changelog_loader.addClass('show');

        if (withReset) {
            $modal_title_small.html('');
            $modal_changelog.find('.release-item').remove();
        }

        $.get('/ajax/changelog/' + page, function(data) {
            for (let i = 0 ; i < data.releases.length ; i++) {
                let $tr = $('<tr class="release-item"></tr>').append(
                    $('<td class="text-center">' + data.releases[i].name + '</td>'),
                    $('<td class="text-center">' + moment(data.releases[i].disponibleLe).format('DD/MM/YYYY HH:mm') + '</td>'),
                    $('<td></td>').html(data.releases[i].description),
                );
                $tr.attr('data-type', data.releases[i].type);
                $tr.insertBefore($modal_changelog_loader);
            }

            if (data.info.pagination.courante === data.info.pagination.total) {
                changelog_end_reached = true;
            }

            $modal_title_small.html('(maj le ' + moment(data.info.majLe).format('DD/MM/YYYY à HH:mm:ss') + ')');

            $modal_changelog_loader.removeClass('show');
        })
        .fail(function () {
            alert("Impossible de récupérer la liste des versions pour l'instant.");
            $modal_changelog.modal('hide');
        });
    };
});
