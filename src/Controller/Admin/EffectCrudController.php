<?php

namespace App\Controller\Admin;

use App\Entity\MainEffect;
use App\Form\EffectEditType;
use App\Form\EffectFilterType;
use App\Repository\CardRepository;
use App\Repository\MainEffectRepository;
use App\Service\EffectParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/effects', name: 'admin_effects_')]
class EffectCrudController extends AbstractController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly MainEffectRepository $effectRepo,
        private readonly CardRepository       $cardRepo,
        private readonly EntityManagerInterface $em,
        private readonly EffectParser         $parser,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $keywordStats = $this->effectRepo->getKeywordStats();

        $filterForm = $this->createForm(EffectFilterType::class, null, [
            'keyword_stats' => $keywordStats,
        ]);
        $filterForm->handleRequest($request);

        $filters = $filterForm->isSubmitted() ? array_filter((array) $filterForm->getData(), fn($v) => $v !== null && $v !== '') : [];
        $page    = max(1, $request->query->getInt('page', 1));
        $sort    = $request->query->getString('sort', 'id');
        $dir     = $request->query->getString('dir', 'asc');

        [$effects, $total] = $this->effectRepo->findFiltered($filters, $page, self::PER_PAGE, $sort, $dir);

        return $this->render('admin/effects/index.html.twig', [
            'effects'      => $effects,
            'total'        => $total,
            'page'         => $page,
            'totalPages'   => max(1, (int) ceil($total / self::PER_PAGE)),
            'filterForm'   => $filterForm,
            'stats'        => $this->effectRepo->getTriggerStats(),
            'keywordStats' => $keywordStats,
            'sort'         => $sort,
            'dir'          => $dir,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(MainEffect $effect): Response
    {
        return $this->render('admin/effects/show.html.twig', [
            'effect'         => $effect,
            'editForm'       => $this->createForm(EffectEditType::class, $effect),
            'cardRefs'       => $this->cardRepo->findReferencesByEffect($effect->getId()),
            'cardCount'      => $this->cardRepo->countByEffect($effect->getId()),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(MainEffect $effect, Request $request): Response
    {
        $form = $this->createForm(EffectEditType::class, $effect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->request->getBoolean('reparse') && $effect->getTextFr()) {
                $effect->setKeywords($this->parser->parseKeywords($effect->getTextFr()) ?: null);
            }

            $this->em->flush();
            $this->addFlash('success', 'Effet mis à jour.');
        }

        return $this->redirectToRoute('admin_effects_show', ['id' => $effect->getId()]);
    }
}
