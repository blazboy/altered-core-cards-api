<?php

namespace App\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/autocomplete', name: 'admin_autocomplete_')]
class EffectAutocompleteController extends AbstractController
{
    public function __construct(private readonly Connection $connection) {}

    #[Route('/conditions', name: 'conditions', methods: ['GET'])]
    public function conditions(Request $request): JsonResponse
    {
        $q = trim($request->query->getString('q'));

        if (strlen($q) < 2) {
            return $this->json([]);
        }

        $rows = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT text_fr
             FROM ability_condition
             WHERE text_fr ILIKE ?
             ORDER BY text_fr
             LIMIT 10',
            ['%' . $q . '%']
        );

        return $this->json($rows);
    }
}
