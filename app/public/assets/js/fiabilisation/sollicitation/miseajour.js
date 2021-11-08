$(function() {

    // bouton de redirection
    $('button[data-url]').on('click', function(){
        document.location = $(this).data('url');
    });

    // afficher le cadre de modification lorsque l'on clique sur le bouton demandant la modification
    $('.show-balf-updater').on('click', function(event) {
        event.preventDefault();
        $('.balf-updater').removeClass('d-none');
        $(this).addClass('hidden');
    });

});
