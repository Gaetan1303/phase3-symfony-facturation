<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products')]
class ProductController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/', name: 'product_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $products = $this->em->getRepository(Product::class)->findBy(['user' => $user], ['name' => 'ASC']);

        return $this->render('product/index.html.twig', ['products' => $products]);
    }

    #[Route('/new', name: 'product_new', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setUser($this->getUser());
            $this->em->persist($product);
            $this->em->flush();

            $this->addFlash('success', 'Produit créé.');

            return $this->redirectToRoute('product_index');
        }

        return $this->render('product/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/edit', name: 'product_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Product $product): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if ($product->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Produit mis à jour.');

            return $this->redirectToRoute('product_index');
        }

        return $this->render('product/edit.html.twig', ['form' => $form->createView(), 'product' => $product]);
    }

    #[Route('/{id}/delete', name: 'product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product): Response
    {
        if ($product->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $this->em->remove($product);
            $this->em->flush();
            $this->addFlash('success', 'Produit supprimé.');
        }

        return $this->redirectToRoute('product_index');
    }
}
