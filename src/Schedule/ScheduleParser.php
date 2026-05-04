<?php
/**
 * Schedule Parser
 * Extracts class schedule from COR (Certificate of Registration)
 * Supports PDF parsing via Gemini AI
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/Environment.php';

class ScheduleParser {
    private int $studentId;
    private array $parsedSchedules = [];

    public function __construct(int $studentId) {
        $this->studentId = $studentId;
    }

    /**
     * Parse COR file using Gemini AI
     * Returns extracted schedule data
     */
    public function parseUsingGemini(string $filepath): array {
        require_once __DIR__ . '/../../gemini_ai.php';

        try {
            $result = gemini_analyze_cor($filepath);
            
            if (!empty($result['ok']) || !empty($result['success'])) {
                $schedules = $result['data']['schedules'] ?? [];
                return [
                    'success' => true,
                    'schedules' => $schedules
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to parse COR'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Manually add schedule (for testing or manual entry)
     */
    public function addSchedule(array $schedule): array {
        // Validate required fields
        $required = ['subject_code', 'subject_name', 'day_of_week', 'start_time', 'end_time'];
        foreach ($required as $field) {
            if (!isset($schedule[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }

        // Validate time format
        if (!$this->isValidTime($schedule['start_time']) || !$this->isValidTime($schedule['end_time'])) {
            return [
                'success' => false,
                'error' => 'Invalid time format. Use HH:MM'
            ];
        }

        // Validate day of week
        $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        if (!in_array($schedule['day_of_week'], $validDays)) {
            return [
                'success' => false,
                'error' => 'Invalid day of week'
            ];
        }

        $this->parsedSchedules[] = $schedule;
        return [
            'success' => true,
            'schedule' => $schedule
        ];
    }

    /**
     * Save parsed schedules to database
     */
    public function saveToDatabase(?int $corUploadId = null): array {
        try {
            Database::beginTransaction();

            $saved = 0;
            foreach ($this->parsedSchedules as $schedule) {
                $data = [
                    'student_id' => $this->studentId,
                    'cor_upload_id' => $corUploadId,
                    'subject_code' => $schedule['subject_code'] ?? null,
                    'subject_name' => $schedule['subject_name'] ?? null,
                    'day_of_week' => $schedule['day_of_week'] ?? null,
                    'start_time' => $schedule['start_time'] ?? null,
                    'end_time' => $schedule['end_time'] ?? null,
                    'room' => $schedule['room'] ?? null,
                    'instructor' => $schedule['instructor'] ?? null,
                    'units' => $schedule['units'] ?? null
                ];

                Database::insert('class_schedules', $data);
                $saved++;
            }

            Database::commit();

            return [
                'success' => true,
                'saved_count' => $saved,
                'schedules' => $this->parsedSchedules
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
     * Get student's current schedule
     */
    public function getStudentSchedule(): array {
        $sql = "SELECT * FROM class_schedules WHERE student_id = ? ORDER BY day_of_week, start_time";
        $schedules = Database::query($sql, [$this->studentId]);

        return [
            'success' => true,
            'schedules' => $schedules,
            'count' => count($schedules)
        ];
    }

    /**
     * Identify free time slots based on schedule
     */
    public function identifyFreeTimeSlots(array $workingHours = []): array {
        $defaultWorkingHours = $workingHours ?: [
            'start' => '08:00',
            'end' => '17:00'
        ];

        // Get current schedule
        $scheduleResult = $this->getStudentSchedule();
        if (!$scheduleResult['success']) {
            return $scheduleResult;
        }

        $schedules = $scheduleResult['schedules'];
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $freeSlots = [];

        try {
            Database::beginTransaction();

            // Clear previous derived slots to avoid duplicates on re-run.
            Database::delete('free_time_slots', 'student_id = ?', [$this->studentId]);

            foreach ($days as $day) {
                // Get all classes for this day
                $dayClasses = array_filter($schedules, fn($s) => $s['day_of_week'] === $day);
                
                // Sort by start time
                usort($dayClasses, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));

                $freeSlots[$day] = $this->calculateFreeSlotsForDay(
                    $day,
                    $dayClasses,
                    $defaultWorkingHours
                );
            }

            // Save to database
            foreach ($freeSlots as $day => $slots) {
                foreach ($slots as $slot) {
                    Database::insert('free_time_slots', [
                        'student_id' => $this->studentId,
                        'day_of_week' => $day,
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                        'duration_minutes' => $slot['duration_minutes'],
                        'slot_type' => $slot['slot_type']
                    ]);
                }
            }

            Database::commit();

            return [
                'success' => true,
                'free_slots' => $freeSlots
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
     * Calculate free slots for a specific day
     */
    private function calculateFreeSlotsForDay(string $day, array $dayClasses, array $workingHours): array {
        $slots = [];
        $workStart = strtotime($workingHours['start']);
        $workEnd = strtotime($workingHours['end']);
        $currentTime = $workStart;

        // Sort classes by start time
        usort($dayClasses, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));

        foreach ($dayClasses as $class) {
            $classStart = strtotime($class['start_time']);
            $classEnd = strtotime($class['end_time']);

            // If there's a gap before this class
            if ($currentTime < $classStart) {
                $this->appendDutySlotsWithLunchBreak($slots, $currentTime, $classStart);
            }

            $currentTime = max($currentTime, $classEnd);
        }

        // Check for free time after last class
        if ($currentTime < $workEnd) {
            $this->appendDutySlotsWithLunchBreak($slots, $currentTime, $workEnd);
        }

        return $slots;
    }

    /**
     * Add duty slots while excluding lunch break (12:00-13:00) and applying 2-hour minimum.
     */
    private function appendDutySlotsWithLunchBreak(array &$slots, int $startTs, int $endTs): void {
        $lunchStart = strtotime('12:00');
        $lunchEnd = strtotime('13:00');

        $segments = [];

        // No overlap with lunch, keep as one segment.
        if ($endTs <= $lunchStart || $startTs >= $lunchEnd) {
            $segments[] = [$startTs, $endTs];
        } else {
            // Split around lunch break.
            if ($startTs < $lunchStart) {
                $segments[] = [$startTs, $lunchStart];
            }
            if ($endTs > $lunchEnd) {
                $segments[] = [$lunchEnd, $endTs];
            }
        }

        foreach ($segments as [$segStart, $segEnd]) {
            $duration = (int) round(($segEnd - $segStart) / 60);

            // Minimum duty block: 2 hours.
            if ($duration < 120) {
                continue;
            }

            $slots[] = [
                'start_time' => date('H:i', $segStart),
                'end_time' => date('H:i', $segEnd),
                'duration_minutes' => $duration,
                'slot_type' => $this->getSlotType(date('H:i', $segStart))
            ];
        }
    }

    /**
     * Determine slot type (morning/afternoon/evening)
     */
    private function getSlotType(string $time): string {
        [$hour] = explode(':', $time);
        $hour = (int) $hour;

        if ($hour < 12) {
            return 'morning';
        } elseif ($hour < 17) {
            return 'afternoon';
        } else {
            return 'evening';
        }
    }

    /**
     * Validate time format (HH:MM)
     */
    private function isValidTime(string $time): bool {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
    }

    /**
     * Clear existing schedules (for re-parsing)
     */
    public function clearSchedules(): array {
        try {
            Database::beginTransaction();

            Database::delete('free_time_slots', 'student_id = ?', [$this->studentId]);
            Database::delete('class_schedules', 'student_id = ?', [$this->studentId]);

            Database::commit();

            $this->parsedSchedules = [];
            return ['success' => true];
        } catch (Exception $e) {
            Database::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
