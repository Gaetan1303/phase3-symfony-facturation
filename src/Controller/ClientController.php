<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients')]
class ClientController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/', name: 'client_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $clients = $this->em->getRepository(Client::class)->findBy(['user' => $user], ['name' => 'ASC']);

        return $this->render('client/index.html.twig', ['clients' => $clients]);
    }

    #[Route('/new', name: 'client_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $client->setUser($this->getUser());
            $this->em->persist($client);
            $this->em->flush();

            $this->addFlash('success', 'Client créé avec succès.');

            return $this->redirectToRoute('client_index');
        }

        $user = $this->getUser();
        $clients = $this->em->getRepository(Client::class)->findBy(['user' => $user], ['name' => 'ASC']);

        return $this->render('client/new.html.twig', [
            'form' => $form,
            'clients' => $clients,
        ]);
    }

    #[Route('/{id}/edit', name: 'client_edit', methods: ['GET', 'POST'])]
    public function edit(Client $client, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Vérifier que le client appartient à l'utilisateur connecté
        if ($client->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Client modifié avec succès.');

            return $this->redirectToRoute('client_index');
        }

        return $this->render('client/edit.html.twig', [
            'form' => $form,
            'client' => $client,
        ]);
    }

    #[Route('/{id}/delete', name: 'client_delete', methods: ['POST'])]
    public function delete(Client $client, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Vérifier que le client appartient à l'utilisateur connecté
        if ($client->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier le CSRF token
        if ($this->isCsrfTokenValid('delete' . $client->getId(), $request->request->get('_token'))) {
            $this->em->remove($client);
            $this->em->flush();

            $this->addFlash('success', 'Client supprimé avec succès.');
        }

        return $this->redirectToRoute('client_index');
    }
}
