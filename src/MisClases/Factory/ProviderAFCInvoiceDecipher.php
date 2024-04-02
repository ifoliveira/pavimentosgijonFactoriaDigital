<?php
namespace App\MisClases\Factory;

class ProviderAFCInvoiceDecipher implements InvoiceDecipherStrategy {
    public function decipher(string $text): array {
        $data =  []; // Array para almacenar los datos extraídos
        $data['proveedor'] = 'Antonio Fdz Castellanos';
        // Extracción de la fecha de vencimiento
        $patternVencimiento = '/Ve ncimie ntos\s*:\s*(\d{2}\/\d{2}\/\d{2})/';
        if (preg_match($patternVencimiento, $text, $matchesVencimiento)) {
            $data['fechaVencimiento'] = $matchesVencimiento[1];
        } else {
            $data['fechaVencimiento'] = 'No especificada';
        }

        // Extracción del importe total
        $patternImporte = '/(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)\s*Eur\s*7\s*Forma\s*de\s*Pago/';


        preg_match($patternImporte, $text, $matchesImporte);
        
        if (!empty($matchesImporte)) {
            $data['importeTotal'] = $matchesImporte[1]; // Aquí debería estar tu número capturado
        } else {
            $data['importeTotal'] = 'No encontrado';
        }
        
        $patternProductos = '/([A-Z]{4}|\d{6}|\d{2}\s+\d{3}\s+\d{3}|\d{2}[A-Z]{3}\d{6})\s+(\d+\.\d{2})\s+(.*?)\s+(\d+\.\d{2})\s+(\d+\.\d{2})\s+(\d+\.\d{2})/';

        preg_match_all($patternProductos, $text, $matchesProductos, PREG_SET_ORDER);
        
        $productosDetalles = [];
        
        foreach ($matchesProductos as $producto) {
            $productosDetalles[] = [
                'codigo' => $producto[1],
                'cantidad' => $producto[2],
                'descripcion' => trim($producto[3]), // Elimina espacios en blanco adicionales
                'precio' => $producto[4], // Elimina espacios en blanco adicionales
                'precio2' => $producto[5],
                'precio3' => $producto[6]


            ];
        }       
        
        
        $data['producto'] = $productosDetalles;

        return $data; // Devuelve los datos extraídos
    }
}