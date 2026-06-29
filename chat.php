<?php
/**
 * Mesajlaşma - Firma ve Müşteri Arasında
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireLogin();
$user = currentUser();
checkUserStatus();

$pdo       = getDB();
$orderId   = (int)($_GET['order_id']   ?? 0);
$companyId = (int)($_GET['company_id'] ?? 0);

if (!$orderId || !$companyId) {
    header('Location: ' . APP_URL . '/');
    exit;
}

$order   = null;
$company = null;
$canAccess = false;

if ($user['role'] === 'customer') {
    $stmt = $pdo->prepare('SELECT o.*, u.name AS customer_name FROM orders o JOIN users u ON u.id = o.customer_id WHERE o.id = ? AND o.customer_id = ?');
    $stmt->execute([$orderId, $user['id']]);
    $order = $stmt->fetch();

    if ($order) {
        $cStmt = $pdo->prepare(
            'SELECT c.* FROM companies c
             WHERE c.id = ?
             AND EXISTS (
               SELECT 1 FROM offers WHERE order_id = ? AND company_id = c.id
               UNION
               SELECT 1 FROM orders WHERE id = ? AND company_id = c.id
             ) LIMIT 1'
        );
        $cStmt->execute([$companyId, $orderId, $orderId]);
        $company = $cStmt->fetch();
        if ($company) $canAccess = true;
    }

} elseif ($user['role'] === 'company') {
    $myCompany = getCompanyByUserId($user['id']);
    if ($myCompany && (int)$myCompany['id'] === $companyId) {
        $stmt = $pdo->prepare(
            'SELECT o.*, u.name AS customer_name
             FROM orders o JOIN users u ON u.id = o.customer_id
             WHERE o.id = ?
             AND EXISTS (
               SELECT 1 FROM offers WHERE order_id = ? AND company_id = ?
               UNION
               SELECT 1 FROM orders WHERE id = ? AND company_id = ?
             ) LIMIT 1'
        );
        $stmt->execute([$orderId, $orderId, $companyId, $orderId, $companyId]);
        $order = $stmt->fetch();
        if ($order) {
            $company   = $myCompany;
            $canAccess = true;
        }
    }

} elseif (in_array($user['role'], ['admin', 'consultant'])) {
    $oStmt = $pdo->prepare('SELECT o.*, u.name AS customer_name FROM orders o JOIN users u ON u.id = o.customer_id WHERE o.id = ?');
    $oStmt->execute([$orderId]);
    $order = $oStmt->fetch();

    $cStmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $cStmt->execute([$companyId]);
    $company = $cStmt->fetch();

    if ($order && $company) $canAccess = true;
}

if (!$canAccess || !$order || !$company) {
    $_SESSION['flash'][] = ['type' => 'error', 'text' => 'Bu konuşmaya erişim yetkiniz yok.'];
    header('Location: ' . APP_URL . '/');
    exit;
}

// Mesajları okundu olarak işaretle (karşı tarafın mesajları)
$pdo->prepare('UPDATE messages SET is_read = 1 WHERE order_id = ? AND company_id = ? AND sender_id != ?')
    ->execute([$orderId, $companyId, $user['id']]);

// POST: Mesaj gönder
if (isPost() && isset($_POST['send_message'])) {
    verifyCsrf();
    $text = trim($_POST['message_text'] ?? '');
    if ($text !== '' && mb_strlen($text) <= 2000) {
        $pdo->prepare('INSERT INTO messages (order_id, company_id, sender_id, message) VALUES (?, ?, ?, ?)')
            ->execute([$orderId, $companyId, $user['id'], $text]);
    }
    header('Location: ' . APP_URL . '/chat.php?order_id=' . $orderId . '&company_id=' . $companyId . '#bottom');
    exit;
}

// Konuşmayı yükle
$msgStmt = $pdo->prepare(
    'SELECT m.*, u.name AS sender_name, u.role AS sender_role
     FROM messages m JOIN users u ON u.id = m.sender_id
     WHERE m.order_id = ? AND m.company_id = ?
     ORDER BY m.created_at ASC'
);
$msgStmt->execute([$orderId, $companyId]);
$chatMessages = $msgStmt->fetchAll();

// Geri linki: müşteri → order-detail, firma → company-orders
if ($user['role'] === 'customer') {
    $backUrl = APP_URL . '/order-detail.php?id=' . $orderId;
} elseif ($user['role'] === 'company') {
    $backUrl = APP_URL . '/company-orders.php?id=' . $orderId;
} else {
    $backUrl = APP_URL . '/admin/';
}

// Karşı tarafın adı
$otherName = ($user['role'] === 'customer')
    ? e($company['company_name'])
    : e($order['customer_name']);

$extraCss = '<style>
.chat-wrap{display:flex;flex-direction:column;gap:0;max-width:720px;margin:0 auto;}
.chat-box{background:#fff;border-radius:12px;border:1px solid var(--border-color);overflow:hidden;}
.chat-header{padding:16px 20px;border-bottom:1px solid var(--border-color);background:var(--bg-secondary);}
.chat-messages{padding:20px;display:flex;flex-direction:column;gap:12px;min-height:300px;max-height:520px;overflow-y:auto;}
.msg{display:flex;flex-direction:column;max-width:75%;}
.msg.mine{align-self:flex-end;align-items:flex-end;}
.msg.theirs{align-self:flex-start;align-items:flex-start;}
.msg-bubble{padding:10px 14px;border-radius:18px;font-size:14px;line-height:1.5;word-break:break-word;white-space:pre-wrap;}
.msg.mine .msg-bubble{background:var(--primary);color:#fff;border-bottom-right-radius:4px;}
.msg.theirs .msg-bubble{background:#f0f2f5;color:var(--text-primary);border-bottom-left-radius:4px;}
.msg-meta{font-size:11px;color:var(--text-muted);margin-top:3px;padding:0 4px;}
.chat-form{padding:16px 20px;border-top:1px solid var(--border-color);background:var(--bg-secondary);}
.chat-form-row{display:flex;gap:10px;align-items:flex-end;}
.chat-form textarea{resize:none;border-radius:20px;padding:10px 16px;line-height:1.4;min-height:42px;max-height:120px;flex:1;}
.chat-form .btn-send{border-radius:50%;width:42px;height:42px;padding:0;flex-shrink:0;font-size:18px;display:flex;align-items:center;justify-content:center;}
.chat-empty{text-align:center;color:var(--text-muted);font-size:14px;padding:40px 20px;}
</style>';

$pageTitle = 'Mesajlar - ' . $order['tracking_code'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>💬 Mesajlaşma</h1>
    <p><?= e($order['tracking_code']) ?> · <?= $otherName ?></p>
  </div>
</div>

<div class="page-content">
  <div class="container">
    <div style="margin-bottom:14px;">
      <a href="<?= $backUrl ?>" class="btn btn-sm btn-outline-primary">← Siparişe Dön</a>
    </div>

    <div class="chat-wrap">
      <div class="chat-box">
        <div class="chat-header">
          <strong><?= $otherName ?></strong>
          <span style="font-size:13px;color:var(--text-muted);margin-left:10px;">
            · Sipariş: <?= e($order['tracking_code']) ?>
          </span>
        </div>

        <div class="chat-messages" id="chatMessages">
          <?php if (empty($chatMessages)): ?>
            <div class="chat-empty">
              <div style="font-size:36px;margin-bottom:8px;">💬</div>
              <p>Henüz mesaj yok. İlk mesajı göndererek konuşmayı başlatın.</p>
            </div>
          <?php else: ?>
            <?php foreach ($chatMessages as $m): ?>
              <?php $isMine = (int)$m['sender_id'] === (int)$user['id']; ?>
              <div class="msg <?= $isMine ? 'mine' : 'theirs' ?>">
                <div class="msg-bubble"><?= e($m['message']) ?></div>
                <div class="msg-meta">
                  <?= $isMine ? 'Siz' : e($m['sender_name']) ?>
                  · <?= formatDateTimeTR($m['created_at']) ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <div id="bottom"></div>
        </div>

        <div class="chat-form">
          <form method="POST" id="chatForm">
            <?= csrfInput() ?>
            <div class="chat-form-row">
              <textarea name="message_text" class="form-control" id="msgInput"
                        placeholder="Mesajınızı yazın..."
                        maxlength="2000" rows="1" required></textarea>
              <button type="submit" name="send_message" class="btn btn-primary btn-send" title="Gönder">
                ➤
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Sayfayı en alta kaydır
(function(){
  var box = document.getElementById('chatMessages');
  if (box) box.scrollTop = box.scrollHeight;
})();

// Enter ile gönder (Shift+Enter = yeni satır)
document.getElementById('msgInput').addEventListener('keydown', function(e){
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    document.getElementById('chatForm').submit();
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
