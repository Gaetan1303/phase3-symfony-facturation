<?php

namespace App\Controller;

use App\Form\ProfileType;
use App\Service\ProfileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    public function __construct(private ProfileManager $profileManager)
    {
    }

    #[Route('/', name: 'user_profile', methods: ['GET','POST'])]
    public function edit(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $request->request->has('profile_save')) {
            // Délégué au service pour respecter SRP et exécuter la persistance dans une transaction
            $this->profileManager->saveProfile($user);
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('user_profile');
        }

        return $this->render('profile/edit.html.twig', ['form' => $form->createView()]);
    }
}
