<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\Status;
use App\Form\InvoiceType;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use SensioLabs\GotenbergBundle\GotenbergPdfInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invoice')]
final class InvoiceController extends AbstractController
{
    #[Route(name: 'app_invoice_index', methods: ['GET'])]
    public function index(InvoiceRepository $invoiceRepository): Response
    {
        return $this->render('invoice/index.html.twig', [
            'invoices' => $invoiceRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_invoice_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        InvoiceRepository $invoiceRepository,
        ProductRepository $productRepository
    ): Response {
        $invoice = new Invoice();

        $now = new \DateTime();
        $count = $invoiceRepository->countByMonth((int)$now->format('Y'), (int)$now->format('m'));
        $invoice->setNumber(sprintf('FACT-%s-%d', $now->format('Ymd'), $count + 1));

        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoice->setUser($this->getUser());
            $invoice->setCreatAt($now);

            $saveAs = $request->request->get('save_as', 'draft');
            $invoice->setStatus($saveAs === 'pending' ? Status::Pending_payment : Status::Draft);

            // Traitement des lignes de facture
            $lines = json_decode($request->request->get('invoice_lines', '[]'), true);
            foreach ($lines as $line) {
                $product = $entityManager->find(\App\Entity\Product::class, $line['id']);
                if ($product) {
                    $item = new InvoiceItem();
                    $item->setProduct($product);
                    $item->setQuantity((int)$line['qty']);
                    $item->setUnitPrice((float)$line['price']);
                    $invoice->addInvoiceItem($item);
                }
            }

            $entityManager->persist($invoice);
            $entityManager->flush();

            return $this->redirectToRoute('app_invoice_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice/new.html.twig', [
            'invoice' => $invoice,
            'form'     => $form,
            'products' => $productRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_invoice_show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        return $this->render('invoice/show.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_invoice_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Invoice $invoice,
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository
    ): Response {
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Supprimer les anciennes lignes et les remplacer
            foreach ($invoice->getInvoiceItems() as $existingItem) {
                $invoice->removeInvoiceItem($existingItem);
                $entityManager->remove($existingItem);
            }

            $lines = json_decode($request->request->get('invoice_lines', '[]'), true);
            foreach ($lines as $line) {
                $product = $entityManager->find(\App\Entity\Product::class, $line['id']);
                if ($product) {
                    $item = new InvoiceItem();
                    $item->setProduct($product);
                    $item->setQuantity((int)$line['qty']);
                    $item->setUnitPrice((float)$line['price']);
                    $invoice->addInvoiceItem($item);
                }
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice/edit.html.twig', [
            'invoice'  => $invoice,
            'form'     => $form,
            'products' => $productRepository->findAll(),
        ]);
    }

    // Valider la facture : draft → pending
    #[Route('/{id}/validate', name: 'app_invoice_validate', methods: ['GET'])]
    public function validate(Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        $invoice->setStatus(Status::Pending_payment);
        $entityManager->flush();

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    // Marquer comme payée : pending → paid
    #[Route('/{id}/pay', name: 'app_invoice_pay', methods: ['GET'])]
    public function pay(Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        $invoice->setStatus(Status::Paid);
        $entityManager->flush();

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    // PDF avec Gotenberg
    #[Route('/{id}/pdf', name: 'app_invoice_pdf', methods: ['GET'])]
    public function pdf(Invoice $invoice, GotenbergPdfInterface $gotenberg): Response
    {
        $pdf = $gotenberg
            ->html()
            ->content('invoice/pdf.html.twig', ['invoice' => $invoice])
            ->generate()
            ->process();

        return new Response(
            $pdf->getContent(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="facture-' . $invoice->getNumber() . '.pdf"',
            ]
        );
    }

    #[Route('/{id}', name: 'app_invoice_delete', methods: ['POST'])]
    public function delete(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        // Bloquer la suppression si pas brouillon
        if ($invoice->getStatus() !== Status::Draft) {
            $this->addFlash('error', 'Seules les factures en brouillon peuvent être supprimées.');
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        if ($this->isCsrfTokenValid('delete'.$invoice->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($invoice);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_invoice_index', [], Response::HTTP_SEE_OTHER);
    }
}