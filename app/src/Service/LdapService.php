<?php

namespace App\Service;

use Symfony\Component\Ldap\Ldap;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LdapService
{
    /** @var Ldap */
    private $ldap;
    /** @var int */
    private const NBR_RESULTATS = 30;
    /** @var bool */
    private $disableLdap;

    /**
     * Constructeur du service Ldap
     * @param ParameterBagInterface $environnement
     */
    public function __construct(ParameterBagInterface $environnement)
    {
        $this->disableLdap = empty($environnement->get('ldap.dsn'));

        if (!$this->disableLdap) {
            $ldapHost = explode('//', $environnement->get('ldap.dsn'))[1];
            $ldapHost = explode(':', $ldapHost);

            $fp = fsockopen($ldapHost[0], $ldapHost[1], $errno, $errstr, 1);
            fclose($fp);

            $this->ldap = Ldap::create('ext_ldap', [
                'connection_string' => $environnement->get('ldap.dsn')
            ]);
            $this->ldap->bind(
                $environnement->get('ldap.dn'),
                $environnement->get('ldap.pass')
            );
        }
    }

    /**
     * Recherche sur l'annuaire ldap
     * @param string $domaine
     * @param string $filtres
     * @return array
     */
    private function rechercheLdap(string $domaine, string $filtres): array
    {
        if ($this->disableLdap) {
            return [];
        }

        $requeteLdap = $this->ldap->query(
            $domaine,
            $filtres,
            [
                'maxItems' => self::NBR_RESULTATS
            ]
        );
        return $requeteLdap->execute()->toArray();
    }

    /**
     * Formattage de la réponse pour récupérer les nom et mails
     * @param array $donnees
     * @param string $colonneNom
     * @param string $colonneMail
     * @return array
     */
    private function formattageReponseRecherche(array $donnees, string $colonneNom = "cn", string $colonneMail = "mail"): array
    {
        $formattageDonnees = [];
        foreach ($donnees as $entree) {
            if ($entree->getAttribute($colonneMail)) {
                $formattageDonnees[] = [
                    'nom' => $entree->getAttribute($colonneNom)[0],
                    'mail' => strtolower($entree->getAttribute($colonneMail)[0])
                ];
            }
        }
        return $formattageDonnees;
    }

    /**
     * Méthode permettant de rechercher dans l'annuaire des personnes
     * @param string $recherche
     * @return array
     */
    public function recherchePersonnes(string $recherche): array
    {
        $resultatLdap = $this->rechercheLdap(
            'ou=personnes,ou=dgfip,ou=mefi,o=gouv,c=fr',
            '(&(objectClass=mefiPersonne)(cn=*' . $recherche . '*))'
        );
        return $this->formattageReponseRecherche($resultatLdap);
    }

    /**
     * Méthode permettant de rechercher dans l'annuaire des structures
     * @param string $recherche
     * @return array
     */
    public function rechercheStructures(string $recherche): array
    {
        $resultatLdap = $this->rechercheLdap(
            'ou=structures,ou=dgfip,ou=mefi,o=gouv,c=fr',
            '(&(objectClass=mefiStructure)(sigle=*' . $recherche . '*))'
        );
        return $this->formattageReponseRecherche($resultatLdap, 'sigle');
    }
}
