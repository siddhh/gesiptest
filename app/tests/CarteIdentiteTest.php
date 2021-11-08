<?php

namespace App\Tests;

use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Composant;
use App\Entity\Service;
use App\Entity\CarteIdentite;
use App\Entity\CarteIdentiteEvenement;

class CarteIdentiteTest extends UserWebTestCase
{

    /**
     * Test d'accès aux Cartes d'Identité
     * @dataProvider getAccesCarteIdentite
     */
    public function testCarteIdentiteControleDesAcces(string $role)
    {
        $client = static::getClientByRole($role);

        // on teste l'existence du modèle de carte d'identité
        $modele = $client->getKernel()->getProjectDir() . "/public/assets/uploads/modele_carte_identite.ods";
        $this->assertFileExists($modele);

        // on teste l'accès à la page de gestion
        $crawler = $client->request(Request::METHOD_GET, '/gestion/carte-identite');
        $codeHttpAttendu = ($role == 'NON_CONNECTE' ? 302 : 200);
        $codeHttpRecu = $client->getResponse()->getStatusCode();
        $this->assertEquals($codeHttpAttendu, $codeHttpRecu);
        if ($codeHttpRecu === 200) {
            // on vérifie qu'il s'agit de la bonne page
            $this->assertSelectorTextSame('.page-header h2', "Carte d'identité des Composants");

            // on teste la présence de la partie spécifique à ROLE_ADMIN
            $crawlerPartiel = $crawler
                ->filter('form h3')
                ->reduce(function ($node) {
                    return $node->text() === 'Mises à jour ou création par les services';
                });
            $this->assertEquals(count($crawlerPartiel), ($role == Service::ROLE_ADMIN ? 1 : 0));

            // on teste la création de la carte d'un composant pris au hasard
            $composants = static::getEmRepository(Composant::class)->findBy(['archiveLe' => null]);
            $loterie = array_rand($composants, 1);
            $composantId = $composants[$loterie]->getId();
            $composantLabel = $composants[$loterie]->getLabel();
            $form = $crawler->selectButton('submitModaleAjoutModificationCarteIdentite')->form();
            $form['carte_identite[composant]']->select($composantId);
            $form['carte_identite[fichier]']->upload($modele);
            $form['carte_identite[commentaire]'] = 'création';
            $crawler = $client->submit($form);
            if ($role == Service::ROLE_INVITE) {
                // si rôle Invité : pas de message de succès
                $this->assertResponseIsSuccessful();
                $crawlerPartiel = $crawler->filter('.toast-body.alert-success');
                $this->assertEquals(count($crawlerPartiel), 0);

                // et aucune entité créée
                $cartes = static::getEmRepository(CarteIdentite::class)->findBy([], ['id' => 'ASC']);
                $this->assertEquals(count($cartes), 0);
            } else {
                // si autre rôle, on teste l'envoi du mail
                $this->assertEmailCount(1);
                $email = $this->getMailerMessage(0);
                $this->assertEmailHtmlBodyContains($email, 'composant ' . $composantLabel);

                // on suit la redirection
                $this->assertResponseRedirects();
                $crawler = $client->followRedirect();

                // on vérifie la présence du message de succès ...
                $crawlerPartiel = $crawler->filter('.toast-body.alert-success');
                $this->assertEquals(count($crawlerPartiel), 1);

                // ... et du composant dans la liste des cartes existantes
                $crawlerPartiel = $crawler
                    ->filter('#visualiser_composant option')
                    ->reduce(function ($node) use ($composantId) {
                        return $node->attr('value') == '/gestion/carte-identite/composant/' . $composantId;
                    });
                $this->assertEquals(count($crawlerPartiel), 1);

                // on teste la modification de la carte après adaptation du formulaire
                $document = new \DOMDocument();
                libxml_use_internal_errors(true);
                $document->loadHTML(str_replace('value="ajout"', 'value="modification"', $crawler->outerHtml()));
                libxml_clear_errors();
                $crawler->clear();
                $crawler->addDocument($document);
                $form = $crawler->selectButton('submitModaleAjoutModificationCarteIdentite')->form();
                $form['carte_identite[composant]']->select($composantId);
                $form['carte_identite[fichier]']->upload($modele);
                $form['carte_identite[commentaire]'] = 'modification';
                $crawler = $client->submit($form);

                // on teste l'envoi du mail
                $this->assertEmailCount(1);
                $email = $this->getMailerMessage(0);
                $this->assertEmailHtmlBodyContains($email, 'composant ' . $composantLabel);

                // on suit la redirection
                $this->assertResponseRedirects();
                $crawler = $client->followRedirect();

                // on vérifie la présence du message de succès
                $crawlerPartiel = $crawler->filter('.toast-body.alert-success');
                $this->assertEquals(count($crawlerPartiel), 1);

                // on teste la présence des 2 entités en base ...
                $cartes = static::getEmRepository(CarteIdentite::class)->findBy([], ['id' => 'ASC']);
                $this->assertEquals(count($cartes), 2);
                $carteId = $cartes[0]->getId();
                $this->assertEquals($cartes[0]->getComposant()->getId(), $composantId);
                $this->assertEquals($cartes[1]->getComposant()->getId(), $composantId);


                // ... et celle des 2 évènements
                $evenements = static::getEmRepository(CarteIdentiteEvenement::class)->findBy([], ['id' => 'ASC']);
                $this->assertEquals(count($evenements), 2);
                $this->assertEquals($evenements[0]->getComposant()->getId(), $composantId);
                $this->assertEquals($evenements[0]->getEvenement(), 'Nouvel enregistrement');
                $this->assertEquals($evenements[0]->getCommentaire(), 'création');
                $this->assertEquals($evenements[1]->getComposant()->getId(), $composantId);
                $this->assertEquals($evenements[1]->getEvenement(), 'Transmision aux administrateurs');
                $this->assertEquals($evenements[1]->getCommentaire(), 'modification');

                // on teste la suppression de la 2ème version de la carte (autorisée à ROLE_ADMIN uniquement)
                if ($role == Service::ROLE_ADMIN) {
                    $this->assertSelectorExists('.carte-identite-supprimer');
                    $form = $crawler->selectButton('carte_identite_supprimer')->form();
                    $crawler = $client->submit($form);

                    // on suit la redirection
                    $this->assertResponseRedirects();
                    $crawler = $client->followRedirect();

                    // on vérifie la présence du message de succès
                    $crawlerPartiel = $crawler->filter('.toast-body.alert-success');
                    $this->assertEquals(count($crawlerPartiel), 1);

                    // On vérifie qu'une seule carte d'identité est encore affichée
                    $crawlerPartiel = $crawler->filter('.liste-carte_identite tbody tr');
                    $this->assertEquals(count($crawlerPartiel), 1);

                    // On vérifie que la carte d'identité est conservée en base de données
                    $cartes = static::getEmRepository(CarteIdentite::class)->findBy([], ['id' => 'ASC']);
                    $this->assertEquals(count($cartes), 2);
                } else {
                    $this->assertSelectorNotExists('.carte-identite-supprimer');
                }

                // On teste la requête Ajax de transmission de carte avec un destinataire non pris en charge
                $client->xmlHttpRequest('POST', '/ajax/carte-identite/transmission/' . $carteId, ['destinataires' => ['toto']]);
                $this->assertResponseIsSuccessful();
                $this->assertJson($client->getResponse()->getContent());
                // On teste la requête Ajax de transmission de carte avec un destinataire pris en charge mais dont le service n'existe pas
                $client->xmlHttpRequest('POST', '/ajax/carte-identite/transmission/' . $carteId, ['destinataires' => ['Service Manager']]);
                $this->assertResponseIsSuccessful();
                $this->assertJson($client->getResponse()->getContent());
                $this->assertEquals($client->getResponse()->getContent(), '{"statut":"ko","message":"BALF Service Manager non trouv\u00e9e"}');
            }
        }
    }

    public function getAccesCarteIdentite(): array
    {
        $roles = [['NON_CONNECTE'], [Service::ROLE_ADMIN], [Service::ROLE_DME], [Service::ROLE_INTERVENANT], [Service::ROLE_INVITE]];
        return $roles;
    }
}
