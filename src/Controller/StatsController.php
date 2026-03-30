<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatsController extends AbstractController
{
    public function __construct(
        private readonly Connection       $connection,
        private readonly CacheItemPoolInterface $cache,
    ) {}

    #[Route('/stats', name: 'stats')]
    public function index(): Response
    {
        $cached = $this->cache->getItem('stats.page_data');

        if ($cached->isHit()) {
            ['sets' => $sets, 'globalRarities' => $globalRarities] = $cached->get();

            return $this->render('stats/index.html.twig', [
                'sets'           => $sets,
                'globalRarities' => $globalRarities,
            ]);
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                cs.name        AS set_name,
                cs.reference   AS set_ref,
                cs.date        AS set_date,
                f.name         AS faction_name,
                f.code         AS faction_code,
                f.position     AS faction_position,
                r.reference    AS rarity_ref,
                r.name_en      AS rarity_name,
                r.position     AS rarity_position,
                COUNT(DISTINCT cg.id) AS nb
             FROM card_group cg
             JOIN card c       ON c.card_group_id = cg.id
             JOIN card_set cs  ON c.set_id        = cs.id
             JOIN faction f    ON cg.faction_id   = f.id
             JOIN rarity r     ON cg.rarity_id    = r.id
             GROUP BY cs.id, cs.name, cs.reference, cs.date,
                      f.id, f.name, f.code, f.position,
                      r.id, r.reference, r.name_en, r.position
             ORDER BY cs.date DESC NULLS LAST, cs.name,
                      f.position,
                      CASE r.reference WHEN \'COMMON\' THEN 1 WHEN \'RARE\' THEN 2 WHEN \'EXALTED\' THEN 3 WHEN \'UNIQUE\' THEN 4 ELSE 5 END'
        );

        // Global totals per rarity
        $globalRarityRows = $this->connection->fetchAllAssociative(
            'SELECT r.reference AS rarity_ref, r.name_en AS rarity_name, COUNT(DISTINCT cg.id) AS nb
             FROM card_group cg
             JOIN rarity r ON cg.rarity_id = r.id
             GROUP BY r.id, r.reference, r.name_en
             ORDER BY CASE r.reference WHEN \'COMMON\' THEN 1 WHEN \'RARE\' THEN 2 WHEN \'EXALTED\' THEN 3 WHEN \'UNIQUE\' THEN 4 ELSE 5 END'
        );
        $globalRarities = [];
        foreach ($globalRarityRows as $r) {
            $globalRarities[$r['rarity_ref']] = [
                'ref'  => $r['rarity_ref'],
                'name' => $r['rarity_name'] ?? $r['rarity_ref'],
                'nb'   => (int) $r['nb'],
            ];
        }

        // Nest: sets → factions → rarities
        $sets = [];
        foreach ($rows as $row) {
            $setRef     = $row['set_ref'];
            $factionCode = $row['faction_code'];

            if (!isset($sets[$setRef])) {
                $sets[$setRef] = [
                    'name'     => $row['set_name'],
                    'ref'      => $setRef,
                    'total'    => 0,
                    'factions' => [],
                ];
            }

            if (!isset($sets[$setRef]['factions'][$factionCode])) {
                $sets[$setRef]['factions'][$factionCode] = [
                    'name'      => $row['faction_name'],
                    'code'      => $factionCode,
                    'total'     => 0,
                    'rarities'  => [],
                ];
            }

            $nb = (int) $row['nb'];
            $sets[$setRef]['factions'][$factionCode]['rarities'][] = [
                'ref'  => $row['rarity_ref'],
                'name' => $row['rarity_name'] ?? $row['rarity_ref'],
                'nb'   => $nb,
            ];
            $sets[$setRef]['factions'][$factionCode]['total'] += $nb;
            $sets[$setRef]['total'] += $nb;
        }

        $cached->set(['sets' => $sets, 'globalRarities' => $globalRarities]);
        $cached->expiresAfter(3600);
        $this->cache->save($cached);

        return $this->render('stats/index.html.twig', [
            'sets'           => $sets,
            'globalRarities' => $globalRarities,
        ]);
    }
}
