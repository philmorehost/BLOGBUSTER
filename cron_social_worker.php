<?php
// Set execution via Linux Cron: * * * * * /usr/bin/php /path/to/cron_social_worker.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/SocialDispatcher.php';

$now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("SELECT q.*, a.access_token, a.account_id 
                       FROM social_post_queue q 
                       JOIN social_accounts a ON q.platform = a.platform 
                       WHERE q.status = 'pending' AND q.scheduled_at <= ? AND a.status = 'active'");
$stmt->execute([$now]);
$pendingPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pendingPosts as $item) {
    if ($item['platform'] === 'facebook') {
        $res = SocialDispatcher::postToFacebook($item['account_id'], $item['access_token'], $item['message'], $item['media_url']);
    } elseif ($item['platform'] === 'twitter') {
        $res = SocialDispatcher::postToX($item['access_token'], $item['message']);
    }

    if (isset($res['code']) && $res['code'] >= 200 && $res['code'] < 300) {
        $update = $pdo->prepare("UPDATE social_post_queue SET status = 'sent' WHERE id = ?");
        $update->execute([$item['id']]);
    } else {
        $errorMsg = json_encode($res);
        $update = $pdo->prepare("UPDATE social_post_queue SET status = 'failed', error_log = ? WHERE id = ?");
        $update->execute([$errorMsg, $item['id']]);
    }
}