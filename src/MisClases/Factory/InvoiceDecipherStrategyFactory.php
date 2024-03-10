<?php
namespace App\MisClases\Factory;

use App\MisClases\Factory\InvoiceDecipherStrategy;


class InvoiceDecipherStrategyFactory {
    public static function create(string $identifier): InvoiceDecipherStrategy {
        switch ($identifier) {
            case 'ProviderAFC':
                return new ProviderAFCInvoiceDecipher();
            // Agrega más casos según sea necesario
            default:
                throw new \Exception("No decipher strategy found for identifier: $identifier");
        }
    }
}