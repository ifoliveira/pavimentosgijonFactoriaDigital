<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Entity\Banco; // Asegúrate de que este use statement corresponda a tu entidad Banco
use App\Form\ExcelType; // Asegúrate de incluir tu nuevo formulario
use App\Entity\Tiposmovimiento;
use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\DateValue;
use App\MisClases\CategoriaMovimiento;
use App\Repository\BancoRepository;

class BankController extends AbstractController
{


    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }

    /**
     * @Route("/admin/upload", name="uploadBank")
     */
    public function uploadBankData(Request $request, CategoriaMovimiento $categoriaMovimiento, BancoRepository $bancoRepository): Response
    {
        $form = $this->createForm(ExcelType::class);
        $form->handleRequest($request);
        $bancos = [];

        // Busca la fecha más reciente en los registros existentes
        $ultimaFecha = $bancoRepository->createQueryBuilder('b')
            ->select('MAX(b.fecha_Bn)')
            ->getQuery()
            ->getSingleScalarResult();

      

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('excel_file')->getData();
            if ($file) {
                $spreadsheet = IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $firstRow = true; 

                foreach ($sheet->getRowIterator() as $row) {

                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false); // Loop all cells, even if they are empty

                    if ($firstRow) {

                        $data = [];
                        foreach ($cellIterator as $cell) {
                            $data[] = $cell->getValue();
                        }
                        if (str_starts_with($data[0], 'Fecha')) {
                            $firstRow = false; // Desactiva la bandera para las próximas iteraciones
                        }
                        continue; // Salta el resto del código en esta iteración y pasa a la siguiente fila
                    }


                    
                    $data = [];
                    foreach ($cellIterator as $cell) {
                        $data[] = $cell->getValue();

                    }

                    $fechaStr = trim($data[0]);
                    $fechaObj = false;
                    
                    if (is_numeric($fechaStr)) {
                        // Separar parte entera (días) y decimal (horas)
                        $dias = floor($fechaStr);
                        $fraccionDia = $fechaStr - $dias;
                    
                        // Crear fecha base de Excel
                        $baseDate = \DateTime::createFromFormat('Y-m-d H:i:s', '1899-12-30 00:00:00');
                    
                        // Sumar días enteros
                        $baseDate->modify("+$dias days");
                    
                        // Sumar horas, minutos, segundos desde fracción
                        $segundos = round($fraccionDia * 86400); // 86400 segundos por día
                        $baseDate->modify("+$segundos seconds");
                    
                        $fechaObj = $baseDate;
                    } else {
                        try {
                            $fechaObj = new \DateTime($fechaStr);
                        } catch (\Exception $e) {
                            continue; // Saltamos si no se puede parsear
                        }
                    }
                    
                    // Validamos que se haya obtenido una fecha válida
                    if (!$fechaObj) {
                        continue;
                    }
                    
                    $fechacomp = $fechaObj->format('Y-m-d');
                    
                    if ($fechacomp > $ultimaFecha)                         {
        
                        $categoria = $categoriaMovimiento->getCategoriaPorConcepto($data[2]);

                        $banco = new Banco();
                        $banco->setCategoriaBn($categoria);
                        $banco->setConceptoBn($data[2]);
                        $banco->setFechaBn($fechaObj);
                        $banco->setImporteBn(floatval($data[3]));
                        $banco->setConciliado(false);
                        $banco->setTimestampBn(new \DateTime()); // Usamos la fecha y hora actual
                        $this->em->persist($banco);
                        $bancos[] = $banco;

                    }
                }

                $this->em->flush(); // Guarda todos los nuevos objetos Banco en la base de datos

                $this->addFlash('success', 'Datos importados exitosamente');

            }
        }

        return $this->render('banco/excel.html.twig', [
            'form' => $form->createView(),
            'bancos' => $bancos,
        ]);
    }
}