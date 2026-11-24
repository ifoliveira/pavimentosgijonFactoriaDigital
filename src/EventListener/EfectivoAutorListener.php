<?php

namespace App\EventListener;

use App\Entity\Efectivo;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EfectivoAutorListener
{
    public function __construct(private TokenStorageInterface $tokens) {}

    public function prePersist(LifecycleEventArgs $event): void
    {
        $movimiento = $event->getObject();
        if ($movimiento instanceof Efectivo && $movimiento->getRegistradoPor() === null) {
            $movimiento->setRegistradoPor($this->tokens->getToken()?->getUser()?->getUserIdentifier());
        }
    }
}
