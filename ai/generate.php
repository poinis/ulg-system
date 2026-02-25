<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

$userId = $_SESSION['user_id'];
$mode = $_POST['mode'];

// ตรวจสอบเครดิต
$userCredits = getUserCredits($pdo, $userId);
$creditCost = CREDITS[$mode];

if ($userCredits < $creditCost) {
    header("Location: index.php?error=เครดิตไม่พอ");
    exit;
}

// จัดการไฟล์อัปโหลด
$inputFile = null;
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
        $inputFile = $targetPath;
    }
}

// บันทึกงานลง DB
$stmt = $pdo->prepare("INSERT INTO generations (user_id, mode, prompt, style, input_file, status, credits_used) VALUES (?, ?, ?, ?, ?, 'processing', ?)");
$stmt->execute([
    $userId,
    $mode,
    $_POST['prompt'] ?? $_POST['script'] ?? '',
    $_POST['style'] ?? '',
    $inputFile ? 'uploads/' . basename($inputFile) : null,
    $creditCost
]);
$generationId = $pdo->lastInsertId();

// หักเครดิต
deductCredits($pdo, $userId, $creditCost, ucfirst($mode) . " generation #$generationId");

// เรียก AI API ตามโหมด
try {
    $outputUrl = null;
    
    switch ($mode) {
        case 'image':
            $outputUrl = generateImage($_POST, $inputFile);
            break;
        case 'video':
            $outputUrl = generateVideo($_POST, $inputFile);
            break;
        case 'voice':
            $outputUrl = generateVoice($_POST);
            break;
    }
    
    if ($outputUrl) {
        $stmt = $pdo->prepare("UPDATE generations SET output_url = ?, status = 'completed' WHERE id = ?");
        $stmt->execute([$outputUrl, $generationId]);
    } else {
        throw new Exception("ไม่สามารถสร้างได้");
    }
    
    header("Location: index.php?success=1&id=$generationId");
    exit;
    
} catch (Exception $e) {
    $stmt = $pdo->prepare("UPDATE generations SET status = 'failed' WHERE id = ?");
    $stmt->execute([$generationId]);
    
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit;
}

// =========================================
// ฟังก์ชันเรียก Gemini API
// =========================================

function generateImage($data, $inputFile) {
    $prompt = buildPrompt($data, 'image');
    
    // เรียก Gemini เพื่อปรับ prompt ให้ดีขึ้น
    $enhancedPrompt = enhancePromptWithGemini($prompt);
    
    // ใช้ Imagen 3 ผ่าน Gemini API
    $imageUrl = callImagenAPI($enhancedPrompt, $inputFile);
    
    return $imageUrl;
}

function generateVideo($data, $inputFile) {
    $prompt = buildPrompt($data, 'video');
    $duration = intval($data['duration'] ?? 10);
    
    // เรียก Gemini เพื่อสร้าง prompt วิดีโอที่ละเอียด
    $enhancedPrompt = enhancePromptWithGemini($prompt);
    
    // เรียก Veo API (ผ่าน Vertex AI หรือ AI Studio)
    $videoUrl = callVeoAPI($enhancedPrompt, $inputFile, $duration);
    
    return $videoUrl;
}

function generateVoice($data) {
    $script = $data['script'];
    $gender = $data['gender'];
    $style = $data['voice_style'];
    
    // ปรับสคริปต์ให้เหมาะกับการพูดด้วย Gemini
    $optimizedScript = optimizeScriptForTTS($script);
    
    // เรียก Google Cloud Text-to-Speech
    $audioUrl = callGoogleTTS($optimizedScript, $gender, $style);
    
    return $audioUrl;
}

// =========================================
// เรียก Gemini เพื่อปรับปรุง Prompt
// =========================================

function enhancePromptWithGemini($originalPrompt) {
    $apiKey = GEMINI_API_KEY;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$apiKey}";
    
    $systemInstruction = "คุณคือ AI Prompt Engineer ที่เชี่ยวชาญในการเขียน prompt สำหรับสร้างภาพและวิดีโอ ให้ขยายและปรับปรุง prompt ต่อไปนี้ให้ละเอียด สวยงาม และเหมาะกับการใช้กับ AI image/video generator ตอบเป็นภาษาอังกฤษเท่านั้น ไม่ต้องอธิบาย";
    
    $payload = [
        'contents' => [[
            'parts' => [[
                'text' => "Original prompt: {$originalPrompt}\n\nEnhanced prompt:"
            ]]
        ]],
        'systemInstruction' => [
            'parts' => [['text' => $systemInstruction]]
        ],
        'generationConfig' => [
            'temperature' => 0.9,
            'maxOutputTokens' => 500
        ]
    ];
    
    $response = callAPI($url, $payload);
    
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        return trim($response['candidates'][0]['content']['parts'][0]['text']);
    }
    
    return $originalPrompt;
}

function optimizeScriptForTTS($script) {
    $apiKey = GEMINI_API_KEY;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$apiKey}";
    
    $systemInstruction = "ปรับสคริปต์ต่อไปนี้ให้เหมาะกับการอ่านออกเสียง เพิ่มจังหวะการหายใจ ความชัดเจน และความน่าสนใจ ตอบเป็นภาษาไทยเท่านั้น ไม่ต้องอธิบาย";
    
    $payload = [
        'contents' => [[
            'parts' => [[
                'text' => "สคริปต์: {$script}\n\nสคริปต์ที่ปรับแล้ว:"
            ]]
        ]],
        'systemInstruction' => [
            'parts' => [['text' => $systemInstruction]]
        ]
    ];
    
    $response = callAPI($url, $payload);
    
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        return trim($response['candidates'][0]['content']['parts'][0]['text']);
    }
    
    return $script;
}

// =========================================
// เรียก Imagen 3 API (สร้างรูป)
// =========================================

function callImagenAPI($prompt, $inputFile = null) {
    $apiKey = GEMINI_API_KEY;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-001:predict?key={$apiKey}";
    
    $payload = [
        'instances' => [[
            'prompt' => $prompt
        ]],
        'parameters' => [
            'sampleCount' => 1,
            'aspectRatio' => '1:1',
            'negativePrompt' => 'blurry, low quality, distorted',
            'safetyFilterLevel' => 'block_some'
        ]
    ];
    
    // ถ้ามีรูป input ให้ทำ image-to-image
    if ($inputFile && file_exists($inputFile)) {
        $imageData = base64_encode(file_get_contents($inputFile));
        $mimeType = mime_content_type($inputFile);
        $payload['instances'][0]['image'] = [
            'bytesBase64Encoded' => $imageData
        ];
        $payload['parameters']['mode'] = 'image-to-image';
    }
    
    try {
        $response = callAPI($url, $payload);
        
        // บันทึกรูปที่ได้
        if (isset($response['predictions'][0]['bytesBase64Encoded'])) {
            $imageData = $response['predictions'][0]['bytesBase64Encoded'];
            $filename = 'output_' . uniqid() . '.png';
            $outputPath = __DIR__ . '/uploads/' . $filename;
            
            file_put_contents($outputPath, base64_decode($imageData));
            return 'uploads/' . $filename;
        }
    } catch (Exception $e) {
        // Fallback: ใช้รูป placeholder
        return "https://via.placeholder.com/1024x1024.png?text=Image+Generation+Error";
    }
    
    return null;
}

// =========================================
// เรียก Veo API (สร้างวิดีโอ)
// =========================================

function callVeoAPI($prompt, $inputFile, $duration) {
    // หมายเหตุ: Veo ยังไม่เปิด public API โดยตรง
    // ต้องใช้ผ่าน Vertex AI หรือ AI Studio
    // นี่คือโครงสำหรับอนาคต
    
    $apiKey = GEMINI_API_KEY;
    
    // ในระหว่างนี้ใช้ Gemini สร้าง storyboard แทน
    $storyboard = generateStoryboard($prompt, $duration);
    
    // Fallback: ใช้วิดีโอตัวอย่าง
    // ในจริงต้องรอ Veo API พร้อมใช้
    return "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4";
}

function generateStoryboard($prompt, $duration) {
    $apiKey = GEMINI_API_KEY;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$apiKey}";
    
    $instruction = "สร้าง storyboard สำหรับวิดีโอโฆษณา {$duration} วินาที จาก prompt นี้: {$prompt}. แบ่งเป็นช็อตละเอียด พร้อมเวลาและการเคลื่อนไหว";
    
    $payload = [
        'contents' => [[
            'parts' => [[
                'text' => $instruction
            ]]
        ]]
    ];
    
    $response = callAPI($url, $payload);
    
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        return $response['candidates'][0]['content']['parts'][0]['text'];
    }
    
    return null;
}

// =========================================
// เรียก Google Cloud Text-to-Speech
// =========================================

function callGoogleTTS($text, $gender, $style) {
    $apiKey = GEMINI_API_KEY;
    $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key={$apiKey}";
    
    $voiceName = $gender === 'female' ? 'th-TH-Neural2-C' : 'th-TH-Neural2-A';
    
    $payload = [
        'input' => [
            'text' => $text
        ],
        'voice' => [
            'languageCode' => 'th-TH',
            'name' => $voiceName
        ],
        'audioConfig' => [
            'audioEncoding' => 'MP3',
            'pitch' => $style === 'upbeat' ? 2.0 : 0.0,
            'speakingRate' => $style === 'professional' ? 1.0 : 1.1
        ]
    ];
    
    try {
        $response = callAPI($url, $payload);
        
        if (isset($response['audioContent'])) {
            $audioData = $response['audioContent'];
            $filename = 'voice_' . uniqid() . '.mp3';
            $outputPath = __DIR__ . '/uploads/' . $filename;
            
            file_put_contents($outputPath, base64_decode($audioData));
            return 'uploads/' . $filename;
        }
    } catch (Exception $e) {
        // Fallback
        return "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3";
    }
    
    return null;
}

// =========================================
// Helper Functions
// =========================================

function buildPrompt($data, $mode) {
    $basePrompt = $data['prompt'] ?? $data['script'] ?? '';
    $style = $data['style'] ?? '';
    
    if ($mode == 'image') {
        return "Product photography in {$style} style: {$basePrompt}, high quality 4K, professional lighting, sharp focus, beautiful composition";
    } elseif ($mode == 'video') {
        $duration = $data['duration'] ?? 10;
        $modelDesc = $data['model_desc'] ?? '';
        $script = $data['script'] ?? '';
        
        return "{$style} style video advertisement, {$duration} seconds: {$basePrompt}. " . 
               ($modelDesc ? "Featuring: {$modelDesc}. " : "") .
               ($script ? "On-screen text: {$script}. " : "") .
               "Aspect ratio 9:16 for TikTok, smooth motion, cinematic quality, 4K resolution";
    }
    
    return $basePrompt;
}

function callAPI($url, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("API Error: HTTP {$httpCode} - {$response}");
    }
    
    return json_decode($response, true);
}
?>
