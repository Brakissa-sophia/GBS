<?php

namespace App\Twig;

use App\Service\BrandService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BrandExtension extends AbstractExtension
{
    public function __construct(private BrandService $brandService) 
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_brands', [$this, 'getBrands']),
        ];
    }

    public function getBrands(): array
    {
        return $this->brandService->getAllBrands();
    }
}