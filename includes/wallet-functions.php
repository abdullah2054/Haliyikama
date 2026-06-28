<?php
/**
 * Bakiye / Wallet sistemi yardımcıları
 */

/**
 * Firma bakiyesini günceller ve hareketi kaydeder
 *
 * @param int    $companyId  Firma ID
 * @param string $type       credit|debit|block|refund
 * @param float  $amount     Tutar (pozitif)
 * @param string $description Açıklama
 * @param int|null $orderId  İlgili sipariş
 * @return bool
 */
function walletTransaction(
    int $companyId,
    string $type,
    float $amount,
    string $description,
    ?int $orderId = null
): bool {
    $pdo = getDB();

    $pdo->beginTransaction();
    try {
        // Bakiye güncelle
        $sql = match($type) {
            'credit', 'refund' => 'UPDATE companies SET balance = balance + ? WHERE id = ?',
            'debit', 'block'   => 'UPDATE companies SET balance = balance - ? WHERE id = ?',
            default            => throw new \InvalidArgumentException('Geçersiz işlem türü'),
        };
        $pdo->prepare($sql)->execute([$amount, $companyId]);

        // Hareket kaydı ekle
        $pdo->prepare(
            'INSERT INTO wallet_transactions (company_id, order_id, type, amount, description)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$companyId, $orderId, $type, $amount, $description]);

        $pdo->commit();
        return true;
    } catch (\Exception $e) {
        $pdo->rollBack();
        error_log('Wallet işlem hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Firma bakiyesini getirir
 */
function getCompanyBalance(int $companyId): float
{
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT balance FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    return (float)($stmt->fetchColumn() ?? 0);
}

/**
 * Platform ücretini bakiyeden keser (sipariş kabul edildiğinde)
 */
function chargePlatformFee(int $companyId, int $orderId): bool
{
    $fee = (float)getSetting('platform_fee', '25.00');
    if ($fee <= 0) return true;

    // Bakiye yeterli mi?
    if (getCompanyBalance($companyId) < $fee) {
        return false;
    }

    $ok = walletTransaction(
        $companyId,
        'debit',
        $fee,
        'Platform hizmet bedeli - Sipariş #' . $orderId,
        $orderId
    );

    if ($ok) {
        // fee_status güncelle
        $pdo = getDB();
        $pdo->prepare('UPDATE orders SET platform_fee = ?, fee_status = "charged" WHERE id = ?')
            ->execute([$fee, $orderId]);
    }
    return $ok;
}

/**
 * Platform ücretini iade eder (müşteri kaynaklı iptal)
 */
function refundPlatformFee(int $companyId, int $orderId): bool
{
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT platform_fee, fee_status FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || $order['fee_status'] !== 'charged') {
        return false;
    }

    $fee = (float)$order['platform_fee'];
    if ($fee <= 0) return true;

    $ok = walletTransaction(
        $companyId,
        'refund',
        $fee,
        'Müşteri kaynaklı iptal iadesi - Sipariş #' . $orderId,
        $orderId
    );

    if ($ok) {
        $pdo->prepare('UPDATE orders SET fee_status = "refunded" WHERE id = ?')->execute([$orderId]);
    }
    return $ok;
}

/**
 * Firma bakiye hareketlerini sayfalayarak getirir
 */
function getWalletHistory(int $companyId, int $page = 1, int $perPage = 20): array
{
    $pdo    = getDB();
    $offset = ($page - 1) * $perPage;

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM wallet_transactions WHERE company_id = ?');
    $countStmt->execute([$companyId]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT wt.*, o.tracking_code
         FROM wallet_transactions wt
         LEFT JOIN orders o ON o.id = wt.order_id
         WHERE wt.company_id = ?
         ORDER BY wt.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$companyId, $perPage, $offset]);
    $rows = $stmt->fetchAll();

    return [
        'rows'        => $rows,
        'total'       => $total,
        'total_pages' => (int)ceil($total / $perPage),
        'current'     => $page,
    ];
}
