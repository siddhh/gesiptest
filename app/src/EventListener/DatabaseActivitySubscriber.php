<?php

/**
 * Classe chargée de mémoriser les modifications effectuées en base de données.
 *  doc - cf: https://symfony.com/doc/current/doctrine/events.html
 */

namespace App\EventListener;

use App\Entity\Composant;
use App\Entity\Composant\PlageUtilisateur;
use App\Entity\Interfaces\HistorisableInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ActionHistory;
use App\Entity\Service;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

class DatabaseActivitySubscriber implements EventSubscriber
{

    /**
     * @ array
     */
    private static $cache = [];

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Injection des dépendances
     */
    public function __construct(EntityManagerInterface $em, Security $security, RequestStack $requestStack, SerializerInterface $serializer)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->serializer = $serializer;
    }

    /**
     * Retourne la liste des évenements gérés par notre classe
     */
    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,        // Après création de nouveaux objets
            Events::postUpdate,         // Après mise à jour
            Events::preRemove,          // Après suppression
        ];
    }

    /**
     * Méthode appelée lors de la création d'objet
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $this->logActivity(ActionHistory::CREATE, $args);
    }

    /**
     * Méthode appelée lors de la mise à jour
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->logActivity(ActionHistory::UPDATE, $args);
    }

    /**
     * Méthode appelée lors de la suppression d'un objet
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $this->logActivity(ActionHistory::REMOVE, $args);
    }

    /**
     * Méthode appelée lors d'une modification sur la base de données
     *  afin de mémoriser les changements
     */
    private function logActivity(string $action, LifecycleEventArgs $args)
    {
        // récupère les infos sur l'entité en cours
        $entity = $args->getObject();
        // récupère la requète courante
        $request = $this->requestStack->getCurrentRequest();
        // récupère le service connecté actuellement
        $service = $this->security->getUser();
        // récupère les détails
        $details = $this->getDetailsFromArgs($args, $action);
        // enregistrement si il doit être enregistré et si il y a une modification détectée
        if (self::doitEtreEnregistre($entity) &&
            (
                (isset($details['old']) && isset($details['new']) && $details['old'] !== $details['new']) ||
                (isset($details['old']) && !isset($details['new'])) ||
                (!isset($details['old']) && isset($details['new']))
            )
        ) {
            $actionHistory = new ActionHistory();
            $service instanceof Service && $actionHistory->setServiceId($service->getId());
            $request && $actionHistory->setIp($request->getClientIp());
            $actionHistory->setAction($action);
            $actionHistory->setObjetClasse(get_class($entity));
            $actionHistory->setObjetId($entity->getId());
            $actionHistory->setDetails($details);
            $this->em->persist($actionHistory);
            $this->em->flush();
        }
    }

    /**
     *  Méthode retournant vrai si l'action sur cette entité doit être enregistrée
     */
    private static function doitEtreEnregistre(object $entity): bool
    {
        return ($entity instanceof HistorisableInterface);
    }

    /**
     * Retourne la structure d'une entité
     */
    private function getDetailsFromArgs(LifecycleEventArgs $args, string $action)
    {
        $details = null;
        if (($entity = $args->getObject()) !== null) {
            switch ($action) {
                // Pour une création on retourne la nouvelle entité  sous forme de tableau
                case ActionHistory::CREATE:
                    $details = ['new' => $this->serializeObject($entity)];
                    break;
                // Lors d'une mise à jour on retourne à la fois l'ancienne version, et la nouvelle
                case ActionHistory::UPDATE:
                    $arrayOldEntity = clone($entity);
                    $entityChanges = $args->getEntityManager()->getUnitOfWork()->getEntityChangeSet($entity);
                    $attributesDirty = array_keys($entityChanges);

                    // Lorsqu'on modifie une plage utilisateur on enregistre aussi son composant_id
                    //  (obligatoire pour savoir sur quel composant une modification a été effectuée si elle a été effectuée avant une suppression)
                    if (PlageUtilisateur::class === get_class($args->getObject())) {
                        $attributesDirty[] = 'composant';
                    }

                    $details = [
                        'old' => $this->serializeObject($arrayOldEntity, $attributesDirty, $entityChanges),
                        'new' => $this->serializeObject($entity, $attributesDirty)
                    ];

                    $reflection = new \ReflectionClass($entity);
                    /** @var \ReflectionProperty $property */
                    do {
                        foreach ($reflection->getProperties() as $property) {
                            $propertyName = $property->getName();
                            if (!$entity instanceof Composant || !in_array($propertyName, ['composantsImpactes', 'impactesParComposants', 'annuaire'])) {
                                if (method_exists($entity, 'get' . ucfirst($propertyName))) {
                                    $value = call_user_func(array($entity, 'get' . ucfirst($propertyName)));
                                    if ($value instanceof PersistentCollection && $value->isInitialized()) {
                                        $propertyOldValues = [];
                                        $propertyNewValues = [];
                                        foreach ($value->getSnapshot() as $v) {
                                            $id = $v->getId();
                                            $propertyOldValues[$id] = method_exists($v, 'getInfos') ? $v->getInfos() : $id;
                                        }
                                        foreach ($value->getValues() as $v) {
                                            $id = $v->getId();
                                            $propertyNewValues[$id] = method_exists($v, 'getInfos') ? $v->getInfos() : $id;
                                        }
                                        if ($value->isDirty() || $propertyOldValues !== $propertyNewValues) {
                                            $details['old'][$propertyName] = $propertyOldValues;
                                            $details['new'][$propertyName] = $propertyNewValues;
                                        }
                                    }
                                }
                            }
                        }
                    } while ($reflection = $reflection->getParentClass());

                    break;
                // Pour la suppression, on retourne l'ancienne entité sous forme de tableau
                case ActionHistory::REMOVE:
                    $details = ['old' => $this->serializeObject($entity)];
                    break;
            }
        }
        return $details;
    }

    /**
     * Fonction permettant de sérializer une entitée de manière simple
     * (le second paramètre, si il est rempli, permet de définir les attributs à afficher dans le tableau)
     *
     * @param object $object
     * @param array $onlyAttributes
     * @param array $entityChanges
     * @return array
     * @throws \ReflectionException
     */
    private function serializeObject($object, array $onlyAttributes = [], array $entityChanges = []) : array
    {
        $reflection = new \ReflectionClass($object);
        $array = [];
        /** @var \ReflectionProperty $property */
        do {
            foreach ($reflection->getProperties() as $property) {
                $propertyName = $property->getName();
                if ($propertyName != 'machineEtat') {
                    if (count($onlyAttributes) == 0 || (count($onlyAttributes) > 0 && in_array($propertyName, $onlyAttributes))) {
                        $value = null;
                        if (count($entityChanges) > 0 && isset($entityChanges[$propertyName])) {
                            $value = $entityChanges[$propertyName][0];
                        } elseif (method_exists($object, 'get' . ucfirst($propertyName))) {
                            $value = call_user_func(array($object, 'get' . ucfirst($propertyName)));
                        }
                        $valueSerialized = $value;

                        if ($value instanceof PersistentCollection && $value->isDirty()) {
                            $valueSerialized = [];
                            foreach ($value->getValues() as $v) {
                                $valueSerialized[] = method_exists($v, 'getInfos') ? $v->getInfos() : $v->getId();
                            }
                        } elseif ($value instanceof \DateTime) {
                            $valueSerialized = $value->format('c');
                        } elseif (is_object($value) && !$value instanceof PersistentCollection) {
                            $valueSerialized = method_exists($value, 'getInfos') ? $value->getInfos() : $value->getId();
                        }

                        $array[$propertyName] = $valueSerialized;
                    }
                }
            }
        } while ($reflection = $reflection->getParentClass());

        return $array;
    }
}
