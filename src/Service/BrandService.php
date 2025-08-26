<?php

namespace App\Service;

use App\Repository\BrandRepository;

class BrandService
{
    public function __construct(private BrandRepository $brandRepository) 
    {
    }

    public function getAllBrands(): array
    {
        return $this->brandRepository->findAll();
    }
}