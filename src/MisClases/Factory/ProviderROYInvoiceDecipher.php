<?php
namespace App\MisClases\Factory;

class ProviderROYInvoiceDecipher implements InvoiceDecipherStrategy {
    public function decipher(string $text): array {
        $data =  []; // Array para almacenar los datos extraídos
        $data['proveedor'] = 'Antonio Fdz Castellanos';
        // Extracción de la fecha de vencimiento
        $patternVencimiento = '/(\d{2}\/\d{2}\/\d{4})\s+([\d.,]+)/';


        if (preg_match($patternVencimiento, $text, $matches)) {
   
            $data['fechaVencimiento'] = $matches[1]; // Esto captura la fecha
            $data['importeTotal'] =  floatval(str_replace(',', '.', str_replace('.', '', $matches[2]))); // Esto captura el importe
        } else {
            $data['fechaVencimiento'] = 'No especificada';
            $data['importeTotal'] = 'No especificada';
        }


        $patternProductos = '/(.*?)\s+(\d+,\d{2})\s*(-\d+,\d{2})\s*(\d+,\d{2})/';

        preg_match_all($patternProductos, $text, $matchesProductos, PREG_SET_ORDER);

        $productosDetalles = [];
        
        foreach ($matchesProductos as $producto) {
                        
            $productosDetalles[] = [
                'codigo' => $producto[1],
                'cantidad' => floatval(1),
                'descripcion' => $producto[1],
                'precio' => floatval(str_replace(',', '.', str_replace('.', '', $producto[4]))),
                'descuento' => floatval(str_replace(',', '.', str_replace('.', '', $producto[3])))*-1,

            ];
        }       


        $data['producto'] = $productosDetalles;

        return $data; // Devuelve los datos extraídos
    }
}