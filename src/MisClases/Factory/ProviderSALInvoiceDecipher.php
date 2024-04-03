<?php
namespace App\MisClases\Factory;

class ProviderSALInvoiceDecipher implements InvoiceDecipherStrategy {
    public function decipher(string $text): array {
        $data =  []; // Array para almacenar los datos extraídos
        $data['proveedor'] = 'Antonio Fdz Castellanos';
        // Extracción de la fecha de vencimiento
        $patternImporte = '/(\d+,\d{2})\s*EUR/';


        if (preg_match($patternImporte, $text, $matches)) {
                     
            $data['importeTotal'] =  floatval(str_replace(',', '.', str_replace('.', ',', $matches[1]))); // Esto captura el importe
        } else {

            $data['importeTotal'] = 'No especificada kas';
        }

        $patternImporte = '/Fecha Salida:\s(\d{2}\/\d{2}\/\d{4})\s+/';


        if (preg_match($patternImporte, $text, $matches)) {
                     
            $data['fechaVencimiento'] =  $matches[1]; // Esto captura el importe

        } else {

            $data['fechaVencimiento'] = 'No especificada kas';
        }

        $patternProductos = '/(.*?)\s+(\d{1,2}\.\d{2})\s+(.+?)\s+(\d+\.\d{2})\s+(\d+\.\d{2})\s+(\d+\.\d{2})/';

        preg_match_all($patternProductos, $text, $matchesProductos, PREG_SET_ORDER);
        
        $productosDetalles = [];
        
        foreach ($matchesProductos as $producto) {
            $productosDetalles[] = [
                'codigo' => $producto[1],
                'cantidad' => $producto[2],
                'descripcion' => trim($producto[3]), // Elimina espacios en blanco adicionales
                'precio' => $producto[4]- ($producto[4]*$producto[5]/100), // Elimina espacios en blanco adicionales
                'descuento' => floatval(60),


            ];
        }       
        
        
        $data['producto'] = $productosDetalles;

        return $data; // Devuelve los datos extraídos
    }
}