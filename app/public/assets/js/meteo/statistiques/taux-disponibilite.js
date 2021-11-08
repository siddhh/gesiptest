$(() => {

   // On initialise quelques variables qui seront utiles par la suite
   let $filtresContainer = $('.page-filters');
   let urlAction = $filtresContainer.data('url');
   let $filtresForm = $filtresContainer.find('form');

   // Si on retourne en arrière, on recharge la page
   $(window).on("popstate", function(e) {
      window.bigLoadingDisplay(true);
      location.reload();
   });

   // Lorsque l'on clique sur la soumission du formulaire
   $filtresForm.submit(function(e) {
      e.preventDefault();

      // On remet à zéro pour les erreurs
      $filtresForm.find('.form-control-error').removeClass('form-control-error');
      $filtresForm.find('.form-label-error').removeClass('form-label-error');
      $filtresForm.find('.form-errors').html('');

      // On récupère les données du formulaire
      const data = $(this).serializeArray().reduce(function(obj, item) {
         obj[item.name.replace('taux_disponibilite[', '').replace(']', '')] = item.value;
         return obj;
      }, {});

      // Si tous les champs nécessaires ont été saisie
      let erreursDetectees = [];
      // Si le champ Exploitant est vide
      if (data['exploitant'] === '' || data['exploitant'] === undefined) {
         erreursDetectees.push({ 'champ': 'exploitant', 'message': 'Le champ est obligatoire.' });
      }
      // Si le champ Période début est vide
      if (data['debut'] === '') {
         erreursDetectees.push({ 'champ': 'debut', 'message': 'Le champ est obligatoire.' });
      }
      // Si le champ Période fin est vide
      if (data['fin'] === '') {
         erreursDetectees.push({ 'champ': 'fin', 'message': 'Le champ est obligatoire.' });
      }
      // Si le début est antérieur à la fin
      data['debutMoment'] = moment(data['debut'], "DD/MM/YYYY");
      data['finMoment'] = moment(data['fin'], "DD/MM/YYYY");
      if (data['debutMoment'] > data['finMoment']) {
         erreursDetectees.push({ 'champ': 'debut', 'message': '' });
         erreursDetectees.push({ 'champ': 'fin', 'message': 'La période sélectionnée est incohérente. Merci de revoir votre saisie.' });
      }

      // Si il n'y a pas d'erreur
      if (erreursDetectees.length === 0) {
         // on formate l'url d'action et on va chercher les informations
         let urlQuery = urlAction
             .replace('%23SE%23', data['exploitant'])
             .replace('%23PD%23', data['debutMoment'].format('YYYYMMDD'))
             .replace('%23PF%23', data['finMoment'].format('YYYYMMDD'));

         // On lance la requête
         window.bigLoadingDisplay(true);
         $.get(urlQuery)
             .done(function(response) {
                $('#donnees').html($(response).find('#donnees').html());
                history.pushState(null, null,urlQuery);
             })
             .fail(function() {
                alert("Un erreur est survenue lors de la récupération des informations. Merci de réessayer plus tard.");
             })
             .always(function() {
                window.bigLoadingDisplay(false);
             });

      // Sinon, on affiche les erreurs
      } else {
         $.each(erreursDetectees, function(i, d) {
            let $fieldGroup = $filtresForm.find('*[name="taux_disponibilite[' + d['champ'] + ']"]').parents('.form-group');
            $fieldGroup.find('.form-control').addClass('form-control-error');
            $fieldGroup.find('label').addClass('form-label-error');
            $fieldGroup.find('.form-errors').append($('<div></div>').html(d['message']))
         });
      }
   });
});
