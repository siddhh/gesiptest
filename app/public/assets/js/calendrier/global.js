$(function() {

    /**
     * On récupère le cookie pour les filtres
     */
    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for(var i = 0; i <ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }
    let cookieFiltrePilote = getCookie('_filtres_pilote');
    if (cookieFiltrePilote !== undefined || true) {
        $('select[name="pilote"] option[value="' + cookieFiltrePilote + '"]').prop('selected', 'selected');
        $('select[name="pilote"]').selectpicker('refresh');
    }


    /** Permet d'afficher le popover lorsque l'on survol un lien .operation-popover-trigger */
    $('.operation-popover-trigger').popoverButton({
        trigger: 'hover',
        placement: 'auto'
    });

    /** Filtrage des infos */
    let $filtres = $('.calendrier-filters [name]:not([name="filtre"],[name="dateDemandeGesip"])');
    // Lors d'un changement de valeur pour les filtres qui ne sont pas un champ libre
    $filtres.change(function(e) {
        filteringData();
        if ($(this).attr('name') === "pilote") {
            if ($(this).val() == null) {
                document.cookie = "_filtres_pilote=; path=/; max-age=0";
            } else {
                document.cookie = "_filtres_pilote=" + $(this).val() + "; path=/; expires=never";
            }
        }
    });
    // Lors d'un appuie sur une touche dans le champ "filtre"
    $('.calendrier-filters [name="filtre"]').on('keyup', function() {
        filteringData();
    });
    // Fonction permettant de récupérer les valeurs des différents filtres
    let getFiltres = function() {
        var data = {
            'calendrier': [],
            'statut': [],
        };
        var dataArray = $filtres.serializeArray();

        $.each(dataArray, function (i, e) {
            let peutAvoirDesValeursMultiples = e.name.substr(e.name.length - 2) === '[]';

            if (peutAvoirDesValeursMultiples) {
                data[e.name.replace('[]', '')].push(e.value);
            } else {
                data[e.name] = e.value;
            }
        });

        return data;
    };
    // Fonction de filtrage
    let filteringData = function() {
        let filtres = getFiltres();
        let search = $('.calendrier-filters [name="filtre"]').val().toLowerCase();

        $('.operation').filter(function() {
            let $ope = $(this);
            $ope.show();

            $.each(filtres, function(name, value) {
                if ($.isArray(value)) {
                    if ($ope.data(name) !== undefined && $.inArray($ope.data(name), value) === -1) {
                        $ope.hide();
                    }
                } else {
                    if ($ope.data(name) !== undefined && value !== '') {
                        let data = $ope.data(name).toString().split('|');
                        if ($.inArray(value, data) === -1) {
                            $ope.hide();
                        }
                    }
                }
            });

            if (search !== '' && $ope.text().toLowerCase().indexOf(search) === -1) {
                $ope.hide();
            }
        });
    };
    // On lance le filtrage des données dès l'arrivée sur la page
    filteringData();
    // Lorsque nous survolons des checkbox, nous mettons en évidences les opérations dans le tableau ou calendrier
    $('.calendrier-filters [type="checkbox"]').parent('label')
    .on('mouseover', function() {
        let $input = $(this).find('input');
        $('.operation.in-hovering').removeClass('in-hovering');
        $('.operation[data-' + $input.attr('name').replace('[]', '') + '="' + $input.val() + '"]').addClass('in-hovering');
    })
    .on('mouseleave', function() {
        $('.operation.in-hovering').removeClass('in-hovering');
    });
    // Filtre "Date demande Gesip"
    $('.calendrier-filters [name="dateDemandeGesip"]').click(function(e) {
        e.stopPropagation();
        let $this = $(this);
        let $parent = $this.parents('.filtre-dateDemandeGesip');

        // Si la case est déjà cochée, alors on la décoche sinon on la coche. (comportement par défaut, donc pas besoin de le faire nous même)
        if ($parent.data('value') === $this.val()) {
            $this.prop('checked', false);
            $parent.data('value', '');
        }
        $parent.data('value', $this.val());

        refreshDateDemandeGesip();
    });
    // Fonction permettant de filtrer les informations
    let refreshDateDemandeGesip = function () {
        // On récupère la valeur du filtre, et on supprime la classe .warning-date si il existe déjà
        let valeurFiltre = $('.calendrier-filters [name="dateDemandeGesip"]:checked').val();
        $('.operation.warning-date').removeClass('warning-date');

        // On défini les limites de recherche
        let limitMin = -1;
        let limitMax = -1;

        switch (valeurFiltre) {
            case '5j':
                limitMin = 0;
                limitMax = 5;
                break;
            case '10j':
                limitMin = 5;
                limitMax = 10;
                break;
            case '15j':
                limitMin = 10;
                limitMax = 15;
                break;
            case '+15j':
                limitMin = 15;
                break;
        }

        // On parcourt nos opérations si besoin afin de pouvoir mettre en valeur en fonction des limites défini précédemment
        if (limitMin > -1) {
            $('.operation').each(function() {
                let jours = $(this).data('delta-jour-validation');
                if (jours > limitMin) {
                    if (limitMax > -1) {
                        if (jours <= limitMax) {
                            $(this).addClass('warning-date');
                        }
                    } else {
                        $(this).addClass('warning-date');
                    }
                }
            });
        }
    };
    // Si quelque chose est déjà saisie, on filtre !
    refreshDateDemandeGesip();

    /**
     * Masquage / Démasquage des composants impactés
     */
    $('.operation-impacts .operation-impacts-head').click(function(e) {
        e.preventDefault();
        let $btn = $(this);
        let $parent = $btn.parent();
        let $btnIcon = $btn.find('i');

        if ($btnIcon.hasClass('fa-eye')) {
            $btnIcon.removeClass('fa-eye');
            $btnIcon.addClass('fa-eye-slash');
            $parent.find('.operation-impacts-body').show();
        } else {
            $btnIcon.removeClass('fa-eye-slash');
            $btnIcon.addClass('fa-eye');
            $parent.find('.operation-impacts-body').hide();
        }
    });
});
