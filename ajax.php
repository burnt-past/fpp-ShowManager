<?php
// Called with nopage=1 so FPP skips HTML wrapping — pure JSON responses only.
header('Content-Type: application/json');

$scheduleFile = $settings['configDirectory'] . "/ShowManagerSchedule.config";

$schedule = file_exists($scheduleFile)
    ? (json_decode(file_get_contents($scheduleFile), true) ?? ['entries' => []])
    : ['entries' => []];

switch ($_GET['action'] ?? '') {

    case 'get_month':
        $year   = (int)($_GET['year']  ?? date('Y'));
        $month  = (int)($_GET['month'] ?? date('n'));
        $prefix = sprintf('%04d-%02d', $year, $month);
        echo json_encode(array_values(array_filter(
            $schedule['entries'] ?? [],
            fn($e) => str_starts_with($e['date'], $prefix)
        )));
        break;

    case 'save_entry':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['date']) || empty($body['type'])) {
            http_response_code(400); echo json_encode(['error' => 'invalid']); break;
        }
        $entry = ['id' => $body['id'] ?? uniqid('e_'), 'date' => $body['date'], 'type' => $body['type']];
        if ($body['type'] === 'show') {
            $entry['time'] = $body['time'] ?? '19:00';
            if (!empty($body['show_id'])) $entry['show_id'] = $body['show_id'];
            else $entry['rotation_ids'] = $body['rotation_ids'] ?? [];
        } elseif ($body['type'] === 'blackout') {
            $entry['reason'] = $body['reason'] ?? '';
        }
        $entries  = $schedule['entries'] ?? [];
        $replaced = false;
        foreach ($entries as &$e) {
            if ($e['id'] === $entry['id']) { $e = $entry; $replaced = true; break; }
        }
        if (!$replaced) $entries[] = $entry;
        $schedule['entries'] = $entries;
        file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true, 'entry' => $entry]);
        break;

    case 'delete_entry':
        $id = $_GET['id'] ?? '';
        $schedule['entries'] = array_values(array_filter(
            $schedule['entries'] ?? [],
            fn($e) => $e['id'] !== $id
        ));
        file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
}
