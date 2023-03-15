<?php
namespace App\MisClases;
/* A wrapper to do organise item names & prices into columns */
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManager;
use App\Entity\Banco;
use App\Entity\Tiposmovimiento;
   
class nordigen
    {
        private $link;
        private $tipaccess;
        private $hAccept;
        private $secret_id;
        private $secret_key;
        private $Authorization;
        private $routenordigen;
    
        public function __construct($accept = '', $nordigen)
        {
            $this->hAccept = $accept;
            $this->routenordigen = $nordigen;

        }

        public function getAccountBalance($account)
        {
            $client = HttpClient::create(); 

            $response = $client->request('GET', 'https://ob.nordigen.com/api/v2/accounts/'. $account . '/balances/', [
 
                'headers' => [
                    'Accept' => '' . $this->hAccept .'',
                    'Authorization' =>  'Bearer ' . $this->credenciales() . '',
             ],
                  ]);
     
     
            $contentType = $response->getHeaders()['content-type'][0];

            $contenido = $response->getContent();

            return $contenido; 
     

        }
        
        public function sendAuth($bancoid)
        {
            $client = HttpClient::create();    

            $response = $client->request('POST', 'https://ob.nordigen.com/api/v2/requisitions/', [
     
                'headers' => [
                     'Accept' => '' . $this->hAccept .'',
                     'Authorization' =>  'Bearer ' . $this->credenciales() . '',
             ],
                 'body'=> ['redirect'=>'https://127.0.0.1:8000/admin/banco/incluir/link', 'institution_id'=>'' . $bancoid . ''],
                ]);
     
            
             $contentType = $response->getHeaders()['content-type'][0];
     
             // trying to get the response contents will block the execution until
             // the full response contents are received
             $contenido = $response->getContent();

             return $contenido; 
     

        }

        public function getAccountsTrasactions($account, $fechaini, $fechafin)
        {
            $client = HttpClient::create(); 

            $response = $client->request('GET', 'https://ob.nordigen.com/api/v2/accounts/'. $account . '/transactions/?date_from=' . $fechaini .'&date_to='. $fechafin .'' , 
            [
                'timeout' => 120,
                'headers' => [
                    'Accept' => '' . $this->hAccept .'',
                    'Authorization' =>  'Bearer ' . $this->credenciales() . '',
             ],
                  ]);

     
            $contentType = $response->getHeaders()['content-type'][0];

            $contenido = $response->getContent();

            return $decoded_json = json_decode($contenido, false); 
        }


        public function getAccountsDetails($account)
        {
            $client = HttpClient::create(); 

            $response = $client->request('GET', 'https://ob.nordigen.com/api/v2/accounts/'. $account . '/details/', [
 
                'headers' => [
                    'Accept' => '' . $this->hAccept .'',
                    'Authorization' =>  'Bearer ' . $this->credenciales() . '',
             ],
                  ]);
     
     
            $contentType = $response->getHeaders()['content-type'][0];

            $contenido = $response->getContent();

            return $decoded_json = json_decode($contenido, false); 
        }

        public function getAccounts($bankRef)
        {
            $client = HttpClient::create(); 
            $response = $client->request('GET', 'https://ob.nordigen.com/api/v2/requisitions/'. $bankRef . '/', [
     
                'headers' => [
                    'Accept' => '' . $this->hAccept .'',
                    'Authorization' =>  'Bearer ' . $this->credenciales() . '',
             ],
                  ]);
     
            $contentType = $response->getHeaders()['content-type'][0];

            $contenido = $response->getContent();

            return $decoded_json = json_decode($contenido, false); 
        }

        public function newToken($secret_id, $secret_key)
        {
 
            $client = HttpClient::create(); 
            $response = $client->request('POST', 'https://ob.nordigen.com/api/v2/token/new/', [
            'headers' => [
            'Accept' => '' . $this->hAccept .'',
            ],
            'body'=> ['secret_id'=>'' . $secret_id . '', 'secret_key'=>'' . $secret_key . ''],
            ]); 
            $contentType = $response->getHeaders()['content-type'][0];

            $contenido = $response->getContent();
            
            return $decoded_json = json_decode($contenido, false); 
        }

        public function restoreToken($refresh, $secret_id, $secret_key)
        {
            $client = HttpClient::create(); 
            $response = $client->request('POST', 'https://ob.nordigen.com/api/v2/token/refresh/', [
            'headers' => [
            'Accept' => '' . $this->hAccept .'',
            ],
            'body'=> ['refresh'=>'' . $refresh . ''],
            ]); 
            $contentType = $response->getHeaders(false)['content-type'][0];

            switch ($response->getStatusCode()) {
                case 401:
                    unset($response);
                    return $this->newToken($secret_id, $secret_key);
                    break;
                case 200:
                    break;
            }

            $contenido = $response->getContent();
            
            return $decoded_json = json_decode($contenido, false); 
        }
        
        public function __toString()
        {
            return $this->hAccept;
        }

        public function credenciales()
        {

            // Credenciales de acceso a nordigen
            $nombrefic = 'credenciales.json';
            $data = file_get_contents($this->routenordigen .'/'.$nombrefic);
            $credenciales = json_decode($data, true);

            return $credenciales['acceso'];

        }

        public function concepto($line)
        {
            $tipo = new tiposmovimiento;
            $concepto = explode(" ", $line);
            $gastofijo = array("GIJONESAS,", "COTIZACION", "TELECABLE", "LOBO", "Aguas", "IMPUESTOS","COREEDP", "Coffe");
            $ventas= array("TPV", "ORDENANTE");
            $proveedor=array("COMERCIAL", "GME", "CAMACHO","MEDITERRANEA","COREL.","    EUROMUEBLES","CORE1y1", "COREMOGAR", "KASSANDRA", "DUPLACH", "SARIEGO");
            $comision = array ("COMISION", "COMISIONES");
            $otros = array("5540XXXXXXXX5018");
    
            foreach ($gastofijo as &$valor) {
                if (in_array($valor, $concepto)) {
                    $tipo->setDescripcionTm("Gasto Fijo");
                    return $tipo;
                }
            }
    
            foreach ($ventas as &$valor) {
                if (in_array($valor, $concepto)) {
                    $tipo->setDescripcionTm("Ventas");
                    return $tipo;
                }
            }
    
            foreach ($comision as &$valor) {
                if (in_array($valor, $concepto)) {
                    $tipo->setDescripcionTm("Comisiones");
                    return $tipo;
                }
            }
    
    
            foreach ($proveedor as &$valor) {
                if (in_array($valor, $concepto)) {
                    $tipo->setDescripcionTm("Proveedor");
                    return $tipo;
                }
            }
    
            foreach ($otros as &$valor) {
                if (in_array($valor, $concepto)) {
                    $tipo->setDescripcionTm("Otros");
                    return $tipo;
                }
            }
    
            $tipo->setDescripcionTm("Otros");
            return $tipo;
    

        }

    }
?>