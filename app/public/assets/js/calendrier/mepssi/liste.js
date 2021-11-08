$(document).ready(function() {

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

    /** Filtrage des infos */
    let $filtres = $('.calendrier-filters [name]:not([name="filtre"])');
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
});
