<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ProcurementCentre;
use App\Models\Slot;

class SlotGenerator
{
    private SettingService $settings;

    public function __construct()
    {
        $this->settings = new SettingService();
    }

    public function generate(?int $centreId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $today = date('Y-m-d');
        $horizon = max(1, $this->settings->getInt('slot.horizon_days', 7));
        $from = $dateFrom ?? $today;
        $to = $dateTo ?? date('Y-m-d', strtotime($today . ' + ' . $horizon . ' days'));

        if (strtotime($from) > strtotime($to)) {
            $from = $today;
            $to = date('Y-m-d', strtotime($today . ' + ' . $horizon . ' days'));
        }

        $where = ["status = 'ACTIVE'", 'deleted_at IS NULL'];
        $params = [];
        if ($centreId !== null) {
            $where[] = 'id = ?';
            $params[] = $centreId;
        }
        $centres = Database::select(
            "SELECT * FROM procurement_centres WHERE " . implode(' AND ', $where),
            $params
        );

        $summary = [
            'processed_centres' => 0,
            'generated' => 0,
            'skipped_closed' => 0,
            'skipped_break' => 0,
            'skipped_existing' => 0,
            'dates' => [],
        ];

        $breaks = $this->breaks();
        $slotModel = new Slot();

        foreach ($centres as $centre) {
            $summary['processed_centres']++;
            $centreSummary = ['generated' => 0, 'skipped_existing' => 0];

            $workingDays = $this->parseWorkingDays((string) ($centre['working_days'] ?? ''));
            $openStart = $centre['working_hours_start'] ?? null;
            $openEnd = $centre['working_hours_end'] ?? null;
            $duration = max(5, (int) ($centre['slot_duration_minutes'] ?? $this->settings->getInt('slot.default_duration_minutes', 15)));
            $capacity = $this->settings->getInt('slot.default_capacity', 10);

            if ($openStart === null || $openEnd === null) {
                continue;
            }

            $cursor = $from;
            while ($cursor <= $to) {
                $dayName = strtoupper(date('D', strtotime($cursor)));

                if (empty($workingDays) || !in_array($dayName, $workingDays, true)) {
                    $summary['skipped_closed']++;
                    $summary['dates'][$cursor] = 'closed';
                    $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
                    continue;
                }

                $slotsForDate = $this->buildSlotsForDate($openStart, $openEnd, $duration, $capacity, $breaks);
                $generated = 0;
                $skipped = 0;

                foreach ($slotsForDate as $slot) {
                    if ($slotModel->findByCentreDateTime((int) $centre['id'], $cursor, $slot['start'], $slot['end']) !== null) {
                        $summary['skipped_existing']++;
                        $skipped++;
                        continue;
                    }
                    $slotModel->insert([
                        'centre_id' => (int) $centre['id'],
                        'date' => $cursor,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'capacity' => $slot['capacity'],
                        'booked_count' => 0,
                        'status' => Slot::STATUS_ACTIVE,
                        'created_by' => null,
                    ]);
                    $generated++;
                }

                $summary['generated'] += $generated;
                $centreSummary['generated'] += $generated;
                $centreSummary['skipped_existing'] += $skipped;
                $summary['dates'][$cursor] = $generated . ' slots';

                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }

            if ($centreId !== null) {
                $summary['centre'] = [
                    'id' => (int) $centre['id'],
                    'name' => $centre['name'],
                    'generated' => $centreSummary['generated'],
                    'skipped_existing' => $centreSummary['skipped_existing'],
                ];
            }
        }

        return $summary;
    }

    public function breaks(): array
    {
        $raw = $this->settings->get('slot.breaks', []);
        if (!is_array($raw)) {
            return [];
        }

        $parsed = [];
        foreach ($raw as $range) {
            if (!is_string($range)) {
                continue;
            }
            $parts = explode('-', $range);
            if (count($parts) === 2) {
                $parsed[] = ['start' => trim($parts[0]), 'end' => trim($parts[1])];
            }
        }
        return $parsed;
    }

    private function buildSlotsForDate(string $openStart, string $openEnd, int $duration, int $capacity, array $breaks): array
    {
        $startMin = $this->toMinutes($openStart);
        $endMin = $this->toMinutes($openEnd);

        if ($endMin <= $startMin) {
            return [];
        }

        $slots = [];
        $t = $startMin;
        while ($t < $endMin) {
            $slotEnd = $t + $duration;
            if ($slotEnd > $endMin) {
                break;
            }

            $startStr = $this->fromMinutes($t);
            $endStr = $this->fromMinutes($slotEnd);

            if ($this->isInsideBreak($startStr, $endStr, $breaks)) {
                $t += $duration;
                continue;
            }

            $slots[] = [
                'start' => $startStr,
                'end' => $endStr,
                'capacity' => $capacity,
            ];
            $t += $duration;
        }

        return $slots;
    }

    private function isInsideBreak(string $startStr, string $endStr, array $breaks): bool
    {
        $start = $this->toMinutes($startStr);
        $end = $this->toMinutes($endStr);

        foreach ($breaks as $break) {
            $bStart = $this->toMinutes($break['start'] ?? '');
            $bEnd = $this->toMinutes($break['end'] ?? '');
            if ($bEnd <= $bStart) {
                continue;
            }
            if ($start < $bEnd && $end > $bStart) {
                return true;
            }
        }
        return false;
    }

    private function parseWorkingDays(string $csv): array
    {
        $days = array_filter(array_map('strtoupper', array_map('trim', explode(',', $csv))));
        return array_values($days);
    }

    private function toMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    private function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
