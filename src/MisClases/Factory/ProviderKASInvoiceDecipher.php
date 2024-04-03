<?php
namespace App\MisClases\Factory;

class ProviderKASInvoiceDecipher implements InvoiceDecipherStrategy {
    public function decipher(string $text): array {
        $data =  []; // Array para almacenar los datos extraídos
        $data['proveedor'] = 'Antonio Fdz Castellanos';
        // Extracción de la fecha de vencimiento
        $patternVencimiento = '/(\d{2}\/\d{2}\/\d{4})\s+([\d,]+[\,\.]\d{2})/';


        if (preg_match($patternVencimiento, $text, $matches)) {
   
            $data['fechaVencimiento'] = $matches[1]; // Esto captura la fecha
                       
            $data['importeTotal'] =  floatval(str_replace(',', '.', str_replace('.', ',', $matches[2]))); // Esto captura el importe
        } else {
            $data['fechaVencimiento'] = 'No especificada kas';
            $data['importeTotal'] = 'No especificada kas';
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