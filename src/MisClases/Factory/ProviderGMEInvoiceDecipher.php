<?php
namespace App\MisClases\Factory;

class ProviderGMEInvoiceDecipher implements InvoiceDecipherStrategy {
    public function decipher(string $text): array {
        $data = []; // Array para almacenar los datos extraídos
        $data['proveedor'] = 'GME';
        // Extracción de la fecha de vencimiento
        $patternVencimiento = "/(\d{2}\/\d{2}\/\d{4})\s([\d.,]+)/";
        if (preg_match($patternVencimiento, $text, $matches)) {
   
            $data['fechaVencimiento'] = $matches[1]; // Esto captura la fecha
            $data['importeTotal'] =  floatval(str_replace(',', '.', str_replace('.', '', $matches[2]))); // Esto captura el importe
        } else {
            $data['fechaVencimiento'] = 'No especificada';
        }

        $patternProductos = "/(\d+)\s(.*?)\s(\d+)\s(\d{1,3}(?:,\d{2,3})?(?:\.\d{2})?)\s(\d{1,3}(?:,\d{2,3})?(?:\.\d{2})?)/";

        preg_match_all($patternProductos, $text, $matchesProductos, PREG_SET_ORDER);
        
        $productosDetalles = [];
        
        foreach ($matchesProductos as $producto) {
            $productosDetalles[] = [
                'codigo' => $producto[1],
                'cantidad' => $producto[3],
                'descripcion' => trim($producto[2]), // Elimina espacios en blanco adicionales
                'precio' => floatval(str_replace(',', '.', str_replace('.', '', $producto[4]))) , // Elimina espacios en blanco adicionales
                'precio2' => floatval(str_replace(',', '.', str_replace('.', '', $producto[5]))),
                'precio3' => floatval(str_replace(',', '.', str_replace('.', '', $producto[5])))


            ];
        }       
        
        
        $data['producto'] = $productosDetalles;

        return $data; // Devuelve los datos extraídos
    }
}