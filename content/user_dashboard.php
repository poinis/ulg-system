<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$currentUsername = $_SESSION['username'];

// ดึง role ของผู้ใช้
$sql_role = "SELECT role FROM users WHERE username = ?";
$stmt_role = mysqli_prepare($conn, $sql_role);
mysqli_stmt_bind_param($stmt_role, "s", $currentUsername);
mysqli_stmt_execute($stmt_role);
mysqli_stmt_bind_result($stmt_role, $userRole);
mysqli_stmt_fetch($stmt_role);
mysqli_stmt_close($stmt_role);

$sql = "SELECT * FROM content_brief ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard งานทั้งหมด</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      body {
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        max-width: 1400px;
        margin: 20px auto;
        padding: 10px;
        background-color: #f7f9fc;
        color: #222;
      }
      h2 {
        text-align: center;
        margin-bottom: 10px;
        color: #2c3e50;
      }
      .btn-brief {
        display: inline-block;
        background-color: #2980b9;
        color: white;
        padding: 10px 25px;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        transition: background-color 0.3s ease;
      }
      .btn-brief:hover {
        background-color: #1c5980;
      }
      .table-container {
        width: 100%;
        overflow-x: auto;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-radius: 8px;
        overflow: hidden;
        min-width: 900px;
      }
      th {
        background-color: #2980b9;
        color: white;
        text-align: left;
        padding: 12px 10px;
        font-weight: 600;
        letter-spacing: 0.02em;
        font-size: 14px;
      }
      td {
        padding: 12px 10px;
        border-bottom: 1px solid #e1e8f0;
        vertical-align: top;
        font-size: 14px;
      }
      tr:hover {
        background-color: #ecf0f1;
      }
      .btn {
        display: inline-block;
        padding: 6px 14px;
        margin: 2px 3px 2px 0;
        color: white;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        border: none;
        font-size: 13px;
      }
      .btn-edit {
        background-color: #2980b9;
      }
      .btn-edit:hover {
        background-color: #1c5980;
      }
      .btn-disabled {
        background-color: #95a5a6;
        cursor: not-allowed;
      }
      .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        font-size: 12px;
        display: inline-block;
      }
      .status-pending { background: #dd8f86ff; }
      .status-need_info { background: #ff1900ff; }
      .status-need_update { background: #920f00ff; }
      .status-in_progress { background: #f39c12; }
      .status-completed { background: #2ecc71; }
      .status-approved { background: #004777ff; }
      .row-number {
        text-align: center;
        font-weight: bold;
        color: #7f8c8d;
      }
      @media (max-width: 768px) {
        th, td {
          padding: 10px 6px;
          font-size: 13px;
        }
        table {
          min-width: unset;
        }
        .btn-brief {
          width: 100%;
          text-align: center;
          padding: 14px 0;
          margin-bottom: 20px;
        }
      }
    </style>
</head>
<body>

<h2>Dashboard งานทั้งหมด</h2>

<a href="brief_form.php" class="btn-brief">บรีฟงาน</a>
<a href="index.php" class="btn-brief">กลับหน้าหลัก</a>

<div class="table-container">
  <table>
<thead>
  <tr>
    <th style="width: 50px;">ลำดับ</th>
    <th>ชื่องาน</th>
    <th>แบรนด์</th>
    <th>หมวดหมู่</th>
    <th>แพลตฟอร์ม</th>
    <th>สถานะ</th>
    <th>เหตุผลตีกลับ</th>
    <th>วันสร้าง</th>
    <th>ผู้สร้าง</th>
    <th>จัดการ</th>
  </tr>
</thead>
<tbody>
<?php 
$rowNumber = 1;
while ($row = mysqli_fetch_assoc($result)) { 
?>
  <tr>
    <td class="row-number"><?php echo $rowNumber++; ?></td>
    <td><?php echo htmlspecialchars($row['job_title']); ?></td>
    <td><?php echo htmlspecialchars($row['brand']); ?></td>
    <td><?php echo htmlspecialchars($row['category']); ?></td>
    <td><?php echo htmlspecialchars($row['platform']); ?></td>
    <td>
      <span class="status-badge status-<?php echo htmlspecialchars($row['status']); ?>">
        <?php 
        $status_text = [
          'pending' => 'รอรับบรีฟ',
          'need_info' => 'ตีกลับ/สอบถาม',
          'need_update' => 'รอแก้ไข',
          'in_progress' => 'กำลังดำเนินการ',
          'completed' => 'รออนุมัติ',
          'approved' => 'อนุมัติแล้ว'
        ];
        echo $status_text[$row['status']] ?? $row['status'];
        ?>
      </span>
    </td>
    <td><?php echo nl2br(htmlspecialchars($row['reject_reason'])); ?></td>
    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
    <td><?php echo htmlspecialchars($row['username']); ?></td>
    <td>
      <?php 
      // Admin สามารถแก้ไขได้ทุกงาน หรือ เจ้าของงานแก้ไขได้เมื่อสถานะ need_info
      if ($userRole === 'admin' || ($row['username'] === $currentUsername && $row['status'] === 'need_info')) { 
      ?>
        <a href="edit_brief_form.php?id=<?php echo $row['id']; ?>" class="btn btn-edit">แก้ไข</a>
      <?php } else { ?>
        <button class="btn btn-disabled" disabled>แก้ไขไม่ได้</button>
      <?php } ?>
    </td>
  </tr>
<?php } ?>
</tbody>
  </table>
</div>

</body>
</html>