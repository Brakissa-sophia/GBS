<?php

namespace App\Controller;

use App\DTO\ContactDTO;
use App\Form\ContactForm;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ContactDTOController extends AbstractController
{
    #[Route('/contact', name: 'app_contact_dto')]
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        $data = new ContactDTO();
        $form = $this->createForm(ContactForm::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
            $email = (new TemplatedEmail())
                ->to('contact@gbs.com')
                ->from($data->email)
                ->subject('Demande de contact')
                ->htmlTemplate('email/contact.html.twig')
                ->context(['data' => $data]);
                } catch (\Exception $e) {
                    $this -> addFlash('danger', 'Impossible d\'envoyer votre email');
                }

            $mailer->send($email); 

            $this->addFlash('success', 'Votre message a bien été envoyé');
            return $this->redirectToRoute('app_contact_dto');
        }

        return $this->render('contact/contact.html.twig', [
            'form' => $form,
            
        ]);
    }
}