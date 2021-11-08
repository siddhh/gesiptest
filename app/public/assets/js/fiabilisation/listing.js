/**
 * Gestion des demandes
 */
$(document).ready(function(){

    /**
     * Initialisation
     */
    // On déclare quelques variables
    let actionUrl = $('.page-list form').attr('action');
    let actionRequest = null;
    let $checkboxesContainer = $('.checkall-container');
    let $actionsBtn = $('.btn.accept, .btn.refuse');
    // On désactive les boutons si jamais lors de l'actualisation de la page ils sont toujours actifs
    $actionsBtn.prop('disabled', 'disabled');

    /**
     * Activation / Désactivation des boutons "Accepter" / "Refuser" en fonction du nombre de cases cochées
     */
    $checkboxesContainer.on('CheckboxesChange', function(e, nbr) {
        let possedeDesDemandesCochees = (nbr > 0);
        $actionsBtn.prop('disabled', possedeDesDemandesCochees ? '' : 'disabled');
        $('.accept').toggleClass('btn-success', possedeDesDemandesCochees);
        $('.refuse').toggleClass('btn-danger', possedeDesDemandesCochees);
    });

    /**
     * Déclaration de quelques fonctions utiles
     */
    // Effectue la requête vers l'api pour accepter / refuser une(des) demande(s)
    let submitDemandeAction = function(actionType) {
        // On affiche le big loading
        window.bigLoadingDisplay(true);
        // Si une requête est déjà en cours, on l'annule
        if (actionRequest !== null) {
            actionRequest.abort();
        }
        //
        let demandeIds = [];
        $checkboxesContainer.data('checkedBoxes').each(function(){
            demandeIds.push($(this).val());
        });
        actionRequest = $.ajax({
            type: 'put',
            url: actionUrl.replace('accept', '') + actionType,
            dataType: 'json',
            data: {
                demandeIds: demandeIds,
                comment: $('textarea[name="comment"]').val()
            },
            success: function(data) {
                // On renvoie le formulaire de recherche pour forcer le rechargement de la page...
                $('form[name="recherche"]').submit();
            },
            error: function(error) {
                // Si nous ne sommes pas dans le cas d'une requête annulée
                if (error.status !== 0) {
                    alert('Impossible de poursuivre l\'action demandée. Le traitement a été annulé.');
                    window.bigLoadingDisplay(false);
                }
            }
        });
    };
    // Vérifie si le service qui traite la demande correspond au demande (cas DME)
    let isCurrentServiceDemands = function(serviceId) {
        let status = true;
        $checkboxesContainer.data('checkedBoxes').each(function () {
            if ($(this).parents('tr').data('service-id') !== serviceId) {
                return status = false;
            }
        });
        return status;
    };
    // Affiche et customize la fenêtre modale en fonction des cas
    let showConfirmationModal = function(step, actionType)
    {
        // On initialise la fenêtre modale
        let $divModal = $('.modal-confirm');
        $divModal.find('.modal-body').empty();
        $divModal.find('.confirm').off('click');

        // Si nous demandons confirmation de refus/acceptation d'une demande concernant une autre équipe que le DME connecté
        if(step === 'dme-check') {
            $divModal.find('.modal-body').append($('<p>Vous avez sélectionné une(des) demande(s) qui concerne une autre équipe.</p>'));
            $divModal.find('.modal-body').append($('<p>Souhaitez-vous poursuivre ?</p>'));
            $divModal.find('.confirm').on('click', function(){
                showConfirmationModal('approve-check', actionType);
            });
        // Sinon, nous demandons confirmation avant de lancer la requête vers l'API
        } else {
            $divModal.find('.modal-body').append($(actionType === 'accept' ? '<p>Accepter les demandes sélectionnées ?</p>' : '<p>Refuser les demandes sélectionnées ?</p>'));
            $divModal.find('.confirm').on('click', function(){
                $divModal.modal('hide');
                submitDemandeAction(actionType);
            });
        }

        // On affiche la modale dans tous les cas
        $divModal.modal('show');
    };

    /**
     * Gestion des boutons "Accepter" / "Refuser"
     */
    $actionsBtn.on('click', function(e) {
        e.preventDefault();

        // On déclare quelques variables
        let serviceDmeId = $('.page-label').data('dme-service-id');
        let estAdmin = (serviceDmeId === undefined);
        let actionType = $(this).hasClass('accept') ? 'accept' : 'refuse';

        // Si l'utilisateur n'est pas un admin, et que l'une des demandes sélectionnées ne fait pas parti de son équipe
        if (!estAdmin && serviceDmeId && !isCurrentServiceDemands(serviceDmeId)) {
            showConfirmationModal('dme-check', actionType)
        } else {
            showConfirmationModal('approve-check', actionType)
        }
    });

});
