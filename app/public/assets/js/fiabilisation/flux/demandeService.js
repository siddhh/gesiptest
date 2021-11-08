$(function() {

    /**
     * On initialise le nécessaire pour le bon fonctionnement du script
     */
    let requeteEnCours = null;
    let $flux = $('.flux-composant');
    let $filtre = $flux.find('.label-search');
    let $loading = $flux.find('.presel-loading');
    let $tousSelectionner = $flux.find('.checkAll');
    let $checkBoxes = $flux.find('.form-check-input');
    let $btnAjout = $flux.find('.remove-add-toolbar .add');
    let $btnRetrait = $flux.find('.remove-add-toolbar .remove');
    let $preselListe = $flux.find('ul.presel-composants');
    let $selListe = $flux.find('ul.sel-composants');
    let $btnSend = $('.page-actions .btn-send');

    /**
     * On réinitialise la recherche (en cas de rechargement de la page)
     */
    $filtre.val('');

    /**
     * Fonction permettant de créer un élément en lui passant un composant en paramètre
     */
    var ajoutComposantListe = function($liste, composant) {
        // On met en forme notre composant (case à cocher + label)
        let $label = $('<label></label>').addClass('form-check-label');
        let $chk = $('<input />').attr({type: 'checkbox'}).addClass('form-check-input').val(composant.id);
        $label.append($chk);
        $label.append($('<span>' + composant.label + '</span>'));
        let $li = $('<li></li>').addClass('form-check').append($label)

        // On ajoute l'élément à la fin de notre liste
        $liste.append($li);

        // On retourne l'élément afin de pouvoir l'utiliser par la suite
        return $li;
    }

    /**
     * Fonction de récupération des composants et du filtrage
     */
    var ajaxRecuperationComposants = function () {
        // Si une requête est déjà en cours, on l'annule
        if (requeteEnCours !== null) {
            requeteEnCours.abort();
        }

        // On décheck les cases à cocher des boutons "Tout sélectionner" et des composants
        $checkBoxes.prop('checked', null);
        $tousSelectionner.prop('checked', null);
        // On vide la liste de gauche
        $preselListe.empty();
        // On affiche l'image de loading
        $loading.show();

        // On envoi la requête de recherche
        requeteEnCours = $.ajax({
            url: '/ajax/composant/recherche/label',
            dataType: 'json',
            data: {
                label: $filtre.val()
            },
            success: function(data) {
                // On parcourt tous les résultats
                $(data).each(function() {
                    // On met en forme notre élément pour l'ajouter à la liste de gauche
                    var $composant = ajoutComposantListe($preselListe, this);

                    // On récupère l'équivalent au composant mais dans la liste de droite
                    let $composantDejaSaisieAvecCetId = $selListe.find('[value=' + this.id +']');
                    // Si il n'y a pas d'équivalent ou si le composant est en mode retrait
                    if (
                        $composantDejaSaisieAvecCetId.length === 0 ||
                        $composantDejaSaisieAvecCetId.parents('.form-check').hasClass('remove')
                    ) {
                        // On affiche le composant dans la liste de gauche
                        $composant.addClass('visible');
                    }
                });
            },
            error: function(error) {
                // Si nous ne sommes pas dans le cas d'une requête annulée
                if (error.status !== 0) {
                    alert('Impossible de récupérer la liste des composants.');
                }
            },
            complete: function() {
                // Dans tous les cas, succès ou non, nous masquons l'image de loading
                $loading.hide();
            }
        });
    };

    /**
     * Fonction permettant de savoir si une modification est en cours de la demande, et si oui, on demande confirmation
     * à la fermeture de la fenêtre
     */
    var statutFermetureFenetre = function() {
        var changementDetecte = false;

        // Si on détecte des changements par rapport à la version initiale, on met le flag changementDetecte à vrai
        $selListe.find('.form-check.visible').each(function() {
            var $this = $(this);

            if (
                !$this.hasClass('add') && !$this.hasClass('remove') && $this.data('initial') !== "" ||
                $this.hasClass('add') && $this.data('initial') !== "add" ||
                $this.hasClass('remove') && $this.data('initial') !== "remove"
            ) {
                changementDetecte = true;
                return;
            }
        });

        // Si le flag changementDetecte est à vrai, alors on bloque la fermeture de la fenêtre
        $(window).off('beforeunload');
        if (changementDetecte) {
            $(window).on('beforeunload', function() {
                return "Des modifications sont toujours en cours et ne seront pas enregistrées si vous quittez la page actuelle. Souhaitez-vous quand même quitter la page ?";
            });
        }
    };

    /**
     * Évènement keyup sur la recherche
     */
    $filtre.keyup(function(e) {
        ajaxRecuperationComposants();
    });

    /**
     * Évènement lors d'un clic sur le bouton "Tout sélectionner"
     */
    $tousSelectionner.change(function(e) {
        e.preventDefault();
        $(this).parents('.info').find('input').prop('checked', $(this).prop('checked'));
    });

    /**
     * C'est parti, on charge les composants à afficher dans la liste de gauche de la page
     */
    ajaxRecuperationComposants();

    /**
     * Lors d'un clic sur le bouton d'ajout
     */
    $btnAjout.click(function(e) {
       e.preventDefault();
       var $composantsSelectionnes = $preselListe.find('input:checked');

       // Si nous avons au moins sélectionné une valeur dans la liste de gauche
       if ($composantsSelectionnes.length > 0) {

           // On parcourt les éléments sélectionné
           $composantsSelectionnes.each(function() {

               // On récupère les informations du composant
               var composantId = $(this).val();
               var composantLabel = $(this).parent().find('span').text();

               // Si l'élément n'existe pas déjà, on l'ajoute avec la classe "ajout" et "visible"
               var $composantDejaDansLaListe = $selListe.find('input[value=' + $(this).val() + ']');
               if ($composantDejaDansLaListe.length === 0) {
                   var $composant = ajoutComposantListe($selListe, { id: composantId, label: composantLabel });
                   $composant.addClass('visible');
                   $composant.addClass('add');
               } else {
                   // Sinon, cela veux dire qu'on a demander un retrait et donc il faut retirer la classe "retrait"
                   $composantDejaDansLaListe.parents('.form-check').removeClass('remove');
               }

               // On rend invisible les composants sélectionné dans la liste de gauche (afin de pouvoir de ne plus pouvoir les sélectionner)
               $preselListe.find('input[value=' + $(this).val() + ']').parents('.form-check').removeClass('visible');
           });

           // On trie le tableau de droite pour afficher les élements par ordre alphabétique
           var $listeAOrdonner = $selListe.find('.form-check').clone();
           $listeAOrdonner.sort(function(a, b) {
               var $aLabel = $(a).find('span').text().toUpperCase();
               var $bLabel = $(b).find('span').text().toUpperCase();
               return $aLabel.localeCompare($bLabel);
           });
           // On vide le tableau de droite
           $selListe.empty();
           // On parcourt la liste triée
           $listeAOrdonner.each(function(idx, itm) {
               // On ajoute l'élément à chaque fois
               $selListe.append(itm);
           });

           // On décoche toutes les cases à cocher cochées de la liste de gauche
           $preselListe.find('input:checked').prop('checked', null);

           // Ainsi que les "Tout sélectionner"
           $tousSelectionner.prop('checked', null);

           // On met à jour le statut de la fenêtre
           statutFermetureFenetre();
       } else {
           // Dans le cas où nous n'avons pas sélectionner de composant
           alert("La demande d'ajout nécessite la sélection d'au moins un composant.");
       }
    });

    /**
     * Lors d'un clic sur le bouton de retrait
     */
    $btnRetrait.click(function(e) {
       e.preventDefault();
        var $composantsSelectionnes = $selListe.find('input:checked');

        // Si nous avons au moins sélectionné une valeur dans la liste de droite
        if ($composantsSelectionnes.length > 0) {
            // On parcourt les éléments sélectionné
            $composantsSelectionnes.each(function() {
                // Tout se joue sur le parent que l'on récupère
                var $parent = $(this).parents('.form-check');

                // Si une demande d'ajout est en cours pour ce composant
                if ($parent.hasClass('add')) {
                    // On le supprime simplement
                    $parent.remove();
                } else {
                    // Sinon on ajoute la classe retrait pour signifier que l'on souhaite le supprimer
                    $parent.addClass('remove')
                }

                // On rend visible les composants sélectionné dans la liste de gauche (afin de pouvoir de nouveau les sélectionner)
                $preselListe.find('input[value=' + $(this).val() + ']').parents('.form-check').addClass('visible');
            });

            // On décoche toutes les cases à cocher cochées de la liste de droite
            $selListe.find('input:checked').prop('checked', null);

            // Ainsi que les "Tout sélectionner"
            $tousSelectionner.prop('checked', null);

            // On met à jour le statut de la fenêtre
            statutFermetureFenetre();
        } else {
            // Dans le cas où nous n'avons pas sélectionner de composant
            alert("La demande de retrait nécessite la sélection d'au moins un composant.");
        }
    });

    /**
     * Lors d'un clic sur le bouton permettant d'enregistrer les demandes saisies
     */
    $btnSend.click(function(e) {
        e.preventDefault();

        // On initialise les variables dont nous avons besoin
        var $this = $(this);
        var ajaxUrl = $this.data('action');
        var ajouts = [];
        var retraits = [];

        // On parcours la sélection
        $selListe.find('.form-check').each(function() {
            var $composant = $(this);
            var composantId = $composant.find('.form-check-input').val();

            // Si c'est un ajout, on ajoute dans le tableau des ajouts
            if ($composant.hasClass('add')) {
                ajouts.push(composantId);

            // Si c'est un retrait, on ajoute dans le tableau des retraits
            } else if ($composant.hasClass('remove')) {
                retraits.push(composantId);
            }
        });

        // On désactive le bouton de soumissions
        $(this).addClass('btn-loading');
        $(this).prop('disabled', 'disabled');

        // On envoie les données au serveur
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                ajouts: ajouts,
                retraits: retraits
            },
            success: function(data) {
                $(window).off('beforeunload');
                window.location.reload();
            },
            error: function(error) {
                // On cas d'erreur, on affiche un message
                alert('Impossible de soumettre les informations au serveur.');
                // On réactive le bouton de soumission
                $(this).removeClass('btn-loading');
                $(this).prop('disabled', null);
            }
        });
    });
});
