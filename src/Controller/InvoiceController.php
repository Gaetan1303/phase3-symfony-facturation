<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Form\InvoiceType;
use App\Service\InvoiceNumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invoices')]
class InvoiceController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/', name: 'app_invoices', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $invoices = $this->em->getRepository(Invoice::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('invoice/index.html.twig', ['invoices' => $invoices]);
    }

    #[Route('/new', name: 'invoice_new', methods: ['GET', 'POST'])]
    public function new(Request $request, \App\Service\InvoiceManager $invoiceManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $invoice = new Invoice();
        $form = $this->createForm(InvoiceType::class, $invoice, ['user' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoiceManager->persistInvoice($invoice, $this->getUser(), $form->get('validate')->isClicked());

            $this->addFlash('success', 'Facture enregistrée.');

            return $this->redirectToRoute('app_invoices');
        }

        return $this->render('invoice/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/edit', name: 'invoice_edit', methods: ['GET', 'POST'])]
    public function edit(Invoice $invoice, Request $request, \App\Service\InvoiceManager $invoiceManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($invoice->getStatus() !== \App\Enum\InvoiceStatus::DRAFT) {
            $this->addFlash('error', 'Une facture validée ne peut plus être modifiée.');
            return $this->redirectToRoute('app_invoices');
        }

        if ($invoice->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $form = $this->createForm(InvoiceType::class, $invoice, ['user' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoiceManager->persistInvoice($invoice, $this->getUser(), $form->get('validate')->isClicked());

            $this->addFlash('success', 'Facture mise à jour.');
            return $this->redirectToRoute('app_invoices');
        }

        return $this->render('invoice/edit.html.twig', ['form' => $form->createView(), 'invoice' => $invoice]);
    }

    #[Route('/{id}/delete', name: 'invoice_delete', methods: ['POST'])]
    public function delete(Invoice $invoice, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($invoice->getStatus() !== \App\Enum\InvoiceStatus::DRAFT) {
            $this->addFlash('error', 'Seules les factures en brouillon peuvent être supprimées.');
            return $this->redirectToRoute('app_invoices');
        }

        if ($invoice->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if ($this->isCsrfTokenValid('delete' . $invoice->getId(), $request->request->get('_token'))) {
            $this->em->wrapInTransaction(function () use ($invoice): void {
                $this->em->remove($invoice);
            });

            $this->addFlash('success', 'Facture supprimée.');
        }

        return $this->redirectToRoute('app_invoices');
    }
}