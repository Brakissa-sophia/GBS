<?php

namespace App\Controller;

use App\Entity\SkinType;
use App\Form\SkinTypeForm;
use App\Repository\SkinTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/type_de_peau')]
final class SkinTypeController extends AbstractController
{
    #[Route(name: 'app_skin_type_index', methods: ['GET'])]
    public function index(SkinTypeRepository $skinTypeRepository): Response
    {
        return $this->render('skin_type/index.html.twig', [
            'skin_types' => $skinTypeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_skin_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $skinType = new SkinType();
        $form = $this->createForm(SkinTypeForm::class, $skinType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($skinType);
            $entityManager->flush();

            return $this->redirectToRoute('app_skin_type_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('skin_type/new.html.twig', [
            'skin_type' => $skinType,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_skin_type_show', methods: ['GET'])]
    public function show(SkinType $skinType): Response
    {
        return $this->render('skin_type/show.html.twig', [
            'skin_type' => $skinType,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_skin_type_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SkinType $skinType, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SkinTypeForm::class, $skinType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_skin_type_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('skin_type/edit.html.twig', [
            'skin_type' => $skinType,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_skin_type_delete', methods: ['POST'])]
    public function delete(Request $request, SkinType $skinType, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$skinType->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($skinType);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_skin_type_index', [], Response::HTTP_SEE_OTHER);
    }
}
