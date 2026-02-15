<?php

namespace App\EventSubscriber;

use App\Entity\Visitante;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;
use App\Entity\Evento;

class VisitanteSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();


        $route = $request->attributes->get('_route');

        if (!$route || str_starts_with($route, '_')) {


            return;
        }


        // Solo GET reales
        if (!$request->isMethod('GET')) {
            return;
        }

        // Ignorar assets y profiler
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/_') || str_contains($path, '.')) {
            return;
        }

        // Si ya hay cookie, no hacemos nada
        if ($request->cookies->get('visitor_id')) {
            return;
        }

        // Marcamos que debemos crear visitante en RESPONSE
        $request->attributes->set('crear_visitante', true);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $route = $request->attributes->get('_route');
        if (!$route || str_starts_with($route, '_')) {
            return;
        }

        $visitorId = $request->cookies->get('visitor_id');
                file_put_contents(
            __DIR__.'/../../var/log/visitas_debug.log',
            'Cookie '.$visitorId.PHP_EOL,
            FILE_APPEND
        );        

        // 🔁 Si ya existe → actualizar última visita
        if ($visitorId) {

            $visitante = $this->em
                ->getRepository(Visitante::class)
                ->find($visitorId);

            if ($visitante) {

                $visitante->setFechaUltimaVisita(new \DateTimeImmutable());
                $visitante->setNumeroVisitas($visitante->getNumeroVisitas() + 1);

                // 👇 Crear evento page_view
                $evento = new Evento();
                $evento->setVisitante($visitante);
                $evento->setTipo('page_view');
                $evento->setDatos([
                    'ruta' => $request->getPathInfo()
                ]);
                $evento->setFechaCreacion(new \DateTimeImmutable());

                $this->em->persist($evento);

                $this->em->flush();
            }

            return;
        }

        // 🆕 Si no existe → crear visitante
        if (!$request->attributes->get('crear_visitante')) {
            return;
        }

        // Capturar datos
        $query = $request->query;

        $utmOrigen = $query->get('utm_source');
        $utmMedio = $query->get('utm_medium');
        $utmCampania = $query->get('utm_campaign');
        $gclid = $query->get('gclid');
        $referente = $request->headers->get('referer');

        $origenNormalizado = 'directo';


        if ($gclid) {
            $origenNormalizado = 'google_ads';
        } elseif ($utmMedio) {
            $origenNormalizado = $utmMedio;
        } elseif ($referente) {
            if (str_contains($referente, 'google')) {
                $origenNormalizado = 'google_organico';
            } elseif (str_contains($referente, 'facebook')) {
                $origenNormalizado = 'facebook';
            } else {
                $origenNormalizado = 'referido';
            }
        }

        // Crear visitante
        $visitante = new Visitante();
        $visitante->setId(Uuid::v4());
        $visitante->setFechaPrimeraVisita(new \DateTimeImmutable());
        $visitante->setFechaUltimaVisita(new \DateTimeImmutable());
        $visitante->setOrigenNormalizado($origenNormalizado);
        $visitante->setUtmOrigen($utmOrigen);
        $visitante->setUtmMedio($utmMedio);
        $visitante->setUtmCampaña($utmCampania);
        $visitante->setGclid($gclid);
        $visitante->setReferente($referente);

        $evento = new Evento();
        $evento->setVisitante($visitante);
        $evento->setTipo('page_view');
        $evento->setDatos([
            'ruta' => $request->getPathInfo()
        ]);
        $evento->setFechaCreacion(new \DateTimeImmutable());

        $this->em->persist($evento);

        $this->em->persist($visitante);
        $this->em->flush();

        // Crear cookie
        $cookie = Cookie::create(
            'visitor_id',
            $visitante->getId()
        )
            ->withExpires(strtotime('+1 year'))
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(false);

        $response->headers->setCookie($cookie);
    }
}
