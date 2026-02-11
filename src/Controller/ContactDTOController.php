<?php

namespace App\Controller;

use App\DTO\ContactDTO;
use App\Entity\Contact; //  AJOUT : Import de l'entité Contact
use App\Form\ContactForm;
use Doctrine\ORM\EntityManagerInterface; //  AJOUT : Import de l'EntityManager
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

final class ContactDTOController extends AbstractController
{
    #[Route('/contact', name: 'app_contact_dto')]
    public function contact(
        Request $request, 
        MailerInterface $mailer,
        EntityManagerInterface $entityManager //  AJOUT : Injection de l'EntityManager
    ): Response {
        $data = new ContactDTO();
        $form = $this->createForm(ContactForm::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // AJOUT : Sauvegarde en base de données
                $contact = new Contact();
                $contact->setName($data->name);
                $contact->setEmail($data->email);
                $contact->setMessage($data->message);
                
                $entityManager->persist($contact);
                $entityManager->flush();

                // Envoi de l'email (code existant)
                $email = (new TemplatedEmail())
                    ->from(new Address('no-reply@gbs.com', 'Glow Beauty Skin'))
                    ->to('contact@gbs.com')
                    ->replyTo($data->email)
                    ->subject('Demande de contact depuis le site')
                    ->htmlTemplate('email/contact.html.twig')
                    ->context(['data' => $data]);

                $mailer->send($email);

                $this->addFlash('success', 'Votre message a bien été envoyé.');
                return $this->redirectToRoute('app_contact_dto');
                
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Impossible d\'envoyer votre message. Veuillez réessayer.');
            }
        }

        return $this->render('contact/contact.html.twig', [
            'form' => $form,
        ]);
    }
}




