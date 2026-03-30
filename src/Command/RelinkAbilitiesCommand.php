<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:abilities:relink',
    description: 'Re-link main_effect rows using [] bracket structure from text_en',
)]
class RelinkAbilitiesCommand extends Command
{
    private const BATCH_SIZE = 500;

    /**
     * Altered IDs for symbol / time-of-day triggers detected from text.
     * Used in parseTrigger() for text-prefix detection.
     */
    private const SYMBOL_TRIGGER_MAP = [
        // [{H}, alteredId, stripPattern]
        ['/^\{H\}/u', 7,   '/^\{H\}\s*/u'],
        ['/^\{J\}/u', 24,  '/^\{J\}\s*/u'],
        ['/^\{R\}/u', 4,   '/^\{R\}\s*/u'],
        ['/^\{D\}/u', 18,  '/^\{D\}\s*/u'],
        // {T}, {1}, {X}, {I}… any remaining {}: with a colon separator
        ['/^\{[^HJRD}][^}]*\}/u', 342, '/^[^:]+:\s*/u'],
        ['/^At Dusk\b/ui',  22,  '/^At Dusk\s*[—–:\s]*/u'],
        ['/^At Noon\b/ui', 134,  '/^At Noon\s*[—–:\s]*/u'],
    ];

    /** Condition starters that get text-matched against ability_condition */
    private const CONDITION_STARTERS = ['If ', 'Unless ', 'You may ', 'Each time ', 'Roll '];

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run',      null, InputOption::VALUE_NONE, 'Show stats without writing to DB')
            ->addOption('fill-missing', null, InputOption::VALUE_NONE, 'Insert missing condition/effect texts from card text before relinking');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Re-linking main_effect → ability_trigger / condition / effect ([] parsing)');

        if ($input->getOption('fill-missing') && !$dryRun) {
            $this->fillMissingAbilities($io);
        }

        $io->section('Building lookup maps…');
        $triggerMap   = $this->buildTriggerMap();   // by_altered + by_text (sorted longest-first)
        $conditionMap = $this->buildConditionMap(); // lc(text without trailing colon) → dbId
        $effectMap    = $this->buildEffectMap();    // lc(text) → dbId

        $io->text(sprintf(
            'Maps built — triggers: %d text entries, conditions: %d, effects: %d',
            count($triggerMap['by_text']),
            count($conditionMap),
            count($effectMap),
        ));

        // --- Pass 1: resolve all rows in PHP ---
        $io->section('Resolving rows…');
        $total  = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM main_effect');
        $io->progressStart($total);

        $groups = [];   // key => {trigger, condition, effect, ids[]}
        $stats  = ['trigger' => 0, 'condition' => 0, 'effect' => 0, 'skipped' => 0];
        $offset = 0;

        while (true) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, text_en FROM main_effect ORDER BY id LIMIT ? OFFSET ?',
                [self::BATCH_SIZE, $offset],
            );

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                [$triggerId, $conditionId, $effectId] = $this->resolve(
                    (string) ($row['text_en'] ?? ''),
                    $triggerMap,
                    $conditionMap,
                    $effectMap,
                );

                $t = $triggerId   ?? 0;
                $c = $conditionId ?? 0;
                $e = $effectId    ?? 0;
                $gk = "{$t}_{$c}_{$e}";

                if (!isset($groups[$gk])) {
                    $groups[$gk] = [
                        'trigger'   => $triggerId,
                        'condition' => $conditionId,
                        'effect'    => $effectId,
                        'ids'       => [],
                    ];
                }
                $groups[$gk]['ids'][] = (int) $row['id'];

                if ($triggerId)   $stats['trigger']++;
                if ($conditionId) $stats['condition']++;
                if ($effectId)    $stats['effect']++;
                if (!$triggerId && !$conditionId && !$effectId) $stats['skipped']++;

                $io->progressAdvance();
            }

            $offset += self::BATCH_SIZE;
        }

        $io->progressFinish();

        $io->table(
            ['Type', 'Linked', 'Remaining'],
            [
                ['Trigger',   $stats['trigger'],   $total - $stats['trigger']],
                ['Condition', $stats['condition'],  $total - $stats['condition']],
                ['Effect',    $stats['effect'],     $total - $stats['effect']],
                ['No match',  $stats['skipped'],    ''],
            ],
        );

        // --- Pass 2: batch UPDATE ---
        if (!$dryRun) {
            $io->section('Writing updates…');
            $io->progressStart(count($groups));

            foreach ($groups as $g) {
                $abilityKey = ($g['trigger'] === null && $g['condition'] === null && $g['effect'] === null)
                    ? null
                    : sprintf('%d_%d_%d', $g['trigger'] ?? 0, $g['condition'] ?? 0, $g['effect'] ?? 0);

                $ph = implode(',', array_fill(0, count($g['ids']), '?'));
                $this->connection->executeStatement(
                    "UPDATE main_effect
                     SET ability_trigger_id   = ?,
                         ability_condition_id = ?,
                         ability_effect_id    = ?,
                         ability_key          = ?
                     WHERE id IN ({$ph})",
                    [$g['trigger'], $g['condition'], $g['effect'], $abilityKey, ...$g['ids']],
                );
                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('Done.');
        } else {
            $io->warning('Dry-run — no changes written.');
        }

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Core resolution
    // -------------------------------------------------------------------------

    /**
     * Returns [triggerDbId|null, conditionDbId|null, effectDbId|null].
     */
    private function resolve(
        string $textEn,
        array $triggerMap,
        array $conditionMap,
        array $effectMap,
    ): array {
        $textEn = trim($textEn);

        if ($textEn === '') {
            return [null, null, null];
        }

        // Step 1 — trigger
        [$triggerAlteredId, $payload] = $this->parseTrigger($textEn, $triggerMap['by_text']);

        $triggerId = null;
        if ($triggerAlteredId !== null) {
            $triggerId = $triggerMap['by_altered'][$triggerAlteredId] ?? null;
        }

        // Step 2 — condition
        [$conditionAlteredId, $conditionTextKey, $effectText] = $this->parseCondition($payload);

        $conditionId = null;
        if ($conditionTextKey !== null) {
            // Text-match condition (If …, Unless …, You may …, etc.)
            $conditionId = $conditionMap[$conditionTextKey] ?? null;
        } elseif ($conditionAlteredId !== null) {
            // [] = no condition — use the canonical no-condition entry (highest altered_id)
            $conditionId = $triggerMap['no_cond_id'] ?? null;
        }

        // Step 3 — effect
        $effectId = null;
        if ($effectText !== null && $effectText !== '') {
            $effectId = $effectMap[strtolower(trim($effectText))] ?? null;
        }

        return [$triggerId, $conditionId, $effectId];
    }

    // -------------------------------------------------------------------------
    // Trigger parsing
    // -------------------------------------------------------------------------

    /**
     * Returns [alteredId|null, payload].
     * Detects trigger purely from text prefix; $triggerByText is sorted longest-first.
     */
    private function parseTrigger(string $text, array $triggerByText): array
    {
        // --- Symbol / time-of-day triggers: detect from text prefix ---
        foreach (self::SYMBOL_TRIGGER_MAP as [$detectPattern, $alteredId, $stripPattern]) {
            if (preg_match($detectPattern, $text)) {
                $stripped = preg_replace($stripPattern, '', $text);
                return [$alteredId, ltrim($stripped ?? $text)];
            }
        }

        // --- [] at start = no trigger / STATIC / KEYWORD / SORT (alteredId=1) ---
        if (str_starts_with($text, '[]')) {
            return [1, substr($text, 2)];
        }

        // --- TRIGGERED / unknown: try all known trigger texts (longest-first) ---
        $lcText = strtolower($text);
        foreach ($triggerByText as $lcTrigger => $alteredId) {
            if (!str_starts_with($lcText, $lcTrigger)) {
                continue;
            }

            $remainder = substr($text, strlen($lcTrigger));
            // Strip the separator that follows the trigger text
            $remainder = preg_replace('/^\s*[—–:]\s*/u', '', $remainder) ?? $remainder;
            $remainder = ltrim($remainder);

            return [$alteredId, $remainder];
        }

        // --- Fallback: no trigger ---
        return [1, $text];
    }

    // -------------------------------------------------------------------------
    // Condition parsing
    // -------------------------------------------------------------------------

    /**
     * Returns [alteredId|null, conditionTextKey|null, effectText].
     * - alteredId=2  → "no condition" (stored as [] in ability_condition)
     * - alteredId=null, conditionTextKey=string → text-match condition
     */
    private function parseCondition(string $payload): array
    {
        $payload = ltrim($payload);

        // [] = explicit "no condition"
        if (str_starts_with($payload, '[]')) {
            return [2, null, substr($payload, 2)];
        }

        // Condition by text: "If …: effect", "You may …. If you do: effect", etc.
        foreach (self::CONDITION_STARTERS as $starter) {
            if (!str_starts_with($payload, $starter)) {
                continue;
            }

            // Split on first ": " to get condition and effect
            if (preg_match('/^(.+?):\s+(.+)$/us', $payload, $m)) {
                // Store WITHOUT trailing colon as lookup key (map keys also have it stripped)
                $condKey = strtolower(trim($m[1]));
                return [null, $condKey, trim($m[2])];
            }
        }

        // No condition marker → treat as "no condition" (alteredId=2)
        return [2, null, $payload];
    }

    // -------------------------------------------------------------------------
    // Fill missing condition/effect entries from card text
    // -------------------------------------------------------------------------

    /**
     * Parse all main_effect rows, extract condition and effect texts that have
     * no matching entry in ability_condition / ability_effect, and insert them
     * as new rows with NULL altered_id so subsequent relinking can find them.
     */
    private function fillMissingAbilities(SymfonyStyle $io): void
    {
        $io->section('Filling missing ability entries from card text…');

        // Build current lookup maps (lc → id)
        $conditionMap = $this->buildConditionMap();
        $effectMap    = $this->buildEffectMap();
        $triggerMap   = $this->buildTriggerMap();

        $missingConditions = []; // lc(text with colon) → canonical text
        $missingEffects    = [];

        $offset = 0;
        while (true) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, text_en FROM main_effect WHERE text_en IS NOT NULL ORDER BY id LIMIT ? OFFSET ?',
                [self::BATCH_SIZE, $offset],
            );
            if (empty($rows)) break;

            foreach ($rows as $row) {
                [, $conditionId, $effectId] = $this->resolve(
                    (string) $row['text_en'],
                    $triggerMap,
                    $conditionMap,
                    $effectMap,
                );

                // If condition not matched, extract the text via parseCondition
                if ($conditionId === null) {
                    [$triggerAlteredId, $payload] = $this->parseTrigger(
                        trim((string) $row['text_en']),
                        $triggerMap['by_text'],
                    );
                    $payload = ltrim($payload);

                    if (!str_starts_with($payload, '[]')) {
                        foreach (self::CONDITION_STARTERS as $starter) {
                            if (str_starts_with($payload, $starter)) {
                                if (preg_match('/^(.+?):\s+(.+)$/us', $payload, $m)) {
                                    $condText = trim($m[1]) . ':';
                                    $lcKey    = strtolower(rtrim(trim($condText), ': '));
                                    if (!isset($conditionMap[$lcKey])) {
                                        $missingConditions[$lcKey] = $condText;
                                    }
                                    // Re-parse effectText for this row
                                    $effectText = trim($m[2]);
                                    $lcEffect   = strtolower($effectText);
                                    if ($effectText !== '' && !isset($effectMap[$lcEffect])) {
                                        $missingEffects[$lcEffect] = $effectText;
                                    }
                                }
                                break;
                            }
                        }
                    }
                } elseif ($effectId === null) {
                    // Condition matched but effect not — extract effect text
                    [, , $effectText] = $this->parseConditionInternal(trim((string) $row['text_en']), $triggerMap);
                    if ($effectText !== null && $effectText !== '') {
                        $lcEffect = strtolower($effectText);
                        if (!isset($effectMap[$lcEffect])) {
                            $missingEffects[$lcEffect] = $effectText;
                        }
                    }
                }
            }

            $offset += self::BATCH_SIZE;
        }

        $io->text(sprintf(
            'Found %d missing conditions and %d missing effects.',
            count($missingConditions),
            count($missingEffects),
        ));

        // Use synthetic altered_ids starting beyond any market/known IDs
        // (market currently tops out at ~795; we start at 10000 to leave headroom)
        $nextCondId = max(
            10000,
            (int) $this->connection->fetchOne('SELECT COALESCE(MAX(altered_id), 0) FROM ability_condition') + 1,
        );
        $nextEffId = max(
            10000,
            (int) $this->connection->fetchOne('SELECT COALESCE(MAX(altered_id), 0) FROM ability_effect') + 1,
        );

        // Insert missing conditions
        $newConditions = 0;
        foreach ($missingConditions as $text) {
            // Double-check text_en doesn't already exist (text_en has no unique index)
            $exists = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM ability_condition WHERE text_en = ?',
                [$text],
            );
            if ($exists > 0) continue;

            $this->connection->executeStatement(
                'INSERT INTO ability_condition (altered_id, text_en, is_support) VALUES (?, ?, false)',
                [$nextCondId++, $text],
            );
            $newConditions++;
        }

        // Insert missing effects
        $newEffects = 0;
        foreach ($missingEffects as $text) {
            $exists = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM ability_effect WHERE text_en = ?',
                [$text],
            );
            if ($exists > 0) continue;

            $this->connection->executeStatement(
                'INSERT INTO ability_effect (altered_id, text_en, is_support) VALUES (?, ?, false)',
                [$nextEffId++, $text],
            );
            $newEffects++;
        }

        $io->success(sprintf('Inserted %d new conditions and %d new effects.', $newConditions, $newEffects));
    }

    /**
     * Helper: extract the effect text after condition resolution, for rows
     * where the condition was matched but the effect was not.
     */
    private function parseConditionInternal(string $textEn, array $triggerMap): array
    {
        $textEn = trim($textEn);
        if ($textEn === '') return [null, null, null];

        [$triggerAlteredId, $payload] = $this->parseTrigger($textEn, $triggerMap['by_text']);
        $payload = ltrim($payload);

        if (str_starts_with($payload, '[]')) {
            return [null, null, substr($payload, 2)];
        }

        foreach (self::CONDITION_STARTERS as $starter) {
            if (str_starts_with($payload, $starter)) {
                if (preg_match('/^(.+?):\s+(.+)$/us', $payload, $m)) {
                    return [null, strtolower(trim($m[1])), trim($m[2])];
                }
            }
        }

        return [null, null, $payload];
    }

    // -------------------------------------------------------------------------
    // Map builders
    // -------------------------------------------------------------------------

    /**
     * @return array{
     *   by_altered: array<int, int>,
     *   by_text: array<string, int>,
     *   no_cond_id: int|null,
     * }
     */
    private function buildTriggerMap(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, altered_id, text_en FROM ability_trigger',
        );

        $byAltered = [];
        $byText    = [];

        foreach ($rows as $row) {
            $aid = (int) $row['altered_id'];
            $did = (int) $row['id'];

            $byAltered[$aid] = $did;

            if ($row['text_en'] !== null && $row['text_en'] !== '[]') {
                // Strip trailing colon/dash from trigger text for matching
                $key = strtolower(rtrim(trim($row['text_en']), ': '));
                $byText[$key] = $aid; // stores alteredId so we can look up dbId later
            }
        }

        // Sort by key length descending (longest match wins)
        uksort($byText, fn($a, $b) => strlen($b) <=> strlen($a));

        // Canonical "no condition" DB id: pick the '[]' condition with the highest altered_id
        $noCondRow = $this->connection->fetchAssociative(
            "SELECT id FROM ability_condition WHERE text_en = '[]' ORDER BY altered_id DESC LIMIT 1",
        );
        $noCondId = $noCondRow ? (int) $noCondRow['id'] : null;

        return ['by_altered' => $byAltered, 'by_text' => $byText, 'no_cond_id' => $noCondId];
    }

    /**
     * @return array<string, int>  lc(text_en without trailing colon) → DB id
     */
    private function buildConditionMap(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, text_en FROM ability_condition WHERE text_en IS NOT NULL AND text_en != '[]'",
        );

        $map = [];
        foreach ($rows as $row) {
            $key = strtolower(rtrim(trim($row['text_en']), ': '));
            $map[$key] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @return array<string, int>  lc(text_en) → DB id
     */
    private function buildEffectMap(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, text_en FROM ability_effect WHERE text_en IS NOT NULL AND text_en != '[]'",
        );

        $map = [];
        foreach ($rows as $row) {
            $map[strtolower(trim($row['text_en']))] = (int) $row['id'];
        }

        return $map;
    }
}
