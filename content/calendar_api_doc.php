<!DOCTYPE html>
<html>
<head>
    <title>API Documentation - Calendar Engage</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f7fa;
        color: #333;
        line-height: 1.6;
      }
      
      .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        text-align: center;
      }
      .header h1 {
        font-size: 2.5em;
        margin-bottom: 10px;
      }
      
      .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
      }
      
      .nav {
        background: white;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 30px;
      }
      .nav a {
        padding: 10px 20px;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        display: inline-block;
      }
      
      .section {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 30px;
      }
      
      .section h2 {
        color: #667eea;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #ecf0f1;
      }
      
      .endpoint {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #667eea;
      }
      
      .endpoint h3 {
        color: #2c3e50;
        margin-bottom: 15px;
      }
      
      .method {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 5px;
        font-weight: bold;
        margin-right: 10px;
        font-size: 0.9em;
      }
      .method-get { background: #28a745; color: white; }
      .method-post { background: #007bff; color: white; }
      .method-put { background: #ffc107; color: #333; }
      .method-delete { background: #dc3545; color: white; }
      
      .url {
        background: #2c3e50;
        color: #fff;
        padding: 10px 15px;
        border-radius: 5px;
        font-family: 'Courier New', monospace;
        margin: 10px 0;
        overflow-x: auto;
      }
      
      .params {
        margin-top: 15px;
      }
      .params h4 {
        color: #555;
        margin-bottom: 10px;
      }
      .param-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
      }
      .param-table th,
      .param-table td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: left;
      }
      .param-table th {
        background: #667eea;
        color: white;
      }
      
      .code-block {
        background: #2c3e50;
        color: #fff;
        padding: 15px;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
        overflow-x: auto;
        margin: 15px 0;
      }
      
      .example {
        background: #e3f2fd;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
        border-left: 4px solid #2196f3;
      }
      
      .response {
        background: #e8f5e9;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
        border-left: 4px solid #4caf50;
      }
      
      .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 0.85em;
        font-weight: 600;
      }
      .badge-required { background: #ffebee; color: #c62828; }
      .badge-optional { background: #e3f2fd; color: #1565c0; }
    </style>
</head>
<body>

<div class="header">
    <h1>🔌 Calendar Engage API</h1>
    <p>API Documentation สำหรับจัดการข้อมูล Engage</p>
</div>

<div class="container">
    
    <div class="nav">
        <a href="calendar_dashboard.php">🔙 กลับปฏิทิน</a>
    </div>
    
    <!-- Introduction -->
    <div class="section">
        <h2>📖 Introduction</h2>
        <p>Calendar Engage API ให้คุณสามารถจัดการข้อมูล Engagement ของคอนเทนต์ผ่าน RESTful API</p>
        <br>
        <p><strong>Base URL:</strong> <code>https://www.weedjai.com/content/calendar_engage_api.php</code></p>
        <br>
        <p><strong>Response Format:</strong> JSON</p>
        <br>
        <p><strong>Authentication:</strong> API Key (Optional - สามารถเปิดใช้งานได้)</p>
    </div>
    
    <!-- Endpoints -->
    <div class="section">
        <h2>📡 Endpoints</h2>
        
        <!-- List Engages -->
        <div class="endpoint">
            <h3>
                <span class="method method-get">GET</span>
                ดึงรายการ Engage ทั้งหมด
            </h3>
            <div class="url">GET /calendar_engage_api.php?action=list</div>
            
            <div class="params">
                <h4>Query Parameters:</h4>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>status</td>
                            <td>string</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>สถานะ: all, pending, completed (default: all)</td>
                        </tr>
                        <tr>
                            <td>month</td>
                            <td>string</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>เดือน รูปแบบ YYYY-MM (default: เดือนปัจจุบัน)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="example">
                <strong>📝 Example Request:</strong>
                <div class="code-block">
curl -X GET "https://www.weedjai.com/content/calendar_engage_api.php?action=list&status=pending&month=2024-11"
                </div>
            </div>
            
            <div class="response">
                <strong>✅ Example Response:</strong>
                <div class="code-block">
{
    "success": true,
    "message": "Engage list retrieved successfully",
    "data": [
        {
            "id": 1,
            "job_title": "Product Launch Campaign",
            "category": "Promotion",
            "assignee": "จันทร์",
            "post_date": "2024-11-01",
            "engage_date": "2024-11-15",
            "engage_status": "pending",
            "reach": null,
            "impressions": null,
            "likes": null,
            "comments": null,
            "shares": null,
            "saves": null,
            "note": null,
            "updated_by": null,
            "updated_at": null
        }
    ],
    "timestamp": "2024-11-01 10:30:00"
}
                </div>
            </div>
        </div>
        
        <!-- Get Single Engage -->
        <div class="endpoint">
            <h3>
                <span class="method method-get">GET</span>
                ดึงข้อมูล Engage เฉพาะรายการ
            </h3>
            <div class="url">GET /calendar_engage_api.php?action=get&event_id={id}</div>
            
            <div class="params">
                <h4>Query Parameters:</h4>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>event_id</td>
                            <td>integer</td>
                            <td><span class="badge badge-required">Required</span></td>
                            <td>ID ของงานที่ต้องการดู</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="example">
                <strong>📝 Example Request:</strong>
                <div class="code-block">
curl -X GET "https://www.weedjai.com/content/calendar_engage_api.php?action=get&event_id=1"
                </div>
            </div>
        </div>
        
        <!-- Create/Update Engage -->
        <div class="endpoint">
            <h3>
                <span class="method method-post">POST</span>
                สร้าง/อัปเดต Engage
            </h3>
            <div class="url">POST /calendar_engage_api.php?action=create</div>
            
            <div class="params">
                <h4>Request Body (JSON):</h4>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>event_id</td>
                            <td>integer</td>
                            <td><span class="badge badge-required">Required</span></td>
                            <td>ID ของงาน</td>
                        </tr>
                        <tr>
                            <td>reach</td>
                            <td>integer</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>จำนวน Reach</td>
                        </tr>
                        <tr>
                            <td>impressions</td>
                            <td>integer</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>จำนวน Impressions</td>
                        </tr>
                        <tr>
                            <td>likes</td>
                            <td>integer</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>จำนวน Likes</td>
                        </tr>
                        <tr>
                            <td>comments</td>
                            <td>integer</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>จำนวน Comments</td>
                        </tr>
                        <tr>
                            <td>shares</td>
                            <td>integer</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>จำนวน Shares</td>
                        </tr>
                        <tr>
                            <td>saves</td>
                            <td>integer</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>จำนวน Saves</td>
                        </tr>
                        <tr>
                            <td>note</td>
                            <td>string</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>หมายเหตุ</td>
                        </tr>
                        <tr>
                            <td>updated_by</td>
                            <td>string</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>ผู้อัปเดต (default: API)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="example">
                <strong>📝 Example Request (curl):</strong>
                <div class="code-block">
curl -X POST "https://www.weedjai.com/content/calendar_engage_api.php?action=create" \
  -H "Content-Type: application/json" \
  -d '{
    "event_id": 1,
    "reach": 5000,
    "impressions": 8000,
    "likes": 150,
    "comments": 25,
    "shares": 10,
    "saves": 30,
    "note": "ผลลัพธ์ดีมาก engagement สูง",
    "updated_by": "Marketing Team"
  }'
                </div>
            </div>
            
            <div class="example">
                <strong>📝 Example Request (JavaScript/Fetch):</strong>
                <div class="code-block">
fetch('https://www.weedjai.com/content/calendar_engage_api.php?action=create', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    event_id: 1,
    reach: 5000,
    impressions: 8000,
    likes: 150,
    comments: 25,
    shares: 10,
    saves: 30,
    note: 'ผลลัพธ์ดีมาก',
    updated_by: 'Marketing Team'
  })
})
.then(response => response.json())
.then(data => console.log(data));
                </div>
            </div>
            
            <div class="response">
                <strong>✅ Example Response:</strong>
                <div class="code-block">
{
    "success": true,
    "message": "Engage created successfully",
    "data": {
        "event_id": 1,
        "reach": 5000,
        "impressions": 8000,
        "likes": 150,
        "comments": 25,
        "shares": 10,
        "saves": 30
    },
    "timestamp": "2024-11-01 10:30:00"
}
                </div>
            </div>
        </div>
        
        <!-- Delete Engage -->
        <div class="endpoint">
            <h3>
                <span class="method method-delete">DELETE</span>
                ลบข้อมูล Engage
            </h3>
            <div class="url">DELETE /calendar_engage_api.php?action=delete&event_id={id}</div>
            
            <div class="params">
                <h4>Query Parameters:</h4>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>event_id</td>
                            <td>integer</td>
                            <td><span class="badge badge-required">Required</span></td>
                            <td>ID ของงานที่ต้องการลบ</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="example">
                <strong>📝 Example Request:</strong>
                <div class="code-block">
curl -X DELETE "https://www.weedjai.com/content/calendar_engage_api.php?action=delete&event_id=1"
                </div>
            </div>
        </div>
        
        <!-- Get Statistics -->
        <div class="endpoint">
            <h3>
                <span class="method method-get">GET</span>
                ดึงสถิติ Engage
            </h3>
            <div class="url">GET /calendar_engage_api.php?action=stats</div>
            
            <div class="params">
                <h4>Query Parameters:</h4>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>month</td>
                            <td>string</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>เดือน รูปแบบ YYYY-MM</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="example">
                <strong>📝 Example Request:</strong>
                <div class="code-block">
curl -X GET "https://www.weedjai.com/content/calendar_engage_api.php?action=stats&month=2024-11"
                </div>
            </div>
            
            <div class="response">
                <strong>✅ Example Response:</strong>
                <div class="code-block">
{
    "success": true,
    "message": "Statistics retrieved successfully",
    "data": {
        "counts": {
            "total": 50,
            "pending": 10,
            "completed": 38,
            "overdue": 2
        },
        "totals": {
            "total_reach": 250000,
            "total_impressions": 400000,
            "total_likes": 8500,
            "total_comments": 1200,
            "total_shares": 450,
            "total_saves": 1800
        }
    },
    "timestamp": "2024-11-01 10:30:00"
}
                </div>
            </div>
        </div>
    </div>
    
    <!-- Error Responses -->
    <div class="section">
        <h2>❌ Error Responses</h2>
        
        <div class="endpoint">
            <h3>HTTP Status Codes</h3>
            <table class="param-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>200</td>
                        <td>Success</td>
                    </tr>
                    <tr>
                        <td>400</td>
                        <td>Bad Request - Invalid parameters</td>
                    </tr>
                    <tr>
                        <td>401</td>
                        <td>Unauthorized - Invalid API key</td>
                    </tr>
                    <tr>
                        <td>404</td>
                        <td>Not Found - Resource not found</td>
                    </tr>
                    <tr>
                        <td>405</td>
                        <td>Method Not Allowed</td>
                    </tr>
                    <tr>
                        <td>500</td>
                        <td>Internal Server Error</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="response">
            <strong>Error Response Format:</strong>
            <div class="code-block">
{
    "success": false,
    "message": "Error message here",
    "data": null,
    "timestamp": "2024-11-01 10:30:00"
}
            </div>
        </div>
    </div>
    
    <!-- Usage Examples -->
    <div class="section">
        <h2>💡 Usage Examples</h2>
        
        <h3>Python Example:</h3>
        <div class="code-block">
import requests
import json

# Create/Update Engage
url = "https://www.weedjai.com/content/calendar_engage_api.php?action=create"
data = {
    "event_id": 1,
    "reach": 5000,
    "impressions": 8000,
    "likes": 150,
    "comments": 25,
    "shares": 10,
    "saves": 30,
    "note": "ผลลัพธ์ดีมาก",
    "updated_by": "API Script"
}

response = requests.post(url, json=data)
result = response.json()
print(result)
        </div>
        
        <h3>PHP Example:</h3>
        <div class="code-block">
&lt;?php
$url = "https://www.weedjai.com/content/calendar_engage_api.php?action=create";
$data = array(
    "event_id" => 1,
    "reach" => 5000,
    "impressions" => 8000,
    "likes" => 150,
    "comments" => 25,
    "shares" => 10,
    "saves" => 30,
    "note" => "ผลลัพธ์ดีมาก",
    "updated_by" => "API Script"
);

$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    )
);

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$response = json_decode($result, true);

print_r($response);
?&gt;
        </div>
    </div>
    
</div>

</body>
</html>