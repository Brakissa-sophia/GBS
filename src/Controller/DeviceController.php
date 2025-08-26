<?php

namespace App\Controller;

use App\Entity\Device;
use App\Entity\AddDeviceHistory;
use App\Form\DeviceForm;
use App\Form\AddDeviceHistoryForm;
use App\Repository\DeviceRepository;
use App\Repository\CategoryRepository;
use App\Repository\BrandRepository;
use App\Repository\SkinTypeRepository;
use App\Repository\AddDeviceHistoryRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/appareil')]
final class DeviceController extends AbstractController
{
    #[Route('/', name: 'app_device_index')]
    public function index(DeviceRepository $deviceRepository): Response
    {
        $devices = $deviceRepository->findAll();
        
        return $this->render('device/index.html.twig', [
            'devices' => $devices
        ]);
    }

    #[Route('/catalogue', name: 'app_device_catalog')]
    public function catalog(
        DeviceRepository $deviceRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository, 
        Request $request, 
        PaginatorInterface $paginator
    ): Response {
        
        // 1. Pagination optimisée
        $query = $deviceRepository->createQueryBuilder('d')
            ->orderBy('d.id', 'DESC')
            ->getQuery();

        $devices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            16
        );

        // 2. Images avec gestion d'erreurs améliorée
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

        // 3. Données pour les filtres
        return $this->render('device/catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'devices' => $devices, // Pour la pagination dans Twig
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
        ]);
    }

    // Filtre par catégorie
    #[Route('/catalogue/category/{id}', name: 'app_device_catalog_category')]
    public function catalogByCategory(
        int $id, 
        DeviceRepository $deviceRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository
    ): Response {
        $category = $categoryRepository->find($id);
        
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }
        
        $devices = $deviceRepository->findBy(['category' => $category], ['id' => 'DESC']);
        
        // Code pour les images
        $devicesWithImages = [];
        foreach ($devices as $device) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('device/catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedCategory' => $category,
        ]);
    }

    // Filtre par marque
    #[Route('/catalogue/brand/{id}', name: 'app_device_catalog_brand')]
    public function catalogByBrand(
        int $id, 
        DeviceRepository $deviceRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository
    ): Response {
        $brand = $brandRepository->find($id);
        
        if (!$brand) {
            throw $this->createNotFoundException('Marque non trouvée');
        }
        
        $devices = $deviceRepository->findBy(['brand' => $brand], ['id' => 'DESC']);
        
        // Code pour les images
        $devicesWithImages = [];
        foreach ($devices as $device) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('device/catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedBrand' => $brand,
        ]);
    }

    // Filtre par type de peau
    #[Route('/catalogue/skintype/{name}', name: 'app_device_catalog_skintype')]
    public function catalogBySkinType(
        string $name, 
        DeviceRepository $deviceRepository, 
        CategoryRepository $categoryRepository, 
        BrandRepository $brandRepository, 
        SkinTypeRepository $skinTypeRepository
    ): Response {
        $skinType = $skinTypeRepository->findOneBy(['title' => $name]);
        
        if (!$skinType) {
            throw $this->createNotFoundException('Type de peau non trouvé');
        }
        
        // Pour une relation ManyToMany, on utilise une requête différente
        $devices = $deviceRepository->createQueryBuilder('d')
            ->join('d.skin_type', 's')
            ->where('s.id = :skinTypeId')
            ->setParameter('skinTypeId', $skinType->getId())
            ->orderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Code pour les images
        $devicesWithImages = [];
        foreach ($devices as $device) {
            $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
            $files = glob($pattern);
            
            $devicesWithImages[] = [
                'device' => $device,
                'image' => !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg'
            ];
        }
        
        return $this->render('device/catalog.html.twig', [
            'devicesWithImages' => $devicesWithImages,
            'categories' => $categoryRepository->findAll(),
            'brands' => $brandRepository->findAll(),
            'skinTypes' => $skinTypeRepository->findAll(),
            'selectedSkinType' => $skinType,
        ]);
    }

    #[Route('/{id}/catalog/show', name: 'app_device_catalog_show')]
    public function show(Device $device, DeviceRepository $deviceRepository): Response
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

    #[Route('/new', name: 'app_device_new')]
public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
{
    $device = new Device();

    $form = $this->createForm(DeviceForm::class, $device);
    
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        
        // ✅ Force des valeurs par défaut si NULL
        if ($device->getIngredients() === null) {
            $device->setIngredients('');
        }
        if ($device->getUsageAdvice() === null) {
            $device->setUsageAdvice('');
        }
        
        // 1. Sauvegarder d'abord l'outil de beauté pour avoir les relations (brand, category)
        $entityManager->persist($device);
        $entityManager->flush();

        // 2. Traiter les 4 images avec le système de nommage personnalisé
        $this->handleImageUploads($form, $device, $slugger);

        // 3. Historique de stock
        $stockHistory = new AddDeviceHistory();
        $stockHistory->setQte($device->getStock());
        $stockHistory->setDevice($device);
        $stockHistory->setCreatedAT(new \DateTimeImmutable());
        $entityManager->persist($stockHistory);
        $entityManager->flush();

        $this->addFlash('success','L\'outil de beauté "' . $device->getTitle() . '" a bien été ajouté avec ses images');

        return $this->redirectToRoute('app_device_index');
    }

    return $this->render('device/new.html.twig', [
        'formDevice' => $form->createView()
    ]);
}

    /**
     * Méthode privée pour gérer les uploads d'images avec unicité garantie
     */
    private function handleImageUploads($form, Device $device, SluggerInterface $slugger): void
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/devices';

        // Mapping des marques vers leurs abréviations
        $brandAbbreviations = [
            'Anua' => 'Anu',
            'MediCube' => 'Medi',
            'Skin1004' => 'Skin',
            'Beauty of Jason' => 'Beau'
        ];

        // Mapping des catégories vers leurs abréviations pour outils de beauté
        $categoryAbbreviations = [
            'appareils électroniques' => 'Elec',
            'accessoires' => 'Acce',
            'outils de nettoyage' => 'Nett',
            'appareils de massage' => 'Mass'
        ];

        // Récupérer les informations de l'outil de beauté
        $brandName = $device->getBrand() ? $device->getBrand()->getTitle() : 'Unknown';
        $categoryName = $device->getCategory() ? strtolower($device->getCategory()->getName()) : 'unknown';
        $deviceTitle = $slugger->slug($device->getTitle())->toString();

        // Obtenir les abréviations
        $brandAbbr = $brandAbbreviations[$brandName] ?? substr($brandName, 0, 4);
        $categoryAbbr = $categoryAbbreviations[$categoryName] ?? substr($categoryName, 0, 4);

        $imageFields = ['image1', 'image2', 'image3', 'image4'];
        $uploadedCount = 0;
        
        foreach ($imageFields as $index => $fieldName) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get($fieldName)->getData();
            
            if ($imageFile) {
                $imageNumber = $index + 1;
                
                // UNICITÉ GARANTIE : ID device + timestamp + uniqid
                $deviceId = $device->getId();
                $timestamp = time();
                $uniqueId = uniqid();
                
                // Format: Marque-Catégorie-NomOutil-IDDevice-NuméroImage-Timestamp-UniqueID.extension
                $newFilename = $brandAbbr . '-' . $categoryAbbr . '-' . $deviceTitle . '-' . $deviceId . '-' . $imageNumber . '-' . $timestamp . '-' . $uniqueId . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($uploadDirectory, $newFilename);
                    $uploadedCount++;
                    
                } catch (FileException $e) {
                    $this->addFlash('warning', 'Erreur lors de l\'upload de l\'image ' . $imageNumber . ': ' . $e->getMessage());
                }
            }
        }
        
        if ($uploadedCount > 0) {
            $this->addFlash('success', $uploadedCount . ' image(s) uploadée(s) avec succès');
        }
    }

    /**
     * Méthode pour récupérer les images d'un outil de beauté
     */
    public function getDeviceImages(int $deviceId): array
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/devices';
        $images = [];
        
        // Chercher les images par numéro (1, 2, 3, 4)
        for ($i = 1; $i <= 4; $i++) {
            $pattern = $uploadDirectory . '/*-' . $deviceId . '-' . $i . '-*.*';
            $files = glob($pattern);
            
            if (!empty($files)) {
                // Prendre le premier fichier trouvé (il ne devrait y en avoir qu'un)
                $images[$i] = basename($files[0]);
            }
        }
        
        return $images;
    }

    #[Route('/{id}', name: 'app_device_show')]
    public function showDevice(Device $device): Response
    {
        // Récupérer les images de l'outil de beauté
        $deviceImages = $this->getDeviceImages($device->getId());
        
        return $this->render('device/show.html.twig', [
            'device' => $device,
            'deviceImages' => $deviceImages
        ]);
    }

    #[Route('/{id}/edit', name: 'app_device_edit')]
    public function edit(Device $device, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $existingImages = $this->getDeviceImages($device->getId());
        
        $form = $this->createForm(DeviceForm::class, $device);
    
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $imageFields = ['image1', 'image2', 'image3', 'image4'];
            $hasNewImages = false;
            
            // Pour chaque champ d'image, supprimer l'ancienne si une nouvelle est uploadée
            foreach ($imageFields as $index => $fieldName) {
                $imageFile = $form->get($fieldName)->getData();
                
                if ($imageFile) {
                    $hasNewImages = true;
                    $imageNumber = $index + 1;
                    
                    // Supprimer l'ancienne image de ce numéro spécifique
                    $this->deleteSpecificDeviceImage($device->getId(), $imageNumber);
                }
            }
            
            if ($hasNewImages) {
                $this->handleImageUploads($form, $device, $slugger);
                $this->addFlash('success', 'Les images ont été mises à jour avec succès');
            }
            
            $entityManager->flush();
            $this->addFlash('success','L\'outil de beauté "' . $device->getTitle() . '" a bien été modifié');
            return $this->redirectToRoute('app_device_index');
        }

        return $this->render('device/edit.html.twig', [
            'device' => $device,
            'formDevice' => $form,
            'existingImages' => $existingImages
        ]);
    }

    /**
     * Supprimer une image spécifique par numéro
     */
    private function deleteSpecificDeviceImage(int $deviceId, int $imageNumber): bool
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/devices';
        $pattern = $uploadDirectory . '/*-' . $deviceId . '-' . $imageNumber . '-*.*';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                return true;
            }
        }
        
        return false;
    }

    #[Route('/{id}/delete', name: 'app_device_delete')]
public function delete(Device $device, EntityManagerInterface $entityManager): Response
{
    $deviceTitle = $device->getTitle();
    $deviceId = $device->getId();
    
    // 1. Supprimer d'abord TOUT l'historique lié à cet appareil
    $historyEntries = $device->getAddDeviceHistories();
    $deletedHistoryCount = 0;
    foreach ($historyEntries as $history) {
        $entityManager->remove($history);
        $deletedHistoryCount++;
    }
    
    // 2. Supprimer les images associées à l'outil de beauté AVANT de supprimer l'outil
    $deletedImagesCount = $this->deleteDeviceImages($deviceId);
    
    // 3. Supprimer l'outil de beauté de la base de données
    $entityManager->remove($device);
    $entityManager->flush();
    
    // 4. Message de confirmation avec détails
    $message = 'L\'outil de beauté "' . $deviceTitle . '" a bien été supprimé';
    
    if ($deletedHistoryCount > 0) {
        $message .= ' avec ' . $deletedHistoryCount . ' entrée(s) d\'historique';
    }
    
    if ($deletedImagesCount > 0) {
        $message .= ' et ' . $deletedImagesCount . ' image(s)';
    }
    
    $this->addFlash('success', $message);
    
    return $this->redirectToRoute('app_device_index');
}

/**
 * Méthode pour supprimer les images d'un outil de beauté
 */
private function deleteDeviceImages(int $deviceId): int
{
    $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/devices';
    
    // Chercher tous les fichiers qui contiennent l'ID de l'outil de beauté
    $pattern = $uploadDirectory . '/*-' . $deviceId . '-*.*';
    $files = glob($pattern);
    $deletedCount = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $deletedCount++;
        }
    }
    
    return $deletedCount;
}

    #[Route('/{id}/stock/add', name: 'app_device_stock_add')]
    public function addStock($id, EntityManagerInterface $entityManager, Request $request, DeviceRepository $deviceRepository): Response
    {
        $device = $deviceRepository->find($id);
        
        $addStock = new AddDeviceHistory();
        $form = $this->createForm(AddDeviceHistoryForm::class, $addStock);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            if($addStock->getQte() > 0){
                // Mettre à jour le stock
                $newQte = $device->getStock() + $addStock->getQte();
                $device->setStock($newQte);

                $addStock->setCreatedAt(new \DateTimeImmutable());
                $addStock->setDevice($device);
                $entityManager->persist($addStock);
                $entityManager->flush();

                $this->addFlash('success', 'Le stock de l\'outil de beauté "' . $device->getTitle() . '" a bien été modifié');
                return $this->redirectToRoute('app_device_index');
            } else {
                $this->addFlash('danger', 'Le stock ne doit pas être inférieur à 0');
                return $this->redirectToRoute('app_device_stock_add', ['id' => $device->getId()]);
            }
        }

        return $this->render('device/addStock.html.twig', [
            'form' => $form->createView(),
            'device' => $device  
        ]);
    }

    #[Route('/{id}/stock/history', name: 'app_device_stock_add_history')]
    public function deviceAddHistory($id, DeviceRepository $deviceRepository, AddDeviceHistoryRepository $addDeviceHistoryRepository): Response
    {
        $device = $deviceRepository->find($id);
        $deviceAddedHistory = $addDeviceHistoryRepository->findBy(
            ['device' => $device],
            ['id' => 'DESC']
        );

        return $this->render('device/addedStockHistoryShow.html.twig', [
            'devicesAdded' => $deviceAddedHistory,
            'device' => $device,
        ]);
    }
}