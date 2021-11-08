/**
 * Publication des documents
 */

$(document).ready(function () {

    /**
     * Initialisation
     */
    let $tbodyFichiers = $('.liste-fichiers tbody');
    let $tdAjouteFichier = $('.ajoute-fichier');
    let formFichierTemplate = $tbodyFichiers.find('.template').remove()[0].outerHTML;

    /**
     * Retourne une chaine de caractère représentant la taille d'un fichier
     */
    function getTailleHumanReadable(value, round = 2) {
        let units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        let base = 0;
        while (value / Math.pow(1024, base) > 1000 && base < units.length) {
            base++;
        }
        let rounder = Math.pow(10, round);
        return Math.round(rounder * value / Math.pow(1024, base)) / rounder + ' ' + units[base] + '.';
    }

    /**
     * Retourne le nom du fichier sans extention
     */
    function getFileInfos(fichier) {
        let ret = {
            'fileName': fichier.name,   // Nom complet du fichier (extension comprise)
            'name': '',                 // Nom sans l'extension
            'extension': '',            // Extension du fichier
            'taille': fichier.size,     // Taille du fichier en octets
            'mimeType': fichier.type    // Mime type du fichier
        };
        let dotIndex = fichier.name.lastIndexOf('.');
        if (dotIndex > -1) {
            ret.name = fichier.name.substring(0, dotIndex);
            ret.extension = fichier.name.substring(dotIndex);
        } else {
            ret.name = fichier.name;
        }
        return ret;
    }

    /**
     * Renumérote l'ordre des fichiers
     */
    function setOrdreFichiers() {
        $tbodyFichiers.find('tr').each(function(index) {
            $(this).find('input[type="hidden"]').val(index + 1);
        });
        // Désactive le premier et le dernier bouton permettant de gérer l'ordre des fichiers
        $tbodyFichiers.find('.descendre-fichier:disabled, .monter-fichier:disabled').prop('disabled', false);
        $tbodyFichiers.find('.descendre-fichier:first, .monter-fichier:last').prop('disabled', true);
    }

    /**
     * Ajoute un nouveau fichier à la liste des fichiers du document
     */
    $tdAjouteFichier.on('change', 'input[type="file"]', function(e) {
        let $inputFichier = $(e.target);
        let acceptAttribute = $inputFichier.attr('accept');
        let mimeTypesString = $inputFichier.data('mime-types');
        let mimeTypesAutorises = mimeTypesString.split(',');
        $('.ajoute-fichier .alert').remove();
        $(e.target.files).each(function() {
            // Effectue quelques contrôles avant d'ajouter le fichier
            let lineNumber = $tbodyFichiers.find('tr').length;
            let ordre = lineNumber + 1;
            let fileInfos = getFileInfos(this);
            let maxFileSize = 64 * Math.pow(1024, 2);
            let erreursFichier = false;
            if (fileInfos.taille > maxFileSize) {
                // teste si la taille du fichier est trop importante
                let message = 'Le fichier "' + fileInfos.fileName + '" ne peut pas être ajouté car il dépasse la taille maximum autorisée de ' + (maxFileSize / Math.pow(1024, 2)) + ' Mo.';
                $inputFichier.parent().append($('<div></div>').addClass('alert alert-danger').text(message));
                erreursFichier = true;
            } else if (mimeTypesAutorises.indexOf(fileInfos.mimeType) < 0) {
                // teste si le mime type du fichier est autorisé
                let message = 'Le fichier "' + fileInfos.fileName + '" ne peut pas être ajouté car son type "' + fileInfos.mimeType + '" ne correspond pas aux types de fichier autorisés (' + acceptAttribute.split(',').join(', ') + ').';
                $inputFichier.parent().append($('<div></div>').addClass('alert alert-danger').text(message));
                erreursFichier = true;
            }
            if (erreursFichier) {
                return false;
            }
            // Ajoute une nouvelle entrée dans le formulaire caché
            $formFichier = $(formFichierTemplate.replace(/__name__/g, lineNumber));
            $formFichier.find('input[type="text"]').val(fileInfos.name.replaceAll(' ', '_'));
            $formFichier.find('td:nth-child(4)').text(getTailleHumanReadable(fileInfos.taille));
            $inputFichier.attr({
                'id': 'document_fichiers_' + lineNumber + '_fichier',
                'name': 'document[fichiers][' + lineNumber + '][fichier]'
            }).hide();
            $formFichier.find('td:nth-child(3)').text(fileInfos.extension);
            $formFichier.find('td:nth-child(2)').append($inputFichier);
            $tbodyFichiers.append($formFichier);
            // On a déplacé l'ancien input, donc il faut en créer un nouveau
            let $newInputFichier = $('<input />').attr({'type': 'file', 'accept': acceptAttribute}).data('mime-types', mimeTypesString);
            $tdAjouteFichier.append($newInputFichier);
            setOrdreFichiers();
        });
    });

    /**
     * Supprime un fichier dans la liste des fichiers du document
     */
    $tbodyFichiers.on('click', '.supprime-fichier', function() {
        $(this).parents('tr').remove();
        setOrdreFichiers();
    });

    /**
     * Si un fichier doit remonter / descendre par rapport aux autres
     */
    $tbodyFichiers.on('click', '.descendre-fichier', function() {
        let $tr = $(this).parents('tr');
        let index = $tbodyFichiers.find('tr').index($tr);
        if (index > 0) {
            // Place la rangée courante avant la rangée précédente (pour faire descendre sa position)
            let $trRef = $tbodyFichiers.find('tr:nth-child(' + index + ')');
            $tr.insertBefore($trRef);
            setOrdreFichiers();
        }
    });
    $tbodyFichiers.on('click', '.monter-fichier', function() {
        let $tr = $(this).parents('tr');
        let index = $tbodyFichiers.find('tr').index($tr);
        if (index < $tbodyFichiers.find('tr').length - 1) {
            // Place la rangée courante après la rangée suivante (pour faire remonter sa position)
            let $trRef = $tbodyFichiers.find('tr:nth-child(' + (index + 2) + ')');
            $tr.insertAfter($trRef);
            setOrdreFichiers();
        }
    });

    /**
     * Vérifie les noms des fichiers avant envoi
     */
    $('form').on('submit', function(event) {
        let erreursTrouvees = false;

        //Detecte si au moins un fichier est selectionné
        if ($('.liste-fichiers tbody tr').length === 0) {
            $tdAjouteFichier.find('.form-control-error').removeClass('form-control-error');
            $tdAjouteFichier.addClass('form-control-error');
            $tdAjouteFichier.addClass('form-control-error')
                .append(
                    $('<div></div>')
                    .addClass('alert alert-danger')
                    .text('Veuillez sélectionner au moins un fichier.')
                );
            erreursTrouvees = true;
        }

        //Test les caractères des noms de fichiers
        let labelRegEx = /^([0-9A-Za-z_-]){1,64}$/;
        $tbodyFichiers.find('.alert').remove();
        $tbodyFichiers.find('.form-control-error').removeClass('form-control-error');
        $tbodyFichiers.find('input[type="text"]').each(function() {
            let label = $(this).val();
            if (!label.match(labelRegEx)) {
                $(this).addClass('form-control-error');
                $(this).parent().append($('<div></div>').addClass('alert alert-danger').text('Nom de fichier invalide, seuls les caractères non accentués, les chiffres et les caractères spéciaux -, _ sont autorisés dans la limite de 64 caractères.'));
                erreursTrouvees = true;
            }
        });
        if (erreursTrouvees) {
            return false;
        }
    });

    setOrdreFichiers();

});
