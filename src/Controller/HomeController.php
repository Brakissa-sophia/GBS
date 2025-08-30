<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Device;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\DeviceRepository;
use App\Repository\ProductRepository;
use App\Repository\SkinTypeRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(BrandRepository $brandRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'brand' => $brandRepository->findAll()
        ]);
    }

    #[Route('/marque', name:'app_brand')]
    public function brand(): Response 
    {
        return $this->render('home/brand.html.twig', []);
    }

    // ========== PRODUITS ==========

    #[Route('/catalogue', name: 'app_catalog')]
    public function catalog(
        ProductRepository $productRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository, 
        HttpFoundationRequest $request, 
        PaginatorInterface $paginator
    ): Response {
        
        $query = $productRepository->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->getQuery();

        $products = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );

        $productsWithImages = [];
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/';
        
        foreach ($products as $product) {
            $pattern = $uploadDir . '*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }

        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'products' => $products,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
        ]);
    }

    #[Route('/catalogue/category/{id}', name: 'app_catalog_category')]
    public function catalogByCategory(
        int $id, 
        ProductRepository $productRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository,
        HttpFoundationRequest $request,
        PaginatorInterface $paginator
    ): Response {
        $category = $categoryRepository->find($id);
        
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }
        
        $query = $productRepository->createQueryBuilder('p')
            ->where('p.category = :category')
            ->setParameter('category', $category)
            ->orderBy('p.id', 'DESC')
            ->getQuery();

        $products = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        $productsWithImages = [];
        foreach ($products as $product) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'products' => $products,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedCategory' => $category,
        ]);
    }

    #[Route('/catalogue/brand/{id}', name: 'app_catalog_brand')]
    public function catalogByBrand(
        int $id, 
        ProductRepository $productRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository,
        HttpFoundationRequest $request,
        PaginatorInterface $paginator
    ): Response {
        $brand = $brandRepository->find($id);
        
        if (!$brand) {
            throw $this->createNotFoundException('Marque non trouvée');
        }
        
        $query = $productRepository->createQueryBuilder('p')
            ->where('p.brand = :brand')
            ->setParameter('brand', $brand)
            ->orderBy('p.id', 'DESC')
            ->getQuery();

        $products = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        $productsWithImages = [];
        foreach ($products as $product) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'products' => $products,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedBrand' => $brand,
        ]);
    }

    #[Route('/catalogue/skintype/{name}', name: 'app_catalog_skintype')]
    public function catalogBySkinType(
        string $name, 
        ProductRepository $productRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository,
        HttpFoundationRequest $request,
        PaginatorInterface $paginator
    ): Response {
        $skinType = $skinTypeRepository->findOneBy(['title' => $name]);
        
        if (!$skinType) {
            throw $this->createNotFoundException('Type de peau non trouvé');
        }
        
        $query = $productRepository->createQueryBuilder('p')
            ->join('p.skin_type', 's')
            ->where('s.id = :skinTypeId')
            ->setParameter('skinTypeId', $skinType->getId())
            ->orderBy('p.id', 'DESC')
            ->getQuery();

        $products = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        $productsWithImages = [];
        foreach ($products as $product) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'products' => $products,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedSkinType' => $skinType,
        ]);
    }

    #[Route('/product/{id}/catalog/show', name: 'app_catalog_product_show')]
    public function show(Product $product, ProductRepository $productRepository): Response
    {
        $productImages = [];
        for ($i = 1; $i <= 4; $i++) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-' . $i . '-*.*';
            $files = glob($pattern);
            $productImages[$i] = !empty($files) ? '/uploads/products/' . basename($files[0]) : null;
        }
        
        $productWithImages = [
            'product' => $product,
            'images' => $productImages,
            'mainImage' => $productImages[1],
            'ingredients' => $product->getIngredients(),
            'usageAdvice' => $product->getUsageAdvice()
        ];
        
        $similarProductsData = $productRepository->createQueryBuilder('p')
            ->where('p.category = :category')
            ->andWhere('p.id != :currentProductId')
            ->setParameter('category', $product->getCategory())
            ->setParameter('currentProductId', $product->getId())
            ->setMaxResults(2)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
        
        $similarProducts = [];
        foreach ($similarProductsData as $similarProduct) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $similarProduct->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $similarProducts[] = [
                'product' => $similarProduct,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('home/catalog-show.html.twig', [
            'product' => $product,
            'productWithImages' => $productWithImages,
            'similarProducts' => $similarProducts
        ]);
    }

    // ========== APPAREILS ==========

    #[Route('/appareil', name: 'app_device')]
    public function device(
        DeviceRepository $deviceRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository, 
        HttpFoundationRequest $request, 
        PaginatorInterface $paginator
    ): Response {
        
        $query = $deviceRepository->createQueryBuilder('d')
            ->orderBy('d.id', 'DESC')
            ->getQuery();
            
        $devices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        $devicesWithImages = [];
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/';
        
        foreach ($devices as $device) {
            $pattern = $uploadDir . '*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('home/device.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'devices' => $devices,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
        ]);
    }

    #[Route('/appareil/category/{id}', name: 'app_device_catalog_category')]
    public function deviceByCategory(
        int $id, 
        DeviceRepository $deviceRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository,
        HttpFoundationRequest $request,
        PaginatorInterface $paginator
    ): Response {
        $category = $categoryRepository->find($id);
        
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }
        
        $query = $deviceRepository->createQueryBuilder('d')
            ->where('d.category = :category')
            ->setParameter('category', $category)
            ->orderBy('d.id', 'DESC')
            ->getQuery();

        $devices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        $devicesWithImages = [];
        foreach ($devices as $device) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('home/device-catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'devices' => $devices,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedCategory' => $category,
        ]);
    }

    #[Route('/appareil/brand/{id}', name: 'app_device_catalog_brand')]
    public function deviceByBrand(
        int $id, 
        DeviceRepository $deviceRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository,
        HttpFoundationRequest $request,
        PaginatorInterface $paginator
    ): Response {
        $brand = $brandRepository->find($id);
        
        if (!$brand) {
            throw $this->createNotFoundException('Marque non trouvée');
        }
        
        $query = $deviceRepository->createQueryBuilder('d')
            ->where('d.brand = :brand')
            ->setParameter('brand', $brand)
            ->orderBy('d.id', 'DESC')
            ->getQuery();

        $devices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        $devicesWithImages = [];
        foreach ($devices as $device) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('home/device-catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'devices' => $devices,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedBrand' => $brand,
        ]);
    }

    #[Route('/appareil/skintype/{name}', name: 'app_device_catalog_skintype')]
    public function deviceBySkinType(
        string $name, 
        DeviceRepository $deviceRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository,
        HttpFoundationRequest $request,
        PaginatorInterface $paginator
    ): Response {
        $skinType = $skinTypeRepository->findOneBy(['title' => $name]);
        
        if (!$skinType) {
            throw $this->createNotFoundException('Type de peau non trouvé');
        }
        
        $query = $deviceRepository->createQueryBuilder('d')
            ->join('d.skin_type', 's')
            ->where('s.id = :skinTypeId')
            ->setParameter('skinTypeId', $skinType->getId())
            ->orderBy('d.id', 'DESC')
            ->getQuery();

        $devices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        $devicesWithImages = [];
        foreach ($devices as $device) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('home/device-catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'devices' => $devices,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedSkinType' => $skinType,
        ]);
    }

    #[Route('/device/{id}/catalog/show', name: 'app_device_catalog_show_home')]
    public function showDevice(Device $device, DeviceRepository $deviceRepository): Response
    {
        $deviceImages = [];
        for ($i = 1; $i <= 4; $i++) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-' . $i . '-*.*';
            $files = glob($pattern);
            $deviceImages[$i] = !empty($files) ? '/uploads/devices/' . basename($files[0]) : null;
        }
        
        $deviceWithImages = [
            'device' => $device,
            'images' => $deviceImages,
            'mainImage' => $deviceImages[1],
            'ingredients' => $device->getIngredients(),
            'usageAdvice' => $device->getUsageAdvice()
        ];
        
        $similarDevicesData = $deviceRepository->createQueryBuilder('d')
            ->where('d.category = :category')
            ->andWhere('d.id != :currentDeviceId')
            ->setParameter('category', $device->getCategory())
            ->setParameter('currentDeviceId', $device->getId())
            ->setMaxResults(2)
            ->orderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
        
        $similarDevices = [];
        foreach ($similarDevicesData as $similarDevice) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $similarDevice->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $similarDevices[] = [
                'device' => $similarDevice,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('home/device-catalog-show.html.twig', [
            'device' => $device,
            'deviceWithImages' => $deviceWithImages,
            'similarDevices' => $similarDevices
        ]);
    }

    // ========== PAGES STATIQUES ==========

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response 
    {
        return $this->render('home/contact.html.twig', []);
    }

    #[Route('/favoris', name: 'app_favorite')]
    public function favorite(): Response 
    {
        return $this->render('home/favorite.html.twig', []);
    }
}