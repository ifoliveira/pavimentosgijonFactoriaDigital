<?php
namespace App\MisClases\Factory;

class ProviderCRMInvoiceDecipher implements InvoiceDecipherStrategy {
    public function decipher(string $text): array {
        $data = []; // Array para almacenar los datos extraídos
        $data['proveedor'] = 'Cromados Modernos';

        // Extracción de la fecha de vencimiento
        $patternVencimiento = "/VTO:\s(\d{2}\/\d{2}\/\d{4})/";

        if (preg_match($patternVencimiento, $text, $matches)) {
   
            $data['fechaVencimiento'] = $matches[1]; // Esto captura la fecha
        } else {
          
            $data['fechaVencimiento'] = 'No especificada';
        }

        $patternVencimiento = "/EUROS:\s(\d+,\d{2})/";

        if (preg_match($patternVencimiento, $text, $matches)) {
   
            $data['importeTotal'] =floatval(str_replace(',', '.', str_replace('.', '', $matches[1]))); // Esto captura el importe
        } else {
            $data['importeTotal'] = 'No especificada';
        }

        
        $patternProductos = "/(\d+)[\|}\]]?\s([A-Z0-9]+)\s(.*?)\s(\d+,\d{2})]\s(\d+%) (\d+,\d{2})/";

        preg_match_all($patternProductos, $text, $matchesProductos, PREG_SET_ORDER);
        
        $productosDetalles = [];
        
        foreach ($matchesProductos as $producto) {
            $productosDetalles[] = [
                'codigo' => $producto[2],
                'cantidad' => $producto[1],
                'descripcion' => trim($producto[3]), // Elimina espacios en blanco adicionales
                'precio' => floatval(str_replace(',', '.', str_replace('.', '', $producto[4]))) , // Elimina espacios en blanco adicionales
                'precio2' => floatval(str_replace(',', '.', str_replace('.', '', $producto[5]))),
                'precio3' => floatval(str_replace(',', '.', str_replace('.', '', $producto[5])))


            ];
        }       
        
        
        $data['producto'] = $productosDetalles;

        return $data; // Devuelve los datos extraídos
    }
}