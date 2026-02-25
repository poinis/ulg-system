<?php
/**
 * AI Video Prompt Generator for Veo 3.1 - Version 2.0
 * รองรับ: อัปโหลดรูปสินค้า 1-5 รูป + เลือก/อัปโหลดหน้าคนรีวิว + Gemini Vision + Social Content
 */

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// ==================== CONFIG ====================
$config = [
    'gemini_api_key' => 'AIzaSyAseM5AcZIIPhysOy1wZjzw7aOfYHeujRg', // ใส่ API Key ของคุณ
    'upload_dir' => 'uploads/',
    'max_images' => 5,
    'max_file_size' => 10 * 1024 * 1024,
    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
];

if (!file_exists($config['upload_dir'])) mkdir($config['upload_dir'], 0755, true);

// ==================== PRESENTER OPTIONS ====================
$presenterOptions = [
    'female_young' => [
        'name' => 'ผู้หญิงวัยรุ่น',
        'desc_en' => 'Young Asian woman, 20-25 years old, friendly smile, casual style, natural makeup',
        'desc_th' => 'ผู้หญิงเอเชียวัยรุ่น อายุ 20-25 ปี ยิ้มเป็นกันเอง แต่งตัว casual แต่งหน้าธรรมชาติ'
    ],
    'female_pro' => [
        'name' => 'ผู้หญิงมืออาชีพ',
        'desc_en' => 'Professional Asian woman, 28-35 years old, confident, business casual attire, elegant',
        'desc_th' => 'ผู้หญิงเอเชียมืออาชีพ อายุ 28-35 ปี มั่นใจ แต่งตัว business casual สง่างาม'
    ],
    'female_mature' => [
        'name' => 'ผู้หญิงวัยกลางคน',
        'desc_en' => 'Mature Asian woman, 40-50 years old, warm and trustworthy, sophisticated style',
        'desc_th' => 'ผู้หญิงเอเชียวัยกลางคน อายุ 40-50 ปี อบอุ่นน่าเชื่อถือ สไตล์ดูดี'
    ],
    'male_young' => [
        'name' => 'ผู้ชายวัยรุ่น',
        'desc_en' => 'Young Asian man, 20-25 years old, energetic, trendy casual style, friendly',
        'desc_th' => 'ผู้ชายเอเชียวัยรุ่น อายุ 20-25 ปี กระตือรือร้น แต่งตัวทันสมัย เป็นกันเอง'
    ],
    'male_pro' => [
        'name' => 'ผู้ชายมืออาชีพ',
        'desc_en' => 'Professional Asian man, 28-35 years old, confident, smart casual, well-groomed',
        'desc_th' => 'ผู้ชายเอเชียมืออาชีพ อายุ 28-35 ปี มั่นใจ แต่งตัว smart casual ดูดี'
    ],
    'male_mature' => [
        'name' => 'ผู้ชายวัยกลางคน',
        'desc_en' => 'Mature Asian man, 40-50 years old, authoritative, trustworthy, professional appearance',
        'desc_th' => 'ผู้ชายเอเชียวัยกลางคน อายุ 40-50 ปี น่าเชื่อถือ ดูเป็นผู้เชี่ยวชาญ'
    ],
    'custom' => [
        'name' => 'อัปโหลดรูปหน้าคน',
        'desc_en' => 'Custom presenter based on uploaded reference image',
        'desc_th' => 'กำหนดหน้าตาพรีเซนเตอร์จากรูปที่อัปโหลด'
    ],
    'no_presenter' => [
        'name' => 'ไม่มีคนรีวิว (โชว์สินค้าอย่างเดียว)',
        'desc_en' => 'Product only, no human presenter',
        'desc_th' => 'แสดงสินค้าอย่างเดียว ไม่มีคนรีวิว'
    ],
];

// ==================== VIDEO STYLES ====================
$videoStyles = [
    'ugc_review' => ['name_th' => 'UGC รีวิวสินค้า', 'name_en' => 'UGC Review', 'tone' => 'เป็นกันเอง อบอุ่น', 'visual' => 'handheld camera, intimate framing, eye-level'],
    'product_hero' => ['name_th' => 'Product Hero', 'name_en' => 'Product Hero', 'tone' => 'หรูหรา Premium', 'visual' => 'smooth dolly, 360 rotation, macro close-ups'],
    'cinematic' => ['name_th' => 'Cinematic', 'name_en' => 'Cinematic', 'tone' => 'ดราม่า สร้างอารมณ์', 'visual' => 'cinematic movements, slow motion, depth of field'],
    'fast_promo' => ['name_th' => 'โปรโมชั่นด่วน', 'name_en' => 'Flash Promo', 'tone' => 'ตื่นเต้น เร่งด่วน', 'visual' => 'fast cuts, dynamic angles, quick zooms'],
    'tutorial' => ['name_th' => 'สอนวิธีใช้', 'name_en' => 'Tutorial', 'tone' => 'ชัดเจน เข้าใจง่าย', 'visual' => 'steady shots, close-up on hands, step-by-step'],
    'lifestyle' => ['name_th' => 'Lifestyle', 'name_en' => 'Lifestyle', 'tone' => 'ผ่อนคลาย เป็นธรรมชาติ', 'visual' => 'natural lighting, everyday settings, warm colors'],
];

// ==================== ASPECT RATIOS ====================
$aspectRatios = [
    '9:16' => 'Portrait 9:16 (TikTok/Reels/Shorts)',
    '16:9' => 'Landscape 16:9 (YouTube/Facebook)',
    '1:1' => 'Square 1:1 (Instagram Feed)',
    '4:5' => 'Portrait 4:5 (Instagram Feed)',
];

// ==================== TARGET AUDIENCES ====================
$targetAudiences = [
    'general' => 'ทั่วไป',
    'young_women' => 'ผู้หญิงวัยรุ่น-วัยทำงาน (18-35)',
    'young_men' => 'ผู้ชายวัยรุ่น-วัยทำงาน (18-35)',
    'mothers' => 'คุณแม่ / ครอบครัว',
    'business' => 'นักธุรกิจ / คนทำงาน',
    'health_conscious' => 'คนรักสุขภาพ',
    'tech_savvy' => 'คนรักเทคโนโลยี',
    'seniors' => 'ผู้สูงอายุ',
];

// ==================== PLATFORMS ====================
$platforms = [
    'facebook' => ['name' => 'Facebook', 'icon' => '📘', 'max' => 500],
    'instagram' => ['name' => 'Instagram', 'icon' => '📸', 'max' => 2200],
    'tiktok' => ['name' => 'TikTok', 'icon' => '🎵', 'max' => 150],
    'twitter' => ['name' => 'X/Twitter', 'icon' => '🐦', 'max' => 280],
    'line' => ['name' => 'LINE', 'icon' => '💚', 'max' => 500],
];

// ==================== FUNCTIONS ====================

function uploadImages($files, $key, $config) {
    $uploaded = [];
    if (!isset($files[$key]) || !is_array($files[$key]['name'])) return $uploaded;
    
    $count = min(count($files[$key]['name']), $config['max_images']);
    for ($i = 0; $i < $count; $i++) {
        if ($files[$key]['error'][$i] !== UPLOAD_ERR_OK) continue;
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $files[$key]['tmp_name'][$i]);
        finfo_close($finfo);
        
        if (!in_array($mime, $config['allowed_types'])) continue;
        if ($files[$key]['size'][$i] > $config['max_file_size']) continue;
        
        $ext = pathinfo($files[$key]['name'][$i], PATHINFO_EXTENSION);
        $newName = uniqid('img_') . '.' . $ext;
        $dest = $config['upload_dir'] . $newName;
        
        if (move_uploaded_file($files[$key]['tmp_name'][$i], $dest)) {
            $uploaded[] = [
                'path' => $dest,
                'mime' => $mime,
                'base64' => base64_encode(file_get_contents($dest))
            ];
        }
    }
    return $uploaded;
}

function uploadSingleImage($files, $key, $config) {
    if (!isset($files[$key]) || $files[$key]['error'] !== UPLOAD_ERR_OK) return null;
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $files[$key]['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $config['allowed_types'])) return null;
    if ($files[$key]['size'] > $config['max_file_size']) return null;
    
    $ext = pathinfo($files[$key]['name'], PATHINFO_EXTENSION);
    $newName = uniqid('presenter_') . '.' . $ext;
    $dest = $config['upload_dir'] . $newName;
    
    if (move_uploaded_file($files[$key]['tmp_name'], $dest)) {
        return [
            'path' => $dest,
            'mime' => $mime,
            'base64' => base64_encode(file_get_contents($dest))
        ];
    }
    return null;
}

function callGeminiAPI($images, $presenterImage, $formData, $presenterOptions, $videoStyles, $apiKey) {
    if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
        return ['success' => false, 'error' => 'กรุณาตั้งค่า Gemini API Key'];
    }
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;
    
    $parts = [];
    
    // Add product images
    foreach ($images as $img) {
        $parts[] = ['inline_data' => ['mime_type' => $img['mime'], 'data' => $img['base64']]];
    }
    
    // Add presenter image if custom
    if ($presenterImage) {
        $parts[] = ['inline_data' => ['mime_type' => $presenterImage['mime'], 'data' => $presenterImage['base64']]];
    }
    
    // Presenter description
    $presenterType = $formData['presenter_type'];
    $presenterDesc = '';
    if ($presenterType === 'custom' && $presenterImage) {
        $presenterDesc = "ใช้หน้าตาจากรูป presenter ที่แนบมา (รูปสุดท้าย) เป็นต้นแบบหน้าตาพรีเซนเตอร์ในวิดีโอ";
    } elseif ($presenterType === 'no_presenter') {
        $presenterDesc = "ไม่มีคนรีวิว แสดงเฉพาะสินค้าอย่างเดียว";
    } else {
        $presenterDesc = $presenterOptions[$presenterType]['desc_en'] ?? 'Professional presenter';
    }
    
    $style = $videoStyles[$formData['style']] ?? $videoStyles['ugc_review'];
    $lang = $formData['language'] === 'th' ? 'ภาษาไทย' : 'English';
    
    $promptText = "คุณเป็นผู้เชี่ยวชาญด้านการตลาดและสร้างวิดีโอโฆษณา

## ข้อมูลสินค้า:
- ชื่อ: {$formData['product_name']}
- รายละเอียด: {$formData['product_description']}
- จุดเด่น: {$formData['key_features']}
- ราคา: {$formData['price']}
- โปรโมชั่น: {$formData['promotion']}
- กลุ่มเป้าหมาย: {$formData['target_audience']}

## ตั้งค่าวิดีโอ:
- สไตล์: {$style['name_th']} ({$style['name_en']})
- ความยาว: {$formData['duration']} วินาที
- Aspect Ratio: {$formData['aspect_ratio']}
- Tone: {$style['tone']}
- Visual Style: {$style['visual']}

## พรีเซนเตอร์:
{$presenterDesc}

## สิ่งที่ต้องสร้าง:

### 1. 📷 วิเคราะห์รูปสินค้า
- อธิบายสิ่งที่เห็นในรูป
- จุดเด่นทางภาพ (สี, ดีไซน์, บรรจุภัณฑ์)

### 2. 🎬 VEO VIDEO PROMPT (ภาษาอังกฤษ)
สร้าง prompt สำหรับ Veo 3.1 ที่ละเอียด ประกอบด้วย:
- Scene description
- Presenter description: {$presenterDesc}
- Camera movements: {$style['visual']}
- Lighting และ mood
- Product placement
- Duration: {$formData['duration']} seconds
- Aspect ratio: {$formData['aspect_ratio']}

### 3. 📝 SCRIPT/VOICEOVER ({$lang})
สคริปต์พากย์เสียงที่:
- ถูกต้องตามหลัก AI Ethics (ไม่อ้างว่าใช้จริง ไม่รับรองผลลัพธ์)
- ใช้คำว่า 'ตามข้อมูลสินค้า', 'ออกแบบมาเพื่อ', 'คุณสมบัติที่น่าสนใจ'
- ห้ามใช้คำว่า 'ลองใช้แล้ว', 'ใช้จริง', 'การันตี', 'ได้ผลชัวร์'
- Hook ดึงดูดใน 3 วินาทีแรก
- CTA ท้ายคลิป

### 4. 📱 SOCIAL MEDIA CONTENT ({$lang})
สร้าง Caption สำหรับแต่ละแพลตฟอร์ม:

**Facebook** (ไม่เกิน 500 ตัวอักษร):
- Storytelling + emoji + CTA

**Instagram** (ไม่เกิน 2200 ตัวอักษร):
- Caption + Hashtag 10-15 อัน

**TikTok** (ไม่เกิน 150 ตัวอักษร):
- สั้น กระชับ + hashtag 3-5 อัน

**X/Twitter** (ไม่เกิน 280 ตัวอักษร):
- ตรงประเด็น + hashtag 1-2 อัน

**LINE** (ไม่เกิน 500 ตัวอักษร):
- เป็นกันเอง เหมาะกับคนไทย

### 5. #️⃣ HASHTAGS แนะนำ
- Hashtag ยอดนิยม 15-20 อัน
- แบ่งเป็น: ทั่วไป, เฉพาะกลุ่ม, trending

## ⚠️ AI ETHICS - สำคัญมาก:
- ห้ามอ้างว่าใช้จริง
- ห้ามรับรองผลลัพธ์
- ใช้ข้อมูลจากแบรนด์เท่านั้น
- ถ้ามีพรีเซนเตอร์ ให้ระบุว่าเป็น AI Presenter";

    $parts[] = ['text' => $promptText];
    
    $requestData = [
        'contents' => [['parts' => $parts]],
        'generationConfig' => ['temperature' => 0.8, 'maxOutputTokens' => 8192]
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 120
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($content) return ['success' => true, 'content' => $content];
    }
    
    return ['success' => false, 'error' => 'API Error: ' . $httpCode];
}

function generateBasicPrompt($formData, $presenterOptions, $videoStyles) {
    $style = $videoStyles[$formData['style']] ?? $videoStyles['ugc_review'];
    $presenter = $presenterOptions[$formData['presenter_type']] ?? $presenterOptions['female_young'];
    
    $presenterDesc = $formData['presenter_type'] === 'no_presenter' 
        ? 'Product-only video, no human presenter.' 
        : $presenter['desc_en'];
    
    $prompt = "Create a {$formData['duration']}-second commercial video for \"{$formData['product_name']}\".

PRESENTER:
{$presenterDesc}

VISUAL STYLE:
{$style['visual']}

TONE:
{$style['tone']}

PRODUCT INFO:
{$formData['product_description']}
Key features: {$formData['key_features']}

SCENE:
";

    switch ($formData['style']) {
        case 'ugc_review':
            $prompt .= "Presenter in a clean, well-lit room speaks directly to camera. Natural daylight, warm atmosphere. Presenter holds and demonstrates the product, pointing out features with genuine enthusiasm.";
            break;
        case 'product_hero':
            $prompt .= "Product as the hero. Dramatic reveal shot. Product rotating on clean surface with professional lighting. Multiple angles showcasing design. Sleek, premium feel.";
            break;
        case 'cinematic':
            $prompt .= "Cinematic storytelling. Establishing shot. Product in aspirational lifestyle scene. Dramatic lighting, shallow depth of field. Emotional, memorable.";
            break;
        case 'fast_promo':
            $prompt .= "High energy promo. Quick cuts between product shots. Bold colors, dynamic text overlays. Flash sale energy.";
            break;
        case 'tutorial':
            $prompt .= "Clear instructional video. Hands demonstrating product step by step. Clean background, excellent lighting.";
            break;
        default:
            $prompt .= "Product showcase in beautiful setting. Natural lighting, warm colors.";
    }
    
    $prompt .= "

TECHNICAL:
- Aspect ratio: {$formData['aspect_ratio']}
- Duration: {$formData['duration']} seconds
- Quality: Professional commercial
- Audio: Voiceover + ambient sound";

    return $prompt;
}

// ==================== HANDLE FORM ====================
$result = null;
$uploadedImages = [];
$presenterImage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadedImages = uploadImages($_FILES, 'product_images', $config);
    
    if ($_POST['presenter_type'] === 'custom') {
        $presenterImage = uploadSingleImage($_FILES, 'presenter_image', $config);
    }
    
    $formData = [
        'product_name' => trim($_POST['product_name'] ?? ''),
        'product_description' => trim($_POST['product_description'] ?? ''),
        'key_features' => trim($_POST['key_features'] ?? ''),
        'price' => trim($_POST['price'] ?? ''),
        'promotion' => trim($_POST['promotion'] ?? ''),
        'target_audience' => $_POST['target_audience'] ?? 'general',
        'presenter_type' => $_POST['presenter_type'] ?? 'female_young',
        'style' => $_POST['style'] ?? 'ugc_review',
        'aspect_ratio' => $_POST['aspect_ratio'] ?? '9:16',
        'duration' => intval($_POST['duration'] ?? 15),
        'language' => $_POST['language'] ?? 'th',
        'use_gemini' => isset($_POST['use_gemini']),
    ];
    
    if (!empty($formData['product_name'])) {
        if ($formData['use_gemini'] && !empty($uploadedImages)) {
            $result = callGeminiAPI($uploadedImages, $presenterImage, $formData, $presenterOptions, $videoStyles, $config['gemini_api_key']);
        }
        
        if (!$result || !$result['success']) {
            $result = [
                'success' => true,
                'content' => generateBasicPrompt($formData, $presenterOptions, $videoStyles),
                'is_basic' => true
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Video Prompt Generator v2.0</title>
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #10b981;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --border: #475569;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg-dark); color: var(--text); line-height: 1.6; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        header { text-align: center; padding: 30px; background: linear-gradient(135deg, var(--primary), #8b5cf6); border-radius: 16px; margin-bottom: 25px; }
        header h1 { font-size: 2rem; margin-bottom: 8px; }
        header p { opacity: 0.9; }
        .badge { display: inline-block; background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; margin-top: 8px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 25px; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } }
        
        .card { background: var(--bg-card); border-radius: 12px; padding: 20px; border: 1px solid var(--border); margin-bottom: 20px; }
        .card-title { font-size: 1.1rem; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid var(--primary); display: flex; align-items: center; gap: 8px; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.9rem; }
        .hint { font-weight: normal; color: var(--text-muted); font-size: 0.8rem; }
        .required { color: var(--danger); }
        
        input, textarea, select { width: 100%; padding: 10px 14px; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 0.95rem; }
        input:focus, textarea:focus, select:focus { outline: none; border-color: var(--primary); }
        textarea { min-height: 80px; resize: vertical; }
        
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 600px) { .row { grid-template-columns: 1fr; } }
        
        /* Upload Area */
        .upload-area { border: 2px dashed var(--border); border-radius: 10px; padding: 25px; text-align: center; cursor: pointer; transition: 0.3s; }
        .upload-area:hover { border-color: var(--primary); background: rgba(99,102,241,0.1); }
        .upload-area input { display: none; }
        .upload-icon { font-size: 2.5rem; margin-bottom: 8px; }
        
        .preview-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .preview-item { position: relative; width: 70px; height: 70px; border-radius: 8px; overflow: hidden; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .preview-item .remove { position: absolute; top: 2px; right: 2px; background: var(--danger); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 12px; }
        
        /* Presenter Grid */
        .presenter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; }
        .presenter-option { position: relative; }
        .presenter-option input { position: absolute; opacity: 0; }
        .presenter-option label { display: block; padding: 12px 8px; background: var(--bg-input); border: 2px solid var(--border); border-radius: 8px; cursor: pointer; text-align: center; font-size: 0.85rem; transition: 0.3s; }
        .presenter-option input:checked + label { border-color: var(--primary); background: rgba(99,102,241,0.2); }
        .presenter-option label:hover { border-color: var(--primary); }
        .presenter-icon { font-size: 1.8rem; margin-bottom: 5px; }
        
        .custom-presenter-upload { display: none; margin-top: 12px; padding: 15px; background: rgba(99,102,241,0.1); border-radius: 8px; border: 1px dashed var(--primary); }
        .custom-presenter-upload.show { display: block; }
        
        /* Style Grid */
        .style-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
        .style-option { position: relative; }
        .style-option input { position: absolute; opacity: 0; }
        .style-option label { display: block; padding: 12px 8px; background: var(--bg-input); border: 2px solid var(--border); border-radius: 8px; cursor: pointer; text-align: center; font-size: 0.8rem; transition: 0.3s; }
        .style-option input:checked + label { border-color: var(--secondary); background: rgba(16,185,129,0.2); }
        .style-icon { font-size: 1.5rem; margin-bottom: 4px; }
        
        .checkbox-group { display: flex; align-items: center; gap: 10px; padding: 12px; background: var(--bg-input); border-radius: 8px; border: 2px solid var(--border); }
        .checkbox-group:has(input:checked) { border-color: var(--secondary); background: rgba(16,185,129,0.1); }
        .checkbox-group input { width: 18px; height: 18px; }
        
        .btn { padding: 12px 24px; font-size: 1rem; font-weight: 600; border: none; border-radius: 10px; cursor: pointer; transition: 0.3s; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #8b5cf6); color: white; width: 100%; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99,102,241,0.3); }
        .btn-copy { background: var(--secondary); color: white; padding: 8px 14px; font-size: 0.85rem; }
        
        .output-box { background: var(--bg-input); border: 1px solid var(--border); border-radius: 10px; padding: 15px; white-space: pre-wrap; font-family: 'Consolas', monospace; font-size: 0.85rem; max-height: 500px; overflow-y: auto; line-height: 1.7; }
        
        .ethics-box { background: rgba(245,158,11,0.15); border: 1px solid var(--warning); border-radius: 10px; padding: 15px; margin-top: 15px; }
        .ethics-box h4 { color: var(--warning); margin-bottom: 8px; }
        .ethics-box ul { margin-left: 18px; font-size: 0.85rem; color: var(--text-muted); }
        
        .img-preview-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        .img-preview-row img { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 2px solid var(--primary); }
        
        .loading { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.9); z-index: 1000; justify-content: center; align-items: center; flex-direction: column; }
        .loading.show { display: flex; }
        .spinner { width: 50px; height: 50px; border: 4px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { margin-top: 15px; font-size: 1.1rem; }
        
        footer { text-align: center; padding: 25px; color: var(--text-muted); margin-top: 30px; }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <div class="loading-text">🤖 Gemini กำลังวิเคราะห์และสร้าง Content...</div>
    </div>

    <div class="container">
        <header>
            <h1>🎬 AI Video Prompt Generator</h1>
            <p>สร้าง Prompt + Content สำหรับ Veo 3.1</p>
            <div class="badge">v2.0 - รองรับเลือกพรีเซนเตอร์ + อัปโหลดหน้าคน</div>
        </header>
        
        <form method="POST" enctype="multipart/form-data" id="mainForm">
            <div class="grid">
                <!-- LEFT: Form -->
                <div>
                    <!-- Product Images -->
                    <div class="card">
                        <h2 class="card-title">📷 รูปสินค้า <span class="hint">(1-5 รูป)</span></h2>
                        <div class="upload-area" onclick="document.getElementById('productImages').click()">
                            <input type="file" name="product_images[]" id="productImages" multiple accept="image/*">
                            <div class="upload-icon">📸</div>
                            <div>คลิกเพื่อเลือกรูป หรือลากมาวาง</div>
                            <small class="hint">JPG, PNG, WebP (สูงสุด 10MB/รูป)</small>
                        </div>
                        <div class="preview-grid" id="productPreview"></div>
                    </div>
                    
                    <!-- Presenter Selection -->
                    <div class="card">
                        <h2 class="card-title">👤 เลือกคนรีวิว/พรีเซนเตอร์</h2>
                        <div class="presenter-grid">
                            <?php 
                            $presenterIcons = [
                                'female_young' => '👩',
                                'female_pro' => '👩‍💼',
                                'female_mature' => '👩‍🦰',
                                'male_young' => '👨',
                                'male_pro' => '👨‍💼',
                                'male_mature' => '👨‍🦳',
                                'custom' => '📷',
                                'no_presenter' => '📦',
                            ];
                            foreach ($presenterOptions as $key => $opt): ?>
                            <div class="presenter-option">
                                <input type="radio" name="presenter_type" id="presenter_<?= $key ?>" value="<?= $key ?>" <?= ($_POST['presenter_type'] ?? 'female_young') === $key ? 'checked' : '' ?>>
                                <label for="presenter_<?= $key ?>">
                                    <div class="presenter-icon"><?= $presenterIcons[$key] ?></div>
                                    <div><?= $opt['name'] ?></div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="custom-presenter-upload" id="customPresenterUpload">
                            <label>📷 อัปโหลดรูปหน้าคนต้นแบบ</label>
                            <input type="file" name="presenter_image" id="presenterImage" accept="image/*">
                            <div class="preview-grid" id="presenterPreview" style="margin-top: 10px;"></div>
                            <small class="hint">รูปหน้าตรง ชัดเจน จะถูกใช้เป็นต้นแบบหน้าตาในวิดีโอ</small>
                        </div>
                    </div>
                    
                    <!-- Product Info -->
                    <div class="card">
                        <h2 class="card-title">📦 ข้อมูลสินค้า</h2>
                        <div class="form-group">
                            <label>ชื่อสินค้า <span class="required">*</span></label>
                            <input type="text" name="product_name" required placeholder="เช่น iPhone 15 Pro Max" value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>รายละเอียด</label>
                            <textarea name="product_description" placeholder="อธิบายสินค้าโดยรวม..."><?= htmlspecialchars($_POST['product_description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>จุดเด่น <span class="hint">(แยกบรรทัด)</span></label>
                            <textarea name="key_features" placeholder="- กล้อง 48MP&#10;- ชิป A17 Pro"><?= htmlspecialchars($_POST['key_features'] ?? '') ?></textarea>
                        </div>
                        <div class="row">
                            <div class="form-group">
                                <label>ราคา</label>
                                <input type="text" name="price" placeholder="48,900 บาท" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>โปรโมชั่น</label>
                                <input type="text" name="promotion" placeholder="ลด 20%" value="<?= htmlspecialchars($_POST['promotion'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>กลุ่มเป้าหมาย</label>
                            <select name="target_audience">
                                <?php foreach ($targetAudiences as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($_POST['target_audience'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Video Style -->
                    <div class="card">
                        <h2 class="card-title">🎨 สไตล์วิดีโอ</h2>
                        <div class="style-grid">
                            <?php 
                            $styleIcons = ['ugc_review'=>'👩‍💼', 'product_hero'=>'✨', 'cinematic'=>'🎬', 'fast_promo'=>'⚡', 'tutorial'=>'📖', 'lifestyle'=>'🏠'];
                            foreach ($videoStyles as $k => $s): ?>
                            <div class="style-option">
                                <input type="radio" name="style" id="style_<?= $k ?>" value="<?= $k ?>" <?= ($_POST['style'] ?? 'ugc_review') === $k ? 'checked' : '' ?>>
                                <label for="style_<?= $k ?>">
                                    <div class="style-icon"><?= $styleIcons[$k] ?? '🎥' ?></div>
                                    <div><?= $s['name_th'] ?></div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Settings -->
                    <div class="card">
                        <h2 class="card-title">⚙️ ตั้งค่า</h2>
                        <div class="row">
                            <div class="form-group">
                                <label>Aspect Ratio</label>
                                <select name="aspect_ratio">
                                    <?php foreach ($aspectRatios as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= ($_POST['aspect_ratio'] ?? '9:16') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>ความยาว</label>
                                <select name="duration">
                                    <?php foreach ([5,10,15,30,60] as $d): ?>
                                    <option value="<?= $d ?>" <?= ($_POST['duration'] ?? 15) == $d ? 'selected' : '' ?>><?= $d ?> วินาที</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>ภาษา Content</label>
                            <select name="language">
                                <option value="th" <?= ($_POST['language'] ?? 'th') === 'th' ? 'selected' : '' ?>>🇹🇭 ภาษาไทย</option>
                                <option value="en" <?= ($_POST['language'] ?? '') === 'en' ? 'selected' : '' ?>>🇺🇸 English</option>
                            </select>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="use_gemini" id="useGemini" <?= isset($_POST['use_gemini']) ? 'checked' : '' ?>>
                            <label for="useGemini" style="margin:0; cursor:pointer;">
                                ✨ <strong>ใช้ Gemini AI วิเคราะห์รูป + สร้าง Content</strong><br>
                                <small class="hint">จะวิเคราะห์รูปสินค้าและสร้าง Prompt + Social Content อัตโนมัติ</small>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 15px;">
                            🚀 สร้าง Prompt + Content
                        </button>
                    </div>
                </div>
                
                <!-- RIGHT: Output -->
                <div>
                    <?php if ($result && $result['success']): ?>
                    
                    <?php if (!empty($uploadedImages)): ?>
                    <div class="card">
                        <h2 class="card-title">📷 รูปที่อัปโหลด</h2>
                        <div class="img-preview-row">
                            <?php foreach ($uploadedImages as $img): ?>
                            <img src="<?= htmlspecialchars($img['path']) ?>" alt="Product">
                            <?php endforeach; ?>
                            <?php if ($presenterImage): ?>
                            <img src="<?= htmlspecialchars($presenterImage['path']) ?>" alt="Presenter" style="border-color: var(--secondary);">
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <h2 class="card-title"><?= isset($result['is_basic']) ? '📋 Basic Prompt' : '✨ ผลลัพธ์จาก Gemini AI' ?></h2>
                        <div class="output-box" id="outputContent"><?= htmlspecialchars($result['content']) ?></div>
                        <div style="margin-top: 12px;">
                            <button type="button" class="btn btn-copy" onclick="copyContent()">📋 Copy ทั้งหมด</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="ethics-box">
                            <h4>⚠️ AI Ethics Reminder</h4>
                            <ul>
                                <li>✅ พูดได้: อธิบายฟีเจอร์ + ข้อมูลจริง</li>
                                <li>❌ ห้ามพูด: ประสบการณ์จริง + รับรองผลลัพธ์</li>
                                <li>📌 หลักการ: ไม่สร้างภาพว่ามีคนลองแล้ว</li>
                            </ul>
                        </div>
                    </div>
                    
                    <?php elseif ($result && !$result['success']): ?>
                    <div class="card">
                        <h2 class="card-title">❌ เกิดข้อผิดพลาด</h2>
                        <p style="color: var(--danger);"><?= htmlspecialchars($result['error']) ?></p>
                    </div>
                    
                    <?php else: ?>
                    <div class="card">
                        <h2 class="card-title">👈 เริ่มต้นใช้งาน</h2>
                        <p class="hint" style="margin-bottom: 15px;">อัปโหลดรูปสินค้า เลือกพรีเซนเตอร์ กรอกข้อมูล แล้วกดสร้าง</p>
                        
                        <div style="background: var(--bg-input); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <h4 style="margin-bottom: 10px;">✨ ฟีเจอร์ใหม่ v2.0</h4>
                            <ul style="margin-left: 18px; color: var(--text-muted); font-size: 0.9rem;">
                                <li>🖼️ อัปโหลดรูปสินค้า 1-5 รูป</li>
                                <li>👤 เลือกเพศ/วัยพรีเซนเตอร์</li>
                                <li>📷 อัปโหลดรูปหน้าคนต้นแบบ</li>
                                <li>🤖 Gemini AI วิเคราะห์รูป</li>
                                <li>📱 สร้าง Content ทุก Social Media</li>
                            </ul>
                        </div>
                        
                        <div class="ethics-box">
                            <h4>⚠️ AI Ethics ที่ระบบรองรับ</h4>
                            <ul>
                                <li>✅ พูดได้: อธิบายฟีเจอร์ + ข้อมูลจริง</li>
                                <li>❌ ห้ามพูด: ประสบการณ์จริง + รับรองผลลัพธ์</li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        
        <footer>
            <p>🎬 AI Video Prompt Generator v2.0</p>
            <small>รองรับ Gemini Vision + เลือกพรีเซนเตอร์ + AI Ethics</small>
        </footer>
    </div>

    <script>
    // Product images preview
    const productInput = document.getElementById('productImages');
    const productPreview = document.getElementById('productPreview');
    let productFiles = [];
    
    productInput.addEventListener('change', function(e) {
        const newFiles = Array.from(e.target.files).slice(0, 5 - productFiles.length);
        newFiles.forEach(f => { if (f.type.startsWith('image/') && productFiles.length < 5) productFiles.push(f); });
        updateProductPreview();
    });
    
    function updateProductPreview() {
        productPreview.innerHTML = '';
        productFiles.forEach((f, i) => {
            const reader = new FileReader();
            reader.onload = e => {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove" onclick="removeProduct(${i})">×</button>`;
                productPreview.appendChild(div);
            };
            reader.readAsDataURL(f);
        });
        // Update input
        const dt = new DataTransfer();
        productFiles.forEach(f => dt.items.add(f));
        productInput.files = dt.files;
    }
    
    function removeProduct(i) {
        productFiles.splice(i, 1);
        updateProductPreview();
    }
    
    // Presenter type toggle
    document.querySelectorAll('input[name="presenter_type"]').forEach(el => {
        el.addEventListener('change', function() {
            document.getElementById('customPresenterUpload').classList.toggle('show', this.value === 'custom');
        });
    });
    
    // Presenter image preview
    document.getElementById('presenterImage').addEventListener('change', function(e) {
        const preview = document.getElementById('presenterPreview');
        preview.innerHTML = '';
        if (e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = ev => {
                preview.innerHTML = `<div class="preview-item"><img src="${ev.target.result}"></div>`;
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });
    
    // Form submit loading
    document.getElementById('mainForm').addEventListener('submit', function() {
        if (document.getElementById('useGemini').checked) {
            document.getElementById('loading').classList.add('show');
        }
    });
    
    // Copy function
    function copyContent() {
        const text = document.getElementById('outputContent').innerText;
        navigator.clipboard.writeText(text).then(() => {
            const btn = event.target;
            btn.innerText = '✅ Copied!';
            setTimeout(() => btn.innerText = '📋 Copy ทั้งหมด', 2000);
        });
    }
    
    // Init
    if (document.querySelector('input[name="presenter_type"]:checked')?.value === 'custom') {
        document.getElementById('customPresenterUpload').classList.add('show');
    }
    </script>
</body>
</html>