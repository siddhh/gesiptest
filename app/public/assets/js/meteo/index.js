$(function() {

    // On crée quelques variables pour nous simplifier la vie
    let $panelFiltres = $('.meteo-filtres-container');
    let $panelComposants = $('.meteo-composants-container');
    let $panelComposantsTitre = $('.meteo-composants-titre');
    let $listeComposants = $panelComposants.find('.meteo-composants');
    let $btnValidation = $('.btn-validation');
    let $btnValidationText = $('.btn-validation-text');

    /* Lorsque l'on valide le formulaire en premiere partie de page. */
    $('form.choix-meteo').submit(function(e) {
        // --- Initialisation ---
        // On annule le comportement de base du formulaire et on crée quelques variables pour nous simplifier la vie
        e.preventDefault();
        let $form = $(this);
        let $exploitant = $form.find('#saisie_index_exploitant');
        let $periode = $form.find('.periode-picker');
        let erreurDetecte = false;

        // --- Gestion des erreurs ---
        // On retire toutes les anciennes erreurs si besoin
        $form.find('.form-label-error').removeClass('form-label-error');

        // Si pas d'exploitant saisie, on indique une erreur
        if ($exploitant.val() === '') {
            $exploitant.parents('.form-group').find('label').addClass('form-label-error');
            erreurDetecte = true;
        }

        // Si pas de période saisie, on indique une erreur
        if ($periode.val() === '') {
            $periode.parents('.form-group').find('label').addClass('form-label-error');
            erreurDetecte = true;
        }

        // Si une erreur est détéctée, on affiche un message d'erreur
        if (erreurDetecte) {
            window.afficherToast("Tous les champs n'ont pas été remplis correctement.", "danger");
            return;
        }

        // --- Requêtage ---
        // Si tout est ok, alors on va chercher les composants associés à l'exploitant sur le serveur
        window.bigLoadingDisplay(true);
        let mode = $form.hasClass('saisie') ? 'saisie' : 'consultation';
        let url = '/ajax/meteo/exploitants/' + $exploitant.val()
            + '/composants/' + $periode.val().replaceAll('-', '')
            + '?' + mode + '=1';
        $.get(url)
        .done(function(reponse) {

            // On vide la liste des composants déjà présents
            $listeComposants.html('');
            $panelComposantsTitre.html('Tableau de bord Météo - Semaine du <span class="periode-debut">JJ/MM</span> au <span class="periode-fin">JJ/MM/YYYY</span>');

            // On met à jour les filtres
            let $filtres = $('.meteo-filtres-container');
            $filtres.data('exploitant', $exploitant.val());
            $filtres.data('periode-debut', $periode.val());

            // Si il a des choses à afficher
            if (reponse.composants.length > 0) {
                // On affiche le bouton validation (si existe)
                $btnValidation.show();
                $btnValidation.prop('disabled', '');
                $btnValidationText.show();
                $btnValidationText.html('');
                // On masque notre phrase indiquant "pas de résultats"
                $('.meteo-composants-empty').hide();
                // Bouton à afficher
                if ($form.hasClass('consultation')) {
                    $panelComposantsTitre.append('<div class="page-actions text-center mt-4"><a href="#" class="btn btn-primary" id="bouton-suite">Tous les composants</a></div>');
                    $panelComposantsTitre.find('a').attr('href', '/meteo/consultation/' + $periode.val().replaceAll('-', ''));
                }
                // On crée un composant dans la liste pour chaque résultat
                $.each(reponse.composants, function(i, composant) {
                    $listeComposants.append(
                        $('<div class="col-4"></div>').append(
                            $('<a class="meteo-composant-item btn btn-outline-primary"></a>')
                                .attr('href', composant.href)
                                .attr('title', composant.label)
                                .append($('<span class="meteo-composant-label"></span>').html(composant.label))
                                .append(
                                    $('<span class="meteo-composant-indice"></span>')
                                        .append(
                                            $('<img />').attr('src', '/assets/img/meteo-' + composant.indice + '.svg')
                                        )
                                )
                        )
                    );
                });

            // Sinon, on affiche le message "pas de résultats" (et on masque le bouton de validation)
            } else {
                $btnValidation.hide();
                $btnValidationText.hide();
                $('.meteo-composants-empty').show();
            }

            // Si il y a déjà eu validation de la part de l'exploitant
            if (reponse.validation) {
                let date = moment(reponse.validation);
                $btnValidation.prop('disabled', 'disabled');
                $btnValidationText.html('Accord donné le ' + date.format('DD/MM/YYYY à HH:mm') + '.');
            }

            // On ajoute dans l'historique
            if ($panelFiltres.data('url') && $exploitant.val()) {
                history.pushState(null, null, $panelFiltres.data('url') + '/' + $exploitant.val() + '/' + $periode.val().replaceAll('-', ''));
            }

            // --- Affichage ---
            // On met en forme l'affichage des dates de début et de fin
            //  * Si du 26/12/2019 au 01/01/2020, alors on affiche "Du 26/12/2019 au 01/01/2020"
            //  * Si du 28/11/2019 au 04/12/2019, alors on affiche "Du 28/11 au 04/12/2019"
            //  * Si du 19/12/2019 au 25/12/2019, alors on affiche "Du 19 au 25/12/2019"
            let periodeDebut = moment($periode.val());
            let periodeFin = moment($periode.val()).add(6, 'days');

            // On découpe les mois et années des périodes de début et fin
            let pdMois = periodeDebut.format('MM');
            let pdAnnee = periodeDebut.format('YYYY');
            let pfMois = periodeFin.format('MM');
            let pfAnnee = periodeFin.format('YYYY');

            // On crée nos deux textes représentants les périodes
            let pdTexte = periodeDebut.format('DD');
            let pfTexte = periodeFin.format('DD/MM/YYYY');

            // Si les années de la date de début et de fin sont différentes
            if (pdAnnee !== pfAnnee) {
                // Alors on affiche le mois et l'année en plus de la date du jour
                pdTexte = pdTexte + '/' + pdMois + '/' + pdAnnee;
                // Sinon, si les mois de la date de début et de fin sont différents
            } else if (pdMois !== pfMois) {
                // Alors on affiche le mois en plus de la date du jour
                pdTexte = pdTexte + '/' + pdMois;
            }

            // On affiche les textes généré au bon endroit
            $panelComposantsTitre.find('.periode-debut').html(pdTexte);
            $panelComposantsTitre.find('.periode-fin').html(pfTexte);

            // On switch les écrans
            $panelFiltres.hide();
            $panelComposants.show();
        })
        .fail(function() {
            alert("Impossible de récupérer la liste des composants pour le moment. Merci de réessayer plus tard.");
        })
        .always(function() {
            window.bigLoadingDisplay(false);
        });
    });

    /* Lorsque l'on souhaite revenir au formulaire quand nous sommes déjà en train de visualiser les composants. */
    $('.meteo-composants-container .btn-back').click(function(e) {
        e.preventDefault();
        $panelFiltres.show();
        $panelComposants.hide();
        $listeComposants.html('');

        if ($panelFiltres.data('exploitant') || $panelFiltres.data('periode-debut')) {
            let $form = $('form');
            let $exploitant = $form.find('#saisie_index_exploitant');
            let $periode = $form.find('.meteopicker');

            $exploitant.val($panelFiltres.data('exploitant'));
            $exploitant.selectpicker('refresh');

            $periode.data('DateTimePicker').viewDate($panelFiltres.data('periode-debut'));
            setTimeout(function() {
                $periode.data('DateTimePicker').date($panelFiltres.data('periode-debut'));
            }, 250);
        }
    });

    // Si on retourne en arrière, on recharge la page
    $(window).on("popstate", function(e) {
        window.bigLoadingDisplay(true);
        document.location.reload(true);
    });

    /* Lorsqu'un exploitant clique sur le bouton "Bon pour publication" */
    $btnValidation.click(function(e) {
        e.preventDefault();
        window.bigLoadingDisplay(true);
        let url = '/ajax/meteo/exploitants/' + $panelFiltres.data('exploitant')
            + '/validation/' + $panelFiltres.data('periode-debut').replaceAll('-', '');
        $.get(url)
        .done(function(reponse) {
            if (reponse.statut !== 'ok') {
                window.bigLoadingDisplay(false);
                alert("Impossible de d'effectuer la validation de la météo. Merci de réessayer plus tard.");
            } else {
                document.location.reload(true);
            }
        })
        .fail(function() {
            window.bigLoadingDisplay(false);
            alert("Impossible de d'effectuer la validation de la météo. Merci de réessayer plus tard.");
        });
    });
});
