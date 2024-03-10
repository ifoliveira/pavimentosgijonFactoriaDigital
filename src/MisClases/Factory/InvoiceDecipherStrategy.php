<?php
namespace App\MisClases\Factory;

interface InvoiceDecipherStrategy {
    public function decipher(string $text): array;
}



