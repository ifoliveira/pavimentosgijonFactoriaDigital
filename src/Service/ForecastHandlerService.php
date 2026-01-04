<?php
namespace App\Service;

use App\Entity\Forecast;
use App\Entity\Tiposmovimiento;
use App\Form\ForecastType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class ForecastHandlerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FormFactoryInterface $formFactory
    ) {}

    public function crearFormulariosForecast(array &$datos, Request $request): array
    {
        $formularios = [];
        $session = $request->getSession();
        $formulariosEnviados = array_keys($request->request->all());
        $indiceGuardado = null;

        // Buscar qué formulario fue enviado
        foreach ($formulariosEnviados as $clave) {
            if (str_starts_with($clave, 'forecast_')) {
                $indiceGuardado = (int) str_replace('forecast_', '', $clave);
                break;
            }
        }

        if (!isset($datos['vencimientos']) || !is_array($datos['vencimientos'])) {
            return $formularios;
        }

        foreach ($datos['vencimientos'] as $i => $vencimiento) {
            $forecast = new Forecast();
            $rawFecha = str_replace('-', '/', $vencimiento['fecha']);
            $fecha = \DateTime::createFromFormat('d/m/Y', $rawFecha);

            $forecast->setFechaFr($fecha);
            $forecast->setImporteFr((float) str_replace(',', '.', $vencimiento['importe']) * -1);
            $forecast->setConceptoFr('Factura ' . ($datos['empresa_emisora']['nombre'] ?? ''));
            $forecast->setOrigenFr('Banco');
            $forecast->setFijovarFr('V');
            $forecast->setEstadoFr('P');
            $forecast->setTipoFr(
                $this->em->getRepository(Tiposmovimiento::class)->findOneBy(['descripcionTm' => 'Proveedor'])
            );

            $form = $this->formFactory->createNamed("forecast_$i", ForecastType::class, $forecast);
            $form->handleRequest($request);

            if ($i === $indiceGuardado && $form->isSubmitted() && $form->isValid()) {
                $this->em->persist($forecast);
                $this->em->flush();

                // Eliminar vencimiento ya procesado
                unset($datos['vencimientos'][$i]);
                $datos['vencimientos'] = array_values($datos['vencimientos']);
                $session->set('factura_datos', $datos);

                return ['redirect' => true];
            }

            $formularios[] = $form;
        }

        return $formularios;
    }
}

?>