<?php

namespace App\EventListener;

use App\Util\IdGenerator;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Event Subscriber für automatische ID-Generierung
 * Generiert 12-hex IDs für neue Entities
 */
class IdGeneratorSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [Events::prePersist];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Nur Entities mit getId/setId Methoden
        if (!method_exists($entity, 'getId') || !method_exists($entity, 'setId')) {
            return;
        }
        
        // Nur wenn ID noch leer ist (try-catch wegen typed properties)
        try {
            $currentId = $entity->getId();
            if ($currentId !== null && $currentId !== '') {
                return;
            }
        } catch (\Error $e) {
            // ID ist noch nicht initialisiert, also generieren wir eine
        }
        
        // Generiere ID basierend auf Entity-Typ
        $entity->setId(IdGenerator::generateForEntity($entity));
    }
}
