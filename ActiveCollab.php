<?php

/**
 * =========================================================
 * ActiveCollab Dashboard
 * =========================================================
 *
 * Project: gaconstructionmn.com
 *
 * HOW TO RUN
 * ---------------------------------------------------------
 * 1. Save as:
 *    ActiveCollab.php
 *
 * 2. Replace YOUR_TOKEN_HERE with your API token
 *
 * 3. Run:
 *    php -S localhost:8010
 *
 * 4. Open:
 *    http://localhost:8010/ActiveCollab.php
 *
 * =========================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* =========================================================
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
========================================================= */

$BASE_URL   = 'https://designer.edeveloperz.com';
$TOKEN      = 'YOUR_TOKEN_HERE';
$PROJECT_ID = 2507;

/* =========================================================
|--------------------------------------------------------------------------
| IMAGE PROXY
| Handles: ?img=ATTACHMENT_ID
| Routes image requests through PHP with auth token
| so browser can display authenticated images normally.
|--------------------------------------------------------------------------
========================================================= */

if (isset($_GET['img'])) {

    $attachId = (int) $_GET['img'];

    // /api/v1/attachments/{id}/download is confirmed working
    $url = $BASE_URL . "/api/v1/attachments/$attachId/download";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'X-Angie-AuthApiToken: ' . $TOKEN,
        ],
    ]);

    $imgData     = curl_exec($ch);
    $status      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
    $curlError   = curl_error($ch);
    curl_close($ch);

    if (isset($_GET['debug'])) {
        echo "<b>URL:</b> $url<br>";
        echo "<b>Status:</b> $status<br>";
        echo "<b>Content-Type:</b> $contentType<br>";
        echo "<b>Curl Error:</b> " . ($curlError ?: 'none') . "<br>";
        echo "<b>Response size:</b> " . strlen($imgData) . " bytes<br>";
        echo "<b>First bytes (hex):</b> " . bin2hex(substr($imgData, 0, 8)) . "<br>";
        exit;
    }

    if ($status === 200 && strlen($imgData) > 100) {
        // Strip charset if present e.g. "image/jpeg; charset=..."
        $mime = strtok($contentType, ';') ?: 'image/jpeg';
        header('Content-Type: ' . trim($mime));
        header('Cache-Control: max-age=86400');
        header('Content-Length: ' . strlen($imgData));
        echo $imgData;
    } else {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo "Image not found. Status: $status. Error: $curlError";
    }

    exit;
}

/* =========================================================
|--------------------------------------------------------------------------
| FILE PROXY
| Handles: ?file=ATTACHMENT_ID&name=FILENAME
| Forces download of any attachment through PHP with auth.
|--------------------------------------------------------------------------
========================================================= */

if (isset($_GET['file'])) {

    $attachId = (int) $_GET['file'];
    $fileName = basename($_GET['name'] ?? 'download');

    $url = $BASE_URL . "/api/v1/attachments/$attachId/download";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'X-Angie-AuthApiToken: ' . $TOKEN,
        ],
    ]);

    $fileData    = curl_exec($ch);
    $status      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
    curl_close($ch);

    if ($status === 200 && strlen($fileData) > 0) {
        $mime = strtok($contentType, ';') ?: 'application/octet-stream';
        header('Content-Type: ' . trim($mime));
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($fileData));
        echo $fileData;
    } else {
        http_response_code(404);
        echo 'File not found. Status: ' . $status;
    }

    exit;
}

/* =========================================================
|--------------------------------------------------------------------------
| CURL REQUEST
|--------------------------------------------------------------------------
========================================================= */

function acRequest($endpoint)
{
    global $BASE_URL, $TOKEN;

    $ch = curl_init($BASE_URL . $endpoint);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'X-Angie-AuthApiToken: ' . $TOKEN
        ],
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("Curl Error: " . curl_error($ch));
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        die("API Error ($status): " . htmlspecialchars($response));
    }

    return json_decode($response, true);
}

/* =========================================================
|--------------------------------------------------------------------------
| FETCH PROJECT TASKS
|--------------------------------------------------------------------------
========================================================= */

$projectTasks = acRequest("/api/v1/projects/$PROJECT_ID/tasks");

$project   = $projectTasks['project']    ?? [];
$tasks     = $projectTasks['tasks']      ?? [];
$taskLists = $projectTasks['task_lists'] ?? [];

/* =========================================================
|--------------------------------------------------------------------------
| TASK LIST MAP
|--------------------------------------------------------------------------
========================================================= */

$taskListMap = [];

foreach ($taskLists as $list) {
    $taskListMap[$list['id']] = $list['name'];
}

/* =========================================================
|--------------------------------------------------------------------------
| FETCH FULL TASK DETAILS + COMMENTS
|--------------------------------------------------------------------------
========================================================= */

$fullTasks = [];

foreach ($tasks as $task) {

    $taskId  = $task['id'];
    $details = acRequest("/api/v1/projects/$PROJECT_ID/tasks/$taskId");

    $fullTasks[] = $details;
}

/* =========================================================
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
========================================================= */

function formatDateValue($timestamp)
{
    if (!$timestamp) {
        return '-';
    }

    return date('d M Y h:i A', $timestamp);
}

function getDueClass($timestamp)
{
    if (!$timestamp) {
        return '';
    }

    return $timestamp < time() ? 'overdue' : 'upcoming';
}

function cleanHtml($html)
{
    if (!$html) {
        return '';
    }

    return strip_tags(
        $html,
        '<a><b><strong><i><em><br><p><ul><ol><li><h1><h2><h3><h4><span>'
    );
}

function proxyImageUrl($attachmentId)
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/ActiveCollab.php';
    return $script . '?img=' . (int) $attachmentId;
}

function proxyFileUrl($attachmentId, $fileName)
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/ActiveCollab.php';
    return $script . '?file=' . (int) $attachmentId
         . '&name=' . urlencode($fileName);
}

/* =========================================================
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
========================================================= */

$totalTasks     = count($tasks);
$completedTasks = 0;

foreach ($tasks as $task) {
    if (!empty($task['is_completed'])) {
        $completedTasks++;
    }
}

$openTasks = $totalTasks - $completedTasks;

?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>ActiveCollab Dashboard</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f6fb;
            font-family: Arial, sans-serif;
            color: #222;
        }

        .container {
            width: 95%;
            margin: 30px auto;
        }

        /* ---- HERO ---- */

        .hero {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white;
            padding: 35px;
            border-radius: 18px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,.12);
        }

        .hero h1 {
            margin: 0;
            font-size: 42px;
        }

        .project-meta {
            margin-top: 10px;
            opacity: .9;
        }

        .stats {
            margin-top: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .stat {
            background: rgba(255,255,255,.08);
            padding: 18px 20px;
            border-radius: 14px;
            min-width: 170px;
        }

        .stat-title {
            font-size: 14px;
            opacity: .8;
        }

        .stat-value {
            font-size: 28px;
            margin-top: 5px;
            font-weight: bold;
        }

        /* ---- SEARCH ---- */

        .search-box {
            margin-bottom: 25px;
        }

        .search-box input {
            width: 100%;
            padding: 18px;
            border-radius: 14px;
            border: none;
            font-size: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
            outline: none;
        }

        .search-box input:focus {
            box-shadow: 0 2px 14px rgba(37,99,235,.25);
        }

        /* ---- TASK CARD ---- */

        .task-card {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 22px;
            box-shadow: 0 5px 18px rgba(0,0,0,.06);
        }

        .task-header {
            padding: 22px;
            cursor: pointer;
            border-left: 6px solid #2563eb;
            transition: background .2s;
            user-select: none;
        }

        .task-header:hover {
            background: #f8fafc;
        }

        .task-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chevron {
            margin-left: auto;
            font-size: 18px;
            transition: transform .25s;
            color: #94a3b8;
            flex-shrink: 0;
        }

        .task-header.open .chevron {
            transform: rotate(180deg);
        }

        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 14px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: bold;
        }

        .badge-open {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-done {
            background: #dcfce7;
            color: #15803d;
        }

        /* ---- TASK BODY ---- */

        .task-body {
            display: none;
            padding: 22px;
            border-top: 1px solid #eee;
            background: #fafafa;
        }

        .task-description {
            margin-bottom: 22px;
            padding: 16px 20px;
            background: white;
            border-radius: 12px;
            border: 1px solid #eee;
            line-height: 1.7;
        }

        .section-title {
            margin: 0 0 16px;
            font-size: 18px;
            color: #334155;
        }

        /* ---- COMMENT ---- */

        .comment {
            background: white;
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 18px;
            border: 1px solid #ececec;
        }

        .comment-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 20px;
        }

        .comment-user {
            font-weight: bold;
            color: #1e293b;
        }

        .comment-date {
            color: #888;
            font-size: 13px;
            white-space: nowrap;
        }

        .comment-body {
            line-height: 1.7;
            color: #374151;
        }

        /* ---- ATTACHMENTS ---- */

        .attachments {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .attachment-img {
            position: relative;
            width: 220px;
        }

        .attachment-img a {
            display: block;
        }

        .attachment-img img {
            width: 100%;
            border-radius: 12px;
            border: 1px solid #ddd;
            transition: transform .2s, box-shadow .2s;
            display: block;
            background: #f1f5f9;
            min-height: 80px;
        }

        .attachment-img img:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
        }

        .attachment-img .img-name {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .attachment-file {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #f1f5f9;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: #1d4ed8;
            font-size: 14px;
            font-weight: 500;
            transition: background .2s;
        }

        .attachment-file:hover {
            background: #e0e7ff;
        }

        .attachment-file .file-icon {
            font-size: 20px;
        }

        /* ---- MISC ---- */

        .empty {
            color: #94a3b8;
            padding: 10px 0;
            font-style: italic;
        }

        .overdue {
            color: #dc2626;
            font-weight: bold;
        }

        .upcoming {
            color: #15803d;
            font-weight: bold;
        }

        .hidden {
            display: none !important;
        }

        /* ---- RESPONSIVE ---- */

        @media (max-width: 768px) {

            .hero h1 {
                font-size: 28px;
            }

            .task-title {
                font-size: 18px;
            }

            .attachment-img {
                width: 100%;
            }

            .comment-top {
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- ======================================================
    | HERO
    ======================================================= -->

    <div class="hero">

        <h1>
            <?= htmlspecialchars($project['name'] ?? 'Project Dashboard') ?>
        </h1>

        <div class="project-meta">
            Project ID: <?= $PROJECT_ID ?>
        </div>

        <div class="stats">

            <div class="stat">
                <div class="stat-title">Total Tasks</div>
                <div class="stat-value"><?= $totalTasks ?></div>
            </div>

            <div class="stat">
                <div class="stat-title">Open Tasks</div>
                <div class="stat-value"><?= $openTasks ?></div>
            </div>

            <div class="stat">
                <div class="stat-title">Completed</div>
                <div class="stat-value"><?= $completedTasks ?></div>
            </div>

            <div class="stat">
                <div class="stat-title">Task Lists</div>
                <div class="stat-value"><?= count($taskLists) ?></div>
            </div>

            <div class="stat">
                <div class="stat-title">Members</div>
                <div class="stat-value"><?= count($project['members'] ?? []) ?></div>
            </div>

        </div>

    </div>

    <!-- ======================================================
    | SEARCH
    ======================================================= -->

    <div class="search-box">
        <input
            type="text"
            id="searchInput"
            placeholder="&#128269; Search task, comment, member..."
            autocomplete="off"
        >
    </div>

    <!-- ======================================================
    | TASKS
    ======================================================= -->

    <?php foreach ($fullTasks as $taskData): ?>

        <?php

        $task     = $taskData['single']   ?? [];
        $comments = $taskData['comments'] ?? [];

        ?>

        <div class="task-card" data-search="<?= htmlspecialchars(
            strtolower($task['name'] ?? '')
            . ' ' . strtolower(strip_tags($task['body'] ?? ''))
        ) ?>">

            <!-- TASK HEADER -->

            <div class="task-header">

                <div class="task-title">
                    <?= htmlspecialchars($task['name'] ?? '') ?>
                    <span class="chevron">&#9660;</span>
                </div>

                <div class="meta-row">

                    <span class="badge <?= !empty($task['is_completed']) ? 'badge-done' : 'badge-open' ?>">
                        <?= !empty($task['is_completed']) ? '&#10003; Completed' : 'Open' ?>
                    </span>

                    <span>
                        Task List:
                        <strong>
                            <?= htmlspecialchars(
                                $taskListMap[$task['task_list_id'] ?? ''] ?? '-'
                            ) ?>
                        </strong>
                    </span>

                    <span class="<?= getDueClass($task['due_on'] ?? 0) ?>">
                        Due: <?= formatDateValue($task['due_on'] ?? 0) ?>
                    </span>

                    <span>
                        Comments: <?= count($comments) ?>
                    </span>

                    <span>
                        Updated: <?= formatDateValue($task['updated_on'] ?? 0) ?>
                    </span>

                </div>

            </div>

            <!-- TASK BODY -->

            <div class="task-body">

                <?php if (!empty($task['body'])): ?>
                    <div class="task-description">
                        <?= cleanHtml($task['body']) ?>
                    </div>
                <?php endif; ?>

                <h2 class="section-title">
                    &#128172; Comments (<?= count($comments) ?>)
                </h2>

                <?php if (empty($comments)): ?>
                    <div class="empty">No comments yet.</div>
                <?php endif; ?>

                <?php foreach ($comments as $comment): ?>

                    <?php

                    $attachments = $comment['attachments'] ?? [];

                    ?>

                    <div class="comment">

                        <div class="comment-top">
                            <div class="comment-user">
                                &#128100;
                                <?= htmlspecialchars($comment['created_by_name'] ?? '') ?>
                            </div>
                            <div class="comment-date">
                                <?= formatDateValue($comment['created_on'] ?? 0) ?>
                            </div>
                        </div>

                        <div class="comment-body">
                            <?= cleanHtml($comment['body_formatted'] ?? '') ?>
                        </div>

                        <!-- ATTACHMENTS -->

                        <?php if (!empty($attachments)): ?>

                            <div class="attachments">

                                <?php foreach ($attachments as $att): ?>

                                    <?php

                                    $attId   = (int) ($att['id']   ?? 0);
                                    $attName = $att['name'] ?? 'file';
                                    $isImage = ($att['file_type'] ?? '') === 'image';

                                    ?>

                                    <?php if ($isImage): ?>

                                        <!-- IMAGE: proxied through PHP so auth token is sent -->

                                        <div class="attachment-img">
                                            <a
                                                href="<?= htmlspecialchars(proxyImageUrl($attId)) ?>"
                                                target="_blank"
                                                title="<?= htmlspecialchars($attName) ?>"
                                            >
                                                <img
                                                    src="<?= htmlspecialchars(proxyImageUrl($attId)) ?>"
                                                    alt="<?= htmlspecialchars($attName) ?>"
                                                    loading="lazy"
                                                    onerror="this.parentElement.parentElement.style.opacity='.4'"
                                                >
                                            </a>
                                            <div class="img-name">
                                                <?= htmlspecialchars($attName) ?>
                                            </div>
                                        </div>

                                    <?php else: ?>

                                        <!-- FILE: proxied download -->

                                        <?php

                                        $ext      = strtolower($att['extension'] ?? '');
                                        $fileIcon = match($ext) {
                                            'pdf'            => '&#128196;',
                                            'doc','docx'     => '&#128209;',
                                            'xls','xlsx'     => '&#128202;',
                                            'zip','rar','7z' => '&#128230;',
                                            'mp4','mov','avi'=> '&#127916;',
                                            'mp3','wav'      => '&#127925;',
                                            default          => '&#128190;',
                                        };

                                        ?>

                                        <a
                                            class="attachment-file"
                                            href="<?= htmlspecialchars(proxyFileUrl($attId, $attName)) ?>"
                                            download="<?= htmlspecialchars($attName) ?>"
                                        >
                                            <span class="file-icon"><?= $fileIcon ?></span>
                                            <?= htmlspecialchars($attName) ?>
                                        </a>

                                    <?php endif; ?>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<!-- ======================================================
| JS
======================================================= -->

<script>

    /* ---- Toggle task body ---- */

    document.querySelectorAll('.task-header').forEach(header => {
        header.addEventListener('click', () => {
            const body = header.nextElementSibling;
            const isOpen = body.style.display === 'block';
            body.style.display = isOpen ? 'none' : 'block';
            header.classList.toggle('open', !isOpen);
        });
    });

    /* ---- Search ---- */

    const searchInput = document.getElementById('searchInput');

    searchInput.addEventListener('input', function () {

        const value = this.value.toLowerCase().trim();

        document.querySelectorAll('.task-card').forEach(card => {

            if (!value) {
                card.classList.remove('hidden');
                return;
            }

            // Search in task title, description, comments text
            const text = card.innerText.toLowerCase();
            const meta = card.dataset.search || '';

            card.classList.toggle(
                'hidden',
                !text.includes(value) && !meta.includes(value)
            );
        });

        // Show no-results message
        const visible = document.querySelectorAll('.task-card:not(.hidden)').length;
        let noResult  = document.getElementById('no-result');

        if (!noResult) {
            noResult = document.createElement('div');
            noResult.id = 'no-result';
            noResult.style.cssText = 'text-align:center;padding:40px;color:#94a3b8;font-size:18px;';
            noResult.textContent   = 'No tasks match your search.';
            document.querySelector('.container').appendChild(noResult);
        }

        noResult.style.display = (value && visible === 0) ? 'block' : 'none';

    });

</script>

</body>
</html>