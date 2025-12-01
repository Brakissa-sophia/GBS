<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Device;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\DeviceRepository;
use App\Repository\ProductRepository;
use App\Repository\SkinTypeRepository;
use Dom\Entity;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur principal de la partie publique du site
 * Gère l'affichage des catalogues de produits et d'appareils
 * ainsi que les fonctionnalités de recherche
 */
final class HomeController extends AbstractController
{
    /**
     * Page d'accueil du site
     * Affiche toutes les marques disponibles
     * 
     * @param BrandRepository $brandRepository Repository pour accéder aux marques
     * @return Response Rendu de la page d'accueil
     */
    #[Route('/', name: 'app_home')]
    public function index(BrandRepository $brandRepository): Response
    {
        // Rendu du template Twig avec toutes les marques en base de données
        return $this->render('home/index.html.twig', [
            'brand' => $brandRepository->findAll()
        ]);
    }

    /**
     * Page dédiée aux marques
     * 
     * @return Response Rendu de la page des marques
     */
    #[Route('/marque', name:'app_brand')]
    public function brand(): Response 
    {
        // Rendu du template sans données particulières
        return $this->render('home/brand.html.twig', []);
    }

    // ========== ROUTES UNIVERSELLES ==========
    // Ces routes analysent le contenu (produits/appareils) et redirigent vers la bonne page

    /**
     * Route universelle pour afficher un catalogue par catégorie
     * Détecte automatiquement s'il y a des produits, des appareils, ou les deux
     * et redirige vers la route appropriée
     * 
     * @param int $id ID de la catégorie
     * @param ProductRepository $productRepository Repository des produits
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Redirection vers la route appropriée
     */
    #[Route('/catalogue-universel/category/{id}', name: 'app_universal_catalog_category')]
    public function universalCatalogByCategory(
        int $id, 
        ProductRepository $productRepository, 
        DeviceRepository $deviceRepository,
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository,
        HttpFoundationRequest $request,
        PaginatorInterface $paginator
    ): Response {
        // Recherche de la catégorie par son ID
        $category = $categoryRepository->find($id);
        
        // Si la catégorie n'existe pas, lever une exception 404
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }
        
        // Compter le nombre de produits dans cette catégorie
        // Utilisation du QueryBuilder pour construire une requête SQL optimisée
        $productsCount = $productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)') // Compte uniquement les IDs (plus rapide qu'un COUNT(*))
            ->where('p.category = :category') // Filtre sur la catégorie
            ->setParameter('category', $category) // Binding du paramètre pour éviter les injections SQL
            ->getQuery()
            ->getSingleScalarResult(); // Retourne un seul résultat scalaire (nombre)
            
        // Compter le nombre d'appareils dans cette catégorie
        // Même logique que pour les produits
        $devicesCount = $deviceRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
            
        // Logique de redirection selon ce qui existe
        if ($productsCount > 0 && $devicesCount == 0) {
            // Cas 1: Seuls des produits existent dans cette catégorie
            return $this->redirectToRoute('app_catalog_category', ['id' => $id]);
        } elseif ($devicesCount > 0 && $productsCount == 0) {
            // Cas 2: Seuls des appareils existent dans cette catégorie
            return $this->redirectToRoute('app_device_catalog_category', ['id' => $id]);
        } elseif ($productsCount > 0 && $devicesCount > 0) {
            // Cas 3: Les deux types existent - on privilégie les produits par défaut
            return $this->redirectToRoute('app_catalog_category', ['id' => $id]);
        } else {
            // Cas 4: Aucun produit ni appareil n'existe - redirection vers le catalogue général
            $this->addFlash('info', 'Aucun produit ou appareil trouvé dans cette catégorie.');
            return $this->redirectToRoute('app_catalog');
        }
    }

    /**
     * Route universelle pour afficher un catalogue par marque
     * Même logique que pour les catégories mais filtre sur la marque
     * 
     * @param int $id ID de la marque
     * @param ProductRepository $productRepository Repository des produits
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Redirection vers la route appropriée
     */
    #[Route('/catalogue-universel/brand/{id}', name: 'app_universal_catalog_brand')]
    public function universalCatalogByBrand(
        int $id, 
        ProductRepository $productRepository, 
        DeviceRepository $deviceRepository,
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository,
        HttpFoundationRequest $request,
        PaginatorInterface $paginator
    ): Response {
        // Recherche de la marque par son ID
        $brand = $brandRepository->find($id);
        
        // Vérification de l'existence de la marque
        if (!$brand) {
            throw $this->createNotFoundException('Marque non trouvée');
        }
        
        // Compter le nombre de produits pour cette marque
        $productsCount = $productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.brand = :brand') // Filtre sur la marque au lieu de la catégorie
            ->setParameter('brand', $brand)
            ->getQuery()
            ->getSingleScalarResult();
            
        // Compter le nombre d'appareils pour cette marque
        $devicesCount = $deviceRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.brand = :brand')
            ->setParameter('brand', $brand)
            ->getQuery()
            ->getSingleScalarResult();
            
        // Redirection selon les résultats
        if ($productsCount > 0 && $devicesCount == 0) {
            // Seuls des produits
            return $this->redirectToRoute('app_catalog_brand', ['id' => $id]);
        } elseif ($devicesCount > 0 && $productsCount == 0) {
            // Seuls des appareils
            return $this->redirectToRoute('app_device_catalog_brand', ['id' => $id]);
        } elseif ($productsCount > 0 && $devicesCount > 0) {
            // Les deux existent - priorité aux produits
            return $this->redirectToRoute('app_catalog_brand', ['id' => $id]);
        } else {
            // Aucun résultat
            $this->addFlash('info', 'Aucun produit ou appareil trouvé pour cette marque.');
            return $this->redirectToRoute('app_catalog');
        }
    }

    /**
     * Route universelle pour afficher un catalogue par type de peau
     * Utilise le nom du type de peau au lieu d'un ID
     * 
     * @param string $name Nom du type de peau
     * @param ProductRepository $productRepository Repository des produits
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Redirection vers la route appropriée
     */
    #[Route('/catalogue-universel/skintype/{name}', name: 'app_universal_catalog_skintype')]
    public function universalCatalogBySkinType(
        string $name, 
        ProductRepository $productRepository, 
        DeviceRepository $deviceRepository,
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository,
        HttpFoundationRequest $request,
        PaginatorInterface $paginator
    ): Response {
        // Recherche du type de peau par son titre/nom
        $skinType = $skinTypeRepository->findOneBy(['title' => $name]);
        
        // Vérification de l'existence
        if (!$skinType) {
            throw $this->createNotFoundException('Type de peau non trouvé');
        }
        
        // Compter les produits pour ce type de peau
        // Nécessite une jointure car skin_type est une relation ManyToMany
        $productsCount = $productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.skin_type', 's') // Jointure avec la table de liaison
            ->where('s.id = :skinTypeId') // Filtre sur l'ID du type de peau
            ->setParameter('skinTypeId', $skinType->getId())
            ->getQuery()
            ->getSingleScalarResult();
            
        // Compter les appareils pour ce type de peau
        $devicesCount = $deviceRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.skin_type', 's')
            ->where('s.id = :skinTypeId')
            ->setParameter('skinTypeId', $skinType->getId())
            ->getQuery()
            ->getSingleScalarResult();
            
        // Redirection selon les résultats
        if ($productsCount > 0 && $devicesCount == 0) {
            return $this->redirectToRoute('app_catalog_skintype', ['name' => $name]);
        } elseif ($devicesCount > 0 && $productsCount == 0) {
            return $this->redirectToRoute('app_device_catalog_skintype', ['name' => $name]);
        } elseif ($productsCount > 0 && $devicesCount > 0) {
            // Les deux existent - priorité aux produits
            return $this->redirectToRoute('app_catalog_skintype', ['name' => $name]);
        } else {
            $this->addFlash('info', 'Aucun produit ou appareil trouvé pour ce type de peau.');
            return $this->redirectToRoute('app_catalog');
        }
    }

    // ========== PRODUITS ==========
    // Section dédiée à l'affichage des produits cosmétiques

    /**
     * Catalogue général des produits
     * Affiche tous les produits avec pagination
     * 
     * @param ProductRepository $productRepository Repository des produits
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page catalogue
     */
    #[Route('/catalogue', name: 'app_catalog')]
    public function catalog(
        ProductRepository $productRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository, 
        HttpFoundationRequest $request, 
        PaginatorInterface $paginator
    ): Response {
        
        // Création de la requête pour récupérer tous les produits
        $query = $productRepository->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC') // Tri par ID décroissant (du plus récent au plus ancien)
            ->getQuery();

        // Pagination des résultats
        $products = $paginator->paginate(
            $query, // La requête à paginer
            $request->query->getInt('page', 1), // Numéro de la page actuelle (1 par défaut)
            16 // Nombre d'éléments par page
        );

        // Tableau pour associer chaque produit à son image
        $productsWithImages = [];
        
        // Chemin absolu du dossier d'upload des images de produits
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/';
        
        // Parcourir tous les produits paginés
        foreach ($products as $product) {
            // Pattern de recherche d'image
            // Format attendu: [prefix]-[productId]-1-[suffix].[extension]
            // Le "1" représente l'image principale (première image)
            $pattern = $uploadDir . '*-' . $product->getId() . '-1-*.*';
            
            // glob() recherche tous les fichiers correspondant au pattern
            $files = glob($pattern);
            
            // Ajout du produit et de son image au tableau
            $productsWithImages[] = [
                'product' => $product, // L'entité Product complète
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
                // Si une image existe, on prend la première trouvée, sinon image par défaut
            ];
        }

        // Rendu du template Twig avec toutes les données nécessaires
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages, // Produits avec leurs images
            'products' => $products, // Objet de pagination (contient infos de pagination)
            'categories' => $categoryRepository->findAll(), // Pour le menu de filtres
            'brands' => $brandRepository->findAll(), // Pour le menu de filtres
            'skinTypes' => $skinTypeRepository->findAll(), // Pour le menu de filtres
        ]);
    }

    /**
     * Catalogue des produits filtrés par catégorie
     * 
     * @param int $id ID de la catégorie
     * @param ProductRepository $productRepository Repository des produits
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page catalogue filtrée
     */
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
        // Récupération de la catégorie
        $category = $categoryRepository->find($id);
        
        // Vérification de l'existence
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }
        
        // Création de la requête avec filtre sur la catégorie
        $query = $productRepository->createQueryBuilder('p')
            ->where('p.category = :category') // Clause WHERE pour filtrer
            ->setParameter('category', $category) // Binding sécurisé du paramètre
            ->orderBy('p.id', 'DESC')
            ->getQuery();

        // Pagination des résultats filtrés
        $products = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        // Association des images aux produits
        $productsWithImages = [];
        foreach ($products as $product) {
            // Recherche de l'image principale
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        // Rendu avec la catégorie sélectionnée en plus
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'products' => $products,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedCategory' => $category, // Permet de mettre en surbrillance le filtre actif
        ]);
    }

    /**
     * Catalogue des produits filtrés par marque
     * 
     * @param int $id ID de la marque
     * @param ProductRepository $productRepository Repository des produits
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page catalogue filtrée
     */
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
        // Récupération de la marque
        $brand = $brandRepository->find($id);
        
        // Vérification de l'existence
        if (!$brand) {
            throw $this->createNotFoundException('Marque non trouvée');
        }
        
        // Requête filtrée par marque
        $query = $productRepository->createQueryBuilder('p')
            ->where('p.brand = :brand')
            ->setParameter('brand', $brand)
            ->orderBy('p.id', 'DESC')
            ->getQuery();

        // Pagination
        $products = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        // Association des images
        $productsWithImages = [];
        foreach ($products as $product) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        // Rendu avec la marque sélectionnée
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'products' => $products,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedBrand' => $brand, // Marque actuellement filtrée
        ]);
    }

    /**
     * Catalogue des produits filtrés par type de peau
     * 
     * @param string $name Nom du type de peau
     * @param ProductRepository $productRepository Repository des produits
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page catalogue filtrée
     */
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
        // Recherche du type de peau par son nom
        $skinType = $skinTypeRepository->findOneBy(['title' => $name]);
        
        // Vérification
        if (!$skinType) {
            throw $this->createNotFoundException('Type de peau non trouvé');
        }
        
        // Requête avec jointure sur skin_type (relation ManyToMany)
        $query = $productRepository->createQueryBuilder('p')
            ->join('p.skin_type', 's') // Jointure sur la relation
            ->where('s.id = :skinTypeId')
            ->setParameter('skinTypeId', $skinType->getId())
            ->orderBy('p.id', 'DESC')
            ->getQuery();

        // Pagination
        $products = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        // Association des images
        $productsWithImages = [];
        foreach ($products as $product) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        // Rendu avec le type de peau sélectionné
        return $this->render('home/catalogue.html.twig', [
            'productsWithImages' => $productsWithImages,
            'products' => $products,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedSkinType' => $skinType, // Type de peau actuellement filtré
        ]);
    }

    /**
     * Page de détail d'un produit
     * Affiche toutes les informations du produit, sa galerie d'images
     * et des produits similaires
     * 
     * @param Product $product L'entité Product (injection automatique via l'ID)
     * @param ProductRepository $productRepository Repository pour les produits similaires
     * @return Response Rendu de la page de détail
     */
    #[Route('/product/{id}/catalog/show', name: 'app_catalog_product_show')]
    public function show(Product $product, ProductRepository $productRepository): Response
    {
        // Tableau pour stocker jusqu'à 4 images du produit
        $productImages = [];
        
        // Boucle pour récupérer les images numérotées de 1 à 4
        for ($i = 1; $i <= 4; $i++) {
            // Pattern pour chaque image: *-{productId}-{imageNumber}-*.*
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-' . $i . '-*.*';
            $files = glob($pattern);
            
            // Si l'image existe, on stocke son chemin, sinon null
            $productImages[$i] = !empty($files) ? '/uploads/products/' . basename($files[0]) : null;
        }
        
        // Préparation des données du produit avec toutes ses informations
        $productWithImages = [
            'product' => $product, // L'entité complète
            'images' => $productImages, // Tableau des 4 images possibles
            'mainImage' => $productImages[1], // Image principale (première image)
            'ingredients' => $product->getIngredients(), // Liste des ingrédients
            'usageAdvice' => $product->getUsageAdvice() // Conseils d'utilisation
        ];
        
        // Récupération de 2 produits similaires de la même catégorie
        $similarProductsData = $productRepository->createQueryBuilder('p')
            ->where('p.category = :category') // Même catégorie
            ->andWhere('p.id != :currentProductId') // Exclure le produit actuel
            ->setParameter('category', $product->getCategory())
            ->setParameter('currentProductId', $product->getId())
            ->setMaxResults(2) // Limiter à 2 produits
            ->orderBy('p.id', 'DESC') // Les plus récents en premier
            ->getQuery()
            ->getResult();
        
        // Association des images aux produits similaires
        $similarProducts = [];
        foreach ($similarProductsData as $similarProduct) {
            // Récupération de l'image principale uniquement
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $similarProduct->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $similarProducts[] = [
                'product' => $similarProduct,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        // Rendu de la page de détail
        return $this->render('home/catalog-show.html.twig', [
            'product' => $product, // Produit actuel
            'productWithImages' => $productWithImages, // Produit avec toutes ses images
            'similarProducts' => $similarProducts // Suggestions de produits similaires
        ]);
    }

    // ========== APPAREILS ==========
    // Section dédiée à l'affichage des appareils de beauté
    // La logique est identique à celle des produits mais appliquée aux appareils

    /**
     * Catalogue général des appareils
     * Affiche tous les appareils avec pagination
     * 
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page catalogue des appareils
     */
    #[Route('/appareil', name: 'app_device')]
    public function device(
        DeviceRepository $deviceRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository, 
        HttpFoundationRequest $request, 
        PaginatorInterface $paginator
    ): Response {
        
        // Requête pour tous les appareils
        $query = $deviceRepository->createQueryBuilder('d')
            ->orderBy('d.id', 'DESC')
            ->getQuery();
            
        // Pagination (16 appareils par page)
        $devices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        // Association des images aux appareils
        $devicesWithImages = [];
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/'; // Dossier spécifique aux appareils
        
        foreach ($devices as $device) {
            // Pattern identique aux produits mais dans le dossier devices/
            $pattern = $uploadDir . '*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        // Rendu du template dédié aux appareils
        return $this->render('home/device.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'devices' => $devices,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
        ]);
    }

    /**
     * Catalogue des appareils filtrés par catégorie
     * 
     * @param int $id ID de la catégorie
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page catalogue filtrée
     */
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
        // Récupération de la catégorie
        $category = $categoryRepository->find($id);
        
        // Vérification
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }
        
        // Requête filtrée par catégorie
        $query = $deviceRepository->createQueryBuilder('d')
            ->where('d.category = :category')
            ->setParameter('category', $category)
            ->orderBy('d.id', 'DESC')
            ->getQuery();

        // Pagination
        $devices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        // Association des images aux appareils
        $devicesWithImages = [];
        foreach ($devices as $device) {
            // Recherche de l'image principale de chaque appareil
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        // Rendu avec la catégorie sélectionnée
        return $this->render('home/device-catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'devices' => $devices,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedCategory' => $category, // Catégorie actuellement filtrée
        ]);
    }

    /**
     * Catalogue des appareils filtrés par marque
     * 
     * @param int $id ID de la marque
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page catalogue filtrée
     */
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
        // Récupération de la marque
        $brand = $brandRepository->find($id);
        
        // Vérification de l'existence
        if (!$brand) {
            throw $this->createNotFoundException('Marque non trouvée');
        }
        
        // Requête filtrée par marque
        $query = $deviceRepository->createQueryBuilder('d')
            ->where('d.brand = :brand')
            ->setParameter('brand', $brand)
            ->orderBy('d.id', 'DESC')
            ->getQuery();

        // Pagination des résultats
        $devices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        // Association des images aux appareils
        $devicesWithImages = [];
        foreach ($devices as $device) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        // Rendu avec la marque sélectionnée
        return $this->render('home/device-catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'devices' => $devices,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedBrand' => $brand, // Marque actuellement filtrée
        ]);
    }

    /**
     * Catalogue des appareils filtrés par type de peau
     * 
     * @param string $name Nom du type de peau
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param HttpFoundationRequest $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page catalogue filtrée
     */
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
        // Recherche du type de peau par son nom
        $skinType = $skinTypeRepository->findOneBy(['title' => $name]);
        
        // Vérification de l'existence
        if (!$skinType) {
            throw $this->createNotFoundException('Type de peau non trouvé');
        }
        
        // Requête avec jointure sur skin_type
        $query = $deviceRepository->createQueryBuilder('d')
            ->join('d.skin_type', 's') // Jointure sur la relation ManyToMany
            ->where('s.id = :skinTypeId')
            ->setParameter('skinTypeId', $skinType->getId())
            ->orderBy('d.id', 'DESC')
            ->getQuery();

        // Pagination
        $devices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );
        
        // Association des images
        $devicesWithImages = [];
        foreach ($devices as $device) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        // Rendu avec le type de peau sélectionné
        return $this->render('home/device-catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'devices' => $devices,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedSkinType' => $skinType, // Type de peau actuellement filtré
        ]);
    }

    /**
     * Page de détail d'un appareil
     * Affiche toutes les informations de l'appareil, sa galerie d'images
     * et des appareils similaires
     * 
     * @param Device $device L'entité Device (injection automatique via l'ID de la route)
     * @param DeviceRepository $deviceRepository Repository pour les appareils similaires
     * @return Response Rendu de la page de détail de l'appareil
     */
    #[Route('/device/{id}/catalog/show', name: 'app_device_catalog_show_home')]
    public function showDevice(Device $device, DeviceRepository $deviceRepository): Response
    {
        // Tableau pour stocker jusqu'à 4 images de l'appareil
        $deviceImages = [];
        
        // Boucle pour récupérer les images numérotées de 1 à 4
        for ($i = 1; $i <= 4; $i++) {
            // Pattern pour chaque image: *-{deviceId}-{imageNumber}-*.*
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-' . $i . '-*.*';
            $files = glob($pattern);
            
            // Si l'image existe, on stocke son chemin, sinon null
            $deviceImages[$i] = !empty($files) ? '/uploads/devices/' . basename($files[0]) : null;
        }
        
        // Préparation des données de l'appareil avec toutes ses informations
        $deviceWithImages = [
            'device' => $device, // L'entité complète
            'images' => $deviceImages, // Tableau des 4 images possibles
            'mainImage' => $deviceImages[1], // Image principale (première image)
            'ingredients' => $device->getIngredients(), // Composants/matériaux de l'appareil
            'usageAdvice' => $device->getUsageAdvice() // Mode d'emploi et conseils
        ];
        
        // Récupération de 2 appareils similaires de la même catégorie
        $similarDevicesData = $deviceRepository->createQueryBuilder('d')
            ->where('d.category = :category') // Même catégorie que l'appareil actuel
            ->andWhere('d.id != :currentDeviceId') // Exclure l'appareil actuel des résultats
            ->setParameter('category', $device->getCategory())
            ->setParameter('currentDeviceId', $device->getId())
            ->setMaxResults(2) // Limiter à 2 appareils similaires
            ->orderBy('d.id', 'DESC') // Les plus récents en premier
            ->getQuery()
            ->getResult();
        
        // Association des images aux appareils similaires
        $similarDevices = [];
        foreach ($similarDevicesData as $similarDevice) {
            // Récupération de l'image principale uniquement pour chaque appareil similaire
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $similarDevice->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $similarDevices[] = [
                'device' => $similarDevice,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        // Rendu de la page de détail de l'appareil
        return $this->render('home/device-catalog-show.html.twig', [
            'device' => $device, // Appareil actuel
            'deviceWithImages' => $deviceWithImages, // Appareil avec toutes ses images
            'similarDevices' => $similarDevices // Suggestions d'appareils similaires
        ]);
    }

    // ========== PAGES STATIQUES ==========
    // Section réservée pour les futures pages statiques
    // Exemples: À propos, Mentions légales, CGV, Contact, FAQ, etc.
    // Cette section est vide pour le moment et peut être complétée selon les besoins


    // ========== MOTEUR DE RECHERCHE ==========
    // Section dédiée aux fonctionnalités de recherche sur le site

    /**
     * Recherche simple dans le catalogue
     * Recherche un terme dans les produits et appareils
     * Affiche les résultats combinés
     * 
     * @param HttpFoundationRequest $request Requête HTTP contenant le terme de recherche
     * @param ProductRepository $productRepository Repository des produits
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page de résultats de recherche
     */
    #[Route('/recherche', name: 'app_search')]
    public function search(
        HttpFoundationRequest $request,
        ProductRepository $productRepository,
        DeviceRepository $deviceRepository,
        CategoryRepository $categoryRepository,
        BrandRepository $brandRepository,
        SkinTypeRepository $skinTypeRepository,
        PaginatorInterface $paginator
    ): Response {
        // Récupération du terme de recherche depuis le paramètre GET 'q'
        // trim() supprime les espaces en début et fin de chaîne
        $searchTerm = trim($request->query->get('q', ''));
        
        // Validation: vérifier que le terme de recherche n'est pas vide
        if (empty($searchTerm)) {
            // Ajouter un message flash d'avertissement qui s'affichera sur la page suivante
            $this->addFlash('warning', 'Veuillez saisir un terme de recherche.');
            // Rediriger vers le catalogue général
            return $this->redirectToRoute('app_catalog');
        }

        // Recherche dans les produits via une méthode personnalisée du repository
        // Cette méthode doit être définie dans ProductRepository
        $products = $productRepository->searchProducts($searchTerm);
        
        // Compter le nombre de résultats produits
        // Méthode personnalisée du repository pour obtenir le compte total
        $productsCount = $productRepository->countSearchResults($searchTerm);

        // Recherche dans les appareils via une méthode personnalisée du repository
        // Cette méthode doit être définie dans DeviceRepository
        $devices = $deviceRepository->searchDevices($searchTerm);
        
        // Compter le nombre de résultats appareils
        $devicesCount = $deviceRepository->countSearchResults($searchTerm);

        // Préparer les données avec images pour les produits trouvés
        $productsWithImages = [];
        foreach ($products as $product) {
            // Recherche de l'image principale du produit
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
            $files = glob($pattern);
            
            // Ajout au tableau avec le produit et son image
            $productsWithImages[] = [
                'product' => $product,
                'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }

        // Préparer les données avec images pour les appareils trouvés
        $devicesWithImages = [];
        foreach ($devices as $device) {
            // Recherche de l'image principale de l'appareil
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            // Ajout au tableau avec l'appareil et son image
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }

        // Calcul du nombre total de résultats (produits + appareils)
        $totalResults = $productsCount + $devicesCount;

        // Rendu de la page de résultats de recherche
        return $this->render('home/search-results.html.twig', [
            'searchTerm' => $searchTerm, // Terme recherché (pour l'afficher dans le formulaire)
            'productsWithImages' => $productsWithImages, // Produits trouvés avec leurs images
            'devicesWithImages' => $devicesWithImages, // Appareils trouvés avec leurs images
            'productsCount' => $productsCount, // Nombre de produits trouvés
            'devicesCount' => $devicesCount, // Nombre d'appareils trouvés
            'totalResults' => $totalResults, // Nombre total de résultats
            'categories' => $categoryRepository->findAll(), // Toutes les catégories (pour filtres)
            'brands' => $brandRepository->findAll(), // Toutes les marques (pour filtres)
            'skinTypes' => $skinTypeRepository->findAll(), // Tous les types de peau (pour filtres)
        ]);
    }

    /**
     * Recherche avancée avec filtres multiples
     * Permet de combiner plusieurs critères de recherche
     * (terme de recherche, marque, catégorie, prix min/max, type de produit)
     * 
     * @param HttpFoundationRequest $request Requête HTTP contenant les critères de recherche
     * @param ProductRepository $productRepository Repository des produits
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param CategoryRepository $categoryRepository Repository des catégories
     * @param BrandRepository $brandRepository Repository des marques
     * @param SkinTypeRepository $skinTypeRepository Repository des types de peau
     * @return Response Rendu de la page de résultats de recherche avancée
     */
    #[Route('/recherche-avancee', name: 'app_advanced_search')]
    public function advancedSearch(
        HttpFoundationRequest $request,
        ProductRepository $productRepository,
        DeviceRepository $deviceRepository,
        CategoryRepository $categoryRepository,
        BrandRepository $brandRepository,
        SkinTypeRepository $skinTypeRepository
    ): Response {
        // Récupération de tous les critères de recherche depuis les paramètres GET
        $criteria = [
            'search' => trim($request->query->get('q', '')), // Terme de recherche textuel
            'brand_id' => $request->query->get('brand_id'), // ID de la marque sélectionnée
            'category_id' => $request->query->get('category_id'), // ID de la catégorie sélectionnée
            'min_price' => $request->query->get('min_price'), // Prix minimum
            'max_price' => $request->query->get('max_price'), // Prix maximum
            'type' => $request->query->get('type', 'all') // Type: 'products', 'devices', ou 'all' (tous)
        ];

        // Initialisation des tableaux de résultats
        $productsWithImages = [];
        $devicesWithImages = [];

        // Si on doit rechercher dans les produits (type 'all' ou 'products')
        if ($criteria['type'] === 'all' || $criteria['type'] === 'products') {
            // Appel de la méthode de recherche avancée dans ProductRepository
            // Cette méthode construit une requête dynamique selon les critères fournis
            $products = $productRepository->advancedSearch($criteria);
            
            // Association des images aux produits trouvés
            foreach ($products as $product) {
                $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
                $files = glob($pattern);
                
                $productsWithImages[] = [
                    'product' => $product,
                    'image' => !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg'
                ];
            }
        }

        // Si on doit rechercher dans les appareils (type 'all' ou 'devices')
        if ($criteria['type'] === 'all' || $criteria['type'] === 'devices') {
            // Appel de la méthode de recherche avancée dans DeviceRepository
            $devices = $deviceRepository->advancedSearch($criteria);
            
            // Association des images aux appareils trouvés
            foreach ($devices as $device) {
                $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
                $files = glob($pattern);
                
                $devicesWithImages[] = [
                    'device' => $device,
                    'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
                ];
            }
        }

        // Rendu de la page de résultats de recherche avancée
        return $this->render('home/advanced-search-results.html.twig', [
            'criteria' => $criteria, // Critères de recherche (pour pré-remplir le formulaire)
            'productsWithImages' => $productsWithImages, // Produits trouvés
            'devicesWithImages' => $devicesWithImages, // Appareils trouvés
            'productsCount' => count($productsWithImages), // Nombre de produits
            'devicesCount' => count($devicesWithImages), // Nombre d'appareils
            'totalResults' => count($productsWithImages) + count($devicesWithImages), // Total
            'categories' => $categoryRepository->findAll(), // Pour les options du formulaire
            'brands' => $brandRepository->findAll(), // Pour les options du formulaire
            'skinTypes' => $skinTypeRepository->findAll(), // Pour les options du formulaire
        ]);
    }

    /**
     * API pour l'autocomplétion de la recherche
     * Fournit des suggestions en temps réel pendant que l'utilisateur tape
     * Retourne les résultats au format JSON pour une utilisation AJAX
     * 
     * @param HttpFoundationRequest $request Requête HTTP contenant le début du terme recherché
     * @param ProductRepository $productRepository Repository des produits
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @return Response Réponse JSON contenant les suggestions
     */
    #[Route('/suggestions', name: 'app_search_suggestions')]
    public function searchSuggestions(
        HttpFoundationRequest $request,
        ProductRepository $productRepository,
        DeviceRepository $deviceRepository
    ): Response {
        // Récupération du terme de recherche partiel
        $query = trim($request->query->get('q', ''));
        
        // Si le terme est trop court (moins de 2 caractères)
        // On ne fait pas de recherche pour éviter trop de résultats
        if (strlen($query) < 2) {
            // Retourner un tableau vide en JSON
            return $this->json([]);
        }

        // Recherche de suggestions dans les titres des produits
        $productSuggestions = $productRepository
            ->createQueryBuilder('p')
            ->select('p.title') // Sélectionner uniquement le champ titre
            ->where('p.title LIKE :query') // Condition LIKE pour recherche partielle
            ->setParameter('query', '%' . $query . '%') // % = wildcard SQL (n'importe quels caractères)
            ->setMaxResults(5) // Limiter à 5 suggestions maximum
            ->getQuery()
            ->getScalarResult(); // Résultat sous forme de tableau simple

        // Recherche de suggestions dans les titres des appareils
        $deviceSuggestions = $deviceRepository
            ->createQueryBuilder('d')
            ->select('d.title') // Sélectionner uniquement le champ titre
            ->where('d.title LIKE :query') // Condition LIKE
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(5) // Limiter à 5 suggestions maximum
            ->getQuery()
            ->getScalarResult(); // Résultat sous forme de tableau simple

        // Fusion des suggestions de produits et d'appareils
        // array_column() extrait uniquement la colonne 'title' de chaque résultat
        $suggestions = array_merge(
            array_column($productSuggestions, 'title'), // Extraire les titres des produits
            array_column($deviceSuggestions, 'title') // Extraire les titres des appareils
        );

        // Retourner les suggestions uniques en JSON
        // array_unique() supprime les doublons si un même titre existe dans les deux tables
        // $this->json() est un raccourci Symfony pour créer une JsonResponse
        return $this->json(array_unique($suggestions));
    }

}