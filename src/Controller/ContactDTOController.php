<?php

namespace App\Controller;

use App\DTO\ContactDTO; // Import du DTO (Data Transfer Object) pour le formulaire de contact
use App\Form\ContactForm; // Import du formulaire de contact
use Symfony\Bridge\Twig\Mime\TemplatedEmail; // Import pour créer un email avec un template Twig
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface; // Import de l'interface pour envoyer des emails
use Symfony\Component\Mime\Address; // Import pour créer des adresses email avec nom
use Symfony\Component\Routing\Attribute\Route;

final class ContactDTOController extends AbstractController // Classe finale (non héritable)
{
    #[Route('/contact', name: 'app_contact_dto')] // Route simple sans préfixe de classe
    public function contact(Request $request, MailerInterface $mailer): Response // Injection du service Mailer
    {
        $data = new ContactDTO(); // Crée une nouvelle instance du DTO (objet de transfert de données)
        $form = $this->createForm(ContactForm::class, $data); // Crée le formulaire lié au DTO
        $form->handleRequest($request); // Traite la requête et remplit le formulaire

        if ($form->isSubmitted() && $form->isValid()) { // Vérifie si le formulaire est soumis et valide
            try { // Bloc try : tente d'exécuter le code, capture les erreurs potentielles
                $email = (new TemplatedEmail()) // Crée un nouvel email avec template
                    ->from(new Address('no-reply@gbs.com', 'Glow Beauty Skin')) // Définit l'expéditeur avec adresse et nom
                    ->to('contact@gbs.com') // Définit le destinataire
                    ->replyTo($data->email) // Définit l'adresse de réponse (celle saisie dans le formulaire)
                    ->subject('Demande de contact depuis le site') // Définit l'objet de l'email
                    ->htmlTemplate('email/contact.html.twig') // Définit le template Twig pour le contenu HTML
                    ->context(['data' => $data]); // Passe le DTO au template (accessible via {{ data.nom }}, {{ data.email }}, etc.)

                $mailer->send($email); // Envoie l'email via le service Mailer

                $this->addFlash('success', 'Votre message a bien été envoyé.'); // Message de succès
                return $this->redirectToRoute('app_contact_dto'); // Redirige vers la même page (évite la resoumission du formulaire)
                
            } catch (\Exception $e) { // Bloc catch : capture toute exception levée dans le try
                // $e contient l'objet Exception avec les détails de l'erreur
                $this->addFlash('danger', 'Impossible d\'envoyer votre message. Veuillez réessayer.'); // Message d'erreur
            }
        }

        return $this->render('contact/contact.html.twig', [ // Affiche le template du formulaire
            'form' => $form, // Passe le formulaire au template
        ]);
    }
}