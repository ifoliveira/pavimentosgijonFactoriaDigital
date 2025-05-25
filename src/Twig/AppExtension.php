<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('floatval_euro', [$this, 'floatvalEuro']),
        ];
    }

    public function floatvalEuro($valor): float
    {
        return (float) str_replace(',', '.', $valor);
    }
}
