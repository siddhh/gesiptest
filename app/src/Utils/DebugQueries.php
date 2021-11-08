<?php

namespace App\Utils;

use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\EntityManagerInterface;

class DebugQueries
{
    /** @var EntityManagerInterface */
    private $em;
    /** @var DebugStack */
    private $debugStack;

    /**
     * Constructeur de DebugQueries
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->debugStack = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($this->debugStack);
    }

    /**
     * Permet de retourner le nombre de requêtes effectuées par le manager de doctrine
     *
     * @return int
     */
    public function getQueriesCount(): int
    {
        return count($this->debugStack->queries);
    }

    /**
     * Permet de récupérer toutes les requêtes lancées par le manager de doctrine
     *
     * @return array
     */
    public function getQueries(): array
    {
        return $this->debugStack->queries;
    }
}
