<?php
require_once 'config.php';
require_once 'helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Validate API Keys
    $apiErrors = validateAPIKeys();
    if (!empty($apiErrors)) {
        throw new Exception(implode(', ', $apiErrors));
    }
    
    // รับข้อมูลจากฟอร์ม
    $productName = sanitizeInput($_POST['product_name'] ?? '');
    $productDetails = sanitizeInput($_POST['product_details'] ?? '');
    $reviewStyle = sanitizeInput($_POST['review_style'] ?? '');
    $objective = sanitizeInput($_POST['objective'] ?? '');
    
    // Validate form data
    validateFormData([
        'product_name' => $productName,
        'product_details' => $productDetails,
        'review_style' => $reviewStyle,
        'objective' => $objective
    ]);
    
    // จัดการไฟล์รูปภาพ
    if (!isset($_FILES['product_image'])) {
        throw new Exception('กรุณาอัปโหลดรูปสินค้า');
    }
    
    validateImage($_FILES['product_image']);
    
    $fileName = generateFileName($_FILES['product_image']['name']);
    $imagePath = UPLOAD_DIR . $fileName;
    
    if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $imagePath)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์รูปภาพได้');
    }
    
    logError("Image uploaded", [
        'filename' => $fileName,
        'path' => $imagePath,
        'size' => $_FILES['product_image']['size']
    ]);
    
    // ขั้นตอน 1: สร้าง Video Prompt และ Caption ด้วย OpenRouter GPT
    $gptPrompt = createGPTPrompt($productName, $productDetails, $reviewStyle, $objective);
    $gptResponse = callOpenRouterAPI($gptPrompt);
    
    $parsedResponse = parseGPTResponse($gptResponse);
    $videoPrompt = $parsedResponse['video_prompt'];
    $caption = $parsedResponse['caption'];
    
    // Log สำหรับ debug
    logError("Generated prompt and caption", [
        'video_prompt' => $videoPrompt,
        'caption' => substr($caption, 0, 200)
    ]);
    
    // ขั้นตอน 2: สร้างวิดีโอด้วย Luma Dream Machine
    $videoUrl = createVideoWithLuma($videoPrompt, $imagePath);
    
    logError("Video created successfully", [
        'video_url' => $videoUrl
    ]);
    
    // ส่งผลลัพธ์กลับ
    jsonResponse(true, [
        'caption' => $caption,
        'video_url' => $videoUrl,
        'video_prompt' => $videoPrompt
    ], 'สร้างคลิปสำเร็จ! ใช้เงิน ~$0.04');
    
} catch (Exception $e) {
    logError("Process error", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    jsonResponse(false, [], 'เกิดข้อผิดพลาด: ' . $e->getMessage());
}

/**
 * สร้าง GPT Prompt สำหรับ Video และ Caption
 */
function createGPTPrompt($name, $details, $style, $objective) {
    return "คุณเป็น AI ผู้เชี่ยวชาญในการสร้างคลิป UGC รีวิวสินค้า

สินค้า: {$name}
รายละเอียด: {$details}
สไตล์: {$style}
วัตถุประสงค์: {$objective}

กรุณาสร้าง:
1. Video Prompt สำหรับ Luma AI Video Generation (ภาษาอังกฤษ, 5 วินาที)
2. Caption ภาษาไทยพร้อมแฮชแท็ก (80-120 คำ)

**หลักการสำคัญสำหรับ Caption:**
- ห้ามใช้คำที่อ้างว่าใช้จริง เช่น 'ฉันใช้แล้วดี', 'ลองแล้วเห็นผล', 'ฉันทดสอบมาแล้ว'
- ใช้คำที่ปลอดภัย เช่น 'จากข้อมูลสินค้า', 'ผลิตภัณฑ์นี้ออกแบบมาเพื่อ', 'คุณสมบัติที่น่าสนใจคือ'
- ระบุว่าเป็น AI ที่นำเสนอข้อมูล เช่น 'ฉันคือ AI นางแบบที่ทำหน้าที่นำเสนอข้อมูลค่ะ' หรือ 'คลิปนี้สร้างด้วย AI'
- เน้นข้อมูลจริงจากสเปกสินค้า ไม่โอเวอร์หรือรับรอง
- ไม่ใช้คำว่า 'รีวิว' หรือ 'ทดลองใช้' เพราะเราไม่ได้ใช้จริง
- เหมาะกับการโพสต์ TikTok, Facebook Reels, Instagram Reels

**Video Prompt Guidelines สำหรับ Luma:**
- สามารถมีคนได้ แต่แนะนำเน้น product showcase
- อธิบาย camera movement: slow zoom in, gentle pan, orbit around product, dolly shot
- อธิบาย action: product rotating, lighting change, smooth reveal
- ระบุ environment: wooden table, minimal background, studio lighting, lifestyle setting
- ระบุ mood: cinematic, professional, warm, cozy, modern
- ความยาว 30-50 words ภาษาอังกฤษ
- Luma เข้าใจ prompt ได้ดี สามารถระบุ motion ละเอียดได้
- ตัวอย่าง: \"Cinematic dolly shot slowly zooming into the product on a wooden table, soft window lighting creates warm atmosphere, smooth camera movement reveals product details, professional product photography style, shallow depth of field\"

ตอบในรูปแบบ JSON เท่านั้น:
{
  \"video_prompt\": \"...\",
  \"caption\": \"...\"
}";
}

/**
 * เรียก OpenRouter API
 */
function callOpenRouterAPI($prompt) {
    $ch = curl_init(OPENROUTER_URL);
    
    $data = [
        'model' => OPENROUTER_MODEL,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];
    
    $referer = (BASE_URL !== 'https://yourdomain.com/') ? BASE_URL : 'http://localhost';
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENROUTER_API_KEY,
            'HTTP-Referer: ' . $referer,
            'X-Title: UGC Video Generator'
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    logError("OpenRouter API call", [
        'http_code' => $httpCode,
        'has_error' => !empty($error),
        'error' => $error
    ]);
    
    if ($httpCode !== 200) {
        $errorDetail = json_decode($response, true);
        $errorMsg = $errorDetail['error']['message'] ?? $response;
        throw new Exception("OpenRouter API Error: HTTP $httpCode - $errorMsg");
    }
    
    return json_decode($response, true);
}

/**
 * แยก JSON จาก GPT Response
 */
function parseGPTResponse($response) {
    $content = $response['choices'][0]['message']['content'] ?? '';
    
    logError("GPT raw response", [
        'content' => substr($content, 0, 500)
    ]);
    
    // ลองแยก JSON จากข้อความหลายรูปแบบ
    // Pattern 1: หา JSON object โดยตรง
    if (preg_match('/\{\s*"video_prompt"\s*:\s*"[^"]+"\s*,\s*"caption"\s*:\s*"[^"]+"\s*\}/s', $content, $matches)) {
        $jsonStr = $matches[0];
    }
    // Pattern 2: หา JSON block ใน markdown
    elseif (preg_match('/``````/s', $content, $matches)) {
        $jsonStr = $matches[1];
    }
    // Pattern 3: หา JSON block ใน code fence
    elseif (preg_match('/``````/s', $content, $matches)) {
        $jsonStr = $matches[1];
    }
    // Pattern 4: หา JSON ที่มี nested quotes
    elseif (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $matches)) {
        $jsonStr = $matches[0];
    }
    // Pattern 5: ลอง decode ทั้งหมด
    else {
        $jsonStr = $content;
    }
    
    // ทำความสะอาด JSON string
    $jsonStr = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonStr); // ลบ control characters
    $jsonStr = trim($jsonStr);
    
    $result = json_decode($jsonStr, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("JSON parse error", [
            'error' => json_last_error_msg(),
            'json_str' => substr($jsonStr, 0, 500)
        ]);
        throw new Exception("ไม่สามารถแยก JSON จาก GPT response: " . json_last_error_msg());
    }
    
    if (empty($result['video_prompt']) || empty($result['caption'])) {
        throw new Exception("ไม่พบ video_prompt หรือ caption ใน GPT response");
    }
    
    return $result;
}

/**
 * สร้างวิดีโอด้วย Luma Dream Machine (Photon Flash)
 */
function createVideoWithLuma($prompt, $imagePath) {
    // อัปโหลดรูปและรับ public URL
    $imageUrl = uploadImageToPublic($imagePath);
    
    logError("Starting Stable Video Diffusion", [
        'image_url' => $imageUrl,
        'prompt' => $prompt
    ]);
    
    $ch = curl_init(REPLICATE_API_URL);
    
    // Payload สำหรับ Stable Video Diffusion
    $data = [
        'version' => REPLICATE_MODEL_VERSION,
        'input' => [
            'input_image' => $imageUrl,
            'cond_aug' => 0.02,
            'decoding_t' => 7,
            'video_length' => '14_frames_with_svd',  // 14 frames = ~2 วินาที (ประหยัด)
            'sizing_strategy' => 'maintain_aspect_ratio',
            'motion_bucket_id' => 127,
            'frames_per_second' => 6
        ]
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . REPLICATE_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 15
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    logError("SVD API response", [
        'http_code' => $httpCode,
        'has_curl_error' => !empty($error),
        'curl_error' => $error,
        'response' => substr($response, 0, 500)
    ]);
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        $errorDetail = json_decode($response, true);
        $errorMsg = $errorDetail['detail'] ?? $errorDetail['error'] ?? $response;
        
        if ($httpCode === 401) {
            throw new Exception("Authentication Error: API Key ไม่ถูกต้อง");
        } elseif ($httpCode === 402) {
            throw new Exception("Payment Required: กรุณาเติมเงิน");
        } elseif ($httpCode === 422) {
            throw new Exception("Input Error: " . $errorMsg);
        } elseif ($httpCode === 429) {
            $retryAfter = $errorDetail['retry_after'] ?? 10;
            throw new Exception("Rate Limit: รอ $retryAfter วินาที");
        }
        
        throw new Exception("API Error: HTTP $httpCode - " . $errorMsg);
    }
    
    $result = json_decode($response, true);
    
    logError("SVD prediction created", [
        'prediction_id' => $result['id'] ?? 'N/A',
        'status' => $result['status'] ?? 'N/A'
    ]);
    
    // Poll status
    if (isset($result['urls']['get'])) {
        return pollLumaStatus($result['urls']['get']);
    } elseif (isset($result['id'])) {
        $statusUrl = REPLICATE_API_URL . '/' . $result['id'];
        return pollLumaStatus($statusUrl);
    } else {
        throw new Exception("ไม่สามารถรับ status URL");
    }
}

/**
 * Poll status - แก้ไขให้รองรับ video file
 */
function pollLumaStatus($statusUrl, $maxAttempts = 60) {
    logError("Start polling", [
        'status_url' => $statusUrl,
        'max_attempts' => $maxAttempts
    ]);
    
    for ($i = 0; $i < $maxAttempts; $i++) {
        sleep(5); // รอ 5 วินาที
        
        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . REPLICATE_API_KEY,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            continue;
        }
        
        $result = json_decode($response, true);
        $status = $result['status'] ?? '';
        
        logError("Polling attempt", [
            'attempt' => $i + 1,
            'status' => $status,
            'has_output' => isset($result['output'])
        ]);
        
        if ($status === 'succeeded') {
            // ตรวจสอบว่าเป็นวิดีโอหรือรูป
            $outputUrl = '';
            
            if (isset($result['output']) && is_string($result['output'])) {
                $outputUrl = $result['output'];
            } elseif (isset($result['output'][0])) {
                $outputUrl = $result['output'][0];
            } elseif (isset($result['output']['video'])) {
                $outputUrl = $result['output']['video'];
            }
            
            if ($outputUrl) {
                // ตรวจสอบว่าเป็น video หรือ image
                $extension = strtolower(pathinfo(parse_url($outputUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                
                logError("Output received", [
                    'url' => $outputUrl,
                    'extension' => $extension
                ]);
                
                if (in_array($extension, ['mp4', 'mov', 'webm', 'avi'])) {
                    // เป็น video ✅
                    logError("Video generation succeeded", [
                        'video_url' => $outputUrl,
                        'total_time' => ($i + 1) * 5 . 's'
                    ]);
                    return $outputUrl;
                } else {
                    // เป็น image ❌
                    logError("WARNING: Output is image, not video", [
                        'url' => $outputUrl,
                        'extension' => $extension
                    ]);
                    throw new Exception("Model ส่ง image กลับมา ไม่ใช่ video (ใช้ model ผิด)");
                }
            }
            
            throw new Exception("ไม่พบ output URL");
            
        } elseif ($status === 'failed') {
            $error = $result['error'] ?? 'Unknown error';
            throw new Exception("Video generation failed: $error");
            
        } elseif ($status === 'canceled') {
            throw new Exception("ถูกยกเลิก");
        }
    }
    
    throw new Exception("Timeout: รอเกิน " . ($maxAttempts * 5) . " วินาที");
}
?>
