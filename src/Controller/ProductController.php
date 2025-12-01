<?php

namespace App\Controller;

use App\Entity\AddProductHistory;
use App\Entity\Product;
use App\Form\AddProductHistoryForm;
use App\Form\ProductForm;
use App\Repository\AddProductHistoryRepository;
use App\Repository\ProductRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/produit')]
final class ProductController extends AbstractController
{
    #[Route('/afficher', name:'app_product_index')]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAll();

        return $this->render('product/index.html.twig', [
            'products' => $products
        ]);
    }

    #[Route('/ajouter', name:'app_product_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $product = new Product();
       
        $form = $this->createForm(ProductForm::class, $product);
        $form->handleRequest($request); 

        if ($form->isSubmitted() && $form->isValid()) {
            
            $entityManager->persist($product);
            $entityManager->flush();

            $this->handleImageUploads($form, $product, $slugger);

            $stockHistory = new AddProductHistory();
            $stockHistory->setQte($product->getStock());
            $stockHistory->setProduct($product);
            $stockHistory->setCreatedAt(new \DateTimeImmutable());
            
            $entityManager->persist($stockHistory);
            $entityManager->flush();

            flash()->success('Le produit "' . $product->getTitle() . '" a bien été ajouté avec ses images');

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/new.html.twig', [
            'formProduct' => $form->createView()
        ]);
    }

    private function handleImageUploads($form, Product $product, SluggerInterface $slugger): void
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/products';

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
            'démaquillant' => 'Déma',
            'nettoyant' => 'Nett',
            'exfoliant' => 'Exfo',
            'sérum' => 'Séru',
            'essence' => 'Esse',
            'contour des yeux' => 'Cont',
            'crème hydratante' => 'Crèm',
            'protection solaire' => 'Prot',
            'masque en tissu' => 'Masq',
            'masque' => 'Masq',
            'tonique' => 'Toni'
        ];

        $brandName = $product->getBrand() ? $product->getBrand()->getTitle() : 'Unknown';
        $categoryName = $product->getCategory() ? strtolower($product->getCategory()->getName()) : 'unknown';
        $productTitle = $slugger->slug($product->getTitle())->toString();

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
                
                $productId = $product->getId();
                $timestamp = time();
                $uniqueId = uniqid();
                
                $newFilename = $brandAbbr . '-' . 
                               $categoryAbbr . '-' . 
                               $productTitle . '-' . 
                               $productId . '-' . 
                               $imageNumber . '-' . 
                               $timestamp . '-' . 
                               $uniqueId . '.' . 
                               $safeExtension;

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

    public function getProductImages(int $productId): array
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        $images = [];
        
        for ($i = 1; $i <= 4; $i++) {
            $pattern = $uploadDirectory . '/*-' . $productId . '-' . $i . '-*.*';
            $files = glob($pattern);
            
            if (!empty($files)) {
                $images[$i] = basename($files[0]);
            }
        }
        
        return $images;
    }

    public function countProductImages(int $productId): int
    {
        return count($this->getProductImages($productId));
    }

    public function getMainProductImage(int $productId): ?string
    {
        $images = $this->getProductImages($productId);
        return $images[1] ?? null;
    }

    private function deleteProductImages(int $productId): int
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        $pattern = $uploadDirectory . '/*-' . $productId . '-*.*';
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

    #[Route('/fiche/{id}', name:'app_product_show')]
    public function show(Product $product): Response
    {
        $productImages = $this->getProductImages($product->getId());
        
        return $this->render('product/show.html.twig', [
            'product' => $product,
            'productImages' => $productImages
        ]);
    }

    #[Route('/modifier/{id}', name:'app_product_edit')]
    public function edit(Product $product, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $existingImages = $this->getProductImages($product->getId());
        $form = $this->createForm(ProductForm::class, $product);
        
        $form->get('ingredients')->setData($product->getIngredients());
        $form->get('usageAdvice')->setData($product->getUsageAdvice());
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $product->setIngredients($form->get('ingredients')->getData());
            $product->setUsageAdvice($form->get('usageAdvice')->getData());
            
            $imageFields = ['image1', 'image2', 'image3', 'image4'];
            $hasNewImages = false;
            
            foreach ($imageFields as $index => $fieldName) {
                $imageFile = $form->get($fieldName)->getData();
                
                if ($imageFile) {
                    $hasNewImages = true;
                    $imageNumber = $index + 1;
                    $this->deleteSpecificProductImage($product->getId(), $imageNumber);
                }
            }
            
            if ($hasNewImages) {
                $this->handleImageUploads($form, $product, $slugger);
                flash()->success('Les images ont été mises à jour avec succès');
            }
            
            $entityManager->flush();
            flash()->success('Le produit "' . $product->getTitle() . '" a bien été modifié');
            
            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'formProduct' => $form,
            'existingImages' => $existingImages
        ]);
    }

    private function deleteSpecificProductImage(int $productId, int $imageNumber): bool
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        $pattern = $uploadDirectory . '/*-' . $productId . '-' . $imageNumber . '-*.*';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                return true;
            }
        }
        
        return false;
    }

    #[Route('/supprimer/{id}', name:'app_product_delete', methods: ['POST'])]
    public function delete(Product $product, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_product_' . $product->getId(), $request->request->get('_token'))) {
            flash()->error('Token CSRF invalide. Suppression annulée.');
            return $this->redirectToRoute('app_product_index');
        }

        $productTitle = $product->getTitle();
        $productId = $product->getId();
        
        $deletedImagesCount = $this->deleteProductImages($productId);
        
        $entityManager->remove($product);
        $entityManager->flush();
        
        if ($deletedImagesCount > 0) {
            flash()->success('Le produit "' . $productTitle . '" et ses ' . $deletedImagesCount . ' image(s) ont bien été supprimés');
        } else {
            flash()->success('Le produit "' . $productTitle . '" a bien été supprimé');
        }
        
        return $this->redirectToRoute('app_product_index');
    }

    #[Route('/add/product/{id}/stock', name:'app_product_stock_add')]
    public function addStock($id, EntityManagerInterface $entityManager, Request $request, ProductRepository $productRepository): Response
    {
        $product = $productRepository->find($id);
        $addStock = new AddProductHistory();
        
        $form = $this->createForm(AddProductHistoryForm::class,$addStock);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            if($addStock->getQte()>0){
                $newQte = $product->getStock() + $addStock->getQte();
                $product->setStock($newQte);

                $addStock->setCreatedAt (new \DateTimeImmutable());
                $addStock->setProduct(($product));
                
                $entityManager->persist($addStock);
                $entityManager->flush();

                flash()->success('Le stock du produit "' . $product->getTitle() . '" a bien été modifié');
                
                return $this->redirectToRoute('app_product_index');
            } else {
                flash()->error('Le stock ne doit pas être inférieur à 0');
                return $this->redirectToRoute('app_product_stock_add', ['id' => $product->getId()]);
            }
        }

        return $this->render('product/addStock.html.twig', [
            'form' => $form->createView(),
            'product' => $product
        ]);
    }

    #[Route('/add/product/{id}/stock/history', name:'app_product_stock_add_history')]
    public function productAddHistory($id, ProductRepository $productRepository, AddProductHistoryRepository $addProductHistoryRepository): Response
    {
        $product = $productRepository->find($id);
        
        $productAddedHistory= $addProductHistoryRepository->findBy(
            ['product' => $product],
            ['id' => 'DESC']
        );

        return $this->render('product/addedStockHistoryShow.html.twig', [
            'productsAdded' => $productAddedHistory,
            'product' => $product,
        ]);
    }
}