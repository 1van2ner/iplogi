<?php
// Autoloader para PHPMailer sin Composer
// Guard: cargar los archivos una sola vez para evitar "Cannot redeclare"
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
    $phpmailerFiles = [
        __DIR__ . '/phpmailer/phpmailer/src/Exception.php',
        __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/phpmailer/phpmailer/src/SMTP.php',
    ];
    foreach ($phpmailerFiles as $file) {
        if (file_exists($file)) require_once $file;
    }
}

spl_autoload_register(function ($class) {
    $map = [
        'PHPMailer\\PHPMailer\\PHPMailer' => __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php',
        'PHPMailer\\PHPMailer\\SMTP'      => __DIR__ . '/phpmailer/phpmailer/src/SMTP.php',
        'PHPMailer\\PHPMailer\\Exception' => __DIR__ . '/phpmailer/phpmailer/src/Exception.php',
    ];
    if (isset($map[$class]) && file_exists($map[$class]) && !class_exists($class, false)) {
        require_once $map[$class];
    }
});
