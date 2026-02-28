<?php

namespace App\EventSubscriber;

use App\Entity\Visitante;
use App\Entity\Sesion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;
use App\Entity\Evento;
use App\Service\BotDetectorService;

class VisitanteSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $em;
    private BotDetectorService $botDetector;

    public function __construct(EntityManagerInterface $em, BotDetectorService $botDetector)
    {
        $this->em = $em;
        $this->botDetector = $botDetector;
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
        $path = $request->getPathInfo();

        if (
            !$route ||
            str_starts_with($route, '_') ||
            str_starts_with($path, '/admin') ||
            str_starts_with($route, 'admin_')
        ) {
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

        // ---------------------------------------------------------
        // 1) VISITANTE (resolver o crear)
        // ---------------------------------------------------------
        $visitorId = $request->cookies->get('visitor_id');
        $visitante = null;
        $crearCookieVisitante = false;

        if ($visitorId) {
            // Si tu Visitante.id es UUID (tipo uuid), mejor fromString:
            try {
                $visitante = $this->em->getRepository(Visitante::class)->find(Uuid::fromString($visitorId));
            } catch (\Throwable $e) {
                $visitante = null; // cookie corrupta
            }

            // Si existe cookie pero no existe en DB, lo tratamos como nuevo
            if (!$visitante) {
                $visitorId = null;
            }
        }

        if (!$visitorId) {
            // Si no tocaba crear visitante (por tus filtros de onKernelRequest), salimos
            if (!$request->attributes->get('crear_visitante')) {
                return;
            }

            // Capturar datos (solo para primera vez)
            $query = $request->query;

            $utmOrigen   = $query->get('utm_source');
            $utmMedio    = $query->get('utm_medium');
            $utmCampania = $query->get('utm_campaign');
            $gclid       = $query->get('gclid');
            $referente   = $request->headers->get('referer');

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

            $visitante = new Visitante();
            $visitanteUuid = Uuid::v4();
            $visitante->setId($visitanteUuid);
            $visitante->setFechaPrimeraVisita(new \DateTimeImmutable());
            $visitante->setFechaUltimaVisita(new \DateTimeImmutable());
            $visitante->setOrigenNormalizado($origenNormalizado);
            $visitante->setUtmOrigen($utmOrigen);
            $visitante->setUtmMedio($utmMedio);
            $visitante->setUtmCampaña($utmCampania);
            $visitante->setGclid($gclid);
            $visitante->setReferente($referente);

            $this->em->persist($visitante);

            $visitorId = $visitanteUuid->toRfc4122();
            $crearCookieVisitante = true;
        } else {
            // Existe visitante
            $visitante->setFechaUltimaVisita(new \DateTimeImmutable());
            $visitante->setNumeroVisitas($visitante->getNumeroVisitas() + 1);
        }

        // ---------------------------------------------------------
        // 2) SESION (resolver o crear)
        // ---------------------------------------------------------
        $sesionId = $request->cookies->get('sesion_id');
        $sesion = null;
        $crearCookieSesion = false;
        $ahora = new \DateTimeImmutable();

        if ($sesionId) {
            $sesion = $this->em->getRepository(Sesion::class)->find($sesionId);

            if ($sesion) {

                // ⏱️ Comprobar inactividad (30 minutos)
                $ultimoEvento = $sesion->getFechaUltimoEvento() ?? $sesion->getFechaInicio();

                $diferencia = $ahora->getTimestamp() - $ultimoEvento->getTimestamp();

                if ($diferencia > 1800) {
                    // 🔴 Sesión expirada → cerramos
                    $sesion->setFechaFin(\DateTimeImmutable::createFromMutable($ultimoEvento));
                    $this->em->persist($sesion);

                    $sesion = null;
                    $sesionId = null;
                }
            } else {
                $sesionId = null;
            }
        }

        if (!$sesionId) {

            // 🆕 Crear nueva sesión
            $sesion = new Sesion();
            $nuevoId = Uuid::v4()->toRfc4122();
            $userAgent = $request->headers->get('user-agent', '');
            $sesion->setIsBot($this->botDetector->isBot($userAgent));
            $sesion->setJsConfirmed(false); // por defecto
            $sesion->setId($nuevoId);
            $sesion->setVisitante($visitante);
            $sesion->setFechaInicio($ahora);
            $sesion->setFechaUltimoEvento($ahora);
            $sesion->setRutaEntrada($request->getPathInfo());
            $sesion->setUserAgent(substr((string) $userAgent, 0, 255));
            $sesion->setNumeroEventos(0);

            $this->em->persist($sesion);

            $sesionId = $nuevoId;
            $crearCookieSesion = true;
        }

        // ---------------------------------------------------------
        // 3) EVENTO (SIEMPRE con sesion y visitante)
        // ---------------------------------------------------------
        $evento = new Evento();
        $evento->setVisitante($visitante);
        $evento->setSesion($sesion); // 🔥 esto es lo que te faltaba
        $evento->setTipo('page_view');
        $evento->setDatos(['ruta' => $request->getPathInfo()]);
        $evento->setFechaCreacion(new \DateTimeImmutable());

        $this->em->persist($evento);

        // Actualiza contador eventos en sesión (si lo usas)
        $sesion->setNumeroEventos($sesion->getNumeroEventos() + 1);
        $sesion->setFechaUltimoEvento(new \DateTimeImmutable());

        // ---------------------------------------------------------
        // 4) FLUSH
        // ---------------------------------------------------------
        $this->em->flush();

        // ---------------------------------------------------------
        // 5) COOKIES (string siempre)
        // ---------------------------------------------------------
        if ($crearCookieVisitante) {
            $cookieVisitante = Cookie::create('visitor_id', $visitorId)
                ->withExpires(strtotime('+1 year'))
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(false);

            $response->headers->setCookie($cookieVisitante);
        }

        if ($crearCookieSesion) {
            $cookieSesion = Cookie::create('sesion_id', $sesionId)
                ->withExpires(strtotime('+30 days'))
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(false);

            $response->headers->setCookie($cookieSesion);
        }
    }
}
