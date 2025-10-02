<?php

namespace App\Controller;

use App\Entity\Newsletter;
use App\Repository\NewsletterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class NewsletterController extends AbstractController
{
    #[Route('/newsletter/subscribe', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request, 
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        $email = $request->request->get('newsletter_email');
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Veuillez entrer une adresse email valide.');
            return $this->redirectToRoute('app_home');
        }

        // Vérifier si l'email existe déjà
        $existingNewsletter = $em->getRepository(Newsletter::class)->findOneBy(['email' => $email]);
        
        if ($existingNewsletter) {
            if ($existingNewsletter->isActive()) {
                $this->addFlash('warning', 'Vous êtes déjà inscrit à notre newsletter.');
                return $this->redirectToRoute('app_home');
            } else {
                $existingNewsletter->setIsActive(true);
                $existingNewsletter->setSubscribeAt(new \DateTimeImmutable());
                $em->flush();
                $this->addFlash('success', 'Votre inscription à la newsletter a été réactivée avec succès.');
                return $this->redirectToRoute('app_home');
            }
        }

        try {
            $newsletter = new Newsletter();
            $newsletter->setEmail($email);
            
            $em->persist($newsletter);
            $em->flush();

            // Envoyer un email de confirmation
            $emailMessage = (new Email())
                ->from('no-reply@glowbeautyskin.com')
                ->to($newsletter->getEmail())
                ->subject('Bienvenue dans la famille Glow Beauty Skin !')
                ->html($this->renderView('email/newsletter_welcome.html.twig', [
                    'user' => ['lastName' => 'Membre'],
                    'token' => $newsletter->getToken()
                ]));

            $mailer->send($emailMessage);

            $this->addFlash('success', 'Merci ! Vous êtes maintenant inscrit à notre newsletter. Un email de confirmation vous a été envoyé.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/newsletter/unsubscribe/{token}', name: 'app_newsletter_unsubscribe')]
    public function unsubscribe(
        string $token,
        NewsletterRepository $repository,
        EntityManagerInterface $em
    ): Response {
        $newsletter = $repository->findOneBy(['token' => $token]);

        if (!$newsletter) {
            $this->addFlash('error', 'Lien de désinscription invalide.');
            return $this->redirectToRoute('app_home');
        }

        $newsletter->setIsActive(false);
        $em->flush();

        $this->addFlash('success', 'Vous avez été désinscrit de notre newsletter avec succès. Nous espérons vous revoir bientôt !');
        
        return $this->redirectToRoute('app_home');
    }

    #[Route('/admin/newsletter', name: 'app_admin_newsletter')]
    public function adminList(NewsletterRepository $repository): Response
    {
        $subscribers = $repository->findBy(['isActive' => true], ['subscribeAt' => 'DESC']);

        return $this->render('newsletter/list.html.twig', [
            'subscribers' => $subscribers,
            'total' => count($subscribers)
        ]);
    }

    #[Route('/admin/newsletter/export', name: 'app_admin_newsletter_export')]
    public function exportCsv(NewsletterRepository $repository): Response
    {
        $subscribers = $repository->findBy(['isActive' => true], ['subscribeAt' => 'DESC']);

        $csv = "Email,Date d'inscription,Statut\n";
        
        foreach ($subscribers as $subscriber) {
            $csv .= sprintf(
                "%s,%s,%s\n",
                $subscriber->getEmail(),
                $subscriber->getSubscribeAt()->format('d/m/Y H:i'),
                $subscriber->isActive() ? 'Actif' : 'Inactif'
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    #[Route('/admin/newsletter/delete/{id}', name: 'app_admin_newsletter_delete')]
    public function delete(
        int $id,
        NewsletterRepository $repository,
        EntityManagerInterface $em
    ): Response {
        $newsletter = $repository->find($id);

        if (!$newsletter) {
            $this->addFlash('error', 'Abonné introuvable.');
            return $this->redirectToRoute('app_admin_newsletter');
        }

        $em->remove($newsletter);
        $em->flush();

        $this->addFlash('success', 'Abonné supprimé avec succès.');
        
        return $this->redirectToRoute('app_admin_newsletter');
    }
}