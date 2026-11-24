<?php

namespace App\Controller;

use App\Entity\Efectivo;
use App\Entity\CajaRecuento;
use App\Service\Efectivo\CajaImporte;
use App\Form\EfectivoType;
use App\Repository\EfectivoRepository;
use App\Repository\DetallecestaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/admin/efectivo')]
class EfectivoController extends AbstractController
{
    private const MOTIVOS_SALIDA = ['Colocación mampara', 'Mano de obra', 'Gasolina', 'Materiales', 'Otros'];


    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }
        
    #[Route('/', name: 'efectivo_index', methods: ['GET', 'POST'])]
    public function index(Request $request, EfectivoRepository $efectivoRepository): Response
    {
        $error = null;
        $datos = $request->request->all();
        $saldo = $efectivoRepository->saldoCentimos();
        // Un identificador por formulario evita repetir una salida al reenviar la petición.
        $session = $request->getSession();
        $nonce = $session->get('caja_formulario');
        if (!$nonce) {
            $nonce = bin2hex(random_bytes(16));
            $session->set('caja_formulario', $nonce);
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('caja', is_string($datos['_token'] ?? null) ? $datos['_token'] : null)) {
                throw $this->createAccessDeniedException('La sesión del formulario ha caducado.');
            }
            try {
                if (($datos['nonce'] ?? '') !== $nonce) {
                    throw new \InvalidArgumentException('Este formulario ya se ha enviado o ha caducado. Revisa los movimientos antes de repetirlo.');
                }
                $accion = $datos['accion'] ?? '';
                $autor = $this->getUser()?->getUserIdentifier() ?? 'Administrador';
                if ($accion === 'recuento') {
                    $contado = CajaImporte::centimos($datos['contado'] ?? null, true);
                    if ((string) $saldo !== ($datos['saldo'] ?? null)) {
                        throw new \InvalidArgumentException('La caja ha cambiado desde que abriste la pantalla. Revisa el saldo actualizado y vuelve a contar.');
                    }
                    $this->em->persist(new CajaRecuento($saldo, $contado, $autor));
                    $diferencia = $contado - $saldo;
                    $mensaje = $diferencia === 0 ? 'Recuento guardado. La caja cuadra.' : sprintf(
                        'Recuento guardado. %s %s €. El saldo no se ha modificado.',
                        $diferencia < 0 ? 'Faltan' : 'Sobran', number_format(abs($diferencia) / 100, 2, ',', '.')
                    );
                } elseif (in_array($accion, ['salida', 'entrada'], true)) {
                    $importe = CajaImporte::centimos($datos['importe'] ?? null);
                    $concepto = is_string($datos['concepto'] ?? null) ? trim($datos['concepto']) : '';
                    if ($accion === 'salida' && !in_array($concepto, self::MOTIVOS_SALIDA, true)) {
                        throw new \InvalidArgumentException('Selecciona uno de los cinco motivos de salida.');
                    }
                    if ($concepto === '' || mb_strlen($concepto) > 255) {
                        throw new \InvalidArgumentException('Indica para qué o para quién es el dinero (máximo 255 caracteres).');
                    }
                    $importe = $accion === 'salida' ? -$importe : $importe;
                    $movimiento = (new Efectivo())->setFechaEf(new \DateTime())
                        ->setImporteEf($importe / 100)->setConceptoEf($concepto)->setRegistradoPor($autor);
                    $this->em->persist($movimiento);
                    $mensaje = sprintf('%s %s € %s caja. Saldo esperado: %s €.',
                        $accion === 'salida' ? 'Se han descontado' : 'Se han añadido',
                        number_format(abs($importe) / 100, 2, ',', '.'),
                        $accion === 'salida' ? 'de la' : 'a la', number_format(($saldo + $importe) / 100, 2, ',', '.'));
                } else {
                    throw new \InvalidArgumentException('Selecciona una operación válida.');
                }
                $this->em->flush();
                $session->remove('caja_formulario');
                $this->addFlash('success', $mensaje);
                return $this->redirectToRoute('efectivo_index');
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
        return $this->render('efectivo/index.html.twig', [
            'efectivos' => $efectivoRepository->findBy([], ['fechaEf' => 'DESC', 'id' => 'DESC'], 300),
            'saldoCentimos' => $saldo,
            'recuentos' => $this->em->getRepository(CajaRecuento::class)->findBy([], ['id' => 'DESC'], 20),
            'nonce' => $nonce, 'error' => $error, 'datos' => $datos,
            'motivosSalida' => self::MOTIVOS_SALIDA,
        ], new Response(status: $error ? 422 : 200));
    }

    #[Route('/new', name: 'efectivo_new', methods: ['GET','POST'])]
    public function new(Request $request, DetallecestaRepository $detallecestaRepository): Response
    {
        $efectivo = new Efectivo();
        $form = $this->createForm(EfectivoType::class, $efectivo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager = $this->em;

            $entityManager->persist($efectivo);
            $entityManager->flush();

            return $this->redirectToRoute('efectivo_index');
        }

        return $this->render('efectivo/new.html.twig', [
            'efectivo' => $efectivo,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'efectivo_show', methods: ['GET'])]
    public function show(Efectivo $efectivo , DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('efectivo/show.html.twig', [
            'efectivo' => $efectivo,
        ]);
    }

    #[Route('/{id}/edit', name: 'efectivo_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Efectivo $efectivo , DetallecestaRepository $detallecestaRepository): Response
    {
        $form = $this->createForm(EfectivoType::class, $efectivo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('efectivo_index');
        }

        return $this->render('efectivo/edit.html.twig', [
            'efectivo' => $efectivo,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'efectivo_delete', methods: ['DELETE'])]
    public function delete(Request $request, Efectivo $efectivo): Response
    {
        if ($this->isCsrfTokenValid('delete'.$efectivo->getId(), $request->request->get('_token'))) {
            $entityManager = $this->em;
            $entityManager->remove($efectivo);
            $entityManager->flush();
        }

        return $this->redirectToRoute('efectivo_index');
    }

    #[Route('/delete/fila', name: 'efectivo_delete_ajax', methods: ['GET','POST'])]
    public function deleteajax(Request $request): JsonResponse
    {
        // Funcion para borrar registro de producto de una efectivo determinada
        // Obtener ID del cesta
        $datos = $request->query->get('id');

        // get EntityManager
        $em = $this->em;
        // Obtener cesta
        $efectivo = $em->getRepository('App\Entity\Efectivo')->find($datos);
        if (is_object($efectivo)){
        // Borrado del detalle
            
            $em->remove($efectivo);
            $em->flush();

        }


        $response = new JsonResponse();

        return $response;

    }     
}
