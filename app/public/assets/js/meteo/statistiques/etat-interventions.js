$(document).ready(function() {
    // on initialise des variables
    var requeteAjax = null;
    var $titreResultats = $('#titre-resultats');
    var $listeResultats = $('#liste-resultats');
    var $liensExport = $('#liens-export');

    /*
     * action en cas de validation du formulaire
     */
    $('#btn-valider').click(function(e) {
        e.preventDefault();

        // on efface le résultat d'une éventuelle demande précédente
        $titreResultats.empty();
        $listeResultats.empty();
        $liensExport.hide();
        $liensExport.find('a').attr('href', '#');

        // on contrôle la saisie
        let anneeDebut = $('#etat_interventions_anneeDebut').val();
        let anneeFin = $('#etat_interventions_anneeFin').val();
        if (anneeFin < anneeDebut) {
            $('.modal-body p').text('La période sélectionnée est incohérente. Merci de revoir votre saisie.');
            $('#erreurSaisieModal').modal('show');
            return;
        }
        let enPourcentages = $('#etat_interventions_typeRestitution').val() == 'Pourcentage' ? true : false;
        if ((enPourcentages == true) && (anneeFin == anneeDebut)) {
            $('.modal-body p').text('La période sélectionnée est incohérente pour ce type de restitution. Merci de revoir votre saisie.');
            $('#erreurSaisieModal').modal('show');
            return;
        }
        let parBureauRattachement = $('#etat_interventions_bureauRattachement').is(':checked') ? true : false;

        // la saisie est correcte - on récupère les données à afficher via une requête Ajax
        window.bigLoadingDisplay(true);
        if (requeteAjax != null) {
            requeteAjax.abort();
        }
        requeteAjax = $.ajax({
            url: '/ajax/meteo/statistiques/etat-interventions/' + anneeDebut + '/' + anneeFin
                 + '?bureau=' + (parBureauRattachement == true ? 'oui' : 'non'),
            method: 'GET',
        })
        .done(function(reponse) {
            window.bigLoadingDisplay(false);
            let totaux = [];
            let precedValeur;
            let debValeur;
            let finValeur;

            // on affiche l'entête du tableau
            let buffer = '<tr><th>' + (parBureauRattachement == true ? 'Bureau de rattachement' : 'Mois') + '</th>';
            if (enPourcentages == true) {
                debValeur = null;
                reponse.annees.forEach(function(annee) {
                    if (debValeur == null) {
                        debValeur = annee;
                    } else {
                        buffer += '<th>' + precedValeur + '-' + annee +'</th>';
                        finValeur = annee;
                    }
                    precedValeur = annee;
                    totaux[annee] = 0;
                });
                buffer += '<th>' + debValeur + '-' + finValeur +'</th>';
            } else {
                reponse.annees.forEach(function(annee) {
                    buffer += '<th>' + annee +'</th>';
                    totaux[annee] = 0;
                });
            }
            buffer += '</tr>';
            $titreResultats.append(buffer);

            // on affiche le corps du tableau
            buffer = '';
            if (enPourcentages == true) {
                for (let item in reponse.comptage) {
                    buffer += '<tr><th>' + reponse.references[item] + '</th>';
                    debValeur = null;
                    reponse.annees.forEach(function(annee) {
                        if (debValeur == null) {
                            debValeur = reponse.comptage[item][annee];
                        } else {
                            buffer += '<td>';
                            if (precedValeur == 0) {
                                if (reponse.comptage[item][annee] == 0) {
                                    buffer += '0%';
                                } else {
                                    buffer += 'n/a';
                                }
                            } else {
                                buffer += Math.round(((reponse.comptage[item][annee] - precedValeur) / precedValeur) * 100) + '%';
                            }
                            buffer += '</td>';
                            finValeur = reponse.comptage[item][annee];
                        }
                        precedValeur = reponse.comptage[item][annee];
                        totaux[annee] += precedValeur;
                    });
                    buffer += '<td>';
                    if (debValeur == 0) {
                        if (finValeur == 0) {
                            buffer += '0%';
                        } else {
                            buffer += 'n/a';
                        }
                    } else {
                        buffer += Math.round(((finValeur - debValeur) / debValeur) * 100) + '%';
                    }
                    buffer += '</td></tr>';
                };
                buffer += '<tr><th>Total</th>';
                debValeur = null;
                reponse.annees.forEach(function(annee) {
                    if (debValeur == null) {
                        debValeur = totaux[annee];
                    } else {
                        buffer += '<td>';
                        if (precedValeur == 0) {
                            if (totaux[annee] == 0) {
                                buffer += '0%';
                            } else {
                                buffer += 'n/a';
                            }
                        } else {
                            buffer += Math.round(((totaux[annee] - precedValeur) / precedValeur) * 100) + '%';
                        }
                        buffer += '</td>';
                        finValeur = totaux[annee];
                    }
                    precedValeur = totaux[annee];
                });
                buffer += '<td>';
                if (debValeur == 0) {
                    if (finValeur == 0) {
                        buffer += '0%';
                    } else {
                        buffer += 'n/a';
                    }
                } else {
                    buffer += Math.round(((finValeur - debValeur) / debValeur) * 100) + '%';
                }
                buffer += '</td></tr>';
            } else {
                for (let item in reponse.comptage) {
                    buffer += '<tr><th>' + reponse.references[item] + '</th>';
                    reponse.annees.forEach(function(annee) {
                        buffer += '<td>' + reponse.comptage[item][annee] + '</td>';
                        totaux[annee] += reponse.comptage[item][annee];
                    });
                    buffer += '</tr>';
                };
                buffer += '<tr><th>Total</th>';
                reponse.annees.forEach(function(annee) {
                    buffer += '<td>' + totaux[annee] + '</td>';
                });
                buffer += '</tr>';
                if (parBureauRattachement == false) {
                    buffer += '<tr><th>Moyenne mensuelle</th>';
                    reponse.annees.forEach(function(annee) {
                        buffer += '<td>' + Math.round(totaux[annee] / 12) + '</td>';
                    });
                    buffer += '</tr>';
                }
            }
            $listeResultats.append(buffer);

            // on affiche les boutons d'export
            let urlPath = '/meteo/statistiques/etat-interventions/' + anneeDebut + '/' + anneeFin + '/';
            let donnees = [];
            $('#tableau-resultats tr').each(function(i) {
                donnees[i] = [];
                $(this).children().each(function(j) {
                    donnees[i][j] = $(this).text();
                });
            });
            let queryString = '?donnees=' + JSON.stringify(donnees);
            $('.export-xlsx').attr('href', urlPath + 'xlsx' + queryString);
            $('.export-pdf').attr('href', urlPath + 'pdf' + queryString);
            $liensExport.show();
        })
        .fail(function() {
            window.bigLoadingDisplay(false);
            alert('Impossible de récupérer les demandes dans la même période concernée.');
        });

    });
});
