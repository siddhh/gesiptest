var Pagination = {
    $elt: null,
    donnees: {
        'total': 0,
        'parPage': 0,
        'pages': 0,
        'pageCourante': 1
    },
    init: function(selecteur = "#pagination") {
        Pagination.$elt = $(selecteur);
        Pagination.$elt.on('click', '.page-item:not(.disabled, .active) .page-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            Pagination.$elt.trigger('changementPage', $(this).data('page'));
        });
    },
    maj: function(donnees) {
        Pagination.donnees = donnees;
        Pagination.purge();
        if(Pagination.donnees.total > 0) {
            Pagination.creation_page_precedente();
            Pagination.creation_pages();
            Pagination.creation_page_suivante();
        }
    },
    changementDePage: function(fonction) {
        Pagination.$elt.on('changementPage', function(e, page) {
            fonction(page, Pagination.$elt);
        });
    },

    purge: function() {
        Pagination.$elt.html('');
    },
    creation_page_precedente: function() {
        var $lien = $(
            '<li class="page-item">' +
            '   <a class="page-link" href="#" aria-label="Previous">'+
            '       <span aria-hidden="true">&laquo;</span>'+
            '   </a>'+
            '</li>'
        );

        if(Pagination.donnees.pageCourante == 1) {
            $lien.addClass('disabled');
        } else {
            $lien.find('a').data('page', (Pagination.donnees.pageCourante - 1));
        }

        Pagination.$elt.append($lien);
    },
    creation_page_suivante: function() {
        var $lien = $(
            '<li class="page-item">' +
            '   <a class="page-link" href="#" aria-label="Previous">'+
            '       <span aria-hidden="true">&raquo;</span>'+
            '   </a>'+
            '</li>'
        );

        if(Pagination.donnees.pageCourante == Pagination.donnees.pages) {
            $lien.addClass('disabled');
        } else {
            $lien.find('a').attr('data-page', (Pagination.donnees.pageCourante + 1));
        }

        Pagination.$elt.append($lien);
    },
    creation_pages: function() {
        for(var i = 1 ; i <= Pagination.donnees.pages ; i++) {
            var $lien = $(
                '<li class="page-item">' +
                '   <a class="page-link" href="#" data-page="' + i + '" aria-label="Page ' + i + '">'+ i + '</a>' +
                '</li>'
            );
            if(i == Pagination.donnees.pageCourante) {
                $lien.addClass('active');
            }
            Pagination.$elt.append($lien);
        }
    }
};
