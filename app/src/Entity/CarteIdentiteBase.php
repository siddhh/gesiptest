<?php

namespace App\Entity;

abstract class CarteIdentiteBase
{

    /**
     * Défini le nom du fichier
     */
    abstract public function setNomFichier(string $hash): CarteIdentiteBase;

    /**
     * Défini la taille du fichier
     */
    abstract public function setTailleFichier(string $taille): CarteIdentiteBase;
}
