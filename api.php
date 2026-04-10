<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$targetBase = "http://127.0.0.1:8090";
$path = str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['REQUEST_URI']);
$targetUrl = $targetBase . $path;

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// --- TRATAMENTO DO CORPO ---
if (in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT','PATCH','DELETE'])) {
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
        $postData = $_POST;
        foreach ($_FILES as $name => $file) {
            if (!empty($file['tmp_name'])) {
                $postData[$name] = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
            }
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents("php://input"));
    }
}

// --- CABEÇALHOS ---
$headers = [];
foreach (getallheaders() as $key => $value) {
    $lowerKey = strtolower($key);
    if (in_array($lowerKey, ['host','content-length','content-type','expect'])) {
        continue;
    }
    $headers[] = "$key: $value";
}
if (isset($_SERVER['CONTENT_TYPE']) && !str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data')) {
    $headers[] = "Content-Type: " . $_SERVER['CONTENT_TYPE'];
}
$headers[] = "Expect:"; // evita 100-continue
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// --- EXECUÇÃO ---
$response = curl_exec($ch);
if ($response === false) {
    error_log("Erro cURL: " . curl_error($ch));
    http_response_code(502);
    echo json_encode(["error" => "Proxy error"]);
    exit;
}

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headers_raw = substr($response, 0, $header_size);
$body = substr($response, $header_size);

curl_close($ch);

// --- REPASSE DE CABEÇALHOS ---
foreach (explode("\r\n", $headers_raw) as $h) {
    if ($h && !preg_match('/^(Transfer-Encoding|Content-Length|Content-Encoding|Connection)/i', $h)) {
        header($h);
    }
}

http_response_code($httpcode);
echo $body
