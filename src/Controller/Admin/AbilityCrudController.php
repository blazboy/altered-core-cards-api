<?php

namespace App\Controller\Admin;

use App\Entity\AbilityCondition;
use App\Entity\AbilityEffect;
use App\Entity\AbilityTrigger;
use App\Form\AbilityEditType;
use App\Repository\AbilityConditionRepository;
use App\Repository\AbilityEffectRepository;
use App\Repository\AbilityTriggerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/abilities', name: 'admin_abilities_')]
class AbilityCrudController extends AbstractController
{
    private const PER_PAGE = 50;

    private const TYPES = ['trigger', 'condition', 'effect'];

    private const TYPE_CLASSES = [
        'trigger'   => AbilityTrigger::class,
        'condition' => AbilityCondition::class,
        'effect'    => AbilityEffect::class,
    ];

    public function __construct(
        private readonly AbilityTriggerRepository   $triggerRepo,
        private readonly AbilityConditionRepository $conditionRepo,
        private readonly AbilityEffectRepository    $effectRepo,
        private readonly EntityManagerInterface     $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $type = $request->query->get('type', 'trigger');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'trigger';
        }

        $q    = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));

        [$abilities, $total] = $this->getRepo($type)->findFiltered($q, $page, self::PER_PAGE);

        return $this->render('admin/abilities/index.html.twig', [
            'type'       => $type,
            'abilities'  => $abilities,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'q'          => $q,
            'counts'     => $this->getCounts(),
        ]);
    }

    #[Route('/{type}/{id}/edit', name: 'edit', requirements: ['type' => 'trigger|condition|effect', 'id' => '\d+'], methods: ['GET'])]
    public function edit(string $type, int $id): Response
    {
        $ability = $this->findOrNotFound($type, $id);
        $form    = $this->createForm(AbilityEditType::class, $ability, ['data_class' => self::TYPE_CLASSES[$type]]);

        return $this->render('admin/abilities/edit.html.twig', [
            'type'    => $type,
            'ability' => $ability,
            'form'    => $form,
        ]);
    }

    #[Route('/{type}/{id}/edit', name: 'update', requirements: ['type' => 'trigger|condition|effect', 'id' => '\d+'], methods: ['POST'])]
    public function update(string $type, int $id, Request $request): Response
    {
        $ability = $this->findOrNotFound($type, $id);
        $form    = $this->createForm(AbilityEditType::class, $ability, ['data_class' => self::TYPE_CLASSES[$type]]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Ability mise à jour.');
            return $this->redirectToRoute('admin_abilities_edit', ['type' => $type, 'id' => $id]);
        }

        return $this->render('admin/abilities/edit.html.twig', [
            'type'    => $type,
            'ability' => $ability,
            'form'    => $form,
        ]);
    }

    // -------------------------------------------------------------------------

    private function getRepo(string $type): AbilityTriggerRepository|AbilityConditionRepository|AbilityEffectRepository
    {
        return match ($type) {
            'trigger'   => $this->triggerRepo,
            'condition' => $this->conditionRepo,
            default     => $this->effectRepo,
        };
    }

    private function findOrNotFound(string $type, int $id): AbilityTrigger|AbilityCondition|AbilityEffect
    {
        $entity = $this->getRepo($type)->find($id);
        if (!$entity) {
            throw $this->createNotFoundException("Ability {$type} #{$id} introuvable.");
        }
        return $entity;
    }

    private function getCounts(): array
    {
        return [
            'trigger'   => $this->triggerRepo->count([]),
            'condition' => $this->conditionRepo->count([]),
            'effect'    => $this->effectRepo->count([]),
        ];
    }
}
