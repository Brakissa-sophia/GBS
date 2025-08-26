<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Device;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\DeviceRepository;
use App\Repository\ProductRepository;
use App\Repository\SkinTypeRepository;
use Knp\Component\Pager\Event\PaginationEvent;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\BrowserKit\Request;
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
        return $this->render('home/brand.html.twig',[]);
    }

    #[Route('/catalogue', name: 'app_catalog')]
public function catalog(
    ProductRepository $productRepository, 
    CategoryRepository $categoryRepository, 
    BrandRepository $brandRepository, 
    SkinTypeRepository $skinTypeRepository, 
    HttpFoundationRequest $request, 
    PaginatorInterface $paginator
): Response {
    
    // 1. Pagination optimisée
    $query = $productRepository->createQueryBuilder('p')
        ->orderBy('p.id', 'DESC')
        ->getQuery();

    $products = $paginator->paginate(
        $query,
        $request->query->getInt('page', 1),
        16
    );

    // 2. Images avec gestion d'erreurs améliorée
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

    // 3. Données pour les filtres
    return $this->render('home/catalogue.html.twig', [
        'productsWithImages' => $productsWithImages,
        'products' => $products, // Pour la pagination dans Twig
        'categories' => $categoryRepository->findAll(),
        'brands' => $brandRepository->findAll(), // 'brand' → 'brands'
        'skinTypes' => $skinTypeRepository->findAll(),
    ]);
}

    // Filtre par catégorie
    #[Route('/catalogue/category/{id}', name: 'app_catalog_category')]
    public function catalogByCategory(int $id, ProductRepository $productRepository, CategoryRepository $categoryRepository, BrandRepository $brandRepository, SkinTypeRepository $skinTypeRepository): Response
    {
        $category = $categoryRepository->find($id);
        
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }
        
        $products = $productRepository->findBy(['category' => $category], ['id' => 'DESC']);
        
        // Code pour les images
        $productsWithImages = [];
        foreach ($products as $product) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? 'uploads/products/' . basename($files[0]) : null
            ];
        }
        
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'categories' => $categoryRepository->findAll(),
            'brand' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedCategory' => $category,
        ]);
    }

    // Filtre par marque
    #[Route('/catalogue/brand/{id}', name: 'app_catalog_brand')]
    public function catalogByBrand(int $id, ProductRepository $productRepository, CategoryRepository $categoryRepository, BrandRepository $brandRepository, SkinTypeRepository $skinTypeRepository): Response
    {
        $brand = $brandRepository->find($id);
        
        if (!$brand) {
            throw $this->createNotFoundException('Marque non trouvée');
        }
        
        $products = $productRepository->findBy(['brand' => $brand], ['id' => 'DESC']);
        
        // Code pour les images
        $productsWithImages = [];
        foreach ($products as $product) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? 'uploads/products/' . basename($files[0]) : null
            ];
        }
        
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'categories' => $categoryRepository->findAll(),
            'brand' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedBrand' => $brand,
        ]);
    }

    // Filtre par type de peau (par nom)
    #[Route('/catalogue/skintype/{name}', name: 'app_catalog_skintype')]
    public function catalogBySkinType(string $name, ProductRepository $productRepository, CategoryRepository $categoryRepository, BrandRepository $brandRepository, SkinTypeRepository $skinTypeRepository): Response
    {
        $skinType = $skinTypeRepository->findOneBy(['title' => $name]);
        
        if (!$skinType) {
            throw $this->createNotFoundException('Type de peau non trouvé');
        }
        
        // Pour une relation ManyToMany, on utilise une requête différente
        $products = $productRepository->createQueryBuilder('p')
            ->join('p.skin_type', 's')
            ->where('s.id = :skinTypeId')
            ->setParameter('skinTypeId', $skinType->getId())
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Code pour les images
        $productsWithImages = [];
        foreach ($products as $product) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? 'uploads/products/' . basename($files[0]) : null
            ];
        }
        
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'categories' => $categoryRepository->findAll(),
            'brand' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedSkinType' => $skinType,
        ]);
    }

    #[Route('/product/{id}/catalog/show', name: 'app_catalog_product_show')]
    public function show(Product $product, ProductRepository $productRepository): Response
    {
        // Récupérer les images du produit principal
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
        
        // Récupérer les produits similaires de la même catégorie
        $similarProductsData = $productRepository->createQueryBuilder('p')
            ->where('p.category = :category')
            ->andWhere('p.id != :currentProductId')
            ->setParameter('category', $product->getCategory())
            ->setParameter('currentProductId', $product->getId())
            ->setMaxResults(2)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Ajouter les images aux produits similaires
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
    

  #[Route('/appareil', name: 'app_device')]
public function device(
    DeviceRepository $deviceRepository, 
    CategoryRepository $categoryRepository, 
    BrandRepository $brandRepository, 
    SkinTypeRepository $skinTypeRepository, 
    HttpFoundationRequest $request, 
    PaginatorInterface $paginator
): Response {
    
    // 1. Pagination optimisée pour les outils de beauté
    $query = $deviceRepository->createQueryBuilder('d')
        ->orderBy('d.id', 'DESC')
        ->getQuery();
        
    $devices = $paginator->paginate(
        $query,
        $request->query->getInt('page', 1),
        16
    );
    
    // 2. Images des outils de beauté avec gestion d'erreurs améliorée
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
    
    // 3. Données pour les filtres des outils de beauté
    return $this->render('home/device.html.twig', [
        'devicesWithImages' => $devicesWithImages,
        'devices' => $devices, // Pour la pagination dans Twig
        'categories' => $categoryRepository->findAll(),
        'brands' => $brandRepository->findAll(),
        'skinTypes' => $skinTypeRepository->findAll(),
    ]);
}

// NOUVELLE MÉTHODE : Affichage détail d'un device depuis le front
#[Route('/device/{id}/catalog/show', name: 'app_device_catalog_show_home')]
public function showDevice(Device $device, DeviceRepository $deviceRepository): Response
{
    // Récupérer les images du device principal
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
    
    // Récupérer les devices similaires de la même catégorie
    $similarDevicesData = $deviceRepository->createQueryBuilder('d')
        ->where('d.category = :category')
        ->andWhere('d.id != :currentDeviceId')
        ->setParameter('category', $device->getCategory())
        ->setParameter('currentDeviceId', $device->getId())
        ->setMaxResults(2)
        ->orderBy('d.id', 'DESC')
        ->getQuery()
        ->getResult();
    
    // Ajouter les images aux devices similaires
    $similarDevices = [];
    foreach ($similarDevicesData as $similarDevice) {
        $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $similarDevice->getId() . '-1-*.*';
        $files = glob($pattern);
        
        $similarDevices[] = [
            'device' => $similarDevice,
            'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
        ];
    }
    
    return $this->render('device/catalog-show.html.twig', [
        'device' => $device,
        'deviceWithImages' => $deviceWithImages,
        'similarDevices' => $similarDevices
    ]);
}

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response 
    {
        return $this->render('home/contact.html.twig',[]);
    }

    #[Route('/favoris', name: 'app_favorite')]
    public function favorite(): Response 
    {
        return $this->render('home/favorite.html.twig',[]);
    }
}