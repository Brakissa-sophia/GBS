<?php

// Déclaration du namespace du contrôleur
namespace App\Controller;

// Importation de l'entité Newsletter qui représente un abonné à la newsletter
use App\Entity\Newsletter;

// Importation du repository pour accéder aux données de la newsletter
use App\Repository\NewsletterRepository;

// Importation de l'EntityManager pour gérer les opérations de base de données
use Doctrine\ORM\EntityManagerInterface;

// Importation des classes de base de Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// Importation des classes pour l'envoi d'emails
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Contrôleur de gestion de la newsletter
 * Gère les inscriptions, désinscriptions et l'administration des abonnés
 */
class NewsletterController extends AbstractController
{
    /**
     * Inscription à la newsletter
     * Permet à un utilisateur de s'abonner à la newsletter du site
     * Envoie un email de confirmation après l'inscription
     * 
     * @param Request $request Requête HTTP contenant l'email de l'utilisateur
     * @param EntityManagerInterface $em Gestionnaire d'entités Doctrine pour les opérations BDD
     * @param MailerInterface $mailer Service d'envoi d'emails Symfony
     * @return Response Redirection vers la page d'accueil avec un message flash
     */
    #[Route('/newsletter/subscribe', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request, 
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        // Récupération de l'email depuis les données POST du formulaire
        // Le champ doit s'appeler 'newsletter_email' dans le HTML
        $email = $request->request->get('newsletter_email');
        
        // Validation de l'email
        // Vérifie que l'email n'est pas vide ET qu'il a un format valide
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Ajouter un message d'erreur qui sera affiché sur la page suivante
            $this->addFlash('error', 'Veuillez entrer une adresse email valide.');
            // Rediriger vers la page d'accueil
            return $this->redirectToRoute('app_home');
        }

        // Vérifier si cet email existe déjà dans la base de données
        // findOneBy() recherche un seul enregistrement selon le critère fourni
        $existingNewsletter = $em->getRepository(Newsletter::class)->findOneBy(['email' => $email]);
        
        // Si l'email existe déjà en base de données
        if ($existingNewsletter) {
            // Vérifier si l'abonnement est actuellement actif
            if ($existingNewsletter->isActive()) {
                // L'utilisateur est déjà inscrit et actif
                $this->addFlash('warning', 'Vous êtes déjà inscrit à notre newsletter.');
                return $this->redirectToRoute('app_home');
            } else {
                // L'utilisateur s'était désinscrit, on réactive son abonnement
                $existingNewsletter->setIsActive(true); // Réactiver l'abonnement
                $existingNewsletter->setSubscribeAt(new \DateTimeImmutable()); // Mettre à jour la date d'inscription
                $em->flush(); // Sauvegarder les modifications en base de données
                
                $this->addFlash('success', 'Votre inscription à la newsletter a été réactivée avec succès.');
                return $this->redirectToRoute('app_home');
            }
        }

        // Bloc try-catch pour gérer les erreurs potentielles lors de l'inscription
        try {
            // Création d'une nouvelle entité Newsletter
            $newsletter = new Newsletter();
            $newsletter->setEmail($email); // Définir l'email de l'abonné
            // Note: Les autres propriétés (token, isActive, subscribeAt) 
            // sont probablement initialisées dans le constructeur de l'entité
            
            // Persister l'entité (préparer l'insertion en base de données)
            $em->persist($newsletter);
            
            // Exécuter réellement l'insertion en base de données
            $em->flush();

            // Préparation de l'email de confirmation
            $emailMessage = (new Email())
                ->from('no-reply@glowbeautyskin.com') // Adresse d'expédition
                ->to($newsletter->getEmail()) // Destinataire (le nouvel abonné)
                ->subject('Bienvenue dans la famille Glow Beauty Skin !') // Sujet de l'email
                ->html($this->renderView('email/newsletter_welcome.html.twig', [
                    // Rendu du template Twig pour le corps de l'email
                    'user' => ['lastName' => 'Membre'], // Données utilisateur (générique ici)
                    'token' => $newsletter->getToken() // Token pour la désinscription
                ]));

            // Envoi de l'email via le service Mailer de Symfony
            $mailer->send($emailMessage);

            // Message de succès affiché à l'utilisateur
            $this->addFlash('success', 'Merci ! Vous êtes maintenant inscrit à notre newsletter. Un email de confirmation vous a été envoyé.');
            
        } catch (\Exception $e) {
            // En cas d'erreur (BDD, email, etc.), afficher un message générique
            // Dans un environnement de production, il serait bon de logger l'erreur
            $this->addFlash('error', 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.');
        }

        // Redirection vers la page d'accueil
        return $this->redirectToRoute('app_home');
    }

    /**
     * Désinscription de la newsletter
     * Permet à un utilisateur de se désabonner via un lien unique
     * Le lien contient un token unique pour identifier l'abonné
     * 
     * @param string $token Token unique de l'abonné (passé dans l'URL)
     * @param NewsletterRepository $repository Repository pour accéder aux données newsletter
     * @param EntityManagerInterface $em Gestionnaire d'entités pour la mise à jour
     * @return Response Redirection vers la page d'accueil avec un message
     */
    #[Route('/newsletter/unsubscribe/{token}', name: 'app_newsletter_unsubscribe')]
    public function unsubscribe(
        string $token, // Le token est extrait de l'URL automatiquement par Symfony
        NewsletterRepository $repository,
        EntityManagerInterface $em
    ): Response {
        // Recherche de l'abonné par son token unique
        $newsletter = $repository->findOneBy(['token' => $token]);

        // Si aucun abonné n'est trouvé avec ce token
        if (!$newsletter) {
            // Le token est invalide ou n'existe pas
            $this->addFlash('error', 'Lien de désinscription invalide.');
            return $this->redirectToRoute('app_home');
        }

        // Désactiver l'abonnement (soft delete - on ne supprime pas, on désactive)
        $newsletter->setIsActive(false);
        
        // Sauvegarder la modification en base de données
        $em->flush();

        // Message de confirmation de désinscription
        $this->addFlash('success', 'Vous avez été désinscrit de notre newsletter avec succès. Nous espérons vous revoir bientôt !');
        
        // Redirection vers la page d'accueil
        return $this->redirectToRoute('app_home');
    }

    /**
     * Liste des abonnés à la newsletter (Interface d'administration)
     * Affiche tous les abonnés actifs pour l'administrateur
     * 
     * @param NewsletterRepository $repository Repository pour récupérer les abonnés
     * @return Response Rendu de la page de liste des abonnés
     */
    #[Route('/admin/newsletter', name: 'app_admin_newsletter')]
    public function adminList(NewsletterRepository $repository): Response
    {
        // Récupération de tous les abonnés actifs
        // findBy() avec deux paramètres:
        // 1er param: critères de recherche (isActive = true)
        // 2ème param: ordre de tri (par date d'inscription décroissante)
        $subscribers = $repository->findBy(
            ['isActive' => true], // Critère: seulement les abonnements actifs
            ['subscribeAt' => 'DESC'] // Tri: du plus récent au plus ancien
        );

        // Rendu du template d'administration avec la liste des abonnés
        return $this->render('newsletter/list.html.twig', [
            'subscribers' => $subscribers, // Liste des abonnés
            'total' => count($subscribers) // Nombre total d'abonnés actifs
        ]);
    }

    /**
     * Export des abonnés au format CSV
     * Permet à l'administrateur de télécharger la liste complète des abonnés
     * Format CSV pour utilisation dans Excel, Google Sheets, etc.
     * 
     * @param NewsletterRepository $repository Repository pour récupérer les abonnés
     * @return Response Fichier CSV en téléchargement
     */
    #[Route('/admin/newsletter/export', name: 'app_admin_newsletter_export')]
    public function exportCsv(NewsletterRepository $repository): Response
    {
        // Récupération de tous les abonnés actifs, triés par date d'inscription
        $subscribers = $repository->findBy(
            ['isActive' => true],
            ['subscribeAt' => 'DESC']
        );

        // Création du contenu CSV
        // Première ligne: en-têtes des colonnes
        $csv = "Email,Date d'inscription,Statut\n";
        
        // Parcourir tous les abonnés pour créer les lignes du CSV
        foreach ($subscribers as $subscriber) {
            // sprintf() formate une chaîne avec des placeholders (%s)
            // Chaque ligne contient: email, date formatée, statut
            $csv .= sprintf(
                "%s,%s,%s\n", // Format: valeur1,valeur2,valeur3\n
                $subscriber->getEmail(), // Email de l'abonné
                $subscriber->getSubscribeAt()->format('d/m/Y H:i'), // Date au format français
                $subscriber->isActive() ? 'Actif' : 'Inactif' // Statut de l'abonnement
            );
        }

        // Création de la réponse HTTP avec le contenu CSV
        $response = new Response($csv);
        
        // Définir le type de contenu comme CSV
        $response->headers->set('Content-Type', 'text/csv');
        
        // Forcer le téléchargement du fichier avec un nom contenant la date du jour
        // "attachment" indique au navigateur de télécharger le fichier
        // Le nom du fichier inclut la date actuelle (YYYY-MM-DD)
        $response->headers->set(
            'Content-Disposition', 
            'attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"'
        );

        // Retourner la réponse (le navigateur déclenchera le téléchargement)
        return $response;
    }

    /**
     * Suppression définitive d'un abonné
     * Permet à l'administrateur de supprimer complètement un abonné de la base
     * Contrairement à la désinscription qui désactive, ici on supprime l'enregistrement
     * 
     * @param int $id ID de l'abonné à supprimer
     * @param NewsletterRepository $repository Repository pour trouver l'abonné
     * @param EntityManagerInterface $em Gestionnaire d'entités pour la suppression
     * @return Response Redirection vers la liste des abonnés
     */
    #[Route('/admin/newsletter/delete/{id}', name: 'app_admin_newsletter_delete')]
    public function delete(
        int $id, // ID de l'abonné extrait de l'URL
        NewsletterRepository $repository,
        EntityManagerInterface $em
    ): Response {
        // Recherche de l'abonné par son ID
        $newsletter = $repository->find($id);

        // Vérification de l'existence de l'abonné
        if (!$newsletter) {
            // L'abonné n'existe pas (ID invalide)
            $this->addFlash('error', 'Abonné introuvable.');
            return $this->redirectToRoute('app_admin_newsletter');
        }

        // Suppression de l'entité de la base de données
        // remove() marque l'entité pour suppression
        $em->remove($newsletter);
        
        // flush() exécute réellement la suppression en base de données
        $em->flush();

        // Message de confirmation de suppression
        $this->addFlash('success', 'Abonné supprimé avec succès.');
        
        // Redirection vers la liste des abonnés
        return $this->redirectToRoute('app_admin_newsletter');
    }
}