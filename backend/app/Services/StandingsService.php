<?php

namespace App\Services;

use App\Enums\MatchOutcome;
use App\Enums\MatchStatus;
use App\Models\Stage;
use App\Models\StageGroup;
use App\Models\TournamentEntry;
use App\Models\TournamentMatch;

/**
 * League / group tables from approved results only.
 *
 * Table points use the stage's setting (default win 3, draw 1, loss 0). These decide group
 * positions; they are not ranking points. Ties are broken by: table points, score difference,
 * score for, wins, then name (a published head-to-head rule can replace this later; D1/D3).
 * For set-based sports the "score" is sets won.
 */
class StandingsService
{
    /** @return list<array{rank:int, entry_id:int, name:string, played:int, won:int, drawn:int, lost:int, for:int, against:int, diff:int, points:int}> */
    public function table(Stage $stage, ?StageGroup $group = null): array
    {
        [$win, $draw, $loss] = $stage->setting('table_points', FixtureService::DEFAULT_TABLE_POINTS);

        $matches = TournamentMatch::query()
            ->with(['participants', 'approvedResult'])
            ->where('stage_id', $stage->id)
            ->when($group, fn ($q) => $q->where('stage_group_id', $group->id))
            ->get();

        $rows = [];
        $entryIds = $matches->flatMap(fn ($m) => $m->participants->pluck('tournament_entry_id'))->filter()->unique();
        $names = TournamentEntry::whereIn('id', $entryIds)->pluck('display_name', 'id');
        foreach ($entryIds as $id) {
            $rows[$id] = ['entry_id' => $id, 'name' => $names[$id] ?? '?', 'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'for' => 0, 'against' => 0, 'points' => 0];
        }

        foreach ($matches as $m) {
            $result = $m->approvedResult;
            if ($m->status !== MatchStatus::Completed || ! $result || $result->outcome === MatchOutcome::Cancelled) {
                continue;
            }
            [$s1, $s2] = $this->scores($result->scores ?? []);
            $a = $m->entryIdInSlot(1);
            $b = $m->entryIdInSlot(2);
            if (! $a || ! $b) {
                continue;
            }
            foreach ([[$a, $s1, $s2, 1], [$b, $s2, $s1, 2]] as [$id, $for, $against, $slot]) {
                $rows[$id]['played']++;
                $rows[$id]['for'] += $for;
                $rows[$id]['against'] += $against;
                if ($result->winner_slot === null) {
                    $rows[$id]['drawn']++;
                    $rows[$id]['points'] += $draw;
                } elseif ($result->winner_slot === $slot) {
                    $rows[$id]['won']++;
                    $rows[$id]['points'] += $win;
                } else {
                    $rows[$id]['lost']++;
                    $rows[$id]['points'] += $loss;
                }
            }
        }

        $rows = array_values(array_map(fn ($r) => $r + ['diff' => $r['for'] - $r['against']], $rows));
        usort($rows, fn ($x, $y) => [$y['points'], $y['diff'], $y['for'], $y['won'], $x['name']] <=> [$x['points'], $x['diff'], $x['for'], $x['won'], $y['name']]);

        foreach ($rows as $i => &$row) {
            $row = ['rank' => $i + 1] + $row;
        }

        return $rows;
    }

    /** @return array{0:int, 1:int} score for slot 1 and slot 2 (sets won when sets are recorded) */
    public function scores(array $scores): array
    {
        if (! empty($scores['sets'])) {
            $a = $b = 0;
            foreach ($scores['sets'] as [$x, $y]) {
                $x > $y ? $a++ : ($y > $x ? $b++ : null);
            }

            return [$a, $b];
        }

        return [(int) ($scores['slot1'] ?? 0), (int) ($scores['slot2'] ?? 0)];
    }
}
