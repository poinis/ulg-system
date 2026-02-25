<?php
/**
 * ระบบสมัครสมาชิก ULG (Cegid Retail / Y2)
 * - บันทึกเฉพาะ Customer (ไม่มี Loyalty Card)
 * - CustomerId: 88xxxxxxxxxxx (13 หลัก)
 * - Store: 77000 ULG ONLINE
 * - Member: ULG
 */

// ==============================================================================
// 1. CONFIGURATION
// ==============================================================================
$api_username = "90643827_002_TEST\\frt"; 
$api_password = "adgjm";
$database_id  = "90643827_002_TEST";

$wsdl_customer = "http://90643827-test-retail-ondemand.cegid.cloud/Y2/CustomerWcfService.svc?wsdl";

$config_storeId = "77000";      // ULG ONLINE
$config_member  = "ULG";        // Member type

$message = "";
$messageType = ""; 

// ==============================================================================
// 2. GENERATE CUSTOMER ID (88xxxxxxxxxxx - 13 หลัก)
// ==============================================================================
function generateCustomerId() {
    // 88 + YYMMDDHHmmss + random (ให้ครบ 13 หลัก)
    // 88 + 10 หลัก timestamp + 1 หลัก random = 13 หลัก
    $timestamp = date('ymdHis'); // 12 หลัก -> ตัดเหลือ 10
    $timestamp = substr($timestamp, 0, 10); // เอา 10 หลัก
    $random = rand(0, 9); // 1 หลัก
    return '88' . $timestamp . $random; // 2 + 10 + 1 = 13 หลัก
}

// ==============================================================================
// 3. PHP LOGIC
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $fname  = trim($_POST['firstname']);
    $lname  = trim($_POST['lastname']);
    $mobile = preg_replace('/[^0-9]/', '', $_POST['mobile']);
    $email  = trim($_POST['email']);
    $gender = $_POST['gender']; 
    $dob    = $_POST['birthdate'];

    // Generate CustomerId 13 หลัก
    $customerId = generateCustomerId();

    // แยกวันเกิด
    $ts = strtotime($dob);
    $birthDay   = (int)date('d', $ts);
    $birthMonth = (int)date('m', $ts);
    $birthYear  = (int)date('Y', $ts);

    $soapOptions = [
        'login' => $api_username,
        'password' => $api_password,
        'trace' => 1,
        'exceptions' => 1,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ])
    ];

    try {
        // --- ตรวจสอบเบอร์โทรซ้ำก่อน ---
        $clientCustomer = new SoapClient($wsdl_customer, $soapOptions);
        
        $searchParams = [
            'searchData' => [
                'PhoneData' => [
                    'CellularPhoneNumber' => $mobile
                ],
                'MaxNumberOfCustomers' => 1
            ],
            'clientContext' => ['DatabaseId' => $database_id]
        ];
        
        $searchResult = $clientCustomer->SearchCustomerIds($searchParams);
        
        // ถ้าเจอลูกค้าที่มีเบอร์นี้แล้ว
        if (!empty($searchResult->SearchCustomerIdsResult->CustomerQueryData)) {
            $existingCustomer = $searchResult->SearchCustomerIdsResult->CustomerQueryData;
            if (is_array($existingCustomer)) {
                $existingCustomer = $existingCustomer[0];
            }
            $existingId = $existingCustomer->CustomerId ?? '';
            
            $messageType = "warning";
            $message = "<strong>⚠️ เบอร์โทรนี้เป็นสมาชิกอยู่แล้ว</strong><br>
                        รหัสสมาชิก: <strong>$existingId</strong><br>
                        กรุณาใช้เบอร์อื่น หรือติดต่อเจ้าหน้าที่";
        } else {
            // --- สร้างลูกค้าใหม่ ---
            $customerData = [
                'customerData' => [
                    'LastName'  => $lname,
                    'FirstName' => $fname,
                    'TitleId'   => ($gender == 'Male' ? 'Mr.' : 'Ms.'),
                    'PhoneData' => [
                        'CellularPhoneNumber' => $mobile
                    ],
                    'BirthDateData' => [
                        'BirthDateDay'   => $birthDay,
                        'BirthDateMonth' => $birthMonth,
                        'BirthDateYear'  => $birthYear
                    ],
                    'UsualStoreId' => $config_storeId,
                    'UserDefinedData' => [
                        'UserDefinedTable1Value' => $config_member  // Member = ULG
                    ],
                    'CustomerId' => $customerId
                ],
                'clientContext' => ['DatabaseId' => $database_id]
            ];

            // เพิ่ม Email ถ้ามี
            if (!empty($email)) {
                $customerData['customerData']['EmailData'] = [
                    'Email' => $email,
                    'EmailingAccepted' => true
                ];
            }

            $resCustomer = $clientCustomer->AddNewCustomer($customerData);
            $customerRef = $resCustomer->AddNewCustomerResult;

            if (empty($customerRef)) {
                throw new Exception("สร้างลูกค้าสำเร็จ แต่ไม่ได้รับ Customer ID กลับมา");
            }

            $messageType = "success";
            $message = "<strong>✅ สมัครสมาชิกสำเร็จ!</strong><br>
                        รหัสสมาชิก: <strong>$customerRef</strong><br>
                        ชื่อ: $fname $lname<br>
                        เบอร์โทร: $mobile";
        }

    } catch (SoapFault $e) {
        $messageType = "danger";
        $errorMsg = $e->getMessage();
        
        // ตรวจสอบ error ซ้ำ
        if (strpos($errorMsg, 'already exists') !== false || strpos($errorMsg, 'CBR00450') !== false) {
            $message = "<strong>⚠️ เบอร์โทรนี้เป็นสมาชิกอยู่แล้ว</strong><br>กรุณาใช้เบอร์อื่น หรือติดต่อเจ้าหน้าที่";
            $messageType = "warning";
        } else {
            $message = "<strong>เกิดข้อผิดพลาด:</strong> กรุณาลองใหม่อีกครั้ง หรือติดต่อเจ้าหน้าที่";
        }
        
    } catch (Exception $e) {
        $messageType = "danger";
        $message = "<strong>เกิดข้อผิดพลาด:</strong> กรุณาลองใหม่อีกครั้ง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก ULG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        .card { border: none; border-radius: 15px; }
        .card-header { border-radius: 15px 15px 0 0 !important; background: linear-gradient(135deg, #e94560 0%, #0f3460 100%); }
        .btn-primary { background: linear-gradient(135deg, #e94560 0%, #0f3460 100%); border: none; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .form-control:focus, .form-select:focus { border-color: #e94560; box-shadow: 0 0 0 0.2rem rgba(233,69,96,0.25); }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="card shadow mx-auto" style="max-width: 500px;">
        <div class="card-header text-white text-center py-3">
            <h4 class="mb-0">🎉 สมัครสมาชิก ULG</h4>
            <small>ลงทะเบียนเพื่อรับสิทธิพิเศษ</small>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                        <input type="text" name="firstname" class="form-control" required 
                               value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>">
                    </div>
                    <div class="col">
                        <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" name="lastname" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                    <input type="tel" name="mobile" class="form-control" required 
                           pattern="[0-9]{10}" placeholder="0812345678"
                           value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">อีเมล</label>
                    <input type="email" name="email" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label">เพศ <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="Male" <?php echo ($_POST['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>ชาย</option>
                            <option value="Female" <?php echo ($_POST['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>หญิง</option>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label">วันเกิด <span class="text-danger">*</span></label>
                        <input type="date" name="birthdate" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['birthdate'] ?? ''); ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                    <strong>สมัครสมาชิก</strong>
                </button>
            </form>
        </div>
        <div class="card-footer text-center text-muted small">
            เงื่อนไขเป็นไปตามที่บริษัทกำหนด
        </div>
    </div>
</div>
</body>
</html>