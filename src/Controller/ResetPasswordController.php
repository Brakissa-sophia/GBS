<?php

// Déclaration du namespace du contrôleur
namespace App\Controller;

// Importation de l'entité utilisateur
use App\Entity\User;

// Importation des formulaires
use App\Form\ChangePasswordForm;         // Formulaire de changement de mot de passe
use App\Form\ResetPasswordRequestForm;   // Formulaire de demande de réinitialisation

// Importation de Doctrine
use Doctrine\ORM\EntityManagerInterface;

// Importation des classes pour l'envoi d'emails
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

// Service de hashage des mots de passe
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// Importation des classes Symfony
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// Bundle SymfonyCasts pour la réinitialisation de mot de passe
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Contrôleur de réinitialisation de mot de passe
 * Gère le processus complet de récupération de mot de passe oublié
 * 
 * Processus en 3 étapes:
 * 1. Demande: l'utilisateur saisit son email
 * 2. Email: réception d'un lien avec token unique
 * 3. Réinitialisation: définition du nouveau mot de passe
 * 
 * Sécurité:
 * - Token unique généré par le bundle SymfonyCasts
 * - Expiration automatique (généralement 1 heure)
 * - Protection contre l'énumération d'emails
 * - Vérification que le nouveau mot de passe diffère de l'ancien
 * - Suppression du token après usage (anti-rejeu)
 * 
 * Toutes les routes sont préfixées par '/reset-password'
 */
#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    // Trait fourni par le bundle SymfonyCasts
    // Ajoute des méthodes utilitaires pour la gestion des tokens
    use ResetPasswordControllerTrait;

    /**
     * Constructeur avec injection de dépendances
     */
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,  // Service de gestion des tokens
        private EntityManagerInterface $entityManager               // Gestionnaire d'entités
    ) {
    }

    /**
     * Étape 1: Demande de réinitialisation
     * Formulaire où l'utilisateur saisit son email
     * Envoie un email avec un lien de réinitialisation
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        // Créer le formulaire de demande (contient juste un champ email)
        $form = $this->createForm(ResetPasswordRequestForm::class);
        
        // Traiter la soumission
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer l'email saisi
            /** @var string $email */
            $email = $form->get('email')->getData();

            // Appeler la méthode privée qui gère l'envoi de l'email
            return $this->processSendingPasswordResetEmail($email, $mailer, $translator);
        }

        // Affichage du formulaire de demande
        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    /**
     * Étape 2: Page de confirmation
     * Affichée après la demande pour informer l'utilisateur
     * Ne révèle pas si l'email existe (sécurité)
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Récupérer le token de la session (s'il existe)
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            // Si pas de token en session (accès direct à cette page)
            // Générer un faux token pour ne pas révéler d'informations
            // (empêche de savoir si une demande réelle a été faite)
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        // Affichage de la page de confirmation
        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,  // Pour affichage (optionnel)
        ]);
    }

    /**
     * Étape 3: Réinitialisation effective du mot de passe
     * Accessible via le lien dans l'email
     * Gère deux cas: réception du token dans l'URL, puis affichage du formulaire
     * 
     * Processus:
     * 1. Première visite: token dans l'URL → stockage en session → redirection
     * 2. Deuxième visite: token en session → affichage du formulaire
     * 3. Soumission: validation + changement de mot de passe
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator, ?string $token = null): Response
    {
        // === CAS 1: TOKEN DANS L'URL (première visite depuis l'email) ===
        if ($token) {
            // Stocker le token en session pour éviter qu'il soit visible dans l'URL
            // (sécurité: évite qu'il soit capturé dans les logs, historique, etc.)
            $this->storeTokenInSession($token);
            
            // Redirection vers la même route sans token dans l'URL
            return $this->redirectToRoute('app_reset_password');
        }

        // === CAS 2: TOKEN EN SESSION (après redirection) ===
        // Récupérer le token depuis la session
        $token = $this->getTokenFromSession();

        // Si aucun token (accès direct sans passer par l'email)
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        // === VALIDATION DU TOKEN ===
        try {
            // Valider le token et récupérer l'utilisateur associé
            // Cette méthode vérifie:
            // - Le token existe
            // - Il n'est pas expiré
            // - Il correspond à un utilisateur
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
            
        } catch (ResetPasswordExceptionInterface $e) {
            // Si le token est invalide ou expiré
            // Afficher un message d'erreur traduit
            $this->addFlash('danger', sprintf(
                '%s - %s',
                $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            // Redirection vers le formulaire de demande
            return $this->redirectToRoute('app_forgot_password_request');
        }

        // === FORMULAIRE DE CHANGEMENT DE MOT DE PASSE ===
        $form = $this->createForm(ChangePasswordForm::class);
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer le nouveau mot de passe en clair
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            
            // === VÉRIFICATION: NOUVEAU MOT DE PASSE ≠ ANCIEN ===
            // Vérifier que le nouveau mot de passe est différent de l'ancien
            if ($passwordHasher->isPasswordValid($user, $plainPassword)) {
                $this->addFlash('danger', 'Le nouveau mot de passe doit être différent de l\'ancien.');
                
                // Réafficher le formulaire avec le message d'erreur
                return $this->render('reset_password/reset.html.twig', [
                    'resetForm' => $form,
                ]);
            }

            // === IMPORTANT: SUPPRIMER LE TOKEN AVANT DE SAUVEGARDER ===
            // Suppression du token pour éviter les attaques par rejeu
            // (le même lien ne peut pas être réutilisé)
            $this->resetPasswordHelper->removeResetRequest($token);

            // === HASHAGE ET SAUVEGARDE DU NOUVEAU MOT DE PASSE ===
            // Hasher le nouveau mot de passe avec bcrypt
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            
            // Sauvegarder en base de données
            $this->entityManager->flush();

            // === NETTOYAGE DE LA SESSION ===
            // Supprimer le token de la session
            $this->cleanSessionAfterReset();
            
            // Message de succès
            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');

            // Redirection vers la page de connexion
            return $this->redirectToRoute('app_login');
        }

        // Affichage du formulaire de réinitialisation
        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    /**
     * Méthode privée pour traiter l'envoi de l'email de réinitialisation
     * 
     * Sécurité:
     * - Ne révèle jamais si un email existe ou non (anti-énumération)
     * - Messages et redirections identiques que l'email existe ou pas
     * - Gestion silencieuse des erreurs pour éviter les fuites d'information
     */
    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer, TranslatorInterface $translator): RedirectResponse
    {
        // Rechercher l'utilisateur par email
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // === PROTECTION CONTRE L'ÉNUMÉRATION ===
        // Si l'utilisateur n'existe pas, rediriger quand même vers la page de confirmation
        // L'utilisateur reçoit le même message que si l'email avait été envoyé
        // (impossible de savoir si un compte existe avec cet email)
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        // === GÉNÉRATION DU TOKEN DE RÉINITIALISATION ===
        try {
            // Générer un token unique pour cet utilisateur
            // Le bundle gère automatiquement:
            // - La création du token
            // - L'expiration (généralement 1 heure)
            // - Le stockage en base de données
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
            
        } catch (ResetPasswordExceptionInterface $e) {
            // Si une erreur survient (ex: token déjà demandé récemment)
            // Ne pas révéler l'erreur pour éviter l'énumération
            // Rediriger vers la page de confirmation comme si tout était OK
            return $this->redirectToRoute('app_check_email');
        }

        // === PRÉPARATION DE L'EMAIL ===
        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@gbs.com', 'Glow Beauty Skin'))
            ->to((string) $user->getEmail())
            ->subject('Réinitialisation de votre mot de passe GBS')
            ->htmlTemplate('email/reset_password.html.twig')
            ->context([
                'resetToken' => $resetToken,  // Le token pour générer le lien
                'user' => $user,              // Informations utilisateur
            ]);

        // === ENVOI DE L'EMAIL AVEC GESTION D'ERREUR SILENCIEUSE ===
        try {
            $mailer->send($email);
        } catch (\Exception $e) {
            // En cas d'erreur d'envoi, ne rien faire
            // L'utilisateur verra quand même la page de confirmation
            // Pour le logging en production:
            // $this->logger->error('Erreur d\'envoi d\'email de réinitialisation', ['exception' => $e]);
        }

        // === STOCKAGE DU TOKEN EN SESSION ===
        // Stocker le token en session pour la page de confirmation
        $this->setTokenObjectInSession($resetToken);

        // Redirection vers la page de confirmation
        return $this->redirectToRoute('app_check_email');
    }
}