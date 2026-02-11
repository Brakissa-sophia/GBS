<?php

// Déclaration du namespace du contrôleur
namespace App\Controller;

// Importation de l'entité utilisateur
use App\Entity\User;

// Importation du formulaire d'inscription
use App\Form\RegistrationForm;

// Importation du repository utilisateur
use App\Repository\UserRepository;

// Importation de Doctrine
use Doctrine\ORM\EntityManagerInterface;

// Importation des classes pour l'envoi d'emails
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

// Service de hashage des mots de passe
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// Annotation de route
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur d'inscription et d'activation de compte
 * Gère le processus complet d'inscription avec validation par email:
 * 
 * 1. Inscription: création du compte avec génération d'un token
 * 2. Envoi d'email d'activation avec lien unique
 * 3. Activation: validation du token et activation du compte
 * 4. Renvoi d'email: pour les utilisateurs n'ayant pas reçu l'email initial
 * 
 * Sécurité:
 * - Token unique de 64 caractères hexadécimaux
 * - Expiration après 24 heures
 * - Compte inactif jusqu'à validation
 * - Protection contre l'énumération d'emails
 */
class RegistrationController extends AbstractController
{
    /**
     * Inscription d'un nouveau compte utilisateur
     * Crée un compte inactif et envoie un email de validation
     * 
     * Processus:
     * 1. Affichage du formulaire d'inscription
     * 2. Validation des données
     * 3. Hashage du mot de passe
     * 4. Génération d'un token d'activation unique
     * 5. Sauvegarde en base avec compte inactif
     * 6. Envoi de l'email d'activation
     * 7. Redirection vers la page de connexion
     */
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        // Créer une nouvelle entité User vide
        $user = new User();
        
        // Créer le formulaire d'inscription
        $form = $this->createForm(RegistrationForm::class, $user);
        
        // Traiter la soumission du formulaire
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer le mot de passe en clair depuis le formulaire
            // (champ non mappé à l'entité pour des raisons de sécurité)
            $plainPassword = $form->get('plainPassword')->getData();

            // === PRÉPARATION DES DONNÉES UTILISATEUR ===
            
            // Normaliser l'email en minuscules pour éviter les doublons (test@mail.com = TEST@mail.com)
            $user->setEmail(strtolower((string) $user->getEmail()));
            
            // Hasher le mot de passe avec bcrypt (algorithme par défaut de Symfony)
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            
            // === GÉNÉRATION DU TOKEN D'ACTIVATION ===
            
            // Générer un token aléatoire unique de 64 caractères hexadécimaux
            // random_bytes(32) génère 32 bytes aléatoires
            // bin2hex() convertit en 64 caractères hexadécimaux (2 caractères hex = 1 byte)
            $user->setToken(bin2hex(random_bytes(32)));
            
            // Définir la date d'expiration du token (24 heures à partir de maintenant)
            $user->setTokenExpiresAt(new \DateTimeImmutable('+24 hours'));
            
            // Le compte est inactif jusqu'à validation par email
            $user->setIsActive(false);

            // === SAUVEGARDE EN BASE DE DONNÉES ===
            $entityManager->persist($user);
            $entityManager->flush();

            // Message de confirmation pour l'utilisateur
            $this->addFlash('success', 'Votre compte a bien été créé. Vous recevrez un e-mail pour activer votre compte.');

            // === PRÉPARATION DE L'EMAIL D'ACTIVATION ===
            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@gbs.com', 'Glow Beauty Skin'))  // Expéditeur avec nom personnalisé
                ->to((string) $user->getEmail())                              // Destinataire
                ->subject('Activation de votre compte GBS')                   // Sujet de l'email
                ->htmlTemplate('email/activation_account.html.twig')          // Template Twig pour le HTML
                ->context([                                                   // Variables pour le template
                    'user' => $user,  // Contient notamment le token pour générer le lien
                ]);

            // === ENVOI DE L'EMAIL AVEC GESTION D'ERREUR ===
            try {
                // Tenter d'envoyer l'email
                $mailer->send($email);
            } catch (\Exception $e) {
                // Si l'envoi échoue (serveur mail indisponible, etc.)
                // Le compte est quand même créé mais l'utilisateur est averti
                $this->addFlash(
                    'warning',
                    'Le compte a été créé mais l\'e-mail d\'activation n\'a pas pu être envoyé. Veuillez réutiliser le lien « Renvoyer l\'e-mail d\'activation ».'
                );
            }

            // Redirection vers la page de connexion
            return $this->redirectToRoute('app_login');
        }

        // Si le formulaire n'est pas soumis ou est invalide
        // Afficher le formulaire d'inscription
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * Activation du compte via le token reçu par email
     * Vérifie la validité du token et active le compte
     * 
     * Le lien d'activation contient le token dans l'URL:
     * /activation-compte/{token}
     * 
     * Vérifications effectuées:
     * 1. Token existe en base de données
     * 2. Token n'est pas expiré (< 24 heures)
     * 3. Si OK: activation du compte et suppression du token
     */
    #[Route('/activation-compte/{token}', name: 'app_activation_account', methods: ['GET'])]
    public function activationAccount(
        string $token,  // Token extrait automatiquement de l'URL par Symfony
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Rechercher l'utilisateur par son token
        $user = $userRepository->findOneBy(['token' => $token]);

        // Obtenir la date/heure actuelle pour comparaison
        $now = new \DateTimeImmutable('now');
        
        // === VÉRIFICATION DE LA VALIDITÉ DU TOKEN ===
        // Le token est valide si:
        // 1. L'utilisateur existe
        // 2. Il a une date d'expiration définie
        // 3. La date d'expiration n'est pas dépassée
        if ($user && $user->getTokenExpiresAt() && $user->getTokenExpiresAt() > $now) {
            // === ACTIVATION DU COMPTE ===
            
            // Supprimer le token (ne peut plus être réutilisé)
            $user->setToken(null);
            
            // Supprimer la date d'expiration
            $user->setTokenExpiresAt(null);
            
            // Activer le compte
            $user->setIsActive(true);
            
            // Sauvegarder les modifications
            $entityManager->flush();

            // Message de succès
            $this->addFlash('success', 'Votre compte est maintenant activé ! Vous pouvez vous connecter.');
        } else {
            // Token invalide ou expiré
            // Message générique pour des raisons de sécurité
            // (ne pas révéler si le token existe ou non)
            $this->addFlash('danger', 'Le lien d\'activation est invalide ou expiré. Veuillez demander un nouveau lien.');
        }

        // Redirection vers la page de connexion dans tous les cas
        return $this->redirectToRoute('app_login');
    }

    /**
     * Renvoi de l'email d'activation
     * Permet à un utilisateur de recevoir un nouveau lien d'activation
     * 
     * Cas d'usage:
     * - Email initial non reçu (spam, erreur serveur)
     * - Token expiré (> 24 heures)
     * - Email perdu ou supprimé
     * 
     * Sécurité:
     * - Message générique pour éviter l'énumération d'emails
     * - Nouveau token généré à chaque demande
     * - Nouvelle expiration de 24 heures
     */
    #[Route('/renvoyer-activation', name: 'app_resend_activation', methods: ['GET', 'POST'])]
    public function resendActivation(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        // Créer un formulaire simple avec juste un champ email
        // (pas besoin d'une classe de formulaire complète)
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'placeholder' => 'votre@email.com',
                    'class' => 'form-control'
                ]
            ])
            ->getForm();
            
        // Traiter la soumission
        $form->handleRequest($request);
        
        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer et normaliser l'email saisi
            $email = strtolower((string) $form->get('email')->getData());
            
            // Rechercher l'utilisateur par email
            $user  = $userRepository->findOneBy(['email' => $email]);
            
            // === VÉRIFICATION: UTILISATEUR EXISTE ET N'EST PAS ACTIVÉ ===
            // Seulement si l'utilisateur existe ET que son compte n'est pas encore activé
            if ($user && !$user->isActive()) {
                // === GÉNÉRATION D'UN NOUVEAU TOKEN ===
                
                // Générer un nouveau token (l'ancien est invalidé)
                $user->setToken(bin2hex(random_bytes(32)));
                
                // Nouvelle date d'expiration (24 heures à partir de maintenant)
                $user->setTokenExpiresAt(new \DateTimeImmutable('+24 hours'));
                
                // Sauvegarder les modifications
                $entityManager->flush();
                
                // === PRÉPARATION ET ENVOI DE L'EMAIL ===
                $emailMessage = (new TemplatedEmail())
                    ->from(new Address('no-reply@gbs.com', 'Glow Beauty Skin'))
                    ->to($user->getEmail())
                    ->subject('Activation de votre compte GBS')
                    ->htmlTemplate('email/activation_account.html.twig')
                    ->context(['user' => $user]);  // Le token est dans l'entité user
                
                try {
                    // Tenter d'envoyer l'email
                    $mailer->send($emailMessage);
                } catch (\Exception $e) {
                    // En cas d'erreur d'envoi
                    $this->addFlash(
                        'warning',
                        'L\'e-mail d\'activation n\'a pas pu être renvoyé pour le moment. Veuillez réessayer dans quelques minutes.'
                    );
                }
            }
            
            // === MESSAGE GÉNÉRIQUE POUR LA SÉCURITÉ ===
            // On affiche TOUJOURS le même message, que l'utilisateur existe ou non
            // Cela empêche un attaquant de deviner quels emails sont enregistrés
            // (protection contre l'énumération d'utilisateurs)
            $this->addFlash('success', 'Si votre compte existe et n\'est pas activé, un e-mail vous a été envoyé pour l\'activer.');
            
            // Redirection vers la même page (pour éviter la resoumission du formulaire)
            return $this->redirectToRoute('app_resend_activation');
        }

        // Affichage du formulaire de renvoi d'activation
        return $this->render('registration/resend_activation.html.twig', [
            'form' => $form->createView()
        ]);
    }
}







