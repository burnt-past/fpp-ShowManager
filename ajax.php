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

// ── Dropbox helpers ─────────────────────────────────────────────────────────
function sm_dropbox_path($settings) {
    return $settings['configDirectory'] . '/ShowManagerDropbox.config';
}
function sm_dropbox_cfg($settings) {
    $f = sm_dropbox_path($settings);
    return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
}
function sm_dropbox_save($settings, $cfg) {
    $f = sm_dropbox_path($settings);
    file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT));
    @chmod($f, 0600);   // holds the app secret + refresh token
}
// Minimal HTTP that prefers cURL (reliable for HTTPS + custom headers), with a
// stream-wrapper fallback. Returns [http_code, response_body, error_string].
function sm_http($method, $url, $headers, $body) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 45,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return [$code, $resp === false ? '' : $resp, $err];
    }
    $opts = ['http' => [
        'method'        => $method,
        'header'        => implode("\r\n", $headers),
        'content'       => $body,
        'timeout'       => 45,
        'ignore_errors' => true,
    ]];
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    $code = 0;
    if (isset($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0] ?? '', $m)) $code = (int)$m[1];
    return [$code, $resp === false ? '' : $resp, ''];
}
// Exchange the stored refresh token for a short-lived access token.
function sm_dropbox_access_token($settings) {
    $c = sm_dropbox_cfg($settings);
    if (empty($c['refresh_token']) || empty($c['app_key']) || empty($c['app_secret']))
        return [null, 'Not connected to Dropbox'];
    $body = http_build_query([
        'grant_type'    => 'refresh_token',
        'refresh_token' => $c['refresh_token'],
        'client_id'     => $c['app_key'],
        'client_secret' => $c['app_secret'],
    ]);
    [$code, $resp] = sm_http('POST', 'https://api.dropbox.com/oauth2/token',
        ['Content-Type: application/x-www-form-urlencoded'], $body);
    $j = json_decode($resp, true);
    if ($code === 200 && !empty($j['access_token'])) return [$j['access_token'], null];
    return [null, $j['error_description'] ?? $j['error'] ?? "Token refresh failed (HTTP $code)"];
}
// Build the standard backup bundle (excludes the Dropbox config so the app
// secret / refresh token never leave the Pi in a backup file).
function sm_backup_bundle($settings) {
    $files = [];
    foreach (glob($settings['configDirectory'] . '/ShowManager*.config') as $f) {
        // Skip files holding secrets so a backup never carries them off the box.
        if (in_array(basename($f), ['ShowManagerDropbox.config', 'ShowManagerPublish.config'], true)) continue;
        $files[basename($f)] = json_decode(file_get_contents($f), true);
    }
    return json_encode([
        'type' => 'showmanager-backup', 'version' => 1,
        'exported' => date('c'), 'host' => gethostname(), 'files' => $files,
    ], JSON_PRETTY_PRINT);
}

// Send one OSC float message to the X-Air mixer (UDP 10024) — for the live
// volume sliders. Minimal encoder: address + ",f" typetag + big-endian float.
function sm_osc_send($ip, $addr, $float) {
    $a = $addr . "\0";
    $a .= str_repeat("\0", (4 - strlen($a) % 4) % 4);   // pad address to 4 bytes
    $pkt = $a . ",f\0\0" . pack('G', (float)$float);      // G = float32 big-endian
    $fp = @fsockopen("udp://$ip", 10024, $e, $s, 1);
    if (!$fp) return false;
    @fwrite($fp, $pkt);
    @fclose($fp);
    return true;
}
function sm_hw_cfg($settings) {
    $f = $settings['configDirectory'] . "/ShowManagerHardware.config";
    return file_exists($f) ? (json_decode(file_get_contents($f), true) ?? []) : [];
}

// Names of FSEQ effects FPP is actually running right now (ground truth for the
// BG Effect status — matches FPP's "Running Effects" panel). Handles the couple
// of shapes getRunningEffects can return; empty array if none / unreachable.
function sm_running_effects() {
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $raw = @file_get_contents('http://localhost/api/fppd/effects', false, $ctx);
    $j = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($j)) return [];
    $list = $j['effects'] ?? $j;          // {"effects":[...]} or a bare array
    $names = [];
    foreach ((array)$list as $e) {
        if (is_array($e)) {
            foreach (['name', 'effect', 'effectName'] as $k) {
                if (!empty($e[$k])) { $names[] = $e[$k]; break; }
            }
        } elseif (is_string($e) && trim($e) !== '') {
            $names[] = trim($e);
        }
    }
    return array_values(array_unique($names));
}

// List useful ALSA playback devices for the announcement-device picker:
// `default`, custom PCMs (e.g. an asound.conf "announce" route), and hardware
// (plughw). Filters out the noisy auto-generated entries.
function sm_audio_devices() {
    $raw  = @shell_exec('aplay -L 2>/dev/null') ?: '';
    $out  = ['default'];
    $block = ['null','pulse','pulseaudio','jack','oss','speex','speexrate','samplerate','upmix','vdownmix','lavrate'];
    foreach (explode("\n", $raw) as $line) {
        if ($line === '' || $line[0] === ' ' || $line[0] === "\t") continue;  // indented = description
        $name = trim($line);
        if ($name === '' || strtolower($name) === 'default') continue;
        if (in_array(strtolower($name), $block, true)) continue;
        $keep = (strpos($name, ':') === false) || strncmp($name, 'plughw:', 7) === 0;
        if ($keep) $out[] = $name;
    }
    return array_values(array_unique($out));
}

// ── Website-link (public schedule feed) ─────────────────────────────────────
// The feed is the contract with the external public website: it renders the
// countdown, tonight's show list, the status banner and special-event cards
// from this one JSON document. It carries only show *times* — no secrets — so
// it is safe to serve publicly and to push to a static host.
//
// Everything site-specific (the destination URL, the auth token, the allowed
// origin) is operator-entered config, never committed — this plugin ships with
// no hostnames or credentials baked in.
function sm_publish_path($settings) {
    return $settings['configDirectory'] . '/ShowManagerPublish.config';
}
function sm_publish_cfg($settings) {
    $f = sm_publish_path($settings);
    return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
}
function sm_publish_save($settings, $cfg) {
    $f = sm_publish_path($settings);
    file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT));
    @chmod($f, 0600);   // holds the upload auth token
}

// Resolve the box's local timezone so each show's ISO offset is correct across
// the PST/PDT change (e.g. -08:00 in December, -07:00 in summer) rather than a
// fixed value — and never a silent UTC fallback. Prefers the OS zone name
// (/etc/timezone on Debian), then PHP's default, then UTC.
function sm_local_tz() {
    static $tz = null;
    if ($tz !== null) return $tz;
    $name = @trim(@file_get_contents('/etc/timezone'));
    if ($name === '' || $name === false) $name = @date_default_timezone_get() ?: 'UTC';
    try { $tz = new DateTimeZone($name); }
    catch (Exception $e) { $tz = new DateTimeZone('UTC'); }
    return $tz;
}
// Parse a local "Y-m-d H:i" / ISO-ish string in the box's zone and return a
// DateTime (with the right DST-aware offset), or null if unparseable.
function sm_local_dt($str) {
    try { return new DateTime($str, sm_local_tz()); }
    catch (Exception $e) { return null; }
}

// Build the public schedule feed in the shape the website expects. Covers
// tonight through the next 7 days (past shows are harmless — the site ignores
// them). Every timestamp is ISO-8601 with the box's Pacific offset for that
// date, so it reads correctly regardless of the visitor's timezone and stays
// correct across the winter DST change.
function sm_build_public_schedule($settings, $schedule) {
    $cfg  = sm_publish_cfg($settings);
    $now  = time();
    $horizonDays = 7;
    $end  = strtotime('+' . $horizonDays . ' days 23:59:59', $now);

    // Collect show entries (manual + rule-generated) for every month the window
    // touches, then keep those inside the window. A show still "counts" briefly
    // after its start, so include from a little before now.
    $floor = $now - 1800;   // keep a just-started show in the list
    $months = [];
    for ($t = $now; $t <= $end; $t = strtotime('+1 day', $t)) $months[date('Y-m', $t)] = true;

    $rows = [];
    foreach (($schedule['entries'] ?? []) as $e) {
        if (($e['type'] ?? 'show') === 'show') $rows[] = $e;
    }
    foreach (array_keys($months) as $prefix) {
        foreach (($schedule['rules'] ?? []) as $rule) {
            $rows = array_merge($rows, expand_rule_for_month($rule, $prefix));
        }
    }

    $shows = [];
    $seen  = [];
    foreach ($rows as $e) {
        $date = $e['date'] ?? '';
        $time = $e['time'] ?? '';
        if ($date === '' || $time === '') continue;
        $dt = sm_local_dt("$date $time");
        if ($dt === null) continue;
        $ts = $dt->getTimestamp();
        if ($ts < $floor || $ts > $end) continue;
        $iso = $dt->format('c');               // e.g. 2026-12-05T19:00:00-08:00
        if (isset($seen[$iso])) continue;     // de-dupe manual + rule overlap
        $seen[$iso] = true;
        $name = $e['playlist'] ?? '';
        if ($name === '' && !empty($e['playlists'])) $name = $e['playlists'][0];
        $shows[] = ['name' => $name !== '' ? $name : 'Light Show', 'start' => $iso, '_ts' => $ts];
    }
    usort($shows, fn($a, $b) => $a['_ts'] <=> $b['_ts']);
    foreach ($shows as &$s) unset($s['_ts']);
    unset($s);

    // Status: paused when the operator flips it, or when the system is disabled
    // (an active "Disable system" override). Otherwise "ok".
    $ovFile = $settings['configDirectory'] . '/ShowManagerOverrides.config';
    $ov = file_exists($ovFile) ? (json_decode(file_get_contents($ovFile), true) ?: []) : [];
    $du = $ov['disabled_until'] ?? null;
    $disabled = $du && strtotime($du) > $now;
    $paused = !empty($cfg['paused']) || $disabled;

    $feed = [
        'status'     => $paused ? 'paused' : 'ok',
        'statusNote' => $paused ? (string)($cfg['status_note'] ?? '') : '',
        'shows'      => $shows,
    ];

    // Special-event cards (operator-curated). Each: name + desc, plus either a
    // datetime (→ ISO "iso" + human "date") or a free-text label for open-ended
    // entries like "All season" (no "iso", so the site skips "Add to calendar").
    $events = [];
    foreach (($cfg['events'] ?? []) as $ev) {
        $name = trim($ev['name'] ?? '');
        if ($name === '') continue;
        $row = ['name' => $name];
        $when = trim($ev['when'] ?? '');
        if ($when !== '' && ($dt = sm_local_dt($when)) !== null) {
            $row['iso']  = $dt->format('c');
            $row['date'] = $dt->format('D, M j');
        } elseif (($lbl = trim($ev['label'] ?? '')) !== '') {
            $row['date'] = $lbl;
        }
        if (($d = trim($ev['desc'] ?? '')) !== '') $row['desc'] = $d;
        $events[] = $row;
    }
    if ($events) $feed['events'] = $events;

    return $feed;
}

switch ($_GET['action'] ?? '') {

    case 'trigger_playlist':
        $playlist = $_GET['playlist'] ?? '';
        if (!$playlist) { http_response_code(400); echo json_encode(['error' => 'no playlist']); break; }
        $enc = rawurlencode($playlist);
        $url = "http://localhost/api/command/Start%20Playlist/$enc/false";
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = isset($http_response_header) ? (int)explode(' ', $http_response_header[0])[1] : 0;
        echo json_encode(['ok' => $code === 200, 'http' => $code, 'response' => $body, 'url' => $url]);
        break;

    case 'stop_playlist':
        // Stop the current playback only. Background music/effects then resume
        // per their schedule — use "Disable system" to keep everything off.
        $url = "http://localhost/api/command/Stop%20Now";
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = isset($http_response_header) ? (int)explode(' ', $http_response_header[0])[1] : 0;
        echo json_encode(['ok' => true, 'http' => $code, 'response' => $body]);
        break;

    case 'get_status':
        $faderRaw = @file_get_contents('/tmp/xr18_current_fader');
        $bgRaw = @file_get_contents('/tmp/showmanager_bg_status.json');
        $bg = $bgRaw !== false ? (json_decode($bgRaw, true) ?: null) : null;
        // The configured background-music playlist name lets the UI tell
        // "background music playing" apart from an actual show (both are FPP
        // playlists). Cheap file read — no /api/effects here.
        $bgcfgFile = $settings['configDirectory'] . '/ShowManagerBackground.config';
        $bgcfg = file_exists($bgcfgFile) ? (json_decode(file_get_contents($bgcfgFile), true) ?? []) : [];
        $hwcfg = sm_hw_cfg($settings);
        echo json_encode([
            'xr18_fader'        => $faderRaw !== false ? (float)trim($faderRaw) : null,
            'background'        => $bg,
            'bg_music_playlist' => $bgcfg['music']['playlist'] ?? '',
            'announce_vol'      => isset($hwcfg['announce_vol']) ? (float)$hwcfg['announce_vol'] : 0.75,
            'running_effects'   => sm_running_effects(),
        ]);
        break;

    case 'set_music_level':
        // Live music-fader control from the Status slider (plugin owns the fader).
        $level = (float)($_GET['level'] ?? -1);
        if ($level < 0 || $level > 1) { http_response_code(400); echo json_encode(['error' => 'level 0-1']); break; }
        $hw = sm_hw_cfg($settings);
        $ip = $hw['mixer_ip'] ?? ($hw['xr18_ip'] ?? '');
        if (!$ip) { http_response_code(400); echo json_encode(['error' => 'no mixer IP']); break; }
        $chs = isset($hw['fader_channel'])
            ? [(int)$hw['fader_channel']]
            : [(int)($hw['music_ch1'] ?? 1), (int)($hw['music_ch2'] ?? 2)];
        foreach ($chs as $c) sm_osc_send($ip, sprintf('/ch/%02d/mix/fader', $c), $level);
        // Shared "current music level" — scheduler reads this for ducking restore
        file_put_contents('/tmp/xr18_current_fader', sprintf('%.4f', $level));
        echo json_encode(['ok' => true, 'level' => $level]);
        break;

    case 'set_announce_level':
        $level = (float)($_GET['level'] ?? -1);
        if ($level < 0 || $level > 1) { http_response_code(400); echo json_encode(['error' => 'level 0-1']); break; }
        $hwFile = $settings['configDirectory'] . "/ShowManagerHardware.config";
        $hw = sm_hw_cfg($settings);
        $ip = $hw['mixer_ip'] ?? ($hw['xr18_ip'] ?? '');
        if (!$ip) { http_response_code(400); echo json_encode(['error' => 'no mixer IP']); break; }
        sm_osc_send($ip, sprintf('/ch/%02d/mix/fader', (int)($hw['announce_ch'] ?? 3)), $level);
        // Persist so the bridge's periodic announce-channel hold keeps it there
        $hw['announce_vol'] = $level;
        file_put_contents($hwFile, json_encode($hw, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true, 'level' => $level]);
        break;

    case 'song_meta':
        // Pull title/artist/album tags from the currently-playing media file
        // via ffprobe (ships with ffmpeg, already a dependency).
        $file = $_GET['file'] ?? '';
        if ($file === '') {
            $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
            $s = @file_get_contents('http://localhost/api/fppd/status', false, $ctx);
            $j = $s !== false ? json_decode($s, true) : null;
            $file = $j['current_song'] ?? '';
        }
        if ($file === '') { echo json_encode(['ok' => false]); break; }
        // Resolve to a real file under the media dir (block path traversal)
        $media = '/home/fpp/media';
        $base  = basename($file);
        $path  = null;
        $cands = [];
        if ($file[0] === '/') $cands[] = $file;
        $cands[] = $media . '/' . ltrim($file, '/');
        $cands[] = $media . '/music/' . $base;
        $cands[] = $media . '/' . $base;
        foreach ($cands as $c) {
            $rp = realpath($c);
            if ($rp && str_starts_with($rp, $media) && is_file($rp)) { $path = $rp; break; }
        }
        if (!$path) { echo json_encode(['ok' => false, 'error' => 'file not found']); break; }
        if (!trim(shell_exec('command -v ffprobe 2>/dev/null') ?: '')) {
            echo json_encode(['ok' => false, 'error' => 'ffprobe not installed']); break;
        }
        $out  = shell_exec('ffprobe -v quiet -print_format json -show_format ' . escapeshellarg($path) . ' 2>/dev/null');
        $tags = json_decode($out ?: '', true)['format']['tags'] ?? [];
        $pick = function($keys) use ($tags) {
            foreach ($tags as $k => $v) if (in_array(strtolower($k), $keys, true)) return trim($v);
            return '';
        };
        echo json_encode([
            'ok'     => true,
            'title'  => $pick(['title']),
            'artist' => $pick(['artist', 'album_artist', 'author', 'composer']),
            'album'  => $pick(['album']),
        ]);
        break;

    case 'get_override':
        $ovFile = $settings['configDirectory'] . "/ShowManagerOverrides.config";
        $ov = file_exists($ovFile) ? (json_decode(file_get_contents($ovFile), true) ?? []) : [];
        $du = $ov['disabled_until'] ?? null;
        if ($du && strtotime($du) <= time()) {   // expired — clean up
            $du = null;
            file_put_contents($ovFile, '{}');   // JSON object, not [] (which breaks .get() in the scheduler)
        }
        echo json_encode(['disabled_until' => $du]);
        break;

    case 'set_disabled':
        $ovFile = $settings['configDirectory'] . "/ShowManagerOverrides.config";
        $mode = $_GET['mode'] ?? '';
        if ($mode === 'off') {
            file_put_contents($ovFile, '{}');   // JSON object, not [] (breaks .get() in the scheduler)
            echo json_encode(['ok' => true, 'disabled_until' => null]);
            break;
        }
        if ($mode === '1h')          $until = date('c', time() + 3600);
        elseif ($mode === 'tonight') $until = date('c', strtotime('tomorrow 04:00'));
        else { http_response_code(400); echo json_encode(['error' => 'bad mode']); break; }
        file_put_contents($ovFile, json_encode(['disabled_until' => $until], JSON_PRETTY_PRINT));
        // Stop anything playing; disabled_until keeps the scheduler from
        // resuming background music/effects until it expires or is re-enabled.
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        @file_get_contents("http://localhost/api/command/Stop%20Now", false, $ctx);
        echo json_encode(['ok' => true, 'disabled_until' => $until]);
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
            if (!empty($body['start_time'])) $entry['start_time'] = $body['start_time'];
            if (!empty($body['end_time']))   $entry['end_time']   = $body['end_time'];
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
        $running = (int)shell_exec('pgrep -fc "[s]how_scheduler.py" 2>/dev/null') > 0;
        if (!file_exists($logFile)) {
            echo json_encode(['lines' => ['(log file not found — scheduler may not have run yet)'], 'running' => $running]);
            break;
        }
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        echo json_encode(['lines' => array_slice($lines, -150), 'running' => $running]);
        break;

    case 'clear_log':
        $logFile = '/home/fpp/media/logs/showmanager.log';
        file_put_contents($logFile, '');
        echo json_encode(['ok' => true]);
        break;

    case 'scheduler_restart':
        $pluginDir = __DIR__;
        shell_exec('pkill -f "[s]how_scheduler.py" 2>/dev/null');
        sleep(1);
        shell_exec("python3 " . escapeshellarg("$pluginDir/Scripts/show_scheduler.py") . " >> /home/fpp/media/logs/showmanager.log 2>&1 &");
        echo json_encode(['ok' => true]);
        break;

    case 'get_hardware':
        $hwFile = $settings['configDirectory'] . "/ShowManagerHardware.config";
        $hw = file_exists($hwFile) ? (json_decode(file_get_contents($hwFile), true) ?? []) : [];
        // Surface values from the legacy config so old installs migrate cleanly
        $legacyFile = $settings['configDirectory'] . "/ShowManager.config";
        $legacy = file_exists($legacyFile) ? (json_decode(file_get_contents($legacyFile), true) ?? []) : [];
        if (empty($hw['mixer_ip']) && !empty($legacy['xr18_ip'])) $hw['mixer_ip'] = $legacy['xr18_ip'];
        foreach (['announce_ch', 'announce_vol'] as $k) {
            if (!isset($hw[$k]) && isset($legacy[$k])) $hw[$k] = $legacy[$k];
        }
        $hw['_devices'] = sm_audio_devices();
        echo json_encode($hw);
        break;

    case 'announce_test':
        // Play a short test tone (mono) out the announcement device so the
        // operator can confirm it lands on the right XR18 channel.
        $dev = $_GET['device'] ?? '';
        if ($dev === '') {
            $hwFile = $settings['configDirectory'] . "/ShowManagerHardware.config";
            $hw = file_exists($hwFile) ? (json_decode(file_get_contents($hwFile), true) ?? []) : [];
            $dev = $hw['announce_device'] ?? 'default';
        }
        if (!trim(shell_exec('command -v ffmpeg 2>/dev/null') ?: '')) {
            http_response_code(400); echo json_encode(['error' => 'ffmpeg not installed']); break;
        }
        // Dual-mono (both L and R) to match how announcements actually play,
        // so the test exercises whichever side feeds the mixer input.
        $cmd = 'ffmpeg -hide_banner -loglevel error -f lavfi -i '
             . escapeshellarg('sine=frequency=660:duration=1.2')
             . ' -af volume=0.25 -ac 2 -f alsa ' . escapeshellarg($dev) . ' 2>&1';
        $err = trim(shell_exec($cmd) ?: '');
        if ($err === '') echo json_encode(['ok' => true, 'device' => $dev]);
        else { http_response_code(400); echo json_encode(['error' => substr($err, 0, 200), 'device' => $dev]); }
        break;

    case 'save_hardware':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { http_response_code(400); echo json_encode(['error' => 'invalid']); break; }
        $hwFile = $settings['configDirectory'] . "/ShowManagerHardware.config";
        file_put_contents($hwFile, json_encode($body, JSON_PRETTY_PRINT));
        // The bridge only reads config at startup — restart it to apply
        $pluginDir = __DIR__;
        shell_exec('pkill -f "[x]r18_bridge.py" 2>/dev/null');
        sleep(1);
        shell_exec("python3 " . escapeshellarg("$pluginDir/Scripts/xr18_bridge.py") . " >> /home/fpp/media/logs/xr18_bridge.log 2>&1 &");
        echo json_encode(['ok' => true]);
        break;

    case 'get_background':
        $bgFile  = $settings['configDirectory'] . "/ShowManagerBackground.config";
        $bg = file_exists($bgFile) ? (json_decode(file_get_contents($bgFile), true) ?? []) : [];
        // Backward-compat: seed the music playlist from the old announcements key
        if (empty($bg['music']['playlist'])) {
            $annFile = $settings['configDirectory'] . "/ShowManagerAnnouncements.config";
            $ann = file_exists($annFile) ? (json_decode(file_get_contents($annFile), true) ?? []) : [];
            if (!empty($ann['background_playlist'])) {
                $bg['music'] = ($bg['music'] ?? []) + [
                    'playlist' => $ann['background_playlist'],
                    'start' => '00:00', 'end' => '00:00', 'enabled' => false,
                ];
            }
        }
        // Available overlay content: .eseq effects and .fseq sequences
        $effRaw = @file_get_contents('http://localhost/api/effects');
        $seqRaw = @file_get_contents('http://localhost/api/sequence');
        $bg['_effects']   = $effRaw !== false ? (json_decode($effRaw, true) ?: []) : [];
        $bg['_sequences'] = $seqRaw !== false ? (json_decode($seqRaw, true) ?: []) : [];
        echo json_encode($bg);
        break;

    case 'save_background':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { http_response_code(400); echo json_encode(['error' => 'invalid']); break; }
        unset($body['_effects']);
        $bgFile = $settings['configDirectory'] . "/ShowManagerBackground.config";
        file_put_contents($bgFile, json_encode($body, JSON_PRETTY_PRINT));
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
        // Audio elsewhere on the box (e.g. dropped into FPP's music/upload
        // folders) — offered as absolute paths, which the scheduler accepts.
        $sysAudio = [];
        foreach (['/home/fpp/media/music', '/home/fpp/media/upload'] as $dir) {
            foreach (glob("$dir/*.{mp3,wav,ogg,m4a,flac,aac}", GLOB_BRACE) ?: [] as $f) {
                $sysAudio[] = ['label' => basename($dir) . '/' . basename($f), 'path' => $f];
            }
        }
        $ann['_sysAudio'] = $sysAudio;
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
        if ($base && $target && str_starts_with($target, $base . DIRECTORY_SEPARATOR) && is_file($target) && unlink($target)) {
            echo json_encode(['ok' => true]);
        } else { http_response_code(400); echo json_encode(['error' => 'invalid path']); }
        break;

    case 'trigger_show':
        // Run a playlist through the scheduler's full show pipeline (brightness,
        // fader levels, effect kill, end detection, post-show fade). We hand the
        // request to the running scheduler via a file it polls every few seconds.
        $playlist = $_GET['playlist'] ?? '';
        if (!$playlist) { http_response_code(400); echo json_encode(['error' => 'no playlist']); break; }
        $running = (int)shell_exec('pgrep -fc "[s]how_scheduler.py" 2>/dev/null') > 0;
        file_put_contents('/tmp/showmanager_run_now', json_encode(['playlist' => $playlist]));
        echo json_encode(['ok' => $running, 'queued' => true, 'scheduler_running' => $running]);
        break;

    case 'export_config':
        // Bundle every ShowManager*.config (except the Dropbox secrets) for download.
        echo sm_backup_bundle($settings);
        break;

    case 'import_config':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || ($body['type'] ?? '') !== 'showmanager-backup' || empty($body['files'])) {
            http_response_code(400); echo json_encode(['error' => 'not a Show Manager backup']); break;
        }
        $written = [];
        foreach ($body['files'] as $name => $content) {
            // Only accept our own config names, basename only (no path traversal)
            $base = basename($name);
            if ($base !== $name || !preg_match('/^ShowManager[A-Za-z]*\.config$/', $base)) continue;
            if ($base === 'ShowManagerDropbox.config') continue;   // never overwrite Dropbox creds from a restore
            file_put_contents($settings['configDirectory'] . '/' . $base, json_encode($content, JSON_PRETTY_PRINT));
            $written[] = $base;
        }
        if (!$written) { http_response_code(400); echo json_encode(['error' => 'no valid config files in backup']); break; }
        // Apply immediately: restart both daemons so new hardware/schedule take effect
        $pluginDir = __DIR__;
        shell_exec('pkill -f "[s]how_scheduler.py" 2>/dev/null; pkill -f "[x]r18_bridge.py" 2>/dev/null');
        sleep(1);
        shell_exec('python3 ' . escapeshellarg("$pluginDir/Scripts/xr18_bridge.py")    . ' >> /home/fpp/media/logs/xr18_bridge.log 2>&1 &');
        shell_exec('python3 ' . escapeshellarg("$pluginDir/Scripts/show_scheduler.py") . ' >> /home/fpp/media/logs/showmanager.log 2>&1 &');
        echo json_encode(['ok' => true, 'restored' => $written]);
        break;

    case 'diagnostics':
        $checks = [];
        $add = function($label, $status, $detail) use (&$checks) {
            $checks[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
        };
        // Daemons
        $sched = (int)shell_exec('pgrep -fc "[s]how_scheduler.py" 2>/dev/null');
        $add('Scheduler daemon', $sched === 1 ? 'ok' : ($sched > 1 ? 'warn' : 'bad'),
             $sched === 1 ? 'Running (1 instance)' : ($sched > 1 ? "$sched instances — restart to dedupe" : 'Not running'));
        $bridge = (int)shell_exec('pgrep -fc "[x]r18_bridge.py" 2>/dev/null');
        $add('XR18 bridge', $bridge >= 1 ? 'ok' : 'bad', $bridge >= 1 ? 'Running' : 'Not running');
        // FPP daemon
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $fppRaw = @file_get_contents('http://localhost/api/fppd/status', false, $ctx);
        $fpp = $fppRaw !== false ? json_decode($fppRaw, true) : null;
        $add('FPP daemon', $fpp ? 'ok' : 'bad', $fpp ? ('Reachable — mode ' . ($fpp['mode_name'] ?? $fpp['mode'] ?? '?')) : 'No response from FPP API');
        // Mixer reachability
        $hwFile = $settings['configDirectory'] . '/ShowManagerHardware.config';
        $hw = file_exists($hwFile) ? (json_decode(file_get_contents($hwFile), true) ?? []) : [];
        $mip = $hw['mixer_ip'] ?? ($hw['xr18_ip'] ?? '');
        if ($mip) {
            $ok = false; $out = [];
            exec('ping -c1 -W1 ' . escapeshellarg($mip) . ' 2>/dev/null', $out, $rc);
            $add('Mixer reachable', $rc === 0 ? 'ok' : 'bad', $rc === 0 ? "Ping OK ($mip)" : "No ping response ($mip)");
        } else {
            $add('Mixer reachable', 'warn', 'No mixer IP configured');
        }
        // Plugins
        $plugDir = dirname(__DIR__);
        $bright = glob($plugDir . '/*rightness*');
        $add('Brightness plugin', $bright ? 'ok' : 'warn', $bright ? basename($bright[0]) : 'Not installed — brightness fades no-op');
        $viewer = array_merge(glob($plugDir . '/*3d*') ?: [], glob($plugDir . '/*3D*') ?: [], glob($plugDir . '/*iewer*') ?: []);
        $add('3D Viewer plugin', $viewer ? 'ok' : 'warn', $viewer ? basename($viewer[0]) : 'Not installed — kiosk preview/garland off');
        // Audio tooling
        $ff = trim(shell_exec('command -v ffmpeg 2>/dev/null') ?: '');
        $mp = trim(shell_exec('command -v mpg123 2>/dev/null') ?: '');
        $add('Audio player', ($ff || $mp) ? 'ok' : 'bad', $ff ? 'ffmpeg' : ($mp ? 'mpg123' : 'Neither ffmpeg nor mpg123 found'));
        // Clock sync
        $ntp = trim(shell_exec('timedatectl show -p NTPSynchronized --value 2>/dev/null') ?: '');
        if ($ntp === '') $add('Clock sync', 'warn', 'Unknown (timedatectl unavailable)');
        else $add('Clock sync', $ntp === 'yes' ? 'ok' : 'warn', $ntp === 'yes' ? 'NTP synced — ' . date('Y-m-d H:i:s') : 'NOT synced — shows may fire at wrong times');
        // Disk
        $free = @disk_free_space('/home/fpp/media');
        if ($free !== false) {
            $mb = $free / 1048576;
            $add('Disk space', $mb > 200 ? 'ok' : ($mb > 50 ? 'warn' : 'bad'), sprintf('%.0f MB free on media', $mb));
        }
        // Config writable
        $add('Config writable', is_writable($settings['configDirectory']) ? 'ok' : 'bad',
             is_writable($settings['configDirectory']) ? $settings['configDirectory'] : 'NOT writable — settings cannot save');
        echo json_encode(['checks' => $checks]);
        break;

    case 'brightness_flash':
        // Visible self-test: dark → full, so an operator can confirm the rig responds.
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
        $ok = true;
        foreach ([0, 100] as $i => $v) {
            if ($i) sleep(1);
            if (@file_get_contents("http://localhost/api/plugin-apis/Brightness/$v", false, $ctx) === false) $ok = false;
        }
        echo json_encode(['ok' => $ok, 'note' => $ok ? 'Flashed 0 → 100%' : 'Brightness plugin did not respond']);
        break;

    case 'get_dropbox':
        $c = sm_dropbox_cfg($settings);
        $appKey = $c['app_key'] ?? '';
        echo json_encode([
            'app_key'     => $appKey,
            'has_secret'  => !empty($c['app_secret']),
            'folder'      => $c['folder'] ?? '/ShowManager',
            'connected'   => !empty($c['refresh_token']),
            'auto'        => !empty($c['auto']),
            'last_backup' => $c['last_backup'] ?? null,
            // Dropbox shows the auth code on-screen (no redirect) with this URL
            'auth_url'    => $appKey
                ? 'https://www.dropbox.com/oauth2/authorize?client_id=' . urlencode($appKey)
                  . '&response_type=code&token_access_type=offline'
                : null,
        ]);
        break;

    case 'save_dropbox':
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $c = sm_dropbox_cfg($settings);
        $c['app_key'] = trim($body['app_key'] ?? '');
        // Blank secret means "keep the saved one" (the UI never receives it back)
        if (isset($body['app_secret']) && trim($body['app_secret']) !== '') $c['app_secret'] = trim($body['app_secret']);
        $folder = '/' . trim(trim($body['folder'] ?? 'ShowManager'), '/');
        $c['folder'] = $folder === '/' ? '' : $folder;
        $c['auto'] = !empty($body['auto']);
        sm_dropbox_save($settings, $c);
        echo json_encode(['ok' => true]);
        break;

    case 'dropbox_connect':
        // Exchange the one-time authorization code for a lasting refresh token.
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $code = trim($body['code'] ?? '');
        $c = sm_dropbox_cfg($settings);
        if (!$code || empty($c['app_key']) || empty($c['app_secret'])) {
            http_response_code(400); echo json_encode(['error' => 'Enter and save the app key & secret first, then paste the code']); break;
        }
        $post = http_build_query([
            'code' => $code, 'grant_type' => 'authorization_code',
            'client_id' => $c['app_key'], 'client_secret' => $c['app_secret'],
        ]);
        [$hc, $resp] = sm_http('POST', 'https://api.dropbox.com/oauth2/token',
            ['Content-Type: application/x-www-form-urlencoded'], $post);
        $j = json_decode($resp, true);
        if ($hc === 200 && !empty($j['refresh_token'])) {
            $c['refresh_token'] = $j['refresh_token'];
            sm_dropbox_save($settings, $c);
            echo json_encode(['ok' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $j['error_description'] ?? $j['error'] ?? "Connect failed (HTTP $hc)"]);
        }
        break;

    case 'dropbox_test':
        [$tok, $err] = sm_dropbox_access_token($settings);
        if (!$tok) { http_response_code(400); echo json_encode(['error' => $err]); break; }
        // check/user echoes back the query — the official "is this token live?" probe
        [$hc, $resp] = sm_http('POST', 'https://api.dropboxapi.com/2/check/user',
            ['Authorization: Bearer ' . $tok, 'Content-Type: application/json'],
            json_encode(['query' => 'showmanager']));
        $j = json_decode($resp, true);
        if ($hc === 200 && ($j['result'] ?? '') === 'showmanager') echo json_encode(['ok' => true]);
        else { http_response_code(400); echo json_encode(['error' => $j['error_summary'] ?? "Test failed (HTTP $hc)"]); }
        break;

    case 'dropbox_backup':
        [$tok, $err] = sm_dropbox_access_token($settings);
        if (!$tok) { http_response_code(400); echo json_encode(['error' => $err]); break; }
        $c = sm_dropbox_cfg($settings);
        $folder = $c['folder'] ?? '/ShowManager';
        $path = $folder . '/showmanager-backup-' . date('Y-m-d_His') . '.json';
        $arg = json_encode(['path' => $path, 'mode' => 'add', 'autorename' => true, 'mute' => true]);
        [$hc, $resp] = sm_http('POST', 'https://content.dropboxapi.com/2/files/upload', [
            'Authorization: Bearer ' . $tok,
            'Dropbox-API-Arg: ' . $arg,
            'Content-Type: application/octet-stream',
        ], sm_backup_bundle($settings));
        $j = json_decode($resp, true);
        if ($hc === 200 && !empty($j['name'])) {
            $c['last_backup'] = date('c');
            sm_dropbox_save($settings, $c);
            echo json_encode(['ok' => true, 'path' => $j['path_display'] ?? $path]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $j['error_summary'] ?? "Upload failed (HTTP $hc)"]);
        }
        break;

    case 'dropbox_disconnect':
        $c = sm_dropbox_cfg($settings);
        unset($c['refresh_token']);
        $c['auto'] = false;
        sm_dropbox_save($settings, $c);
        echo json_encode(['ok' => true]);
        break;

    case 'get_warnings':
        $warn = [];
        $playlists = [];
        foreach (glob('/home/fpp/media/playlists/*.json') as $f) $playlists[] = basename($f, '.json');
        $ents  = $schedule['entries'] ?? [];
        $shows = array_filter($ents, fn($e) => ($e['type'] ?? '') === 'show');
        $blk   = array_filter($ents, fn($e) => ($e['type'] ?? '') === 'blackout');
        // Per-show checks
        $seen = [];
        foreach ($shows as $s) {
            $pl = $s['playlist'] ?? '';
            $pls = $s['playlists'] ?? [];
            $when = ($s['date'] ?? '?') . ' ' . ($s['time'] ?? '');
            if (!$pl && !$pls) { $warn[] = ['bad', "Show on $when has no playlist selected"]; }
            foreach (array_filter(array_merge($pl ? [$pl] : [], $pls)) as $name) {
                if (!in_array($name, $playlists)) $warn[] = ['bad', "Show on $when uses \"$name\" which is not an FPP playlist"];
            }
            // Same date+time duplicate
            $key = ($s['date'] ?? '') . ' ' . ($s['time'] ?? '');
            if (isset($seen[$key])) $warn[] = ['warn', "Two shows scheduled at the same time: $when"];
            $seen[$key] = true;
            // Inside a blackout on the same date
            foreach ($blk as $b) {
                if (($b['date'] ?? '') !== ($s['date'] ?? '')) continue;
                $bs = $b['start_time'] ?? ''; $be = $b['end_time'] ?? '';
                $covered = (!$bs && !$be) || (($bs ?: '00:00') <= ($s['time'] ?? '') && ($s['time'] ?? '') <= ($be ?: '23:59'));
                if ($covered) { $warn[] = ['warn', "Show on $when falls inside a blackout — it will not run"]; break; }
            }
        }
        // Background config
        $bgFile = $settings['configDirectory'] . '/ShowManagerBackground.config';
        $bg = file_exists($bgFile) ? (json_decode(file_get_contents($bgFile), true) ?? []) : [];
        if (!empty($bg['music']['enabled'])) {
            $bpl = $bg['music']['playlist'] ?? '';
            if (!$bpl) $warn[] = ['warn', 'Background music is enabled but no playlist is selected'];
            elseif (!in_array($bpl, $playlists)) $warn[] = ['bad', "Background music playlist \"$bpl\" is not an FPP playlist"];
        }
        if (!empty($bg['effect']['enabled']) && empty($bg['effect']['effect']))
            $warn[] = ['warn', 'Background effect is enabled but no effect/sequence is selected'];
        // Pre-show announcement files
        $annFile = $settings['configDirectory'] . '/ShowManagerAnnouncements.config';
        $ann = file_exists($annFile) ? (json_decode(file_get_contents($annFile), true) ?? []) : [];
        foreach ($ann['pre_show'] ?? [] as $row) {
            $file = $row['file'] ?? '';
            if (!$file) continue;
            $path = $file[0] === '/' ? $file : __DIR__ . '/announcements/' . $file;
            if (!file_exists($path)) $warn[] = ['bad', "Pre-show audio missing: " . basename($file)];
        }
        echo json_encode(['warnings' => array_map(fn($w) => ['level' => $w[0], 'text' => $w[1]], $warn)]);
        break;

    case 'public_schedule':
        // The read-only feed itself. Meant to be fetched server-to-server by the
        // website's proxy through a tunnel — the box is never exposed directly.
        // If a feed key is configured, it must be supplied as ?key=… : the key
        // rides along on the server-side fetch and never reaches any visitor's
        // browser, so a scanner that stumbles onto the tunnel can't read it.
        // Contains show times only — no settings, no secrets.
        $pcfg = sm_publish_cfg($settings);
        $key  = (string)($pcfg['feed_key'] ?? '');
        if ($key !== '' && !hash_equals($key, (string)($_GET['key'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden']);
            break;
        }
        // CORS is only needed if a browser ever fetches this directly; the proxy
        // model doesn't. Send it only when an origin is configured.
        $origin = trim($pcfg['allow_origin'] ?? '');
        if ($origin !== '') header('Access-Control-Allow-Origin: ' . $origin);
        header('Cache-Control: public, max-age=60');
        echo json_encode(sm_build_public_schedule($settings, $schedule));
        break;

    case 'get_publish':
        $c = sm_publish_cfg($settings);
        echo json_encode([
            'enabled'       => !empty($c['enabled']),
            'url'           => $c['url'] ?? '',
            'method'        => $c['method'] ?? 'PUT',
            'auth_header'   => $c['auth_header'] ?? 'Authorization',
            'has_auth'      => !empty($c['auth_value']),
            'interval_mins' => (int)($c['interval_mins'] ?? 5),
            'allow_origin'  => $c['allow_origin'] ?? '',
            'paused'        => !empty($c['paused']),
            'status_note'   => $c['status_note'] ?? '',
            'events'        => array_values($c['events'] ?? []),
            'last_publish'  => $c['last_publish'] ?? null,
            'last_status'   => $c['last_status'] ?? null,
            'last_error'    => $c['last_error'] ?? null,
            // The read-only feed path (the site's proxy fetches this through a
            // tunnel). The UI appends ?key=… client-side so the full URL stays
            // in sync as the key changes without a save.
            'feed_base'     => 'plugin.php?plugin=' . rawurlencode(basename(__DIR__))
                             . '&page=ajax.php&nopage=1&action=public_schedule',
            'feed_key'      => $c['feed_key'] ?? '',
            // Dedicated localhost feed server (for a tunnel) — served by the
            // scheduler daemon, isolated from FPP's web UI.
            'feed_server'   => !empty($c['feed_server']),
            'feed_port'     => (int)($c['feed_port'] ?? 8088),
        ]);
        break;

    case 'save_publish':
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $c = sm_publish_cfg($settings);
        $c['enabled']       = !empty($body['enabled']);
        $c['url']           = trim($body['url'] ?? '');
        $m = strtoupper(trim($body['method'] ?? 'PUT'));
        $c['method']        = in_array($m, ['PUT', 'POST'], true) ? $m : 'PUT';
        $c['auth_header']   = trim($body['auth_header'] ?? 'Authorization') ?: 'Authorization';
        // Blank auth means "keep the saved token" (the UI never receives it back)
        if (array_key_exists('auth_value', $body) && trim($body['auth_value']) !== '')
            $c['auth_value'] = trim($body['auth_value']);
        if (!empty($body['clear_auth'])) unset($c['auth_value']);
        $iv = (int)($body['interval_mins'] ?? 5);
        $c['interval_mins'] = max(1, min(1440, $iv));
        $c['allow_origin']  = trim($body['allow_origin'] ?? '');
        // Feed key: an unguessable token required on the read-only feed URL.
        // Keep it to URL-safe characters so it can't break the query string.
        if (array_key_exists('feed_key', $body))
            $c['feed_key'] = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$body['feed_key']);
        $c['feed_server']   = !empty($body['feed_server']);
        $c['feed_port']     = max(1024, min(65535, (int)($body['feed_port'] ?? 8088)));
        $c['paused']        = !empty($body['paused']);
        $c['status_note']   = trim($body['status_note'] ?? '');
        // Sanitize the events list
        $evs = [];
        foreach ((array)($body['events'] ?? []) as $ev) {
            if (!is_array($ev)) continue;
            $name = trim($ev['name'] ?? '');
            if ($name === '') continue;
            $evs[] = [
                'name'  => $name,
                'when'  => trim($ev['when'] ?? ''),
                'label' => trim($ev['label'] ?? ''),
                'desc'  => trim($ev['desc'] ?? ''),
            ];
        }
        $c['events'] = $evs;
        sm_publish_save($settings, $c);
        echo json_encode(['ok' => true]);
        break;

    case 'publish_now':
        // Build the feed and push it to the configured static host (the "Push"
        // model — nothing inbound is ever opened on this box). Also used by the
        // scheduler's auto-publish loop.
        $c = sm_publish_cfg($settings);
        $url = trim($c['url'] ?? '');
        if ($url === '') { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'No publish URL configured']); break; }
        $json = json_encode(sm_build_public_schedule($settings, $schedule), JSON_PRETTY_PRINT);
        $headers = ['Content-Type: application/json'];
        if (!empty($c['auth_value']))
            $headers[] = ($c['auth_header'] ?: 'Authorization') . ': ' . $c['auth_value'];
        [$hc, $resp, $err] = sm_http($c['method'] ?? 'PUT', $url, $headers, $json);
        $ok = $hc >= 200 && $hc < 300;
        $c['last_publish'] = date('c');
        $c['last_status']  = $ok ? 'ok' : 'err';
        $c['last_error']   = $ok ? null : ($err ?: ('HTTP ' . $hc . ' ' . substr((string)$resp, 0, 160)));
        sm_publish_save($settings, $c);
        if ($ok) echo json_encode(['ok' => true, 'http' => $hc]);
        else { http_response_code(502); echo json_encode(['ok' => false, 'error' => $c['last_error']]); }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
}
