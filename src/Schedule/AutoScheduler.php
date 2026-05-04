<?php
/**
 * Auto Scheduler
 * Generates optimized work schedules based on available time slots
 * Considers constraints: rest time, max hours per day, student preferences
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/Environment.php';

class AutoScheduler {
    private int $studentId;
    private int $targetHoursPerWeek = 20;
    private int $minDutyHours = 2;
    private int $minRestTimeMinutes = 30;
    private array $freeSlots = [];

    public function __construct(int $studentId) {
        $this->studentId = $studentId;
        $this->loadConstraints();
    }

    /**
     * Load scheduling constraints from config
     */
    private function loadConstraints(): void {
        try {
            $config = Database::query(
                "SELECT config_key, config_value FROM system_config WHERE config_key IN (?, ?, ?)",
                ['min_rest_time_minutes', 'max_daily_hours', 'week_target_hours']
            );

            foreach ($config as $setting) {
                if ($setting['config_key'] === 'min_rest_time_minutes') {
                    $this->minRestTimeMinutes = (int) $setting['config_value'];
                } elseif ($setting['config_key'] === 'week_target_hours') {
                    $this->targetHoursPerWeek = (int) $setting['config_value'];
                }
            }
        } catch (Exception $e) {
            // Use defaults if config not found
        }
    }

    /**
     * Generate optimal schedule
     */
    public function generate(): array {
        try {
            // Get student's free time slots
            $freeSlots = Database::query(
                "SELECT * FROM free_time_slots WHERE student_id = ? ORDER BY day_of_week, start_time",
                [$this->studentId]
            );

            if (empty($freeSlots)) {
                return [
                    'success' => false,
                    'error' => 'No free time slots found. Please ensure COR is uploaded and parsed.'
                ];
            }

            // Group slots by day
            $slotsByDay = $this->groupSlotsByDay($freeSlots);

            // Generate schedule
            $schedule = $this->generateOptimalSchedule($slotsByDay);

            // Save to database
            return $this->saveSchedule($schedule);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate schedule with custom target hours
     */
    public function generateWithHours(int $targetHours): array {
        $originalTarget = $this->targetHoursPerWeek;
        $this->targetHoursPerWeek = $targetHours;
        $result = $this->generate();
        $this->targetHoursPerWeek = $originalTarget;
        return $result;
    }

    /**
     * Group free time slots by day
     */
    private function groupSlotsByDay(array $slots): array {
        $grouped = [];
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        foreach ($days as $day) {
            $grouped[$day] = array_filter($slots, fn($s) => $s['day_of_week'] === $day);
        }

        return array_filter($grouped);
    }

    /**
     * Generate optimal schedule
     */
    private function generateOptimalSchedule(array $slotsByDay): array {
        $schedule = [];
        $totalHours = 0;
        $hoursPerDay = [];
        $workDays = [];

        foreach ($slotsByDay as $day => $slots) {
            if (empty($slots)) continue;

            $daySchedule = $this->createDaySchedule($day, $slots);
            if ($daySchedule !== null) {
                $schedule[$day] = $daySchedule;
                $hoursPerDay[$day] = $daySchedule['hours'];
                $totalHours += $daySchedule['hours'];
                $workDays[] = $day;
            }
        }

        return [
            'schedule' => $schedule,
            'total_hours' => $totalHours,
            'hours_per_day' => $hoursPerDay,
            'work_days' => $workDays,
            'constraints' => [
                'target_hours' => $this->targetHoursPerWeek,
                'max_daily_hours' => null,
                'min_duty_hours' => $this->minDutyHours,
                'min_rest_minutes' => $this->minRestTimeMinutes
            ]
        ];
    }

    /**
     * Create day schedule
     */
    private function createDaySchedule(string $day, array $daySlots): ?array {
        usort($daySlots, fn($a, $b) => strcmp((string) $a['start_time'], (string) $b['start_time']));

        $shifts = [];
        $totalMinutes = 0;

        foreach ($daySlots as $slot) {
            $durationMinutes = (int) ($slot['duration_minutes'] ?? 0);

            // Enforce minimum duty block of 2 hours.
            if ($durationMinutes < ($this->minDutyHours * 60)) {
                continue;
            }

            $hours = round($durationMinutes / 60, 2);
            $shifts[] = [
                'start_time' => (string) $slot['start_time'],
                'end_time' => (string) $slot['end_time'],
                'hours' => $hours,
                'slot_type' => (string) ($slot['slot_type'] ?? 'afternoon')
            ];
            $totalMinutes += $durationMinutes;
        }

        if (empty($shifts)) {
            return null;
        }

        $startTime = $shifts[0]['start_time'];
        $endTime = $shifts[count($shifts) - 1]['end_time'];
        $totalHours = round($totalMinutes / 60, 2);

        return [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'hours' => $totalHours,
            'shifts' => $shifts,
            'notes' => "Automatically scheduled from available class-free windows (excluding lunch break 12:00-13:00)."
        ];
    }

    /**
     * Save schedule to database
     */
    private function saveSchedule(array $schedule): array {
        try {
            Database::beginTransaction();

            // Clear old schedules
            Database::delete('generated_work_schedules', 'student_id = ? AND is_active = ?', 
                [$this->studentId, true]);

            $data = [
                'student_id' => $this->studentId,
                'schedule_data' => json_encode($schedule['schedule']),
                'total_recommended_hours' => $schedule['total_hours'],
                'hours_per_day' => json_encode($schedule['hours_per_day']),
                'constraints_applied' => json_encode($schedule['constraints'])
            ];

            $scheduleId = Database::insert('generated_work_schedules', $data);

            Database::commit();

            return [
                'success' => true,
                'schedule_id' => $scheduleId,
                'schedule' => $schedule
            ];
        } catch (Exception $e) {
            Database::rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get generated schedule
     */
    public function getSchedule(): array {
        $sql = "SELECT * FROM generated_work_schedules WHERE student_id = ? AND is_active = ? 
                ORDER BY generated_at DESC LIMIT 1";
        
        $record = Database::queryOne($sql, [$this->studentId, true]);

        if (!$record) {
            return [
                'success' => false,
                'error' => 'No active schedule found'
            ];
        }

        return [
            'success' => true,
            'schedule' => [
                'id' => $record['id'],
                'data' => json_decode($record['schedule_data'], true),
                'total_hours' => $record['total_recommended_hours'],
                'hours_per_day' => json_decode($record['hours_per_day'], true),
                'constraints' => json_decode($record['constraints_applied'], true),
                'generated_at' => $record['generated_at'],
                'applied_at' => $record['applied_at']
            ]
        ];
    }

    /**
     * Apply schedule (mark as active/applied)
     */
    public function applySchedule(int $scheduleId): array {
        try {
            Database::update(
                'generated_work_schedules',
                ['is_active' => true, 'applied_at' => date('Y-m-d H:i:s')],
                'id = ? AND student_id = ?',
                [$scheduleId, $this->studentId]
            );

            return [
                'success' => true,
                'message' => 'Schedule applied successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Set custom constraints
     */
    public function setConstraints(array $constraints): void {
        if (isset($constraints['target_hours_per_week'])) {
            $this->targetHoursPerWeek = (int) $constraints['target_hours_per_week'];
        }
        if (isset($constraints['min_duty_hours'])) {
            $this->minDutyHours = max(1, (int) $constraints['min_duty_hours']);
        }
        if (isset($constraints['min_rest_minutes'])) {
            $this->minRestTimeMinutes = (int) $constraints['min_rest_minutes'];
        }
    }

    /**
     * Get scheduling recommendations
     */
    public function getRecommendations(): array {
        $freeSlots = Database::query(
            "SELECT * FROM free_time_slots WHERE student_id = ?",
            [$this->studentId]
        );

        if (empty($freeSlots)) {
            return ['recommendation' => 'No schedule data available'];
        }

        $totalAvailable = array_sum(array_map(fn($s) => $s['duration_minutes'], $freeSlots)) / 60;

        return [
            'total_free_hours_per_week' => round($totalAvailable, 1),
            'recommended_hours' => $this->targetHoursPerWeek,
            'feasible' => $totalAvailable >= $this->targetHoursPerWeek,
            'message' => $totalAvailable >= $this->targetHoursPerWeek 
                ? "You have sufficient free time to work {$this->targetHoursPerWeek} hours per week"
                : "Your free time allows only {$totalAvailable} hours per week"
        ];
    }
}
