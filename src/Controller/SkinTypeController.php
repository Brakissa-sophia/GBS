<?php

// Déclaration du namespace du contrôleur
namespace App\Controller;

// Importation de l'entité type de peau
use App\Entity\SkinType;

// Importation du formulaire
use App\Form\SkinTypeForm;

// Importation du repository
use App\Repository\SkinTypeRepository;

// Importation de Doctrine
use Doctrine\ORM\EntityManagerInterface;

// Importation des classes Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de gestion des types de peau
 * CRUD complet pour la gestion des types de peau (peau grasse, sèche, mixte, sensible, etc.)
 * 
 * Fonctionnalités:
 * - Liste de tous les types de peau
 * - Création d'un nouveau type
 * - Affichage des détails d'un type
 * - Modification d'un type existant
 * - Suppression d'un type
 * 
 * Les types de peau sont utilisés pour:
 * - Filtrer les produits adaptés
 * - Filtrer les appareils compatibles
 * - Appliquer des codes promo spécifiques
 * 
 * Toutes les routes sont préfixées par '/type_de_peau'
 */
#[Route('/type_de_peau')]
final class SkinTypeController extends AbstractController
{
    /**
     * Liste de tous les types de peau
     * Affiche tous les types enregistrés en base de données
     */
    #[Route(name: 'app_skin_type_index', methods: ['GET'])]
    public function index(SkinTypeRepository $skinTypeRepository): Response
    {
        // Rendu de la page de liste avec tous les types de peau
        return $this->render('skin_type/index.html.twig', [
            'skin_types' => $skinTypeRepository->findAll(),
        ]);
    }

    /**
     * Création d'un nouveau type de peau
     * Affiche le formulaire et traite la soumission
     */
    #[Route('/new', name: 'app_skin_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Créer une nouvelle entité SkinType vide
        $skinType = new SkinType();
        
        // Créer le formulaire
        $form = $this->createForm(SkinTypeForm::class, $skinType);
        
        // Traiter la soumission du formulaire
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Persister le nouveau type de peau en base de données
            $entityManager->persist($skinType);
            $entityManager->flush();

            // Redirection vers la liste avec code HTTP 303 (See Other)
            // HTTP_SEE_OTHER indique que la ressource a été créée et qu'il faut consulter une autre URL
            return $this->redirectToRoute('app_skin_type_index', [], Response::HTTP_SEE_OTHER);
        }

        // Affichage du formulaire de création
        return $this->render('skin_type/new.html.twig', [
            'skin_type' => $skinType,
            'form' => $form,
        ]);
    }

    /**
     * Affichage des détails d'un type de peau
     * Montre toutes les informations du type sélectionné
     */
    #[Route('/{id}', name: 'app_skin_type_show', methods: ['GET'])]
    public function show(SkinType $skinType): Response
    {
        // $skinType est automatiquement injecté par Symfony grâce au ParamConverter
        // Symfony récupère l'ID depuis l'URL et charge l'entité correspondante
        
        // Rendu de la page de détails
        return $this->render('skin_type/show.html.twig', [
            'skin_type' => $skinType,
        ]);
    }

    /**
     * Modification d'un type de peau existant
     * Affiche le formulaire pré-rempli et traite la modification
     */
    #[Route('/{id}/edit', name: 'app_skin_type_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SkinType $skinType, EntityManagerInterface $entityManager): Response
    {
        // Créer le formulaire avec l'entité existante (pré-remplissage automatique)
        $form = $this->createForm(SkinTypeForm::class, $skinType);
        
        // Traiter la soumission
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Les modifications ont été automatiquement appliquées à l'entité
            // Il suffit de sauvegarder en base de données
            $entityManager->flush();

            // Redirection vers la liste avec code HTTP 303
            return $this->redirectToRoute('app_skin_type_index', [], Response::HTTP_SEE_OTHER);
        }

        // Affichage du formulaire de modification
        return $this->render('skin_type/edit.html.twig', [
            'skin_type' => $skinType,
            'form' => $form,
        ]);
    }

    /**
     * Suppression d'un type de peau
     * Méthode POST uniquement pour éviter la suppression accidentelle
     * Protection par token CSRF
     */
    #[Route('/{id}', name: 'app_skin_type_delete', methods: ['POST'])]
    public function delete(Request $request, SkinType $skinType, EntityManagerInterface $entityManager): Response
    {
        // Vérification du token CSRF pour la sécurité
        // $request->getPayload() récupère les données de la requête (POST)
        // getString('_token') extrait la valeur du token
        if ($this->isCsrfTokenValid('delete'.$skinType->getId(), $request->getPayload()->getString('_token'))) {
            // Token valide: procéder à la suppression
            
            // Supprimer le type de peau
            // ATTENTION: Si des produits ou appareils utilisent ce type,
            // il faut configurer onDelete dans l'entité (CASCADE, SET NULL, ou RESTRICT)
            $entityManager->remove($skinType);
            $entityManager->flush();
        }
        // Si le token est invalide, la suppression n'a pas lieu
        // Aucun message d'erreur n'est affiché (redirection silencieuse)

        // Redirection vers la liste dans tous les cas
        return $this->redirectToRoute('app_skin_type_index', [], Response::HTTP_SEE_OTHER);
    }
}