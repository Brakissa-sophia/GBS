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
        dump($product);

        $form = $this->createForm(ProductForm::class, $product);
        
        $form->handleRequest($request);
        dump($form);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // 1. Sauvegarder d'abord le produit pour avoir les relations (brand, category)
            $entityManager->persist($product);
            $entityManager->flush();

            // 2. Traiter les 4 images avec le système de nommage personnalisé
            $this->handleImageUploads($form, $product, $slugger);

            // 3. Historique de stock (votre code existant)
            $stockHistory = new AddProductHistory();
            $stockHistory->setQte($product->getStock());
            $stockHistory->setProduct($product);
            $stockHistory->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($stockHistory);
            $entityManager->flush();

            $this->addFlash('success','Le produit "' . $product->getTitle() . '" a bien été ajouté avec ses images');

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/new.html.twig', [
            'formProduct' => $form->createView()
        ]);
    }

    /**
     * Méthode privée pour gérer les uploads d'images avec unicité garantie
     */
    private function handleImageUploads($form, Product $product, SluggerInterface $slugger): void
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/products';

        // Mapping des marques vers leurs abréviations
        $brandAbbreviations = [
            'Anua' => 'Anu',
            'MediCube' => 'Medi',
            'Skin1004' => 'Skin',
            'Beauty of Jason' => 'Beau'
        ];

        // Mapping des catégories vers leurs abréviations
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

        // Récupérer les informations du produit
        $brandName = $product->getBrand() ? $product->getBrand()->getTitle() : 'Unknown';
        $categoryName = $product->getCategory() ? strtolower($product->getCategory()->getName()) : 'unknown';
        $productTitle = $slugger->slug($product->getTitle())->toString();

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
                
                // UNICITÉ GARANTIE : ID produit + timestamp + uniqid
                $productId = $product->getId();
                $timestamp = time();
                $uniqueId = uniqid();
                
                // Format: Marque-Catégorie-NomProduit-IDProduit-NuméroImage-Timestamp-UniqueID.extension
                // Exemple: Anu-Déma-Nettoyant-Doux-123-1-1691234567-64f8a2b3c.jpg
                $newFilename = $brandAbbr . '-' . $categoryAbbr . '-' . $productTitle . '-' . $productId . '-' . $imageNumber . '-' . $timestamp . '-' . $uniqueId . '.' . $imageFile->guessExtension();

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
     * Méthode pour récupérer les images d'un produit
     */
    public function getProductImages(int $productId): array
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        $images = [];
        
        // Chercher les images par numéro (1, 2, 3, 4)
        for ($i = 1; $i <= 4; $i++) {
            $pattern = $uploadDirectory . '/*-' . $productId . '-' . $i . '-*.*';
            $files = glob($pattern);
            
            if (!empty($files)) {
                // Prendre le premier fichier trouvé (il ne devrait y en avoir qu'un)
                $images[$i] = basename($files[0]);
            }
        }
        
        return $images;
    }

    /**
     * Compte le nombre d'images d'un produit
     */
    public function countProductImages(int $productId): int
    {
        return count($this->getProductImages($productId));
    }

    /**
     * Récupère l'image principale (image1) d'un produit
     */
    public function getMainProductImage(int $productId): ?string
    {
        $images = $this->getProductImages($productId);
        return $images[1] ?? null;
    }

    /**
     * Méthode pour supprimer les images d'un produit
     */
    private function deleteProductImages(int $productId): int
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        
        // Chercher tous les fichiers qui contiennent l'ID du produit
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
        // Récupérer les images du produit
        $productImages = $this->getProductImages($product->getId());
        
        return $this->render('product/show.html.twig', [
            'product' => $product,
            'productImages' => $productImages // Ajouter les images
        ]);
    }

    #[Route('/modifier/{id}', name:'app_product_edit')]
    public function edit(Product $product, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $existingImages = $this->getProductImages($product->getId());
        
        $form = $this->createForm(ProductForm::class, $product);
        
        // 🆕 PRÉ-REMPLIR LES CHAMPS AVEC LES DONNÉES EXISTANTES
        $form->get('ingredients')->setData($product->getIngredients());
        $form->get('usageAdvice')->setData($product->getUsageAdvice());
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // 🆕 RÉCUPÉRER ET SAUVEGARDER LES DONNÉES DES CHAMPS NON MAPPÉS
            $product->setIngredients($form->get('ingredients')->getData());
            $product->setUsageAdvice($form->get('usageAdvice')->getData());
            
            $imageFields = ['image1', 'image2', 'image3', 'image4'];
            $hasNewImages = false;
            
            // 🔥 Pour chaque champ d'image, supprimer l'ancienne si une nouvelle est uploadée
            foreach ($imageFields as $index => $fieldName) {
                $imageFile = $form->get($fieldName)->getData();
                
                if ($imageFile) {
                    $hasNewImages = true;
                    $imageNumber = $index + 1;
                    
                    // Supprimer l'ancienne image de ce numéro spécifique
                    $this->deleteSpecificProductImage($product->getId(), $imageNumber);
                }
            }
            
            if ($hasNewImages) {
                $this->handleImageUploads($form, $product, $slugger);
                $this->addFlash('success', 'Les images ont été mises à jour avec succès');
            }
            
            $entityManager->flush();
            $this->addFlash('success','Le produit "' . $product->getTitle() . '" a bien été modifié');
            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'formProduct' => $form,
            'existingImages' => $existingImages
        ]);
    }

    /**
     * Supprimer une image spécifique par numéro
     */
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

    #[Route('/supprimer/{id}', name:'app_product_delete')]
    public function delete(Product $product, EntityManagerInterface $entityManager): Response
    {
        $productTitle = $product->getTitle();
        $productId = $product->getId();
        
        // Supprimer les images associées au produit AVANT de supprimer le produit
        $deletedImagesCount = $this->deleteProductImages($productId);
        
        // Supprimer le produit de la base de données
        $entityManager->remove($product);
        $entityManager->flush();
        
        if ($deletedImagesCount > 0) {
            $this->addFlash('success', 'Le produit "' . $productTitle . '" et ses ' . $deletedImagesCount . ' image(s) ont bien été supprimés');
        } else {
            $this->addFlash('success', 'Le produit "' . $productTitle . '" a bien été supprimé');
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
                // Mettre à jour le stock
                $newQte = $product->getStock() + $addStock->getQte();
                $product->setStock($newQte);

                $addStock->setCreatedAt (new \DateTimeImmutable());
                $addStock->setProduct(($product));
                $entityManager->persist($addStock);
                $entityManager->flush();

                $this->addFlash('success', 'Le stock du produit "' . $product->getTitle() . '" a bien été modifié');
                return $this->redirectToRoute('app_product_index');
            } else {
                $this->addFlash('danger', 'Le stock ne doit pas être inférieur à 0');
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