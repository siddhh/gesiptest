<?php

namespace App\Utils;

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

class Pagination
{
    /** @var int */
    const ELEMENTS_PAR_PAGE = 20;

    /** @var Query */
    private $requete;

    /** @var int */
    private $elementsParPage;

    /** @var int */
    private $pageCourante;

    /** @var Paginator */
    private $paginator;

    /** @var boolean */
    private $resultatAvecEntities;

    /**
     * Constructeur de la classe de pagination
     *
     * @param Query $requete
     * @param integer $pageCourante
     * @param boolean $resultatAvecEntities
     * @param integer $elementsParPage
     */
    public function __construct(Query $requete, int $pageCourante, bool $resultatAvecEntities = false, int $elementsParPage = self::ELEMENTS_PAR_PAGE)
    {
        $this->requete = $requete;
        $this->pageCourante = $pageCourante;
        $this->resultatAvecEntities = $resultatAvecEntities;
        $this->elementsParPage = $elementsParPage;
        $this->paginator = new Paginator($this->getRequete());
    }

    /**
     * Traitement de la requête paginée
     *
     * @return array
     */
    public function traitement(): array
    {
        $resultat = $this->getPaginator()->getQuery()
            ->setFirstResult($this->getDecalage())
            ->setMaxResults($this->getElementsParPage());

        if (!$this->resultatAvecEntities) {
            $resultat = $resultat->getArrayResult();
        } else {
            $resultat = $resultat->getResult();
        }

        return [
            'donnees' => $resultat,
            'pagination' => [
                'total' => $this->getTotal(),
                'parPage' => $this->getElementsParPage(),
                'pages' => $this->getNombreDePages(),
                'pageCourante' => $this->getPageCourante()
            ]
        ];
    }

    /**
     * Calcul du décalage pour la requête SQL
     *
     * @return int
     */
    private function getDecalage(): int
    {
        return $this->getElementsParPage() * ($this->getPageCourante() - 1);
    }

    /**
     * Récupération du nombre d'éléments total correspondant à la requête (hors pagination)
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->getPaginator()->count();
    }

    /**
     * Récupération du nombre de pages en fonction du nombre de résultat de la requête
     *
     * @return int
     */
    public function getNombreDePages(): int
    {
        return ceil($this->getTotal() / $this->getElementsParPage());
    }

    /**
     * Accesseur de l'attribut requete
     *
     * @return Query
     */
    public function getRequete(): Query
    {
        return $this->requete;
    }

    /**
     * Accesseur de l'attribut elementsParPage
     *
     * @return integer
     */
    public function getElementsParPage(): int
    {
        return $this->elementsParPage;
    }

    /**
     * Accesseur de l'attribut pageCourante
     *
     * @return integer
     */
    public function getPageCourante(): int
    {
        return $this->pageCourante;
    }

    /**
     * Accesseur de l'attribut paginator
     *
     * @return Paginator
     */
    public function getPaginator(): Paginator
    {
        return $this->paginator;
    }
}
