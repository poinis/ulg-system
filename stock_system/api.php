<?php
/**
 * api.php — REST API
 * GET  ?action=dashboard_stats|stock_summary|transfer_plans|reorder_alerts|uploads
 * POST ?action=calculate|approve|reject
 * GET  ?action=export&type=stock|plans|alerts
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/SalesAnalytics.php';
require_once __DIR__ . '/classes/TransferOptimizer.php';

$action   = $_REQUEST['action']    ?? '';
$uploadId = isset($_REQUEST['upload_id']) ? (int)$_REQUEST['upload_id'] : null;
$db       = Database::getInstance();

try {
    $sa  = new SalesAnalytics($uploadId);
    $uid = $sa->uploadId;
    $opt = new TransferOptimizer($uid);

    switch ($action) {

        // ── Stats ─────────────────────────────────────────
        case 'dashboard_stats':
            $stats = $sa->getDashboardStats();
            // Plan counts
            $pc = $db->prepare(
                "SELECT plan_type, status, COUNT(*) AS cnt, COALESCE(SUM(qty),0) AS qty
                 FROM replenish_plans WHERE upload_id=:uid GROUP BY plan_type,status"
            );
            $pc->execute([':uid'=>$uid]);
            $plans = [];
            foreach ($pc->fetchAll() as $r) {
                $plans[$r['plan_type']][$r['status']] = ['cnt'=>(int)$r['cnt'],'qty'=>(int)$r['qty']];
            }
            echo json_encode(['ok'=>true,'stats'=>$stats,'plans'=>$plans,'upload_id'=>$uid]);
            break;

        // ── Stock Summary ─────────────────────────────────
        case 'stock_summary':
            $store  = $_GET['store']  ?? null;
            $status = $_GET['status'] ?? '';
            $data   = $sa->getAllProductsSummary($store, $status);
            echo json_encode(['ok'=>true,'data'=>$data,'count'=>count($data)]);
            break;

        // ── Transfer Plans ────────────────────────────────
        case 'transfer_plans':
            $type   = $_GET['plan_type'] ?? '';
            $status = $_GET['status']    ?? 'PENDING';
            $plans  = $opt->getPlans($type, $status);
            echo json_encode(['ok'=>true,'data'=>$plans,'count'=>count($plans)]);
            break;

        // ── Reorder Alerts ────────────────────────────────
        case 'reorder_alerts':
            $alerts = $sa->getReorderAlerts();
            echo json_encode(['ok'=>true,'data'=>$alerts,'count'=>count($alerts)]);
            break;

        // ── Upload list ────────────────────────────────────
        case 'uploads':
            $rows = $db->query(
                "SELECT ru.*, (SELECT COUNT(DISTINCT barcode) FROM replenish_stock WHERE upload_id=ru.id) AS sku_count
                 FROM replenish_uploads ru ORDER BY id DESC LIMIT 20"
            )->fetchAll();
            echo json_encode(['ok'=>true,'data'=>$rows]);
            break;

        // ── Calculate Plans (POST) ────────────────────────
        case 'calculate':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST only');
            $summary = $opt->generateAndSaveAll();
            $ts      = $sa->calculateTopSellers();
            echo json_encode(['ok'=>true,'summary'=>$summary,'top_sellers'=>count($ts),'upload_id'=>$uid]);
            break;

        // ── Approve (POST) ────────────────────────────────
        case 'approve':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST only');
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $ids  = (array)($data['ids'] ?? []);
            if ($data['all'] ?? false) {
                // Approve all pending (optionally filtered by plan_type)
                $planType = $data['plan_type'] ?? '';
                $aql = "UPDATE replenish_plans SET status='APPROVED',action_at=NOW() WHERE upload_id=:uid AND status='PENDING'";
                $apr = [':uid'=>$uid];
                if ($planType) { $aql .= " AND plan_type=:pt"; $apr[':pt']=$planType; }
                $stmt = $db->prepare($aql);
                $stmt->execute($apr);
                $count = $stmt->rowCount();
            } else {
                $count = 0;
                foreach ($ids as $id) {
                    if ($opt->updateStatus((int)$id, 'APPROVED')) $count++;
                }
            }
            echo json_encode(['ok'=>true,'approved'=>$count]);
            break;

        // ── Reject (POST) ─────────────────────────────────
        case 'reject':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST only');
            $data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id     = (int)($data['id'] ?? 0);
            $reason = $data['reason'] ?? '';
            $ok     = $opt->updateStatus($id, 'REJECTED', $reason);
            echo json_encode(['ok'=>$ok]);
            break;

        // ── Export (GET) ──────────────────────────────────
        case 'export':
            $type = $_GET['type'] ?? 'stock';
            $bom  = "\xEF\xBB\xBF";

            if ($type === 'stock') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="replenish_stock_' . date('Ymd') . '.csv"');
                $rows = $sa->getAllProductsSummary();
                echo $bom . "Store,Short,Barcode,Product,Price,Stock,Qty Sold,Eff Days,Rate/day,Rate/week,Week Cover,Holding Days,Need 14d,Diff,Diff Label,Status\n";
                foreach ($rows as $r) {
                    echo implode(',', [
                        '"' . addslashes($r['store_name'])    . '"',
                        '"' . addslashes($r['store_short'])   . '"',
                        $r['barcode'],
                        '"' . addslashes($r['product_name'] ?? '') . '"',
                        $r['price'],
                        $r['stock'],
                        $r['qty_sold'],
                        $r['effective_days'],
                        $r['daily_rate'],
                        $r['weekly_rate'],
                        $r['week_cover'] >= 999 ? '∞' : $r['week_cover'],
                        $r['holding_days'] >= 999 ? '∞' : $r['holding_days'],
                        $r['need_14d'],
                        $r['diff'],
                        '"' . addslashes($r['diff_label']) . '"',
                        $r['status'],
                    ]) . "\n";
                }

            } elseif ($type === 'plans') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="replenish_plans_' . date('Ymd') . '.csv"');
                $plans = $opt->getPlans('', '');
                echo $bom . "Type,Barcode,Product,From Store,From Short,From WC,From Excess,To Store,To Short,To WC,To Rate/d,Qty,Distance km,Priority,Status,Diff Label\n";
                foreach ($plans as $p) {
                    echo implode(',', [
                        $p['plan_type'],
                        $p['barcode'],
                        '"' . addslashes($p['product_name'] ?? '') . '"',
                        '"' . addslashes($p['from_store_name'] ?? $p['from_store_code']) . '"',
                        $p['from_store_code'],
                        $p['from_week_cover'],
                        $p['from_excess_qty'],
                        '"' . addslashes($p['to_store_name'] ?? $p['to_store_code']) . '"',
                        $p['to_store_code'],
                        $p['to_week_cover'],
                        $p['to_daily_rate'],
                        $p['qty'],
                        $p['distance_km'] ?? '',
                        $p['priority'],
                        $p['status'],
                        '"' . addslashes($p['to_diff_label'] ?? '') . '"',
                    ]) . "\n";
                }

            } elseif ($type === 'alerts') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="replenish_alerts_' . date('Ymd') . '.csv"');
                $alerts = $sa->getReorderAlerts();
                echo $bom . "Rank,Tier,Barcode,Product,Total Stock,Total Sold,Weekly Rate,Week Cover,Target Weeks,Trigger\n";
                foreach ($alerts as $a) {
                    echo implode(',', [
                        $a['rank_position'],
                        $a['tier'],
                        $a['barcode'],
                        '"' . addslashes($a['product_name']) . '"',
                        $a['total_stock'],
                        $a['total_qty_sold'],
                        $a['total_weekly_rate'],
                        $a['total_week_cover'],
                        $a['target_weeks'],
                        match($a['tier']) { 'TOP1_20'=>NOTICE_1_20,'TOP21_40'=>NOTICE_21_40,default=>NOTICE_41_60 },
                    ]) . "\n";
                }
            }
            exit;

        default:
            echo json_encode(['ok'=>false,'error'=>'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}