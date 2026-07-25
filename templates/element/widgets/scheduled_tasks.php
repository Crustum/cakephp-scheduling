<?php
/**
 * Scheduled Tasks Widget
 *
 * This template displays monitored scheduled tasks status from Rhythm data.
 *
 * @var \App\View\AppView $this
 * @var mixed $config
 * @var object $widget
 * @var mixed $widgetName
 */
$this->set('widget', $widget);
$this->set('config', $config);
$this->set('widgetName', $widgetName);


/**
 * Get CSS class for status badge
 *
 * @param string $status Task status
 * @return string
 */
function getStatusClass($status): string
{
    return match ($status) {
        'running' => 'info',
        'completed' => 'success',
        'failed' => 'danger',
        'skipped' => 'warning',
        default => 'secondary',
    };
}

$data = $widget->getData();
$this->set('data', $data);

$this->extend('Crustum/Rhythm.widgets/widget_base');

$this->start('widget_body');

$tasksData = $data['tasks'] ?? [];
$summary = $data['summary'] ?? [];

if (empty($tasksData)) {
    echo $this->element('Crustum/Rhythm.components/widget_placeholder', [
        'message' => 'No monitored scheduled tasks data available.'
    ]);
} else {
    $summaryStats = [
        ['label' => 'Total', 'value' => $data['total_tasks'] ?? 0],
        ['label' => 'Running', 'value' => $data['running_tasks'] ?? 0],
        ['label' => 'Completed', 'value' => $data['completed_tasks'] ?? 0],
        ['label' => 'Failed', 'value' => $data['failed_tasks'] ?? 0],
        ['label' => 'Overdue', 'value' => $data['overdue_tasks'] ?? 0],
    ];

    $head = ['Task', 'Type', 'Status', 'Last Started', 'Last Finished', 'Cron'];
    $body = [];

    foreach ($tasksData as $task) {
        $statusClass = getStatusClass($task['status'] ?? 'unknown');
        $statusBadge = $this->Rhythm->badge(ucfirst($task['status'] ?? 'unknown'), $statusClass);

        if (!empty($task['is_overdue'])) {
            $statusBadge .= ' ' . $this->Rhythm->badge('Overdue', 'critical');
        }

        $taskName = '<code>' . h($task['name'] ?? 'Unknown') . '</code>';
        $type = $this->Rhythm->badge(h($task['type'] ?? 'N/A'), 'info');

        $body[] = [
            $taskName,
            $type,
            $statusBadge,
            $task['last_started'] ?? 'Never',
            $task['last_finished'] ?? 'Never',
            '<code>' . h($task['cron_expression'] ?? 'N/A') . '</code>',
        ];
    }

    $lastUpdated = '';
    if (!empty($data['last_updated'])) {
        $lastUpdated = '<small class="text-muted">Last updated: ' . date('Y-m-d H:i:s', $data['last_updated']) . '</small>';
    }
?>
    <div class="widget-content">
        <?= $this->Rhythm->summaryStats($summaryStats) ?>
        <?= $this->Rhythm->scroll($this->Rhythm->table($head, $body)) ?>
        <?= $lastUpdated ?>
    </div>
<?php
}
$this->end();

