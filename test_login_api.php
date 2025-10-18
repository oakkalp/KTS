<?php
$url = 'http://127.0.0.1/kuryefullsistem/api/auth/login'; // Host makine IP'si
$data = array('username' => 'testkurye', 'password' => '123456'); // Test kullanıcı adı ve şifresi

$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
    ),
);
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    echo "API'ye bağlanırken hata oluştu veya API yanıt vermedi.";
} else {
    echo $result;
}
?>
