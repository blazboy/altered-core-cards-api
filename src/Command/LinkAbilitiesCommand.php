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
    name: 'app:abilities:link-effects',
    description: 'Link main_effect rows to ability_trigger / ability_condition / ability_effect by splitting effect texts',
)]
class LinkAbilitiesCommand extends Command
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show stats without writing to DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Linking main_effect → ability_trigger / condition / effect');

        // Build lookup maps from the ability tables
        $io->section('Building lookup maps…');
        $triggerMap   = $this->buildTriggerMap();
        $conditionMap = $this->buildConditionMap();
        $effectMap    = $this->buildEffectMap();

        $io->text(sprintf(
            'Maps built — triggers: %d, conditions: %d, effects: %d',
            count($triggerMap),
            count($conditionMap),
            count($effectMap),
        ));

        // Load all main_effect rows and resolve IDs in PHP
        $io->section('Loading main_effect rows…');
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM main_effect');
        $io->progressStart($total);

        // Group row IDs by resolved (trigger, condition, effect) tuple for batch updates
        /** @var array<string, array{trigger: int|null, condition: int|null, effect: int|null, ids: int[]}> $groups */
        $groups  = [];
        $stats   = ['trigger' => 0, 'condition' => 0, 'effect' => 0, 'skipped' => 0];
        $offset  = 0;

        while (true) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, text_fr, text_en
                 FROM main_effect
                 ORDER BY id
                 LIMIT ? OFFSET ?',
                [self::BATCH_SIZE, $offset]
            );

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                [$triggerId, $conditionId, $effectId] = $this->resolveAbilityIds(
                    $row,
                    $triggerMap,
                    $conditionMap,
                    $effectMap,
                );

                $t = $triggerId   ?? 0;
                $c = $conditionId ?? 0;
                $e = $effectId    ?? 0;
                $key = "{$t}_{$c}_{$e}";

                if (!isset($groups[$key])) {
                    $groups[$key] = ['trigger' => $triggerId, 'condition' => $conditionId, 'effect' => $effectId, 'ids' => []];
                }
                $groups[$key]['ids'][] = (int) $row['id'];

                if ($triggerId)   $stats['trigger']++;
                if ($conditionId) $stats['condition']++;
                if ($effectId)    $stats['effect']++;
                if (!$triggerId && !$conditionId && !$effectId) $stats['skipped']++;

                $io->progressAdvance();
            }

            $offset += self::BATCH_SIZE;
        }

        $io->progressFinish();

        // Flush grouped updates
        if (!$dryRun) {
            $io->section('Writing updates…');
            $io->progressStart(count($groups));

            foreach ($groups as $group) {
                $abilityKey = ($group['trigger'] === null && $group['condition'] === null && $group['effect'] === null)
                    ? null
                    : sprintf('%d_%d_%d', $group['trigger'] ?? 0, $group['condition'] ?? 0, $group['effect'] ?? 0);

                $placeholders = implode(',', array_fill(0, count($group['ids']), '?'));
                $this->connection->executeStatement(
                    "UPDATE main_effect
                     SET ability_trigger_id   = ?,
                         ability_condition_id = ?,
                         ability_effect_id    = ?,
                         ability_key          = ?
                     WHERE id IN ({$placeholders})",
                    [$group['trigger'], $group['condition'], $group['effect'], $abilityKey, ...$group['ids']]
                );
                $io->progressAdvance();
            }

            $io->progressFinish();
        }

        $io->table(
            ['Type', 'Linked', 'Remaining'],
            [
                ['Trigger',   $stats['trigger'],   $total - $stats['trigger']],
                ['Condition', $stats['condition'],  $total - $stats['condition']],
                ['Effect',    $stats['effect'],     $total - $stats['effect']],
                ['No match',  $stats['skipped'],    ''],
            ]
        );

        if ($dryRun) {
            $io->warning('Dry-run — no changes written.');
        } else {
            $io->success('Done.');
        }

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Resolution
    // -------------------------------------------------------------------------

    private function resolveAbilityIds(
        array $row,
        array $triggerMap,
        array $conditionMap,
        array $effectMap,
    ): array {
        $textEn = trim($row['text_en'] ?? '');

        // --- Trigger: extract from text ---
        $triggerId = null;
        $phrase = $this->extractTriggerPhrase($textEn);
        if ($phrase !== null) {
            $triggerId = $triggerMap['by_text'][strtolower($phrase)] ?? null;
        }

        // --- Split text into parts ---
        [$conditionText, $effectText] = $this->splitConditionAndEffect($textEn);

        // --- Condition ---
        $conditionId = null;
        if ($conditionText !== null) {
            $conditionId = $conditionMap[strtolower(trim($conditionText))] ?? null;
        }

        // --- Effect ---
        $effectId = null;
        if ($effectText !== null) {
            $effectId = $effectMap[strtolower(trim($effectText))] ?? null;
        }

        return [$triggerId, $conditionId, $effectId];
    }

    // -------------------------------------------------------------------------
    // Text extraction helpers
    // -------------------------------------------------------------------------

    /**
     * Extract the trigger phrase from the English full-effect text.
     *
     * Examples:
     *   "When my Expedition moves forward: Gain 1 gold."
     *     → "When my Expedition moves forward"
     *   "When I leave the Expedition zone — After Rest: Gain 1 gold."
     *     → "When I leave the Expedition zone"
     */
    private function extractTriggerPhrase(string $textEn): ?string
    {
        if (!str_starts_with($textEn, 'When ') && !str_starts_with($textEn, 'At ')) {
            return null;
        }

        // Split on ": " (colon + space) — trigger ends before the colon
        if (preg_match('/^(.+?):\s+/u', $textEn, $m)) {
            return rtrim($m[1], ' —–');
        }

        // Split on " — " (em dash) used in some cards
        if (preg_match('/^(.+?)\s+[—–]\s+/u', $textEn, $m)) {
            return rtrim($m[1]);
        }

        return null;
    }

    /**
     * After removing the trigger prefix, split the remainder into condition + effect.
     *
     * Returns [conditionText|null, effectText|null].
     */
    private function splitConditionAndEffect(string $textEn): array
    {
        if ($textEn === '') {
            return [null, null];
        }

        // Remove known trigger prefixes to get the payload
        $payload = preg_replace('/^\{[HJRTDI]\}\s*/u', '', $textEn)
            ?? $textEn;
        if ($payload === $textEn) {
            $payload = preg_replace('/^At (?:Dusk|Noon)\s*[—–:\s]*/u', '', $textEn) ?? $textEn;
        }
        if ($payload === $textEn) {
            $payload = preg_replace('/^(?:When |At ).+?:\s+/u', '', $textEn) ?? $textEn;
        }

        $payload = trim($payload);

        if ($payload === '' || $payload === trim($textEn)) {
            return [null, $textEn ?: null];
        }

        // Check for condition prefix: "If ...: effect", "Unless ...: effect", "You may ...: effect"
        if (preg_match('/^(If |Unless |Each time |You may )/u', $payload)) {
            if (preg_match('/^(.+?):\s+(.+)$/us', $payload, $m)) {
                return [trim($m[1]), trim($m[2])];
            }
        }

        return [null, $payload];
    }

    // -------------------------------------------------------------------------
    // Map builders
    // -------------------------------------------------------------------------

    /**
     * @return array{by_altered: array<int, int>, by_text: array<string, int>}
     *   by_altered: alteredId → DB id
     *   by_text:    lowercase EN text → DB id
     */
    private function buildTriggerMap(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, altered_id, text_en FROM ability_trigger'
        );

        $map = ['by_altered' => [], 'by_text' => []];
        foreach ($rows as $row) {
            $map['by_altered'][(int) $row['altered_id']] = (int) $row['id'];
            if ($row['text_en'] !== null) {
                $map['by_text'][strtolower(trim($row['text_en']))] = (int) $row['id'];
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>  lowercase EN text → DB id
     */
    private function buildConditionMap(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, text_en FROM ability_condition WHERE text_en IS NOT NULL'
        );

        $map = [];
        foreach ($rows as $row) {
            // Strip trailing colon that is stored in ability_condition.text_en
            $key = strtolower(rtrim(trim($row['text_en']), ': '));
            $map[$key] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @return array<string, int>  lowercase EN text → DB id
     */
    private function buildEffectMap(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, text_en FROM ability_effect WHERE text_en IS NOT NULL'
        );

        $map = [];
        foreach ($rows as $row) {
            $map[strtolower(trim($row['text_en']))] = (int) $row['id'];
        }

        return $map;
    }
}
