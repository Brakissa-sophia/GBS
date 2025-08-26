<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/user')]
final class UserController extends AbstractController
{
    #[Route('/', name: 'app_user')]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig',[
            'users'=> $userRepository->findAll(),
        ]);
    }


    #[Route('/{id}/to/editor', name:'app_user_to_editor')]
    public function changeRole(EntityManagerInterface $entityManager, User $user): Response
    {
       $user->setRoles(["ROLE_EDITOR", "ROLE_USER"]);
       $entityManager->flush();
            $this->addFlash('success', 
            $user->getLastName() . ' a désormais le rôle éditeur.');
         
            return $this->redirectToRoute('app_user'); 
    }


    #[Route('/{id}/remove/editor/role', name:'app_user_remove_editor_role')]
    public function edittoRoleRemove(EntityManagerInterface $entityManager, User $user): Response
    {
       $user->setRoles([]);
       $entityManager->flush();

            $this->addFlash('danger', 
            $user->getLastName() . ' ne détient plus le rôle éditeur.');
         
            return $this->redirectToRoute('app_user'); 
    }

    
    #[Route('/{id}/remove/', name: 'app_user_remove')]
    public function userRemove(EntityManagerInterface $entityManager, $id, UserRepository $userRepository): Response
    {
       $userFind = $userRepository->find($id);
       $entityManager->remove ($userFind);
       $entityManager->flush();

            $this->addFlash('danger', 
            $userFind->getLastName() . ' a été supprimé .');
         
            return $this->redirectToRoute(route:'app_user');
    }




 #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
{
    
    if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->get('_token'))) {
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'Le membre "' . $user->getLastName() . '" a bien été supprimé.');
    } else {
        $this->addFlash('danger', 'Le token CSRF est invalide. Suppression annulée.');
    }

    return $this->redirectToRoute('app_user');
}

}
