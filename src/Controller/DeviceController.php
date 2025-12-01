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

#[Route('/admin/appareil')]
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

    #[Route('/new', name: 'app_device_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $device = new Device();

        $form = $this->createForm(DeviceForm::class, $device);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            if ($device->getIngredients() === null) {
                $device->setIngredients('');
            }
            if ($device->getUsageAdvice() === null) {
                $device->setUsageAdvice('');
            }
            
            $entityManager->persist($device);
            $entityManager->flush();

            $this->handleImageUploads($form, $device, $slugger);

            $stockHistory = new AddDeviceHistory();
            $stockHistory->setQte($device->getStock());
            $stockHistory->setDevice($device);
            $stockHistory->setCreatedAT(new \DateTimeImmutable());
            $entityManager->persist($stockHistory);
            $entityManager->flush();

            flash()->success('L\'outil de beauté "' . $device->getTitle() . '" a bien été ajouté avec ses images');

            return $this->redirectToRoute('app_device_index');
        }

        return $this->render('device/new.html.twig', [
            'formDevice' => $form->createView()
        ]);
    }

    private function handleImageUploads($form, Device $device, SluggerInterface $slugger): void
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/devices';

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/webp',
            'image/gif'
        ];
        
        $maxFileSize = 5 * 1024 * 1024;

        $brandAbbreviations = [
            'Anua' => 'Anu',
            'MediCube' => 'Medi',
            'Skin1004' => 'Skin',
            'Beauty of Jason' => 'Beau'
        ];

        $categoryAbbreviations = [
            'appareils électroniques' => 'Elec',
            'accessoires' => 'Acce',
            'outils de nettoyage' => 'Nett',
            'appareils de massage' => 'Mass'
        ];

        $brandName = $device->getBrand() ? $device->getBrand()->getTitle() : 'Unknown';
        $categoryName = $device->getCategory() ? strtolower($device->getCategory()->getName()) : 'unknown';
        $deviceTitle = $slugger->slug($device->getTitle())->toString();

        $brandAbbr = $brandAbbreviations[$brandName] ?? substr($brandName, 0, 4);
        $categoryAbbr = $categoryAbbreviations[$categoryName] ?? substr($categoryName, 0, 4);

        $imageFields = ['image1', 'image2', 'image3', 'image4'];
        $uploadedCount = 0;
        
        foreach ($imageFields as $index => $fieldName) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get($fieldName)->getData();
            
            if ($imageFile) {
                $imageNumber = $index + 1;
                
                if ($imageFile->getSize() > $maxFileSize) {
                    flash()->warning('Image ' . $imageNumber . ' : Fichier trop volumineux (max 5 Mo)');
                    continue;
                }
                
                $mimeType = $imageFile->getMimeType();
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    flash()->warning('Image ' . $imageNumber . ' : Type de fichier non autorisé. Seules les images sont acceptées.');
                    continue;
                }
                
                $originalExtension = strtolower($imageFile->getClientOriginalExtension());
                if (!in_array($originalExtension, $allowedExtensions)) {
                    flash()->warning('Image ' . $imageNumber . ' : Extension non autorisée. Extensions acceptées : jpg, jpeg, png, webp, gif');
                    continue;
                }
                
                $safeExtension = $originalExtension;
                
                $imageInfo = @getimagesize($imageFile->getPathname());
                if ($imageInfo === false) {
                    flash()->warning('Image ' . $imageNumber . ' : Le fichier n\'est pas une image valide.');
                    continue;
                }
                
                $deviceId = $device->getId();
                $timestamp = time();
                $uniqueId = uniqid();
                
                $newFilename = $brandAbbr . '-' . $categoryAbbr . '-' . $deviceTitle . '-' . $deviceId . '-' . $imageNumber . '-' . $timestamp . '-' . $uniqueId . '.' . $safeExtension;

                try {
                    $imageFile->move($uploadDirectory, $newFilename);
                    $uploadedCount++;
                    
                } catch (FileException $e) {
                    flash()->warning('Erreur lors de l\'upload de l\'image ' . $imageNumber . ': ' . $e->getMessage());
                }
            }
        }
        
        if ($uploadedCount > 0) {
            flash()->success($uploadedCount . ' image(s) uploadée(s) avec succès');
        }
    }

    public function getDeviceImages(int $deviceId): array
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/devices';
        $images = [];
        
        for ($i = 1; $i <= 4; $i++) {
            $pattern = $uploadDirectory . '/*-' . $deviceId . '-' . $i . '-*.*';
            $files = glob($pattern);
            
            if (!empty($files)) {
                $images[$i] = basename($files[0]);
            }
        }
        
        return $images;
    }

    #[Route('/{id}', name: 'app_device_show')]
    public function showDevice(Device $device): Response
    {
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
            
            foreach ($imageFields as $index => $fieldName) {
                $imageFile = $form->get($fieldName)->getData();
                
                if ($imageFile) {
                    $hasNewImages = true;
                    $imageNumber = $index + 1;
                    $this->deleteSpecificDeviceImage($device->getId(), $imageNumber);
                }
            }
            
            if ($hasNewImages) {
                $this->handleImageUploads($form, $device, $slugger);
                flash()->success('Les images ont été mises à jour avec succès');
            }
            
            $entityManager->flush();
            flash()->success('L\'outil de beauté "' . $device->getTitle() . '" a bien été modifié');
            return $this->redirectToRoute('app_device_index');
        }

        return $this->render('device/edit.html.twig', [
            'device' => $device,
            'formDevice' => $form,
            'existingImages' => $existingImages
        ]);
    }

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

    #[Route('/{id}/delete', name: 'app_device_delete', methods: ['POST'])]
    public function delete(Device $device, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_device_' . $device->getId(), $request->request->get('_token'))) {
            flash()->error('Token CSRF invalide. Suppression annulée.');
            return $this->redirectToRoute('app_device_index');
        }

        $deviceTitle = $device->getTitle();
        $deviceId = $device->getId();
        
        $historyEntries = $device->getAddDeviceHistories();
        $deletedHistoryCount = 0;
        foreach ($historyEntries as $history) {
            $entityManager->remove($history);
            $deletedHistoryCount++;
        }
        
        $deletedImagesCount = $this->deleteDeviceImages($deviceId);
        
        $entityManager->remove($device);
        $entityManager->flush();

        $message = 'L\'outil de beauté "' . $deviceTitle . '" a bien été supprimé';
        
        if ($deletedHistoryCount > 0) {
            $message .= ' avec ' . $deletedHistoryCount . ' entrée(s) d\'historique';
        }
        
        if ($deletedImagesCount > 0) {
            $message .= ' et ' . $deletedImagesCount . ' image(s)';
        }
        
        flash()->success($message);
        
        return $this->redirectToRoute('app_device_index');
    }

    private function deleteDeviceImages(int $deviceId): int
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/devices';
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
                $newQte = $device->getStock() + $addStock->getQte();
                $device->setStock($newQte);

                $addStock->setCreatedAt(new \DateTimeImmutable());
                $addStock->setDevice($device);
                $entityManager->persist($addStock);
                $entityManager->flush();

                flash()->success('Le stock de l\'outil de beauté "' . $device->getTitle() . '" a bien été modifié');
                return $this->redirectToRoute('app_device_index');
            } else {
                flash()->error('Le stock ne doit pas être inférieur à 0');
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