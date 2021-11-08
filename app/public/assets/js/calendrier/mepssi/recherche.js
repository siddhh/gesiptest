$(document).ready(function() {

   /**
    * Initialisations
    */
   var $boutonExportPdf = $("#btn-export-pdf");
   var urlExportPdf = $boutonExportPdf.attr("href");

   // Buffer contenant l'intégralité des valeurs possibles pour chaque filtres
   let filtresValuesBuffer = {};

    // On initialise notre regex pour récupérer la clé du select (sans être dépendant du form type de symfony)
    const keyregex = /^.*\[(.*)]$/;

    /**
     * Afin de ne pas avoir de problemes de scroll avec les selectpickers, on décharge la liste des valeurs dans un buffer
     */
    $('.filtres-supplementaires select').each(function() {
        const key = keyregex.exec($(this).attr('name'))[1];
        filtresValuesBuffer[key] = [];
        $(this).find('option').each(function() {
            let value = $(this).val();
            if (value !== '') {
                filtresValuesBuffer[key].push({value: value, text: $(this).text()});
            }
        });
    });

    /**
     * Masquage / Démasquage des composants impactés
     */
    $('#donnees').on('click', '.operation-impacts-head', function(e) {
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

    /**
     * Récupère dans le buffer les options correspondantes aux valeurs demandées
     */
    const getBufferedValues = function(key, values) {
        let ret = [];
        if (key in filtresValuesBuffer) {
            filtresValuesBuffer[key].forEach(function(option){
                if (values.indexOf(option.value) > -1) {
                    ret.push(option);
                }
            });
        }
        return ret;
    }

    /**
     * Fonction permettant de mettre à jour les choix dans les filtres en fonction du résultat envoyé.
     */
    const majAffichageChoixFiltres = function() {

        // Avant de vider les listes de sélection, on récupère la valeur actuellement sélectionnée (filtres actifs)
        let filtresValuesSelected = {};
        $('.filtres-supplementaires select').each(function(){
            const key = keyregex.exec($(this).attr('name'))[1];
            $(this).find('option:selected').each(function(){
                let value = $(this).val();
                if (value !== '') {
                    if (!(key in filtresValuesSelected)) {
                        filtresValuesSelected[key] = [];
                    }
                    filtresValuesSelected[key].push(value);
                }

            });
        });

        // On vide les listes pour les remplir avec uniquement les valeurs de filtres possibles dans les résultats (ce qui evitera des problemes de scroll par la suite)
        $('.filtres-supplementaires select').empty();
        $('.filtres-supplementaires select').append($('<option value=""></option>'));

        // On parcourt chaque résultat (qui n'est pas caché)
        let filtresValuesExisting = {};
        $('#donnees table tbody tr:not(.d-none)').each(function() {
            // On parcourt les données "data"
            $.each(this.dataset, function(key, value) {
                // Si jamais la valeur possède plusieurs valeurs, nous coupons la chaine via le séparateur
                $.each(value.split('|'), function(k, v) {
                    if (v !== '') {
                        // On transforme la clé en camel case (utile pour composantsImpactés)
                        key = key.replace(/([-_][a-z])/ig, ($1) => {
                            return $1.toUpperCase()
                                .replace('-', '')
                                .replace('_', '');
                        });
                        // On affiche la valeur dans le sélecteur
                        if (key in filtresValuesBuffer) {
                            if (!(key in filtresValuesExisting)) {
                                filtresValuesExisting[key] = [];
                            }
                            filtresValuesExisting[key].push(v);
                        }
                    }
                });
            });
        });
        
        // On remplie les listes de sélection uniquement avec les valeurs sélectionnées précédemment
        $('.filtres-supplementaires select').each(function(){
            let $select = $(this);
            const key = keyregex.exec($select.attr('name'))[1];
            if (key in filtresValuesExisting) {
                let existingOptions = getBufferedValues(key, filtresValuesExisting[key])
                existingOptions.forEach(option => {
                    let $option = $('<option></option>').text(option.text).val(option.value);
                    if (key in filtresValuesSelected && filtresValuesSelected[key].indexOf(option.value) > -1) {
                        $option.prop('selected', true);
                        $option.attr('selected', 'selected');
                     }
                    $select.append($option);
                });
            }
            $select.selectpicker('refresh');
        });

        // On force le rafraichissement du controle pour refléter le nouvel état de la liste de sélection
        $('.filtres-supplementaires select').selectpicker('refresh');

    };

    /**
     * Fonction permettant de filtrer les opérations en fonction des filtres saisie
     */
    const filtrageOperations = function() {

        // On affiche toutes les opérations
        $('#donnees table tbody tr').removeClass('d-none');

        // On test le filtre de type
        if ($('.filtres-supplementaires #type_gesip:checked').length === 0) {
            $('#donnees table tbody tr[data-gesip="1"]').addClass('d-none');
        }
        if ($('.filtres-supplementaires #type_mepssi:checked').length === 0) {
            $('#donnees table tbody tr[data-mepssi="1"]').addClass('d-none');
        }

        // On parcourt tous les filtres de type select
        $('.filtres-supplementaires select').each(function() {

            // On récupère la clé via notre regex
            const key = keyregex.exec($(this).attr('name'))[1];

            // On récupère la valeur saisie
            const val = $(this).val();

            // Si une valeur est saisie
            if (val !== '') {
                // On parcourt nos opérations non déjà masqué pour regarder si nous avons une infos concordante
                $('#donnees table tbody tr:not(.d-none)').each(function() {
                    const values = $(this).data(key).toString().split('|');
                    if ($.inArray(val, values) === -1) {
                        $(this).addClass('d-none');
                    }
                });
            }
        });

        // On modifie la liste des choix
        majAffichageChoixFiltres();
    };

    /**
     * Dès le chargement de la page, on filtre les informations puis on masque les choix dans les filtres secondaires
     */
    filtrageOperations();
    majAffichageChoixFiltres();
    $('#donnees').show();

    /**
     * Lorsque l'on change la valeur d'un filtre secondaire
     */
    $('.filtres-supplementaires select, .filtres-supplementaires input').change(function(e) {
        e.preventDefault();
        filtrageOperations();
    });

   /**
    * clic sur le bouton de demande d'export PDF
    */
   $boutonExportPdf.on("click", function() {
      //on ajoute la valeur des filtres à l'URL pointée par le bouton
      let filtres = '';
      filtres += ($("#type_gesip:checked").length === 0 ? '' : '&type[]=gesip');
      filtres += ($("#type_mepssi:checked").length === 0 ? '' : '&type[]=mepssi');
      filtres += ($("#recherche_mep_ssi_exploitants").val() == '' ? '' : '&exploitant=' + $("#recherche_mep_ssi_exploitants").val());
      filtres += ($("#recherche_mep_ssi_equipe").val() == '' ? '' : '&equipe=' + $("#recherche_mep_ssi_equipe").val());
      filtres += ($("#recherche_mep_ssi_composants").val() == '' ? '' : '&composant=' + $("#recherche_mep_ssi_composants").val());
      filtres += ($("#recherche_mep_ssi_pilotes").val() == '' ? '' : '&pilote=' + $("#recherche_mep_ssi_pilotes").val());
      filtres += ($("#recherche_mep_ssi_composantsImpactes").val() == '' ? '' : '&composantImpacte=' + $("#recherche_mep_ssi_composantsImpactes").val());
      filtres += ($("#recherche_mep_ssi_demandeur").val() == '' ? '' : '&demandeur=' + $("#recherche_mep_ssi_demandeur").val());
      if (filtres == '' ) {
         $(this).attr("href", urlExportPdf);
      } else {
         $(this).attr("href", urlExportPdf + '?' + filtres.substr(1));
      }
   });

});
