<?php

namespace App\Utils;

use App\Entity\ActionHistory;
use App\Entity\Composant;
use App\Entity\Service;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;

class ExtraComposantHistory
{

    private const INITIAL_STATE = 'init';
    private const FINAL_STATE   = 'final';

    /** @var EntityManager */
    private $entityManager;

    /** @var Request */
    private $request;

    /** @var Service */
    private $service;

    /** @var int */
    private $objectId;

    /** @var array */
    private $states = [];

    /**
     * Initialise cette classe via un entity manager.
     * @param EntityManager $em
     * @param Request $request
     */
    public function __construct(EntityManager $em, Request $request, Service $service)
    {
        $this->entityManager = $em;
        $this->request = $request;
        $this->service = $service;
    }

    /**
     * Retourne l'identifiant de l'object monitoré par cet objet.
     */
    public function getObjectId(): ?int
    {
        return $this->objectId;
    }

    /**
     * Défini l'état initial pour un composant
     * @param Composant $composant
     */
    public function setInitialComposant(Composant $composant): void
    {
        $this->objectId = $composant->getId();
        $this->setState('annuaire', self::INITIAL_STATE, $composant->getAnnuaire());
        $this->setState('composantsImpactes', self::INITIAL_STATE, $composant->getComposantsImpactes());
        $this->setState('impactesParComposants', self::INITIAL_STATE, $composant->getImpactesParComposants());
        $this->setState('majLe', self::INITIAL_STATE, $composant->getMajLe());
    }

    /**
     * Défini l'état final pour un composant
     * @param Composant $composant
     */
    public function setFinalComposant(Composant $composant): void
    {
        $this->setState('annuaire', self::FINAL_STATE, $composant->getAnnuaire());
        $this->setState('composantsImpactes', self::FINAL_STATE, $composant->getComposantsImpactes());
        $this->setState('impactesParComposants', self::FINAL_STATE, $composant->getImpactesParComposants());
        $this->setState('majLe', self::FINAL_STATE, $composant->getMajLe());
    }

    /**
     * Défini l'état d'une propriété (initial, partial, final,...)
     * @param string $propertyName
     * @param string $state
     * @param mixed $stateValues
     */
    private function setState(string $propertyName, string $state, $stateValues): void
    {
        if (null !== $stateValues) {
            if ($stateValues instanceof \Traversable) {
                $values = [];
                foreach ($stateValues as $stateValue) {
                    if (!method_exists($stateValue, 'getSupprimeLe') || $stateValue->getSupprimeLe() === null) {
                        $values[] = method_exists($stateValue, 'getInfos') ? $stateValue->getInfos() : $stateValue->getId();
                    }
                }
                $this->states[$propertyName][$state] = $values;
            } elseif ($stateValues instanceof \DateTimeInterface) {
                $this->states[$propertyName][$state] = $stateValues->format('c');
            } else {
                $this->states[$propertyName][$state] = $stateValues;
            }
        }
    }

    /**
     * Récupère les propriétés ayant subi des changements entre l'état initial et final
     *  Sous forme de tableau old representant les valeurs initiales et new les nouvelles valeurs
     * @param array $propertyNames
     */
    private function getUpdatedProperties(array $propertyNames): array
    {
        $updatedProperties = [];
        foreach ($propertyNames as $propertyName) {
            $initialValues = isset($this->states[$propertyName][self::INITIAL_STATE]) ? $this->states[$propertyName][self::INITIAL_STATE] : null;
            $finalValues = isset($this->states[$propertyName][self::FINAL_STATE]) ? $this->states[$propertyName][self::FINAL_STATE] : null;
            if (is_array($initialValues) && is_array($finalValues)) {
                $old = array_diff($initialValues, $finalValues);
                $new = array_diff($finalValues, $initialValues);
                if (!empty($old) || !empty($new)) {
                    $updatedProperties['old'][$propertyName] = $initialValues;
                    $updatedProperties['new'][$propertyName] = $finalValues;
                }
            } elseif (is_array($initialValues) && null === $finalValues) {
                $updatedProperties['old'][$propertyName] = $initialValues;
                $updatedProperties['new'][$propertyName] = [];
            } elseif (is_array($finalValues) && null === $initialValues) {
                $updatedProperties['old'][$propertyName] = [];
                $updatedProperties['new'][$propertyName] = $finalValues;
            } elseif ($initialValues != $finalValues) {
                $updatedProperties['old'][$propertyName] = $initialValues;
                $updatedProperties['new'][$propertyName] = $finalValues;
            }
        }
        return $updatedProperties;
    }

    /**
     * Retourne les changements sur une propriété donnée (typiquement pour les propriété contenant des valeurs iterable
     * added contient les élements ajoutés, et deleted ceux supprimés
     */
    public function getChangedValues(string $propertyName): array
    {
        $changedValues = ['deleted' => [], 'added' => []];
        $initialValues = isset($this->states[$propertyName][self::INITIAL_STATE]) ? $this->states[$propertyName][self::INITIAL_STATE] : null;
        $finalValues = isset($this->states[$propertyName][self::FINAL_STATE]) ? $this->states[$propertyName][self::FINAL_STATE] : null;
        if (is_array($initialValues) && is_array($finalValues)) {
            $changedValues['deleted'] = array_diff($initialValues, $finalValues);
            $changedValues['added'] = array_diff($finalValues, $initialValues);
        }
        return $changedValues;
    }

    /**
     * Persiste les modifications en base de données si changement il y a
     *  (l'entity manager n'est pas flushé ici)
     */
    public function writeHistory(): void
    {
        $dtNow = new \DateTime('now');
        $updatedProperties = $this->getUpdatedProperties(['composantsImpactes', 'impactesParComposants', 'annuaire']);
        if (!empty($updatedProperties)) {
            // En cas de changement dans les flux sortants on enregistre une ActionHistory pour le composant modifié
            $updatedProperties['old']['majLe'] = $this->states['majLe'][self::INITIAL_STATE];
            $updatedProperties['new']['majLe'] = $dtNow->format('c');
            $actionHistory = new ActionHistory();
            $actionHistory->setAction(ActionHistory::UPDATE);
            $actionHistory->setActionDate($dtNow);
            $actionHistory->setIp($this->request->getClientIp());
            $actionHistory->setServiceId($this->service->getId());
            $actionHistory->setObjetClasse(Composant::class);
            $actionHistory->setObjetId($this->objectId);
            $actionHistory->setDetails($updatedProperties);
            $this->entityManager->persist($actionHistory);
        }
        // On ajoute un ActionHistory pour matérialiser les changements sur les composants associés (ceux ajoutés / supprimés dans les flux)
        foreach (['impactesParComposants', 'composantsImpactes'] as $propertyName) {
            $detailsPropertyName = $propertyName === 'impactesParComposants' ? 'composantsImpactes' : 'impactesParComposants';
            $changedValues = $this->getChangedValues($propertyName);
            if (!empty($changedValues['added'])) {
                $addedComposantIds = $changedValues['added'];
                $addedComposants = $this->entityManager->getRepository(Composant::class)->findBy(['id'=> $addedComposantIds]);
                foreach ($addedComposants as $addedComposant) {
                    $addedComposantId = $addedComposant->getId();
                    $initialIds = [];
                    $finalIds = [];
                    if ($propertyName === 'impactesParComposants') {
                        $addedRelatedComposants = $addedComposant->getComposantsImpactes();
                        foreach ($addedRelatedComposants as $cmp) {
                            $cmpId = $cmp->getId();
                            if ($this->objectId !== $cmpId) {
                                $initialIds[] = $cmpId;
                            }
                            $finalIds[] = $cmpId;
                        }
                    } else {
                        $addedRelatedComposants = $addedComposant->getImpactesParComposants();
                        foreach ($addedRelatedComposants as $cmp) {
                            $initialIds[] = $cmp->getId();
                        }
                        $finalIds = array_merge($initialIds, [$this->objectId]);
                    }
                    $actionHistory = new ActionHistory();
                    $actionHistory->setAction(ActionHistory::UPDATE);
                    $actionHistory->setActionDate($dtNow);
                    $actionHistory->setIp($this->request->getClientIp());
                    $actionHistory->setServiceId($this->service->getId());
                    $actionHistory->setObjetClasse(Composant::class);
                    $actionHistory->setObjetId($addedComposantId);
                    $actionHistory->setDetails([
                        'old' => [
                            $detailsPropertyName    => $initialIds,
                            'majLe'                 => $addedComposant->getMajLe()->format('c')
                        ],
                        'new' => [
                            $detailsPropertyName    => $finalIds,
                            'majLe'                 => $dtNow->format('c')
                        ]
                    ]);
                    $this->entityManager->persist($actionHistory);
                }
            }
            if (!empty($changedValues['deleted'])) {
                $deletedComposantIds = $changedValues['deleted'];
                $deletedComposants = $this->entityManager->getRepository(Composant::class)->findBy(['id'=> $deletedComposantIds]);
                foreach ($deletedComposants as $deletedComposant) {
                    $deletedComposantId = $deletedComposant->getId();
                    $initialIds = [];
                    $finalIds = [];
                    if ($propertyName === 'impactesParComposants') {
                        $deletedRelatedComposants = $deletedComposant->getComposantsImpactes();
                        foreach ($deletedRelatedComposants as $cmp) {
                            $finalIds[] = $cmp->getId();
                        }
                        $initialIds = array_merge([$this->objectId], $finalIds);
                    } else {
                        $deletedRelatedComposants = $deletedComposant->getImpactesParComposants();
                        foreach ($deletedRelatedComposants as $cmp) {
                            $cmpId = $cmp->getId();
                            if ($this->objectId !== $cmpId) {
                                $finalIds[] = $cmpId;
                            }
                            $initialIds[] = $cmpId;
                        }
                    }
                    $actionHistory = new ActionHistory();
                    $actionHistory->setAction(ActionHistory::UPDATE);
                    $actionHistory->setActionDate($dtNow);
                    $actionHistory->setIp($this->request->getClientIp());
                    $actionHistory->setServiceId($this->service->getId());
                    $actionHistory->setObjetClasse(Composant::class);
                    $actionHistory->setObjetId($deletedComposantId);
                    $actionHistory->setDetails([
                        'old' => [
                            $detailsPropertyName    => $initialIds,
                            'majLe'                 => $deletedComposant->getMajLe()->format('c')
                        ],
                        'new' => [
                            $detailsPropertyName    => $finalIds,
                            'majLe'                 => $dtNow->format('c')
                        ]
                    ]);
                    $this->entityManager->persist($actionHistory);
                }
            }
        }
    }
}
