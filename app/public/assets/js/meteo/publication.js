$(document).ready(function() {

    var periodeDebut;
    var periodeFin;
    var action;

    $('form button.btn-primary').click('on', function(e) {
        e.preventDefault();

        let form = '#' + $('form').attr('name');
        action = $(this).attr('id').substring(4);
        let $periode = $(form + '_' + action);

        if ($periode.val() === '') {
            let $modal = $('#erreurSaisieModal');
            $modal.modal('show');
            return false;
        }

        // --- Affichage ---
        // On met en forme l'affichage des dates de début et de fin
        //  * Si du 26/12/2019 au 01/01/2020, alors on affiche "Du 26/12/2019 au 01/01/2020"
        //  * Si du 28/11/2019 au 04/12/2019, alors on affiche "Du 28/11 au 04/12/2019"
        //  * Si du 19/12/2019 au 25/12/2019, alors on affiche "Du 19 au 25/12/2019"
        periodeDebut = moment($periode.val());
        periodeFin = moment($periode.val()).add(6, 'days');

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

        let $modal = $('#enregistrerModificationsModal');
        $modal.find('.modal-body p').html('Confirmez-vous la ' + (action == 'depublication' ? 'dépublication' : action) + ' de la météo pour la période du ' + pdTexte + ' au ' + pfTexte + ' ?');
        $modal.modal('show');
    });

    $("#btn-ouiEnregister").on('click', function(e) {
        e.preventDefault();
        $('#enregistrerModificationsModal').modal('hide');
        $('form button').attr('disabled', true);

        $.ajax({
            url: "/ajax/meteo/periode/action",
            method: 'POST',
            dataType: 'json',
            data: {
                action: action,
                debut: periodeDebut.format("YYYY-MM-DD"),
                fin: periodeFin.format("YYYY-MM-DD")
            },
            success: function() {
                document.location.reload();
            },
            error: function() {
                alert('Impossible de soumettre les informations au serveur.');
                $('form button').removeAttr('disabled');
            },
        });
    });

    $('#btn-verif-validation').click(function(e) {
        // Initialisation
        e.preventDefault();
        let $modale = $('#validationModal');

        // On lance la requête
        window.bigLoadingDisplay(true);
        $.get("/ajax/meteo/validations/" + $('#publication_publication').val().replaceAll('-', ''))
        .done(function(reponse) {
            // On vide le tableau de la modale
            $modale.find('table tbody').html('');

            // On ajoute chaque élément de la réponse
            for (let i = 0 ; i < reponse.length ; i++) {
                let $tr = $('<tr></tr>').append(
                    $('<td class="align-middle"><a href="' + reponse[i]['href'] + '" target="_blank"><i class="fa fa-search"></i></a></td>'),
                    $('<td class="align-middle">' + reponse[i]['label'] + '</td>'),
                    reponse[i]['meteo_saisie']
                        ? $('<td class="text-center align-middle"><strong class="text-success"><i class="fa fa-check"></i></strong></td>')
                        : reponse[i]['meteo_validation'] ? $('<td class="text-center align-middle"><strong>R.A.S.</strong></td>') : $('<td class="text-center align-middle"><strong class="text-danger"><i class="fa fa-times"></i></strong></td>'),
                    reponse[i]['meteo_validation']
                        ? $('<td class="text-center align-middle"><strong class="text-success"><i class="fa fa-check"></i> <small class="font-weight-bold">' + moment(reponse[i]['meteo_validation']).format('DD/MM/YYYY HH:mm:ss')  + '</small></strong></td>')
                        : $('<td class="text-center align-middle"><strong class="text-danger"><i class="fa fa-times"></i></strong></td>'),
                );
                $modale.find('table tbody').append($tr);
            }

            // On affiche la modale
            $modale.modal('show');
        })
        .fail(function() {
            alert("Impossible de d'effectuer la validation de la météo. Merci de réessayer plus tard.");
        })
        .always(function() {
            window.bigLoadingDisplay(false);
        });
    });
});
