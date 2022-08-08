<?php

namespace App\Controller;

use App\Entity\Banco;
use App\Entity\Efectivo;
use App\Entity\Tiposmovimiento;
use App\Form\Banco1Type;
Use App\MisClases\Banks_N43;
use App\MisClases\nordigen;
use App\Repository\BancoRepository;
use App\Repository\DetallecestaRepository;
use App\Repository\EfectivoRepository;
use App\Repository\TiposmovimientoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;



/**
 * @Route("/admin/banco")
 */
class BancoController extends AbstractController
{
    /**
     * @Route("/", name="banco_index", methods={"GET"})
     */
    public function index(BancoRepository $bancoRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('banco/index.html.twig', [
            'bancos' => $bancoRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="banco_new", methods={"GET","POST"})
    */   
    public function new(Request $request,DetallecestaRepository $detallecestaRepository): Response
    {
        $banco = new Banco();
        $form = $this->createForm(Banco1Type::class, $banco);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($banco);
            $entityManager->flush();

            return $this->redirectToRoute('banco_index');
        }

        return $this->render('banco/new.html.twig', [
            'banco' => $banco,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/N43", name="N43")
     */
    public function N43(BancoRepository $bancoRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        $directorio = $this->getParameter("c43Dir");
        $contador = 0;

        $ficheros  =  sprintf("%02d", (count(scandir($directorio, 1)) - 2));
        do {
            $nombrefic = '/C43' . sprintf("%02d",$contador) . '.txt';

            $fichero = file_get_contents($directorio .'/'.$nombrefic);
        
            // informamos cabeceara del ficheor csv 
            $datosC43 = new Banks_N43();

            $datosC43->parse($fichero);

            foreach ($datosC43->accounts as $cuentas){
                foreach ($cuentas->entries as $valor){
            // Buscamos la descripcion del tipo        
                    $entityManager = $this->getDoctrine()->getManager();
                    $tipo = $entityManager->getRepository(Tiposmovimiento::class)->findOneBy(
                        ['descripcionTm' => $valor->banco->getCategoriaBn()->getdescripcionTm()]
                    );

            // Si existe la movemos a objeto banco e insertamo solo banco        
                    if ($tipo) {
                        $valor->banco->setCategoriaBn($tipo);
                    }else{
            // Si no existe la insertamos tambien en el tipo            
                    $entityManager->persist($valor->banco->getCategoriaBn());
                    }

                    $entityManager->persist($valor->banco);
                    $entityManager->flush();
                } 
            }
            unlink($directorio .'/'. $nombrefic);
            $contador++;
        }while($contador < $ficheros);
        

        // Borra el fichero
        //   unlink($directorio .'/C43.txt');

       return $this->render('banco/index.html.twig', [
        'bancos' => $bancoRepository->findAll(),
         ]);

    }

    /**
     * @Route("/{id_Bn}", name="banco_show", methods={"GET"})
     */
    public function show(Banco $banco, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('banco/show.html.twig', [
            'banco' => $banco,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="banco_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Banco $banco , DetallecestaRepository $detallecestaRepository): Response
    {
        $form = $this->createForm(Banco1Type::class, $banco);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('banco_index');
        }

        return $this->render('banco/edit.html.twig', [
            'cesta' => $banco,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id_Bn}", name="banco_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Banco $banco, DetallecestaRepository $detallecestaRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$banco->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($banco);
            $entityManager->flush();
        }

        return $this->redirectToRoute('banco_index');
    }

    /**
     * @Route("/{id}/transferencia", name="banco_transferencia", methods={"GET","POST"})
     */
    public function conciliar(Request $request, Banco $banco,  EfectivoRepository $efectivoRepository, TiposmovimientoRepository $tiposmovimientoRepository): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        
        // Buscamos la descripcion del tipo        
        $tipo = $entityManager->getRepository(Tiposmovimiento::class)->findOneBy(
            ['descripcionTm' => 'Transferencia']
            );

        // Creamos un movimiento en efectivo
        $efectivo = new Efectivo();
        $efectivo->setTipoEf($tipo);
        $efectivo->setConceptoEf("Retirada Cajero");
        $efectivo->setImporteEf($banco->getImporteBn() * -1);
        $efectivo->setFechaEf(new \DateTime());    

        $entityManager->persist($efectivo);

        // Si existe la movemos a objeto banco e insertamo solo banco        
        $banco->setCategoriaBn($tipo);
        $entityManager->flush();

        return $this->redirectToRoute('banco_index');
    }

    /**
     * @Route("/incluir/consultar/movimientos", name="consultamovimientos", methods={"GET", "POST"})
     */
    public function movimientos(Request $request,  BancoRepository $bancoRepository): Response
    {
        $entityManager = $this->getDoctrine()->getManager();

        $bancoid = $request->query->get('banco');
        $fechaini = $bancoRepository->fechamaxima()["fechamaxima"];
        $hoy = date('Y-m-d',strtotime("-1 days"));


        if ($hoy>=$fechaini) {

            // Referencia banco

            $directorio = $this->getParameter("nordigen");
            $nombrefic = 'bancos.json';
            $data = file_get_contents($directorio .'/'.$nombrefic);
            $bancos = json_decode($data, true);

            foreach ($bancos as $value) {
                if ($value['id']== $bancoid) {
                    $referencia = $value['Referencia'];

                    foreach ($value['Cuentas'] as $cuenta) {
                        
                        $apibank = new nordigen("application/json", $this->getParameter("nordigen"));
                            
                        $decoded_json = $apibank->getAccountsTrasactions($cuenta['Referencia'], $fechaini, $hoy);

                        $movimientos = $decoded_json->transactions->booked;
    
                        foreach($movimientos as $movimiento) {
                                $banco = new Banco();
                                $banco->setImporteBn(floatval($movimiento->transactionAmount->amount));
                                $date_time = date_create_from_format('Y-m-d\TH:i:sP', $movimiento->bookingDate."T15:52:01+00:00");
                                $banco->setFechaBn($date_time);

                                 $tipo = $entityManager->getRepository(Tiposmovimiento::class)->findOneBy(
                                    ['descripcionTm' => $apibank->concepto($movimiento->remittanceInformationUnstructured)->getDescripcionTm()]
                                );

                                $banco->setcategoriaBn($tipo);
                                $stg = $movimiento->remittanceInformationUnstructured;
                                $pos =  strpos($stg,"/TXT/");
                                
                                if ($pos === false) {
                                    $banco->setConceptoBn($stg);
                                }
                                else
                                {
                                    $banco->setConceptoBn(substr($stg, $pos + 6 ));
                                }

                                $bancosarray[] = $banco;

                                $entityManager->persist($banco);
                                $entityManager->flush();
                                $banco = new Banco();
                        }
                    };
                }
            };

            if ( true === ( $bancosarray ?? null ) ) {
                $template =$this->render('banco/loop.html.twig')->getContent();
            } else {

                $template =$this->render('banco/loop.html.twig', ['movimientos'=>$bancosarray, ])->getContent();
            }

            // Salida JSON
            return new JsonResponse(['template'=>$template]);
    
             }else{
            $banco = new Banco();
            $bancosarray[] = $banco;
            $template =$this->render('banco/loop.html.twig', ['movimientos'=>$bancosarray, ])->getContent();
            return new JsonResponse(['template'=>$template]);
        }
    }

    /**
     * @Route("/incluir/consultar/banco", name="consultarbanco", methods={"GET", "POST"})
     */
    public function incluir2(Request $request): Response
    {

        $bancoid = $request->query->get('banco');
       
        
        // Referencia banco

        $directorio = $this->getParameter("nordigen");
        $nombrefic = 'bancos.json';
        $data = file_get_contents($directorio .'/'.$nombrefic);
        $bancos = json_decode($data, true);

        foreach ($bancos as $value) {
             if ($value['id']== $bancoid) {
                $referencia = $value['Referencia'];
 
                $apibank = new nordigen("application/json", $this->getParameter("nordigen"));
                // Llamada añadir banco
                $decoded_json = $apibank->getAccounts($referencia  );
                $client = HttpClient::create(); 
                // Actualizamos banco
                $i = 0;
                do {
                
                $detalle['Referencia'] = $decoded_json->accounts[$i];

                $apibank2 = new nordigen("application/json", $this->getParameter("nordigen"));
               
                $decoded_json2 = $apibank->getAccountsDetails($decoded_json->accounts[$i]);

                $detalle['IBAN'] = $decoded_json2->account->iban;
                $value['Cuentas'][]= $detalle;
                $i++;    
            
                } while ($i < count($decoded_json->accounts));
             }

             $values[] = $value;
            }
   
            $newJsonString = json_encode($values);
            file_put_contents($directorio .'/'.$nombrefic . '', $newJsonString);
            
         // Salida JSON
         return new JsonResponse(['salida' => $decoded_json2]);
 
    }

    /**
     * @Route("/incluir/banco", name="incluir_banco", methods={"GET", "POST"})
     */
    public function incluir(Request $request): Response
    {
        $directorio = $this->getParameter("nordigen");
        $nombrefic = 'bancos.json';
        $data = file_get_contents($directorio .'/'.$nombrefic);
        $bancos = json_decode($data, true);

        $directorio = $this->getParameter("nordigen");
        $nombrefic = 'credenciales.json';
        $data = file_get_contents($directorio .'/'.$nombrefic);
        $credenciales = json_decode($data, true);


        // Si no tenemos credenciales de acceso las creamos nuevas
        if ($credenciales['acceso'] = " ") {
            
            $apibank = new nordigen("application/json", $this->getParameter("nordigen"));
            // Llamada a nuevo token
            $decoded_json = $apibank->newToken($credenciales['secret_id'], $credenciales['secret_key']  );

            // Actualizamos JSON credencias
            $credenciales['acceso'] = $decoded_json->access;           
            $credenciales['restaurar'] = $decoded_json->refresh;
            $newJsonString = json_encode($credenciales);
            file_put_contents($directorio .'/'.$nombrefic . '', $newJsonString);

        } else {
        // Si las tenemos, las restauramos para asegurarnos que las consultas funcionen
            $apibank = new nordigen("application/json", $this->getParameter("nordigen"));
            // Llamada a nuevo token
            $decoded_json = $apibank->restoreToken($credenciales['restaurar'], $credenciales['secret_id'], $credenciales['secret_key']  );
            $credenciales['acceso'] = $decoded_json->access;           
            if ( true === ($decoded_json->refresh ?? null ) ) {
                $credenciales['restaurar'] = $decoded_json->refresh;    
            } 
        }

        $newJsonString = json_encode($credenciales);
        file_put_contents($directorio .'/'.$nombrefic . '', $newJsonString);            
        
        return $this->render('banco/incluir.html.twig', [
            'bancosJSON' => $bancos,
        ]);
    }


    /**
     * @Route("/incluir/link", name="linkbanco", methods={"GET","POST"})
     */
    public function ajaxinsclink(Request $request): Response
    {   
        $entityManager = $this->getDoctrine()->getManager();

        $directorio = $this->getParameter("nordigen");
        $nombrefic = 'bancos.json';
        $data = file_get_contents($directorio .'/'.$nombrefic);
        $bancos = json_decode($data, true);

        return $this->render('banco/incluir.cuenta.html.twig', [
            'bancosJSON' => $bancos,
            'movimientos' => "sin movimientos",
        ]);

    } 

    /**
     * @Route("/incluir/requisitions", name="requisitosbanco", methods={"GET","POST"})
     */
    public function ajaxinscRequi(Request $request): jsonResponse
    {

        $bancoid = $request->query->get('banco');
        $index = $request->query->get('indice');

        $apibank = new nordigen("application/json", $this->getParameter("nordigen"));
        $contenido = $apibank->sendAuth($bancoid);
        $decoded_json = json_decode($contenido, false);
        // Creamos banco

        $directorio = $this->getParameter("nordigen");
        $nombrefic = 'bancos.json';
        $data = file_get_contents($directorio .'/'.$nombrefic);
        $bancos = json_decode($data, true);

        foreach ($bancos as $value) {
  
           if ($value['id']== $bancoid) {
                $value['Referencia'] = $decoded_json->id;
                $value['Activo'] = "Si";
            }

            $values[] = $value;
         }

         $newJsonString = json_encode($values);
         file_put_contents($directorio .'/'.$nombrefic . '', $newJsonString);

        // Salida JSON
        return new JsonResponse(['salida' => $contenido ]);

    } 


    /**
     * @Route("/incluir/zzzz/{banco}", name="altabanco", methods={"GET","POST"})
     */
    public function ajaxinscBanc(Request $request): jsonResponse
    {


        $client = HttpClient::create();    

        $response = $client->request('GET', 'https://ob.nordigen.com/api/v2/accounts/26df5a6b-99c0-4d2e-97dd-68257d2d3a71/transactions/?date_from=2022-07-20&date_to=2022-07-21', [
            'timeout' => 200,
            'headers' => [
                 'Accept' => 'application/json',
            'Authorization' =>  'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ0b2tlbl90eXBlIjoiYWNjZXNzIiwiZXhwIjoxNjU4NzM3MDIxLCJqdGkiOiI1NGY0OTNjNWFmMDQ0ZWRlYTk0NzE2ODliMmFmYTFmMyIsImlkIjoxMzU0MCwic2VjcmV0X2lkIjoiMzI3ZWM5OTUtZWQ1YS00NDM0LThmNDctMzZiNjg3ZWE1YjY4IiwiYWxsb3dlZF9jaWRycyI6WyIwLjAuMC4wLzAiLCI6Oi8wIl19.pYnu-IF-UCo2WfgtFUFqapNFK4ggQUpOksAgvLq0Tuo',
       //       'X-CSRFToken'=> 'kpwMUHL5TILe3znN5Y8GCtDpcFoe6MiQ0Nb2QcxZKKWX52I7VfblzdAViM3ttAwk',
          ],

            ]);

        //$response = $client->request('GET', 'https://ob.nordigen.com/api/v2/requisitions/b6acff02-1c0c-4fc3-8a22-cde8cb53b9ef/', [

        //   'headers' => [
        //    'Accept' => 'application/json',
        //    'Authorization' =>  'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ0b2tlbl90eXBlIjoiYWNjZXNzIiwiZXhwIjoxNjU4NzM3MDIxLCJqdGkiOiI1NGY0OTNjNWFmMDQ0ZWRlYTk0NzE2ODliMmFmYTFmMyIsImlkIjoxMzU0MCwic2VjcmV0X2lkIjoiMzI3ZWM5OTUtZWQ1YS00NDM0LThmNDctMzZiNjg3ZWE1YjY4IiwiYWxsb3dlZF9jaWRycyI6WyIwLjAuMC4wLzAiLCI6Oi8wIl19.pYnu-IF-UCo2WfgtFUFqapNFK4ggQUpOksAgvLq0Tuo',
        //  ],
        //   ]);

       //$response = $client->request('POST', 'https://ob.nordigen.com/api/v2/requisitions/', [

        //    'headers' => [
       //     'Accept' => 'application/json',
       //     'Authorization' =>  'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ0b2tlbl90eXBlIjoiYWNjZXNzIiwiZXhwIjoxNjU4NzM3MDIxLCJqdGkiOiI1NGY0OTNjNWFmMDQ0ZWRlYTk0NzE2ODliMmFmYTFmMyIsImlkIjoxMzU0MCwic2VjcmV0X2lkIjoiMzI3ZWM5OTUtZWQ1YS00NDM0LThmNDctMzZiNjg3ZWE1YjY4IiwiYWxsb3dlZF9jaWRycyI6WyIwLjAuMC4wLzAiLCI6Oi8wIl19.pYnu-IF-UCo2WfgtFUFqapNFK4ggQUpOksAgvLq0Tuo',
       //    ],
       //     'body'=> ['redirect'=>'https://localhost/admin', 'institution_id'=>'BANCSABADELL_BSABESBB'],
       //    ]);

        //$response = $client->request('GET', 'https://ob.nordigen.com/api/v2/institutions/?country=es', [

        //   'headers' => [
        //       'Accept' => 'application/json',
        //       'Authorization' =>  'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ0b2tlbl90eXBlIjoiYWNjZXNzIiwiZXhwIjoxNjU4NzM3MDIxLCJqdGkiOiI1NGY0OTNjNWFmMDQ0ZWRlYTk0NzE2ODliMmFmYTFmMyIsImlkIjoxMzU0MCwic2VjcmV0X2lkIjoiMzI3ZWM5OTUtZWQ1YS00NDM0LThmNDctMzZiNjg3ZWE1YjY4IiwiYWxsb3dlZF9jaWRycyI6WyIwLjAuMC4wLzAiLCI6Oi8wIl19.pYnu-IF-UCo2WfgtFUFqapNFK4ggQUpOksAgvLq0Tuo',
        //   ],
        //    ]); 

        //$response = $client->request('POST', 'https://ob.nordigen.com/api/v2/token/new/', [
        //'headers' => [
        //'Accept' => 'application/json',
        //],
        //'body'=> ['secret_id'=>'327ec995-ed5a-4434-8f47-36b687ea5b68', 'secret_key'=>'6dc733f46205e5aedde588f0f70b0f8bfa5a2b4debfed990db2bd76e3698fc75cc67daef836fc6538b99b7890cbffc75555f81f2fba3f51a9c157013b39d75c8'],
        //]); 
      
        $contentType = $response->getHeaders()['content-type'][0];

        // trying to get the response contents will block the execution until
        // the full response contents are received
        $contents = $response->getContent();
         // Cantidad total de elementos en la cesta
         $respuesta = new JsonResponse();
         $respuesta->setStatusCode(200);
        //return $response->setData(['contenido' => $contents]);
        return $respuesta->setData(['contenido' => $contents]);;


    }  

}
