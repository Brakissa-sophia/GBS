<?php

namespace App\Controller; // Déclaration du namespace pour organiser les classes du projet

use App\Entity\Brand; // Import de l'entité Brand (représente une marque en base de données)
use App\Form\BrandForm; // Import du formulaire pour créer/éditer une marque
use App\Repository\BrandRepository; // Import du repository pour récupérer les marques depuis la base de données
use Doctrine\ORM\EntityManagerInterface; // Import de l'EntityManager pour gérer les opérations en base de données (persist, flush, remove)
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; // Import du contrôleur de base Symfony
use Symfony\Component\HttpFoundation\Request; // Import de l'objet Request pour gérer les requêtes HTTP
use Symfony\Component\HttpFoundation\Response; // Import de l'objet Response pour gérer les réponses HTTP
use Symfony\Component\Routing\Attribute\Route; // Import de l'attribut Route pour définir les routes via annotations PHP 8

#[Route('/marque')] // Préfixe de route : toutes les routes de ce contrôleur commenceront par "/marque"
final class BrandController extends AbstractController // Déclaration de la classe contrôleur (final = non héritable)
{
    #[Route(name: 'app_brand_index', methods: ['GET'])] // Route pour afficher la liste (GET uniquement)
    public function index(BrandRepository $brandRepository): Response // Méthode pour lister toutes les marques
    {
        return $this->render('brand/index.html.twig', [ // Rendu du template Twig
            'brands' => $brandRepository->findAll(), // Récupère toutes les marques et les passe au template
        ]);
    }

    #[Route('/new', name: 'app_brand_new', methods: ['GET', 'POST'])] // Route pour créer une nouvelle marque (GET = affichage form, POST = soumission)
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $brand = new Brand(); // Crée une nouvelle instance vide de Brand
        $form = $this->createForm(BrandForm::class, $brand); // Crée le formulaire associé à l'entité Brand
        $form->handleRequest($request); // Traite la requête et remplit le formulaire avec les données soumises

        if ($form->isSubmitted() && $form->isValid()) { // Vérifie si le formulaire a été soumis ET qu'il est valide
            $entityManager->persist($brand); // Prépare l'entité pour être sauvegardée (la met en file d'attente)
            $entityManager->flush(); // Exécute réellement l'insertion en base de données

            return $this->redirectToRoute('app_brand_index', [], Response::HTTP_SEE_OTHER); // Redirige vers la liste des marques avec code HTTP 303
        }

        return $this->render('brand/new.html.twig', [ // Si formulaire non soumis/invalide, affiche le template
            'brand' => $brand,
            'form' => $form, // Passe le formulaire au template
        ]);
    }

    #[Route('/{id}', name: 'app_brand_show', methods: ['GET'])] // Route pour afficher une marque spécifique (paramètre {id} dans l'URL)
    public function show(Brand $brand): Response // ParamConverter : Symfony récupère automatiquement l'entité Brand depuis l'ID
    {
        return $this->render('brand/show.html.twig', [
            'brand' => $brand, // Passe la marque au template
        ]);
    }

    #[Route('/{id}/edit', name: 'app_brand_edit', methods: ['GET', 'POST'])] // Route pour éditer une marque existante
    public function edit(Request $request, Brand $brand, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BrandForm::class, $brand); // Crée le formulaire pré-rempli avec les données de la marque existante
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush(); // Met à jour l'entité en base (pas de persist car l'entité existe déjà et est gérée par Doctrine)

            return $this->redirectToRoute('app_brand_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('brand/edit.html.twig', [
            'brand' => $brand,
            'form' => $form,
        ]);
    }

  #[Route('/{id}', name: 'app_brand_delete', methods: ['POST'])]
public function delete(Request $request, Brand $brand, EntityManagerInterface $entityManager): Response
{
    if ($this->isCsrfTokenValid('delete'.$brand->getId(), $request->getPayload()->getString('_token'))) {
        $entityManager->remove($brand);
        $entityManager->flush();
    }

    return $this->redirectToRoute('app_brand_index', [], Response::HTTP_SEE_OTHER);
}
}