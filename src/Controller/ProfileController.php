<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(Request $request, EntityManagerInterface $em): Response
{
    $user = $this->getUser(); // Récupère l'utilisateur connecté
    $form = $this->createForm(ProfileType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush(); // Enregistre les modifications en base de données
        $this->addFlash('success', 'Profil mis à jour !');
        return $this->redirectToRoute('app_profile');
    }

    return $this->render('profile/index.html.twig', [
        'profileForm' => $form->createView(),
    ]);
}
}
