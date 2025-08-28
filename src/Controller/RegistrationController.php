<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationForm;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $user->setToken(uniqid());
            $entityManager->persist($user);
            $entityManager->flush();
            $this->addFlash('success', 'votre compte a bien été créé');

            $email = (new TemplatedEmail())
            ->from(new Address('no-reply@gbs.com', 'Glow Beauty Skin'))
            ->to((string) $user->getEmail())
            ->subject('Activation de votre compte GBS')
            ->htmlTemplate('email/activation_account.html.twig')
            ->context([
                'user' => $user,
            ]);
            $mailer->send($email);
        

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
    #[Route('/activation-compte/{token}', name: 'app_activation_account', methods: ['GET'])]
    public function activationAccount(string $token, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneBy(['token' => $token]);

        if ($user) {
            $user->setToken(null);
            $entityManager->flush();
            $this->addFlash('success', 'Vous etes désormais inscrit, pensez à verrifier votre boite mail pour activer votre compte puis vous connecter');
        } else {
            $this->addFlash('danger', 'Un problème technique est survenu lors de l\'activation de votre compte, veuillez réessayer ultérieurement');
            
        }
    
        return $this->redirectToRoute('app_login');
    }


    #[Route('/renvoyer-activation', name: 'app_resend_activation')]
        public function resendActivation(Request $request, UserRepository $userRepository, MailerInterface $mailer): Response
        {
            if ($request->isMethod('POST')) {
                $email = $request->request->get('email');
                $user = $userRepository->findOneBy(['email' => $email]);
                
                if ($user && $user->getToken() !== null) {
                    $emailMessage = (new TemplatedEmail())
                        ->from(new Address('no-reply@gbs.com', 'Glow Beauty Skin'))
                        ->to($user->getEmail())
                        ->subject('Activation de votre compte GBS')
                        ->htmlTemplate('email/activation_account.html.twig')
                        ->context(['user' => $user]);
                    
                    $mailer->send($emailMessage);
                    $this->addFlash('success', 'Email d\'activation renvoyé');
                }
            }
            
             return $this->render('registration/resend_activation.html.twig');
   }
   
}
