<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Form\InvoiceType;
use App\Service\InvoiceNumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Sensiolabs\GotenbergBundle\Builder\Result\GotenbergFileResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\InvoiceMailer;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route('/invoices')]
class InvoiceController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/{id}/send', name: 'invoice_send', methods: ['POST'])]
    public function sendInvoice(Invoice $invoice, Request $request, InvoiceMailer $invoiceMailer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($invoice->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$this->isCsrfTokenValid('send_invoice' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        $ok = $invoiceMailer->sendInvoice($invoice);
        if ($ok) {
            $this->addFlash('success', 'Email envoyé au client.');
        } else {
            $this->addFlash('error', 'Échec de l\'envoi du mail.');
        }

        if ($request->headers->get('Turbo-Frame') || str_starts_with($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
            $parts = [$this->renderView('turbo/toast_stream.html.twig', ['message' => $ok ? 'Email envoyé au client.' : 'Échec de l\'envoi du mail.', 'type' => $ok ? 'success' : 'error'])];
            return new Response(implode("\n", $parts), 200, ['Content-Type' => 'text/vnd.turbo-stream.html']);
        }

        return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/reminder', name: 'invoice_reminder', methods: ['GET','POST'])]
    public function reminder(Invoice $invoice, Request $request, InvoiceMailer $invoiceMailer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($invoice->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if ($request->isMethod('GET')) {
            return $this->render('invoice/_reminder_form.html.twig', ['invoice' => $invoice]);
        }

        // POST
        if (!$this->isCsrfTokenValid('reminder' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        $message = (string) $request->request->get('message', '');
        $ok = $invoiceMailer->sendReminder($invoice, $message);
        if ($ok) {
            $this->addFlash('success', 'Email de relance envoyé.');
        } else {
            $this->addFlash('error', 'Échec de l\'envoi de la relance.');
        }

        if ($request->headers->get('Turbo-Frame') || str_starts_with($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
            $parts = [$this->renderView('turbo/toast_stream.html.twig', ['message' => $ok ? 'Email de relance envoyé.' : 'Échec de l\'envoi de la relance.', 'type' => $ok ? 'success' : 'error'])];
            return new Response(implode("\n", $parts), 200, ['Content-Type' => 'text/vnd.turbo-stream.html']);
        }

        return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/', name: 'app_invoices', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $invoices = $this->em->getRepository(Invoice::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $pending = $this->em->getRepository(Invoice::class)->findBy(['user' => $user, 'status' => \App\Enum\InvoiceStatus::PENDING_PAYMENT], ['createdAt' => 'DESC']);
        $paid = $this->em->getRepository(Invoice::class)->findBy(['user' => $user, 'status' => \App\Enum\InvoiceStatus::PAID], ['createdAt' => 'DESC']);
        $drafts = $this->em->getRepository(Invoice::class)->findBy(['user' => $user, 'status' => \App\Enum\InvoiceStatus::DRAFT], ['createdAt' => 'DESC']);

        return $this->render('invoice/index.html.twig', ['invoices' => $invoices, 'pending' => $pending, 'paid' => $paid, 'drafts' => $drafts]);
    }

    #[Route('/new', name: 'invoice_new', methods: ['GET', 'POST'])]
    public function new(Request $request, \App\Service\InvoiceManager $invoiceManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $invoice = new Invoice();
        $form = $this->createForm(InvoiceType::class, $invoice, ['user' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $invoiceManager->persistInvoice($invoice, $this->getUser(), $form->get('validate')->isClicked());
                $message = 'Facture enregistrée.';
                $this->addFlash('success', $message);

                // Turbo: return toast stream and instruct client to visit invoices list
                $turboFrame = $request->headers->get('Turbo-Frame');
                if ($turboFrame || str_starts_with($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
                    $url = $this->generateUrl('app_invoices');
                    $content = $this->renderView('turbo/toast_stream.html.twig', ['message' => $message, 'type' => 'success']);
                    return new Response($content, 200, ['Content-Type' => 'text/vnd.turbo-stream.html', 'Turbo-Location' => $url]);
                }

                return $this->redirectToRoute('app_invoices');
            }

            // form submitted but invalid: render replacement of form + error toast for Turbo
            $message = 'Le formulaire contient des erreurs.';
            $this->addFlash('error', $message);
            $turboFrame = $request->headers->get('Turbo-Frame');
            if ($turboFrame || str_starts_with($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
                $formHtml = $this->renderView('invoice/_form_fragment.html.twig', ['form' => $form->createView()]);
                $replace = $this->renderView('turbo/replace_id.html.twig', ['target' => 'invoice-form', 'html' => $formHtml]);
                $toast = $this->renderView('turbo/toast_stream.html.twig', ['message' => $message, 'type' => 'error']);
                $content = $replace . "\n" . $toast;
                return new Response($content, 200, ['Content-Type' => 'text/vnd.turbo-stream.html']);
            }
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

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $invoiceManager->persistInvoice($invoice, $this->getUser(), $form->get('validate')->isClicked());
                $message = 'Facture mise à jour.';
                $this->addFlash('success', $message);
                $turboFrame = $request->headers->get('Turbo-Frame');
                if ($turboFrame || str_starts_with($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
                    $url = $this->generateUrl('app_invoices');
                    $content = $this->renderView('turbo/toast_stream.html.twig', ['message' => $message, 'type' => 'success']);
                    return new Response($content, 200, ['Content-Type' => 'text/vnd.turbo-stream.html', 'Turbo-Location' => $url]);
                }

                return $this->redirectToRoute('app_invoices');
            }

            $message = 'Le formulaire contient des erreurs.';
            $this->addFlash('error', $message);
            $turboFrame = $request->headers->get('Turbo-Frame');
            if ($turboFrame || str_starts_with($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
                $formHtml = $this->renderView('invoice/_form_fragment.html.twig', ['form' => $form->createView(), 'invoice' => $invoice]);
                $replace = $this->renderView('turbo/replace_id.html.twig', ['target' => 'invoice-form', 'html' => $formHtml]);
                $toast = $this->renderView('turbo/toast_stream.html.twig', ['message' => $message, 'type' => 'error']);
                $content = $replace . "\n" . $toast;
                return new Response($content, 200, ['Content-Type' => 'text/vnd.turbo-stream.html']);
            }
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

            $message = 'Facture supprimée.';
            $this->addFlash('success', $message);

            $turboFrame = $request->headers->get('Turbo-Frame');
            if ($turboFrame || str_starts_with($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
                $content = $this->renderView('turbo/toast_stream.html.twig', ['message' => $message, 'type' => 'success']);
                return new Response($content, 200, ['Content-Type' => 'text/vnd.turbo-stream.html']);
            }
        }

        return $this->redirectToRoute('app_invoices');
    }

    #[Route('/{id}', name: 'invoice_show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($invoice->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        return $this->render('invoice/show.html.twig', ['invoice' => $invoice]);
    }

    #[Route('/{id}/generate-pdf', name: 'invoice_generate_pdf', methods: ['POST'])]
    public function generatePdf(Invoice $invoice, Request $request, \App\Service\InvoiceManager $invoiceManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('generate_pdf' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        if ($invoice->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        // Only validated invoices can be transformed
        if ($invoice->getStatus() !== \App\Enum\InvoiceStatus::PENDING_PAYMENT) {
            $this->addFlash('error', 'Seules les factures validées peuvent être exportées en PDF.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        $type = 'success';
        $message = '';
        $ok = false;
        try {
            $path = $invoiceManager->generatePdf($invoice);
            if ($path) {
                $this->em->wrapInTransaction(function () use ($invoice, $path) { $invoice->setPdfPath($path); $this->em->persist($invoice); });
                $ok = true;
                $message = 'PDF généré et stocké.';
                $this->addFlash('success', $message);
            } else {
                $ok = false;
                $type = 'error';
                $message = 'Échec de la génération du PDF.';
                $this->addFlash('error', $message);
            }
        } catch (\Throwable $e) {
            $ok = false;
            $type = 'error';
            $message = 'Erreur lors de la génération du PDF.';
            $this->addFlash('error', $message);
        }

        // If Turbo request, return streams: replace frame (on success) + toast
        $turboFrame = $request->headers->get('Turbo-Frame');
        if ($turboFrame || str_starts_with($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
            $parts = [];
            if ($ok) {
                $parts[] = $this->renderView('turbo/replace_invoice_frame.html.twig', ['invoice' => $invoice]);
            }
            $parts[] = $this->renderView('turbo/toast_stream.html.twig', ['message' => $message, 'type' => $type]);
            $content = implode("\n", $parts);
            return new Response($content, 200, ['Content-Type' => 'text/vnd.turbo-stream.html']);
        }

        // Non-Turbo flow: if file exists, serve for download, otherwise redirect back with flash
        if ($ok && isset($path)) {
            $projectDir = $this->getParameter('kernel.project_dir');
            $fullPath = $projectDir . DIRECTORY_SEPARATOR . $path;
            if (file_exists($fullPath)) {
                $response = new BinaryFileResponse($fullPath);
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $invoice->getNumber() . '.pdf');
                return $response;
            }
        }

        return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/pdf', name: 'invoice_pdf', methods: ['GET'])]
    public function pdf(Invoice $invoice, GotenbergPdfInterface $gotenbergPdf): GotenbergFileResult
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($invoice->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $builder = $gotenbergPdf->html()
            ->content('invoice/pdf.html.twig', ['invoice' => $invoice])
            ->fileName($invoice->getNumber());

        return $builder->generate();
    }

    #[Route('/{id}/download', name: 'invoice_download', methods: ['GET'])]
    public function download(Invoice $invoice): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($invoice->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $path = $invoice->getPdfPath();
        if (!$path) {
            $this->addFlash('error', 'Aucun PDF disponible pour cette facture.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $fullPath = $projectDir . DIRECTORY_SEPARATOR . $path;
        if (!file_exists($fullPath)) {
            $this->addFlash('error', 'Fichier introuvable.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        $response = new BinaryFileResponse($fullPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $invoice->getNumber() . '.pdf');
        return $response;
    }

    #[Route('/{id}/mark-paid', name: 'invoice_mark_paid', methods: ['POST'])]
    public function markPaid(Invoice $invoice, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('mark_paid' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        if ($invoice->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        // Only pending payment invoices can be marked paid
        if ($invoice->getStatus() !== \App\Enum\InvoiceStatus::PENDING_PAYMENT) {
            $this->addFlash('error', 'Seules les factures en attente de paiement peuvent être marquées comme payées.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        $ok = false;
        $message = '';
        $type = 'success';
        try {
            $this->em->wrapInTransaction(function () use ($invoice) {
                $invoice->setStatus(\App\Enum\InvoiceStatus::PAID);
                $this->em->persist($invoice);
            });
            $ok = true;
            $message = 'Facture marquée comme payée.';
            $this->addFlash('success', $message);
        } catch (\Throwable $e) {
            $ok = false;
            $type = 'error';
            $message = 'Impossible de marquer la facture comme payée.';
            $this->addFlash('error', $message);
        }

        // If this request comes from Turbo (frame/stream), return Turbo Streams: replace frame + append toast (or only toast on error)
        $turboFrame = $request->headers->get('Turbo-Frame');
        if ($turboFrame || str_starts_with($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
            $parts = [];
            if ($ok) {
                $parts[] = $this->renderView('turbo/replace_invoice_frame.html.twig', ['invoice' => $invoice]);
            }
            $parts[] = $this->renderView('turbo/toast_stream.html.twig', ['message' => $message, 'type' => $type]);
            $content = implode("\n", $parts);
            return new Response($content, 200, ['Content-Type' => 'text/vnd.turbo-stream.html']);
        }

        return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
    }
}