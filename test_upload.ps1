# Script para probar la API de videos
# PowerShell 5.1 compatible

$videoPath = ".\videotest_20251211.mp4"
$uri = "http://localhost:8270/v1/videos/upload"
$apiKey = "dev-api-key-123"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "   VIDEO UPLOAD API - TEST SCRIPT" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Test 1: Health Check
Write-Host "[TEST 1] Health Check..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8270/v1/videos/health" -Method GET -UseBasicParsing
    $json = $response.Content | ConvertFrom-Json
    Write-Host "✓ Status: $($json.status.code) - $($json.status.description)" -ForegroundColor Green
    Write-Host "✓ Database: $($json.data.database)" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
}

# Test 2: Upload Video usando curl.exe
Write-Host "[TEST 2] Upload Video..." -ForegroundColor Yellow
if (Test-Path $videoPath) {
    Write-Host "Video file: $videoPath" -ForegroundColor Gray
    $fileSize = (Get-Item $videoPath).Length
    Write-Host "File size: $([math]::Round($fileSize / 1MB, 2)) MB" -ForegroundColor Gray
    Write-Host ""
    
    # Usar curl.exe (Windows 10+)
    Write-Host "Uploading..." -ForegroundColor Gray
    $curlArgs = @(
        "-X", "POST",
        "$uri",
        "-H", "X-API-Key: $apiKey",
        "-F", "video=@$videoPath",
        "-F", "project_id=PROJECT_TEST",
        "-F", "video_identifier=VIDEO_TEST_001",
        "-F", 'metadata={"user":"test","device":"PowerShell","timestamp":"2025-12-11"}'
    )
    
    $result = & curl.exe @curlArgs 2>&1
    
    try {
        $json = $result | ConvertFrom-Json
        Write-Host "✓ Status: $($json.status.code) - $($json.status.description)" -ForegroundColor Green
        if ($json.data) {
            Write-Host "✓ Video ID: $($json.data.id)" -ForegroundColor Green
            Write-Host "✓ File Path: $($json.data.file_path)" -ForegroundColor Green
        }
    } catch {
        Write-Host "Response: $result" -ForegroundColor Yellow
    }
    Write-Host ""
} else {
    Write-Host "✗ Video file not found: $videoPath" -ForegroundColor Red
    Write-Host ""
}

# Test 3: Get Video by ID (usar el ID del upload anterior)
Write-Host "[TEST 3] Get Video by ID..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8270/v1/videos/1" -Method GET -Headers @{"X-API-Key" = $apiKey} -UseBasicParsing
    $json = $response.Content | ConvertFrom-Json
    Write-Host "✓ Video found: $($json.data.original_filename)" -ForegroundColor Green
    Write-Host "✓ Status: $($json.data.status)" -ForegroundColor Green
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    if ($statusCode -eq 404) {
        Write-Host "✓ 404 Not Found (expected if no video exists yet)" -ForegroundColor Yellow
    } else {
        Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
    }
}
Write-Host ""

# Test 4: Get Videos by Project
Write-Host "[TEST 4] Get Videos by Project..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8270/v1/videos/project/PROJECT_TEST?page=1&per_page=10" -Method GET -Headers @{"X-API-Key" = $apiKey} -UseBasicParsing
    $json = $response.Content | ConvertFrom-Json
    Write-Host "✓ Found $($json.data.videos.Count) videos" -ForegroundColor Green
    Write-Host "✓ Page: $($json.data.pagination.page) | Per Page: $($json.data.pagination.per_page)" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
}

# Test 5: Error - No API Key
Write-Host "[TEST 5] Upload without API Key (should fail)..." -ForegroundColor Yellow
try {
    $curlArgs = @(
        "-X", "POST",
        "$uri",
        "-F", "video=@$videoPath",
        "-F", "project_id=PROJECT_TEST",
        "-F", "video_identifier=VIDEO_TEST_ERROR",
        "-s"
    )
    $result = & curl.exe @curlArgs 2>&1
    $json = $result | ConvertFrom-Json
    if ($json.status.code -eq 401) {
        Write-Host "✓ Correctly rejected: 401 Unauthorized" -ForegroundColor Green
    } else {
        Write-Host "✗ Unexpected status: $($json.status.code)" -ForegroundColor Red
    }
    Write-Host ""
} catch {
    Write-Host "Response: $result" -ForegroundColor Yellow
    Write-Host ""
}

# Test 6: Error - Invalid API Key
Write-Host "[TEST 6] Upload with invalid API Key (should fail)..." -ForegroundColor Yellow
try {
    $curlArgs = @(
        "-X", "POST",
        "$uri",
        "-H", "X-API-Key: invalid-key-xxx",
        "-F", "video=@$videoPath",
        "-F", "project_id=PROJECT_TEST",
        "-F", "video_identifier=VIDEO_TEST_ERROR2",
        "-s"
    )
    $result = & curl.exe @curlArgs 2>&1
    $json = $result | ConvertFrom-Json
    if ($json.status.code -eq 401) {
        Write-Host "✓ Correctly rejected: 401 Unauthorized" -ForegroundColor Green
    } else {
        Write-Host "✗ Unexpected status: $($json.status.code)" -ForegroundColor Red
    }
    Write-Host ""
} catch {
    Write-Host "Response: $result" -ForegroundColor Yellow
    Write-Host ""
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "   TESTS COMPLETED" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
