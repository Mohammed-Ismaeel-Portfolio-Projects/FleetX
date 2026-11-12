<?php

namespace App\Simulation\Core;

/**
 * Scheduler for timed events and tasks.
 */
class Scheduler
{
    private array $scheduledTasks = [];
    private int $taskIdCounter = 0;

    /**
     * Schedule a task to run at a specific simulation time.
     */
    public function scheduleAt(\DateTime $when, callable $task): int
    {
        $taskId = $this->taskIdCounter++;
        $this->scheduledTasks[] = [
            'id' => $taskId,
            'when' => clone $when,
            'task' => $task,
            'executed' => false,
        ];
        return $taskId;
    }

    /**
     * Schedule a task to run after a delay.
     */
    public function scheduleAfter(\DateInterval $delay, callable $task): int
    {
        $when = new \DateTime();
        $when->add($delay);
        return $this->scheduleAt($when, $task);
    }

    /**
     * Schedule a recurring task.
     */
    public function scheduleRecurring(\DateInterval $interval, callable $task): int
    {
        $taskId = $this->taskIdCounter++;
        $this->scheduledTasks[] = [
            'id' => $taskId,
            'interval' => $interval,
            'task' => $task,
            'lastRun' => null,
            'recurring' => true,
        ];
        return $taskId;
    }

    /**
     * Execute tasks due at the given simulation time.
     */
    public function executeDueTasks(\DateTime $currentTime): void
    {
        foreach ($this->scheduledTasks as &$task) {
            if ($task['executed'] ?? false) {
                continue;
            }

            if (isset($task['recurring']) && $task['recurring']) {
                $nextRun = $task['lastRun'] ? clone $task['lastRun'] : new \DateTime();
                $nextRun->add($task['interval']);
                if ($currentTime >= $nextRun) {
                    $task['task']();
                    $task['lastRun'] = clone $currentTime;
                }
            } else {
                if ($currentTime >= $task['when']) {
                    $task['task']();
                    $task['executed'] = true;
                }
            }
        }
        unset($task);
    }

    /**
     * Cancel a scheduled task.
     */
    public function cancelTask(int $taskId): void
    {
        foreach ($this->scheduledTasks as $key => $task) {
            if ($task['id'] === $taskId) {
                unset($this->scheduledTasks[$key]);
                break;
            }
        }
    }

    /**
     * Clear all scheduled tasks.
     */
    public function clearAll(): void
    {
        $this->scheduledTasks = [];
    }

    /**
     * Get pending task count.
     */
    public function getPendingTaskCount(): int
    {
        return count(array_filter($this->scheduledTasks, fn($t) => !($t['executed'] ?? false)));
    }
}
