<?php

namespace App\Command;

use App\Entity\AbilityCondition;
use App\Entity\AbilityEffect;
use App\Entity\AbilityTrigger;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import:abilities',
    description: 'Import Trigger / Condition / Effect abilities from market.sabotageafter.rest and link them to main_effect rows',
)]
class ImportAbilitiesCommand extends Command
{
    private const SOURCE_URL  = 'https://market.sabotageafter.rest/abilities-list';
    private const BATCH_SIZE  = 100;

    // Locales used on the source site → our column names
    private const LOCALE_MAP = [
        'fr' => 'fr',
        'en' => 'en',
        'de' => 'de',
        'es' => 'es',
        'it' => 'it',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection             $connection,
        private readonly HttpClientInterface    $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('link-only', null, InputOption::VALUE_NONE, 'Skip fetching — only link existing ability rows to main_effect')
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'Override source URL', self::SOURCE_URL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Import abilities (Trigger / Condition / Effect)');

        if (!$input->getOption('link-only')) {
            $data = $this->fetchAbilities($io, $input->getOption('url'));
            if ($data === null) {
                return Command::FAILURE;
            }

            $this->importParts($io, $data['trigger']   ?? [], 'Trigger');
            $this->importParts($io, $data['condition'] ?? [], 'Condition');
            $this->importParts($io, $data['effect']    ?? [], 'Effect');

            $this->em->flush();
            $io->success('Ability parts imported.');

            $this->enrichTranslationsFromMainEffect($io);
        }

        $this->linkMainEffects($io);

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Fetch
    // -------------------------------------------------------------------------

    private function fetchAbilities(SymfonyStyle $io, string $url): ?array
    {
        $io->info("Fetching: {$url}");

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Accept'          => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'User-Agent'      => 'Mozilla/5.0 (compatible; altered-core-bot/1.0)',
                ],
                'timeout' => 30,
            ]);
            $html = $response->getContent(false);
        } catch (\Throwable $e) {
            $io->error("Cannot fetch {$url}: " . $e->getMessage());
            return null;
        }

        if (empty($html)) {
            $io->error("Empty response from {$url}");
            return null;
        }

        // Remix streams data via remixContext.streamController.enqueue("...") where
        // the argument is a JSON-encoded string that itself contains a JSON flat array.
        $needle = 'streamController.enqueue(';
        $pos    = strpos($html, $needle);

        if ($pos !== false) {
            // The argument starts at the character right after the opening paren.
            // It is a JSON string starting with '"', so we extract it by finding
            // the matching closing '"' (respecting backslash escapes) then ')'.
            $start = $pos + strlen($needle);
            if (($html[$start] ?? '') === '"') {
                $rawArg = $this->extractJsonString($html, $start);
                if ($rawArg !== null) {
                    try {
                        $innerJson = json_decode($rawArg, flags: JSON_THROW_ON_ERROR); // step 1: unescape
                        $flat      = json_decode($innerJson, true, 512, JSON_THROW_ON_ERROR); // step 2: flat array

                        if (is_array($flat)) {
                            $io->info(sprintf('Decoded Remix flat array (%d elements).', count($flat)));
                            return $this->decodeRemixFlatArray($flat);
                        }
                    } catch (\Throwable $e) {
                        $io->warning('Failed to parse Remix flat array: ' . $e->getMessage());
                    }
                }
            }
        }

        $io->error('Could not extract ability data from the page. The Remix streaming format may have changed.');
        return null;
    }

    /**
     * Walk the HTML string starting at $offset (which must point to an opening '"')
     * and return the full JSON-encoded string literal including the surrounding quotes.
     */
    private function extractJsonString(string $html, int $offset): ?string
    {
        $len = strlen($html);
        if ($offset >= $len || $html[$offset] !== '"') {
            return null;
        }

        $i = $offset + 1;
        while ($i < $len) {
            $ch = $html[$i];
            if ($ch === '\\') {
                $i += 2; // skip escaped character
                continue;
            }
            if ($ch === '"') {
                // closing quote found
                return substr($html, $offset, $i - $offset + 1);
            }
            $i++;
        }

        return null;
    }

    /**
     * Decode Remix's flat-array serialization format.
     *
     * The flat array encodes objects as {"_N": V, ...} where:
     *   - N is the index in $flat of the key name (string)
     *   - V is the index in $flat of the value (primitive or another object/array)
     * Arrays of indices reference positions in $flat.
     *
     * Structure for this page:
     *   flat[6]  = abilityParts object descriptor {'_7':8, '_300':301, '_755':756}
     *   flat[7]  = "trigger",  flat[8]  = [idx, idx, ...] trigger item indices
     *   flat[300]= "condition",flat[301]= [idx, idx, ...] condition item indices
     *   flat[755]= "effect",   flat[756]= [idx, idx, ...] effect item indices
     */
    private function decodeRemixFlatArray(array $flat): array
    {
        $decodeItem = function (int $objIdx) use ($flat): ?array {
            $v = $flat[$objIdx] ?? null;
            if (!is_array($v) || array_is_list($v)) {
                return null;
            }
            $result = [];
            foreach ($v as $kStr => $vIdx) {
                $kNum = (int) ltrim((string) $kStr, '_');
                $key  = $flat[$kNum] ?? null;
                if ($key === null) continue;
                $result[$key] = (is_int($vIdx) && $vIdx >= 0 && isset($flat[$vIdx]))
                    ? $flat[$vIdx]
                    : null;
            }
            return $result;
        };

        $decodeList = function (int $listIdx) use ($flat, $decodeItem): array {
            $list = $flat[$listIdx] ?? [];
            if (!is_array($list) || !array_is_list($list)) return [];
            return array_values(array_filter(array_map(
                fn(int $i) => $decodeItem($i),
                $list
            )));
        };

        // Locate abilityParts VALUE index.
        // 'abilityParts' is a string at some index i in the flat array.
        // A parent object elsewhere has key "_i" pointing to the actual value index V.
        // flat[V] is the abilityParts descriptor {'_N':trigger_list_idx, ...}
        $abilityPartsValueIdx = null;
        foreach ($flat as $i => $v) {
            if ($v === 'abilityParts') {
                $keyRef = '_' . $i;
                foreach ($flat as $el) {
                    if (is_array($el) && !array_is_list($el) && isset($el[$keyRef])) {
                        $abilityPartsValueIdx = $el[$keyRef];
                        break 2;
                    }
                }
            }
        }

        if ($abilityPartsValueIdx === null) {
            throw new \RuntimeException('Could not locate "abilityParts" in Remix flat array.');
        }

        // Decode the abilityParts object to get the list indices
        $abilityPartsObj = $flat[$abilityPartsValueIdx]; // e.g. {'_7':8, '_300':301, '_755':756}
        $listIndices     = ['trigger' => null, 'condition' => null, 'effect' => null];

        foreach ($abilityPartsObj as $kStr => $vIdx) {
            $kNum = (int) ltrim((string) $kStr, '_');
            $key  = $flat[$kNum] ?? null;
            if (is_string($key) && array_key_exists($key, $listIndices)) {
                $listIndices[$key] = $vIdx;
            }
        }

        return [
            'trigger'   => $listIndices['trigger']   !== null ? $decodeList($listIndices['trigger'])   : [],
            'condition' => $listIndices['condition'] !== null ? $decodeList($listIndices['condition']) : [],
            'effect'    => $listIndices['effect']    !== null ? $decodeList($listIndices['effect'])    : [],
        ];
    }

    // -------------------------------------------------------------------------
    // Import parts
    // -------------------------------------------------------------------------

    /**
     * @param array<array{id: int, text: string, isSupport: bool, translations?: array}> $parts
     */
    private function importParts(SymfonyStyle $io, array $parts, string $type): void
    {
        $io->section("Importing {$type} (" . count($parts) . " entries)");
        $io->progressStart(count($parts));

        $batch = 0;
        foreach ($parts as $part) {
            $alteredId = (int) ($part['id'] ?? 0);
            if ($alteredId === 0) {
                $io->progressAdvance();
                continue;
            }

            $entity = match ($type) {
                'Trigger'   => $this->em->getRepository(AbilityTrigger::class)->findOneBy(['alteredId' => $alteredId])
                               ?? new AbilityTrigger(),
                'Condition' => $this->em->getRepository(AbilityCondition::class)->findOneBy(['alteredId' => $alteredId])
                               ?? new AbilityCondition(),
                default     => $this->em->getRepository(AbilityEffect::class)->findOneBy(['alteredId' => $alteredId])
                               ?? new AbilityEffect(),
            };

            $entity->setAlteredId($alteredId);
            $entity->setIsSupport((bool) ($part['isSupport'] ?? false));

            // The API exposes a single `text` field — assume English unless the
            // site returns per-locale translations in a `translations` map.
            $translations = $part['translations'] ?? [];
            if (!empty($translations)) {
                foreach (self::LOCALE_MAP as $apiLocale => $col) {
                    $text = $translations[$apiLocale] ?? $translations[$apiLocale . '-' . $apiLocale] ?? null;
                    if ($text !== null) {
                        $setter = 'setText' . ucfirst($col);
                        $entity->$setter($text);
                    }
                }
            } else {
                // Single text — treat as English
                $text = $part['text'] ?? null;
                if ($text !== null) {
                    $entity->setTextEn($text);
                }
            }

            $this->em->persist($entity);

            if (++$batch % self::BATCH_SIZE === 0) {
                $this->em->flush();
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
    }

    // -------------------------------------------------------------------------
    // Enrich translations from existing MainEffect rows
    // -------------------------------------------------------------------------

    /**
     * For each ability entity that has textEn but no textFr/textDe/etc.,
     * look for a MainEffect row whose text_en matches and copy the other locales.
     */
    private function enrichTranslationsFromMainEffect(SymfonyStyle $io): void
    {
        $io->section('Enriching ability translations from main_effect…');

        $entityClasses = [AbilityTrigger::class, AbilityCondition::class, AbilityEffect::class];

        foreach ($entityClasses as $class) {
            $entities = $this->em->getRepository($class)->findAll();
            $enriched = 0;

            foreach ($entities as $entity) {
                $needsEnrichment =
                    $entity->getTextFr() === null ||
                    $entity->getTextDe() === null ||
                    $entity->getTextEs() === null ||
                    $entity->getTextIt() === null;

                if (!$needsEnrichment) {
                    continue;
                }

                // Try matching via each available locale
                $mainEffect = null;
                $getterToCol = [
                    'getTextEn' => 'text_en',
                    'getTextFr' => 'text_fr',
                    'getTextDe' => 'text_de',
                    'getTextEs' => 'text_es',
                    'getTextIt' => 'text_it',
                ];
                foreach ($getterToCol as $getter => $col) {
                    $text = $entity->$getter();
                    if ($text === null) continue;

                    $row = $this->connection->fetchAssociative(
                        "SELECT text_fr, text_en, text_de, text_es, text_it
                         FROM main_effect
                         WHERE {$col} = ?
                         AND text_fr IS NOT NULL
                         LIMIT 1",
                        [$text]
                    );

                    if ($row) {
                        $mainEffect = $row;
                        break;
                    }
                }

                if ($mainEffect === null) {
                    continue;
                }

                if ($entity->getTextFr() === null && $mainEffect['text_fr'] !== null) {
                    $entity->setTextFr($mainEffect['text_fr']);
                }
                if ($entity->getTextEn() === null && $mainEffect['text_en'] !== null) {
                    $entity->setTextEn($mainEffect['text_en']);
                }
                if ($entity->getTextDe() === null && $mainEffect['text_de'] !== null) {
                    $entity->setTextDe($mainEffect['text_de']);
                }
                if ($entity->getTextEs() === null && $mainEffect['text_es'] !== null) {
                    $entity->setTextEs($mainEffect['text_es']);
                }
                if ($entity->getTextIt() === null && $mainEffect['text_it'] !== null) {
                    $entity->setTextIt($mainEffect['text_it']);
                }

                $enriched++;
            }

            $this->em->flush();
            $shortName = (new \ReflectionClass($class))->getShortName();
            $io->info("{$shortName}: {$enriched} rows enriched from main_effect.");
        }
    }

    // -------------------------------------------------------------------------
    // Link MainEffect rows to their ability parts
    // -------------------------------------------------------------------------

    /**
     * For each main_effect row, find the matching AbilityTrigger / AbilityCondition /
     * AbilityEffect by comparing text columns, then write the FK + abilityKey.
     *
     * Uses raw SQL for performance (can be millions of comparisons).
     */
    private function linkMainEffects(SymfonyStyle $io): void
    {
        $io->section('Linking main_effect rows to ability parts…');

        // Build in-memory maps: text_fr → alteredId (or text_en as fallback)
        $triggerMap   = $this->buildTextMap('ability_trigger');
        $conditionMap = $this->buildTextMap('ability_condition');
        $effectMap    = $this->buildTextMap('ability_effect');

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM main_effect');
        $io->progressStart($total);

        $offset  = 0;
        $linked  = 0;

        while (true) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, text_fr, text_en, text_de, text_es, text_it
                 FROM main_effect
                 ORDER BY id
                 LIMIT ? OFFSET ?',
                [self::BATCH_SIZE, $offset]
            );

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $triggerId   = $this->matchText($row, $triggerMap);
                $conditionId = $this->matchText($row, $conditionMap);
                $effectId    = $this->matchText($row, $effectMap);

                if ($triggerId === null && $conditionId === null && $effectId === null) {
                    $io->progressAdvance();
                    continue;
                }

                $t = $triggerId   ?? 0;
                $c = $conditionId ?? 0;
                $e = $effectId    ?? 0;

                $abilityKey = ($t === 0 && $c === 0 && $e === 0) ? null : "{$t}_{$c}_{$e}";

                $this->connection->executeStatement(
                    'UPDATE main_effect
                     SET ability_trigger_id   = ?,
                         ability_condition_id = ?,
                         ability_effect_id    = ?,
                         ability_key          = ?
                     WHERE id = ?',
                    [
                        $triggerId,
                        $conditionId,
                        $effectId,
                        $abilityKey,
                        $row['id'],
                    ]
                );

                $linked++;
                $io->progressAdvance();
            }

            $offset += self::BATCH_SIZE;
        }

        $io->progressFinish();
        $io->success("Linked {$linked} main_effect rows.");
    }

    /**
     * Build a map of [text_locale => ability internal id] for a given table.
     * We index all 5 locales so we can match whichever column is populated.
     *
     * @return array<string, int> text → internal DB id
     */
    private function buildTextMap(string $table): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, text_fr, text_en, text_de, text_es, text_it FROM {$table}"
        );

        $map = [];
        foreach ($rows as $row) {
            foreach (['text_fr', 'text_en', 'text_de', 'text_es', 'text_it'] as $col) {
                if ($row[$col] !== null && $row[$col] !== '') {
                    $map[trim($row[$col])] = $row['id'];
                }
            }
        }
        return $map;
    }

    /**
     * Try to find an ability internal id for this main_effect row by checking
     * all locale columns against the pre-built map.
     *
     * @param array<string, string|null> $row
     * @param array<string, int>         $map
     */
    private function matchText(array $row, array $map): ?int
    {
        foreach (['text_fr', 'text_en', 'text_de', 'text_es', 'text_it'] as $col) {
            $text = $row[$col] ?? null;
            if ($text !== null && isset($map[trim($text)])) {
                return $map[trim($text)];
            }
        }
        return null;
    }
}
