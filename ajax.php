<?php
header('Content-Type: application/json');

$scheduleFile = $settings['configDirectory'] . "/ShowManagerSchedule.config";
$schedule = file_exists($scheduleFile)
    ? (json_decode(file_get_contents($scheduleFile), true) ?? ['entries' => [], 'rules' => []])
    : ['entries' => [], 'rules' => []];

// Expand a single rule into virtual show entries for a given YYYY-MM prefix.
function expand_rule_for_month($rule, $prefix) {
    $out   = [];
    $days  = $rule['days']         ?? [0,1,2,3,4,5,6];
    $wStart = $rule['window_start'] ?? '19:00';
    $wEnd   = $rule['window_end']   ?? null;
    $ivMins = (int)($rule['interval_mins'] ?? 0);

    // Build the list of times-per-day
    $times = [$wStart];
    if ($wEnd && $ivMins > 0) {
        $times = [];
        $t = strtotime("2000-01-01 $wStart");
        $e = strtotime("2000-01-01 $wEnd");
        while ($t <= $e) { $times[] = date('H:i', $t); $t += $ivMins * 60; }
    }

    [$y, $m] = explode('-', $prefix);
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$m, (int)$y);

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = sprintf('%s-%02d', $prefix, $d);
        if ($date < $rule['start_date'] || $date > $rule['end_date']) continue;
        if (!in_array((int)date('w', strtotime($date)), $days)) continue;  // date('w') 0=Sun matches JS

        foreach ($times as $time) {
            $entry = [
                'id'      => $rule['id'] . '|' . $date . '|' . $time,
                'date'    => $date, 'type' => 'show', 'time' => $time,
                'rule_id' => $rule['id'],
            ];
            if (!empty($rule['playlist']))  $entry['playlist']  = $rule['playlist'];
            elseif (!empty($rule['playlists'])) $entry['playlists'] = $rule['playlists'];
            $out[] = $entry;
        }
    }
    return $out;
}

switch ($_GET['action'] ?? '') {

    case 'probe_fpp':
        $playlist = $_GET['playlist'] ?? 'Show 1';
        $enc      = rawurlencode($playlist);
        $results  = [];

        // Helper: GET request
        $get = function($url) use (&$http_response_header) {
            $ctx  = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
            $body = @file_get_contents($url, false, $ctx);
            $code = isset($http_response_header) ? (int)explode(' ', $http_response_header[0])[1] : 0;
            return ['http' => $code, 'body' => $body === false ? null : substr($body, 0, 400)];
        };

        // Helper: POST JSON
        $post = function($url, $data) use (&$http_response_header) {
            $payload = json_encode($data);
            $ctx  = stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
                'content' => $payload,
                'timeout' => 4,
                'ignore_errors' => true,
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            $code = isset($http_response_header) ? (int)explode(' ', $http_response_header[0])[1] : 0;
            return ['http' => $code, 'body' => $body === false ? null : substr($body, 0, 400)];
        };

        $results['REST start (GET)']           = $get("http://localhost/api/playlist/$enc/start") + ['url' => "http://localhost/api/playlist/$enc/start"];
        $results['command list']               = $get("http://localhost/api/command") + ['url' => 'http://localhost/api/command'];
        $results['cmd Start Playlist (GET)']   = $get("http://localhost/api/command/Start%20Playlist?args[]=" . urlencode($playlist) . "&args[]=0&args[]=0&args[]=0") + ['url' => ''];
        $results['cmd Start Playlist (POST)']  = $post("http://localhost/api/command/Start%20Playlist", ['args' => [$playlist, '0', '0', '0']]) + ['url' => 'http://localhost/api/command/Start%20Playlist'];
        $results['cmd startPlaylist (POST)']   = $post("http://localhost/api/command/startPlaylist",    ['args' => [$playlist, '0', '0', '0']]) + ['url' => 'http://localhost/api/command/startPlaylist'];

        echo json_encode($results);
        break;

    case 'get_status':
        $faderRaw = @file_get_contents('/tmp/xr18_current_fader');
        echo json_encode(['xr18_fader' => $faderRaw !== false ? (float)trim($faderRaw) : null]);
        break;

    case 'get_month':
        $year   = (int)($_GET['year']  ?? date('Y'));
        $month  = (int)($_GET['month'] ?? date('n'));
        $prefix = sprintf('%04d-%02d', $year, $month);

        $manual = array_values(array_filter(
            $schedule['entries'] ?? [],
            fn($e) => str_starts_with($e['date'], $prefix)
        ));
        $generated = [];
        foreach ($schedule['rules'] ?? [] as $rule) {
            $generated = array_merge($generated, expand_rule_for_month($rule, $prefix));
        }
        echo json_encode(['entries' => array_merge($manual, $generated), 'rules' => $schedule['rules'] ?? []]);
        break;

    case 'save_entry':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['date']) || empty($body['type'])) {
            http_response_code(400); echo json_encode(['error' => 'invalid']); break;
        }
        $entry = ['id' => $body['id'] ?? uniqid('e_'), 'date' => $body['date'], 'type' => $body['type']];
        if ($body['type'] === 'show') {
            $entry['time'] = $body['time'] ?? '19:00';
            if (!empty($body['playlist'])) $entry['playlist'] = $body['playlist'];
            else $entry['playlists'] = $body['playlists'] ?? [];
        } elseif ($body['type'] === 'blackout') {
            $entry['reason'] = $body['reason'] ?? '';
        }
        $entries = $schedule['entries'] ?? [];
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
            $schedule['entries'] ?? [], fn($e) => $e['id'] !== $id
        ));
        file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
        break;

    case 'save_rule':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['start_date']) || empty($body['end_date']) || empty($body['window_start'])) {
            http_response_code(400); echo json_encode(['error' => 'invalid']); break;
        }
        $rule = [
            'id'           => $body['id'] ?? uniqid('r_'),
            'start_date'   => $body['start_date'],
            'end_date'     => $body['end_date'],
            'days'         => array_map('intval', $body['days'] ?? [0,1,2,3,4,5,6]),
            'window_start' => $body['window_start'],
        ];
        if (!empty($body['window_end']))    $rule['window_end']    = $body['window_end'];
        if (!empty($body['interval_mins'])) $rule['interval_mins'] = (int)$body['interval_mins'];
        if (!empty($body['playlist']))      $rule['playlist']      = $body['playlist'];
        elseif (!empty($body['playlists'])) $rule['playlists']     = $body['playlists'];

        $rules = $schedule['rules'] ?? [];
        $replaced = false;
        foreach ($rules as &$r) {
            if ($r['id'] === $rule['id']) { $r = $rule; $replaced = true; break; }
        }
        if (!$replaced) $rules[] = $rule;
        $schedule['rules'] = $rules;
        file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true, 'rule' => $rule]);
        break;

    case 'delete_rule':
        $id = $_GET['id'] ?? '';
        $schedule['rules'] = array_values(array_filter(
            $schedule['rules'] ?? [], fn($r) => $r['id'] !== $id
        ));
        file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
        break;

    case 'get_log':
        $logFile = '/home/fpp/media/logs/showmanager.log';
        $running = (int)shell_exec('pgrep -fc show_scheduler.py 2>/dev/null') > 0;
        if (!file_exists($logFile)) {
            echo json_encode(['lines' => ['(log file not found — scheduler may not have run yet)'], 'running' => $running]);
            break;
        }
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        echo json_encode(['lines' => array_slice($lines, -150), 'running' => $running]);
        break;

    case 'scheduler_restart':
        $pluginDir = __DIR__;
        shell_exec("pkill -f show_scheduler.py 2>/dev/null");
        sleep(1);
        shell_exec("python3 " . escapeshellarg("$pluginDir/Scripts/show_scheduler.py") . " >> /home/fpp/media/logs/showmanager.log 2>&1 &");
        echo json_encode(['ok' => true]);
        break;

    case 'get_hardware':
        $hwFile = $settings['configDirectory'] . "/ShowManagerHardware.config";
        $hw = file_exists($hwFile) ? (json_decode(file_get_contents($hwFile), true) ?? []) : [];
        echo json_encode($hw);
        break;

    case 'save_hardware':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { http_response_code(400); echo json_encode(['error' => 'invalid']); break; }
        $hwFile = $settings['configDirectory'] . "/ShowManagerHardware.config";
        file_put_contents($hwFile, json_encode($body, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
        break;

    case 'get_announcements':
        $annFile = $settings['configDirectory'] . "/ShowManagerAnnouncements.config";
        $hwFile  = $settings['configDirectory'] . "/ShowManagerHardware.config";
        $ann = file_exists($annFile) ? (json_decode(file_get_contents($annFile), true) ?? []) : [];
        $hw  = file_exists($hwFile)  ? (json_decode(file_get_contents($hwFile),  true) ?? []) : [];
        if (isset($hw['duck_level']))    $ann['duck_level']    = $hw['duck_level'];
        if (isset($hw['duck_fade_secs'])) $ann['duck_fade_secs'] = $hw['duck_fade_secs'];
        $announceDir = __DIR__ . '/announcements';
        $mainFiles = array_map('basename', glob($announceDir . '/*.{mp3,wav,ogg}', GLOB_BRACE) ?: []);
        $dtFiles   = array_map('basename', glob($announceDir . '/daytime/*.{mp3,wav,ogg}', GLOB_BRACE) ?: []);
        $ann['_files'] = ['main' => $mainFiles, 'daytime' => $dtFiles];
        echo json_encode($ann);
        break;

    case 'save_announcements':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { http_response_code(400); echo json_encode(['error' => 'invalid']); break; }
        $hwFile  = $settings['configDirectory'] . "/ShowManagerHardware.config";
        $annFile = $settings['configDirectory'] . "/ShowManagerAnnouncements.config";
        $hw = file_exists($hwFile) ? (json_decode(file_get_contents($hwFile), true) ?? []) : [];
        if (isset($body['duck_level']))    { $hw['duck_level']    = (float)$body['duck_level'];    unset($body['duck_level']); }
        if (isset($body['duck_fade_secs'])){ $hw['duck_fade_secs']= (float)$body['duck_fade_secs']; unset($body['duck_fade_secs']); }
        file_put_contents($hwFile, json_encode($hw, JSON_PRETTY_PRINT));
        unset($body['_files']);
        $announceDir = __DIR__ . '/announcements';
        $body['folder'] = $announceDir;
        @mkdir($announceDir . '/daytime', 0755, true);
        file_put_contents($annFile, json_encode($body, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
        break;

    case 'upload_announcement':
        $folder = ($_POST['folder'] ?? 'main') === 'daytime' ? 'daytime' : '';
        $announceDir = __DIR__ . '/announcements' . ($folder ? "/$folder" : '');
        @mkdir($announceDir, 0755, true);
        if (empty($_FILES['file']['name'])) { http_response_code(400); echo json_encode(['error' => 'no file']); break; }
        $f = $_FILES['file'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp3','wav','ogg']) || $f['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400); echo json_encode(['error' => 'invalid file']); break;
        }
        move_uploaded_file($f['tmp_name'], $announceDir . '/' . basename($f['name']));
        echo json_encode(['ok' => true]);
        break;

    case 'delete_announcement':
        $path = $_GET['path'] ?? '';
        $base = realpath(__DIR__ . '/announcements');
        $target = realpath(__DIR__ . '/announcements/' . ltrim($path, '/'));
        if ($base && $target && str_starts_with($target, $base . DIRECTORY_SEPARATOR)) {
            unlink($target); echo json_encode(['ok' => true]);
        } else { http_response_code(400); echo json_encode(['error' => 'invalid path']); }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
}
