<?php

namespace App\Tests\Controller;

use App\Controller\BancoController;
use App\Entity\Banco;
use App\Repository\DetallecestaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

final class BancoControllerTest extends KernelTestCase
{
    /** @dataProvider actions */
    public function testNativeResolverSuppliesTheRequestedBank(string $action, string $method): void
    {
        $bank = new Banco();
        $id = new \ReflectionProperty(Banco::class, 'id');
        $id->setValue($bank, 42);

        $response = $this->resolveAction($action, $method, $bank);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('resolved bank 42', $response->getContent());
    }

    /** @dataProvider actions */
    public function testNativeResolverReturns404ForMissingBank(string $action, string $method): void
    {
        $response = $this->resolveAction($action, $method, null);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString(EntityValueResolver::class, $response->getContent());
    }

    public function actions(): array
    {
        return [['show', 'GET'], ['delete', 'DELETE']];
    }

    private function resolveAction(string $action, string $method, ?Banco $bank): Response
    {
        self::bootKernel();
        $container = self::getContainer();
        self::assertSame('test', self::$kernel->getEnvironment());

        // No database connection or write is allowed, even for the DELETE route.
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())->method('find')->with('42')->willReturn($bank);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(Banco::class)->willReturn($repository);
        $manager->expects(self::never())->method('remove');
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::never())->method('flush');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(Banco::class)->willReturn($manager);
        $container->set('doctrine', $registry);

        $nativeResolver = $container->get('doctrine.orm.entity_value_resolver');
        self::assertInstanceOf(EntityValueResolver::class, $nativeResolver);

        self::assertFalse($container->has('sensio_framework_extra.converter.listener'));

        $router = $container->get(RouterInterface::class);
        $router->getContext()->setMethod($method);
        $path = $router->generate('banco_'.$action, ['id_Bn' => 42]);
        $attributes = $router->match($path);
        self::assertSame(BancoController::class.'::'.$action, $attributes['_controller']);
        $request = Request::create($path, $method);
        $request->attributes->add($attributes);
        $request->attributes->set('_controller', [new BancoController($manager), $action]);
        $request->attributes->set('detallecestaRepository', $this->createMock(DetallecestaRepository::class));
        // A competing id must never override the explicit id_Bn mapping.
        $request->attributes->set('id', '999');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, function (ControllerArgumentsEvent $event) use ($bank): void {
            self::assertNotNull($bank, 'Missing entities must stop before the action is called.');
            $banks = array_values(array_filter($event->getArguments(), static fn ($argument) => $argument instanceof Banco));
            self::assertSame([$bank], $banks);
            // Inspect resolved arguments without invoking rendering or deletion.
            $event->setController(static fn (...$arguments): Response => new Response('resolved bank '.$bank->getId()));
        });
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function (ExceptionEvent $event): void {
            if ($event->getThrowable() instanceof NotFoundHttpException) {
                $event->setResponse(new Response($event->getThrowable()->getMessage(), 404));
            }
        });

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), null, new ArgumentResolver(null, [
            new RequestAttributeValueResolver(),
            new RequestValueResolver(),
            $nativeResolver,
        ]));

        return $kernel->handle($request);
    }
}
