/**
 * Propose un contrôle permettant de sélectionner des services
 * Dépendances: Jquery
 */

// Déclare au préalable cette fonction pour qu'elle soit visible en dehors de ce bloc (elle sera réécrite ci-dessous)
let initSelectList = function() { alert('InitSelectList not initialized !'); }

// Après chargement du DOM
$(document).ready(function() {

    /**
     * Initialisations
     */

    // Encart intervenants exterieurs
    let $intervenantsExterieurs = $('.demande-intervenant-exterieurs');
    // Case à cocher 'Autres missions'
    let $servicesSelectionVisibility = $intervenantsExterieurs.find('.selection-visibility');
    // Sélecteur de services
    let $servicesSelector = $intervenantsExterieurs.find('.services-selector');
    // Liste de sélection de services
    let $selServices = $servicesSelector.find('select');
    // Champ de formulaire caché
    let $selectFormControl = $('#intervention_exploitantExterieurs');

    /**
     * Sélectionne un service
     */
    let selectService = function(serviceId, serviceLabel) {
        let $liSelection = $('<li></li>').data('service-id', serviceId).text(serviceLabel);
        let $btRemoveSelection = $('<button></button>').attr('type', 'button')
            .data('service-id', serviceId)
            .addClass('btn btn-sm btn-danger btn-delete')
            .append($('<i></i>').addClass('fa fa-times'))
            .on('click', onServiceUnselect);
        $liSelection.append($btRemoveSelection);
        setServiceToForm(serviceId, true);
        $servicesSelector.find('.selection').append($liSelection);
    }

    /**
     * Importe / récupère la liste des services selectionnés dans le controle de formulaire dédié
     */
    let importServicesFromForm = function() {
        $servicesSelector.find('.selection').empty();
        $selectFormControl.find(':checked').each(function() {
            selectService($(this).val(), $(this).text());
        });
     }

    /**
     * Supprime toutes les entrées selectionnées dans le formulaire cachée (remise à blanc / purge)
     */
    let emptyServicesForm = function() {
        $selectFormControl.find(':selected').prop('selected', false);
    }

    /**
     * Exporte la liste des services sélectionnés vers le controle de formulaire
     */
    let exportServicesToForm = function() {
        emptyServicesForm();
        $servicesSelector.find('.selection li').each(function() {
            let serviceId = parseInt($(this).data('service-id'));
            setServiceToForm(serviceId, true);
        });
    }

    /**
     * Sélectionne / déselectionne un service dans le controle de formulaire dédié
     */
    let setServiceToForm = function(serviceId, selected) {
        $selectFormControl.find('option[value="' + serviceId + '"]').prop('selected', selected);
    }

    /**
     * Appelé lors de la sélection d'un service dans la liste d'ajout (évenement onchange)
     */
    let onServiceSelect = function() {
        let $selectedOption =  $(this).find(':selected');
        selectService($selectedOption.val(), $selectedOption.text());
        initSelectList();
    }

    /**
     * Appelé lors du retrait d'un service précédemment sélectionné (onclick sur bouton de suppression)
     */
    let onServiceUnselect = function() {
        let $liService = $(this).parents('li');
        setServiceToForm($liService.data('service-id'), false);
        $liService.remove();
        initSelectList();
    }

    /**
     * Appelé lorsqu'on doit afficher / cacher le controle de sélection d'exploitants externes
     */
    let onChangeServicesVisibility = function() {
        if ($(this).is(':checked')) {
            exportServicesToForm();
            $servicesSelector.show();
        } else {
            emptyServicesForm();
            $servicesSelector.hide();
        }
    }

    /**
     * Génère la liste du select picker à partir du controle fourni par le formulaire listant tous les services exploitants externes
     */
    initSelectList = function() {
        // On limite la sélection à 3 intervenants externes maximum.
        if ($selectFormControl.find(':selected').length < 3) {
            let selectedServiceIds = getSelectedServiceIds();
            $selServices.empty();
            $selectFormControl.find('option').each(function() {
                let serviceId = parseInt($(this).val());
                // On exclue de la liste de sélection les services déjà ajoutés ou proposés à partir de l'annuaire
                if (selectedServiceIds.indexOf(serviceId) < 0) {
                    let $optService = $('<option></option>').text($(this).text()).val(serviceId);
                    $selServices.append($optService);
                }
            });
            $selServices.val('default');
            $selServices.selectpicker('refresh');
            $selServices.selectpicker('show');
        } else {
            $selServices.selectpicker('hide');
        }
    }

    /**
     * Récupère la liste des identifiants des services déjà ajoutés ou proposés à partir de l'annuaire
     */
     let getSelectedServiceIds = function() {
        let serviceIds = [];
        $('.demande-service__services label').each(function() {
            serviceIds.push(parseInt($(this).data('service-id')));
        });
        $selectFormControl.find(':selected').each(function() {
            serviceIds.push(parseInt($(this).val()));
        });
        return serviceIds;
    }

    /**
     * Au chargement on synchronise les services sélectionnés dans le formulaire avec le controle de sélection de service
     */

    // On commence par se synchroniser avec le champ de formulaire associé (champ caché)
    importServicesFromForm();

    // On initialise la sélection de service exploitant externe à partir des champs déjà sélectionnés
    initSelectList();

    // On défini un handler pour permettre d'ajouter de nouveaux services exploitants externes
    $selServices.on('change', onServiceSelect);

    // On s'abonne au changement de la case à cocher pour cacher / montrer la selection et si il existe des intervenants externes sélectionnés au chargement, la case doit être cochée
    $servicesSelectionVisibility.on('change', onChangeServicesVisibility);
    if ($selectFormControl.find(':selected').length > 0) {
        $servicesSelectionVisibility.prop('checked', true);
    } else {
        onChangeServicesVisibility();
    }

});