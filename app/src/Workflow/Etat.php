<?php

namespace App\Workflow;

abstract class Etat
{
    /** @var MachineEtat */
    private $machine;

    /** @var array */
    private $donnees;

    /**
     * Constructeur d'un état.
     * @param MachineEtat $machine
     * @param array $donnees
     */
    public function __construct(MachineEtat $machine, array $donnees = [])
    {
        $this->machine = $machine;
        $this->donnees = $donnees;
    }

    /**
     * Récupération des données de l'état.
     * @return array
     */
    public function getDonnees(): array
    {
        return $this->donnees;
    }

    /**
     * Permet d'hydrater les données issues de la base de données.
     * @param array $donnees
     * @return array
     */
    public function hydraterDonnees(array $donnees = []): self
    {
        $this->donnees = $donnees;
        return $this;
    }

    /**
     * Retourne True, si nous pouvons sortir de cet état. (Défaut: true)
     * @return bool
     */
    public function peutSortir(): bool
    {
        return true;
    }

    /**
     * Retourne True si nous pouvons entrer dans cet état. (Défaut: true)
     * @return bool
     */
    public function peutEntrer(): bool
    {
        return true;
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        return [];
    }

    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    abstract public function getLibelle(): string;

    /**
     * Fonction permettant de récupérer la machine à état.
     * @return MachineEtat
     */
    protected function getMachineEtat(): MachineEtat
    {
        return $this->machine;
    }

    /**
     * Récupération de la liste des actions possibles sous forme d'action instanciée.
     * @return array
     */
    public function getActionsInstances(): array
    {
        $resultat = [];

        foreach ($this->getActions() as $actionClassName) {
            $resultat[] = new $actionClassName($this->getMachineEtat());
        }

        return $resultat;
    }
}
