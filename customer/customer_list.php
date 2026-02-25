<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';



try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get all customers, latest first
$customers = [];
try {
    $sql = "SELECT * FROM customers ORDER BY created_at DESC LIMIT 100";
    $stmt = $db->query($sql);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching customers: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer List - Backend</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .header {
            background: rgba(2, 136, 209, 0.95);
            backdrop-filter: blur(15px);
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            margin-bottom: 30px;
            max-width: 1400px;
            margin: 0 auto 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .back-btn {
            background: white;
            color: #0288d1;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #f5f5f5;
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        thead th {
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.2s ease;
        }
        
        tbody tr:hover {
            background: #f5f5f5;
        }
        
        tbody td {
            padding: 15px;
            font-size: 14px;
            color: #333;
        }
        
        .btn-fill {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        
        .btn-fill:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-fill:active {
            transform: translateY(0);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4caf50;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: none;
            animation: slideIn 0.3s ease;
            z-index: 1000;
        }
        
        .toast.show {
            display: block;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .badge {
            background: #e3f2fd;
            color: #0288d1;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>👥 Customer List</h1>
            
        </div>
    </div>
    
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <h3>Total Customers</h3>
                <div class="value"><?php echo count($customers); ?></div>
            </div>
            <div class="stat-card">
                <h3>Form Link</h3>
                <div style="font-size: 14px; margin-top: 10px;">
                    <a href="customer_form.php" target="_blank" style="color: white; text-decoration: underline;">
                        customer_form.php
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card">
            <?php if (count($customers) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Cell Phone</th>
                            <th>Email</th>
                            <th>Date of Birth</th>
                            <th>Registered At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo $customer['id']; ?></td>
                            <td><?php echo htmlspecialchars($customer['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['cell_phone'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($customer['email'] ?: '-'); ?></td>
                            <td><?php echo $customer['date_of_birth'] ? date('d/m/Y', strtotime($customer['date_of_birth'])) : '-'; ?></td>
                            <td>
                                <span class="badge">
                                    <?php echo date('d/m/Y H:i', strtotime($customer['created_at'])); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-fill" onclick="copyField('<?php echo htmlspecialchars($customer['first_name']); ?>', 'First Name')">
                                    Copy First Name
                                </button>
                                <button class="btn-fill" onclick="copyField('<?php echo htmlspecialchars($customer['last_name']); ?>', 'Last Name')" style="margin-left: 5px;">
                                    Copy Last Name
                                </button>
                                <button class="btn-fill" onclick="copyField('<?php echo htmlspecialchars($customer['cell_phone']); ?>', 'Cell Phone')" style="margin-left: 5px;">
                                    Copy Phone
                                </button>
                                <br style="margin-bottom: 5px;">
                                <button class="btn-fill" onclick="copyField('<?php echo htmlspecialchars($customer['email']); ?>', 'Email')" style="margin-top: 5px;">
                                    Copy Email
                                </button>
                                <?php if ($customer['date_of_birth']): 
                                    $date = new DateTime($customer['date_of_birth']);
                                ?>
                                <button class="btn-fill" onclick="copyField('<?php echo $date->format('d'); ?>', 'Birth Day')" style="margin-left: 5px; margin-top: 5px;">
                                    Copy Day
                                </button>
                                <button class="btn-fill" onclick="copyField('<?php echo $date->format('m'); ?>', 'Birth Month')" style="margin-left: 5px; margin-top: 5px;">
                                    Copy Month
                                </button>
                                <button class="btn-fill" onclick="copyField('<?php echo $date->format('Y'); ?>', 'Birth Year')" style="margin-left: 5px; margin-top: 5px;">
                                    Copy Year
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="icon">📝</div>
                <h3>No Customers Yet</h3>
                <p>Customers who fill out the form will appear here.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="toast" id="toast">
        ✓ Data copied to clipboard! Press <strong>Ctrl+Shift+F</strong> to auto-fill.
    </div>
    
    <script>
        function fillForm(customer) {
            // Prepare data in format for AutoHotkey
            const data = {
                firstName: customer.first_name || '',
                lastName: customer.last_name || '',
                cellPhone: customer.cell_phone || '',
                email: customer.email || '',
                dateOfBirth: customer.date_of_birth || ''
            };
            
            // Format date of birth for easier parsing
            if (data.dateOfBirth) {
                const date = new Date(data.dateOfBirth);
                data.dobDay = date.getDate();
                data.dobMonth = date.getMonth() + 1;
                data.dobYear = date.getFullYear();
            }
            
            // Convert to JSON and copy to clipboard
            const jsonData = JSON.stringify(data);
            
            navigator.clipboard.writeText(jsonData).then(() => {
                // Show toast notification
                const toast = document.getElementById('toast');
                toast.classList.add('show');
                
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 5000);
                
                console.log('Data copied to clipboard:', data);
            }).catch(err => {
                alert('Failed to copy to clipboard. Please try again.');
                console.error('Clipboard error:', err);
            });
        }
        
        function copyField(value, fieldName) {
            if (!value) {
                alert('This field is empty!');
                return;
            }
            
            navigator.clipboard.writeText(value).then(() => {
                // Show toast notification
                const toast = document.getElementById('toast');
                toast.innerHTML = '✓ ' + fieldName + ' copied: <strong>' + value + '</strong><br>Click on the field and press Ctrl+V to paste.';
                toast.classList.add('show');
                
                setTimeout(() => {
                    toast.classList.remove('show');
                    toast.innerHTML = '✓ Data copied to clipboard! Press <strong>Ctrl+Shift+F</strong> to auto-fill.';
                }, 4000);
            }).catch(err => {
                alert('Failed to copy to clipboard. Please try again.');
                console.error('Clipboard error:', err);
            });
        }
    </script>
</body>
</html>