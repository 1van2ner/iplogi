<?php
namespace PHPMailer\PHPMailer;

class PHPMailer {
    const VERSION             = '6.9.1';
    const STOP_MESSAGE        = 0;
    const STOP_CONTINUE       = 1;
    const STOP_CRITICAL       = 2;
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS    = 'ssl';

    // ── Propiedades públicas ───────────────────────────────────
    public $exceptions        = false;
    public $CharSet           = 'utf-8';
    public $ContentType       = 'text/plain';
    public $Encoding          = '8bit';
    public $ErrorInfo         = '';
    public $From              = 'root@localhost';
    public $FromName          = 'Root User';
    public $Sender            = '';
    public $Subject           = '';
    public $Body              = '';
    public $AltBody           = '';
    public $MIMEBody          = '';
    public $MIMEHeader        = '';
    public $WordWrap          = 0;
    public $Mailer            = 'mail';
    public $Sendmail          = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $ConfirmReadingTo  = '';
    public $Hostname          = '';
    public $MessageID         = '';
    public $MessageDate       = '';
    public $Host              = 'localhost';
    public $Port              = 25;
    public $Helo              = '';
    public $SMTPSecure        = '';
    public $SMTPAutoTLS       = true;
    public $SMTPAuth          = false;
    public $SMTPOptions       = [];
    public $SMTPDebug         = 0;
    public $Debugoutput       = 'echo';
    public $Username          = '';
    public $Password          = '';
    public $AuthType          = '';
    public $Timeout           = 300;
    public $SMTPKeepAlive     = false;
    public $SingleTo          = false;
    public $do_verp           = false;
    public $AllowEmpty        = false;
    public $XMailer           = '';
    public $Priority;
    public $oauth             = null;

    // ── Propiedades protegidas ─────────────────────────────────
    protected $smtp            = null;
    protected $to              = [];
    protected $cc              = [];
    protected $bcc             = [];
    protected $ReplyTo         = [];
    protected $all_recipients  = [];
    protected $attachment      = [];
    protected $CustomHeader    = [];
    protected $lastMessageID   = '';
    protected $message_type    = '';
    protected $boundary        = [];
    protected $error_count     = 0;
    protected $uniqueid        = '';
    protected $LE              = "\r\n";

    public function __construct($exceptions = null) {
        if (null !== $exceptions) $this->exceptions = (bool)$exceptions;
        $this->Hostname = (function_exists('gethostname') ? gethostname() : '') ?: 'localhost.localdomain';
    }

    public function __destruct() { $this->smtpClose(); }

    public function isHTML($isHtml = true) {
        $this->ContentType = $isHtml ? 'text/html' : 'text/plain';
    }
    public function isSMTP()    { $this->Mailer = 'smtp'; }
    public function isMail()    { $this->Mailer = 'mail'; }
    public function isSendmail(){ $this->Mailer = 'sendmail'; }

    // ── Destinatarios ──────────────────────────────────────────
    public function addAddress($address, $name = '')  { return $this->addRecipient('to', $address, $name); }
    public function addCC($address, $name = '')       { return $this->addRecipient('cc', $address, $name); }
    public function addBCC($address, $name = '')      { return $this->addRecipient('bcc', $address, $name); }
    public function addReplyTo($address, $name = '')  {
        $address = trim($address);
        $name    = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!static::validateAddress($address)) { $this->setError("Reply-To address failed: $address"); return false; }
        $this->ReplyTo[strtolower($address)] = [$address, $name];
        return true;
    }

    protected function addRecipient($kind, $address, $name = '') {
        $address = trim($address);
        $name    = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!static::validateAddress($address)) { $this->setError("$kind address failed: $address"); return false; }
        $key = strtolower($address);
        if (!array_key_exists($key, $this->all_recipients)) {
            $this->{$kind}[]              = [$address, $name];
            $this->all_recipients[$key]   = true;
        }
        return true;
    }

    public function setFrom($address, $name = '', $auto = true) {
        $address = trim($address);
        $name    = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!static::validateAddress($address)) { $this->setError("From address failed: $address"); return false; }
        $this->From     = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) $this->Sender = $address;
        return true;
    }

    public static function validateAddress($address, $patternselect = null) {
        return (bool)filter_var($address, FILTER_VALIDATE_EMAIL);
    }

    // ── Envío principal ────────────────────────────────────────
    public function send() {
        try {
            if (!$this->preSend()) return false;
            return $this->postSend();
        } catch (Exception $exc) {
            $this->mailHeader = '';
            $this->setError($exc->getMessage());
            if ($this->exceptions) throw $exc;
            return false;
        }
    }

    public function preSend() {
        if (empty($this->to) && empty($this->cc) && empty($this->bcc)) {
            $this->setError('You must provide at least one recipient email address.');
            return false;
        }
        if (empty($this->From)) { $this->setError('From address not set'); return false; }
        $this->uniqueid    = $this->generateId();
        $this->boundary[1] = 'b1_' . $this->uniqueid;
        $this->message_type = $this->setMessageType();
        $this->MIMEBody    = $this->Body;
        if ($this->Encoding === 'base64') $this->MIMEBody = chunk_split(base64_encode($this->Body), 76, "\r\n");
        $this->MIMEHeader  = $this->createHeader();
        return true;
    }

    public function postSend() {
        try {
            switch ($this->Mailer) {
                case 'smtp':    return $this->smtpSend($this->MIMEHeader, $this->MIMEBody);
                case 'sendmail':return $this->sendmailSend($this->MIMEHeader, $this->MIMEBody);
                default:        return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
            }
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) throw $exc;
            return false;
        }
    }

    // ── SMTP Send ──────────────────────────────────────────────
    protected function smtpSend($header, $body) {
        if (!$this->smtpConnect($this->SMTPOptions)) {
            throw new Exception('SMTP connect() failed: ' . $this->ErrorInfo, self::STOP_CRITICAL);
        }
        $smtp_from = $this->Sender ?: $this->From;
        if (!$this->smtp->mail($smtp_from)) {
            throw new Exception('SMTP MAIL FROM failed: ' . $this->ErrorInfo, self::STOP_CRITICAL);
        }
        $bad_rcpt = [];
        foreach (array_merge($this->to, $this->cc, $this->bcc) as $rcpt) {
            if (!$this->smtp->recipient($rcpt[0])) $bad_rcpt[] = $rcpt[0];
        }
        if (!$this->smtp->data($header . "\r\n" . $body)) {
            throw new Exception('SMTP DATA failed: ' . $this->ErrorInfo, self::STOP_CRITICAL);
        }
        if ($this->SMTPKeepAlive) { $this->smtp->reset(); }
        else { $this->smtp->quit(); $this->smtp->close(); }
        if (!empty($bad_rcpt)) throw new Exception('Recipients failed: ' . implode(', ', $bad_rcpt), self::STOP_CONTINUE);
        return true;
    }

    public function smtpConnect($options = null) {
        if (null === $this->smtp) $this->smtp = $this->getSMTPInstance();
        if ($this->smtp->connected()) return true;

        $this->smtp->setTimeout($this->Timeout);
        $this->smtp->setTimelimit($this->Timeout);
        $this->smtp->setDebugLevel($this->SMTPDebug);
        $this->smtp->setDebugOutput($this->Debugoutput);
        $this->smtp->setVerp($this->do_verp);

        $hosts = explode(';', $this->Host);
        foreach ($hosts as $hostentry) {
            $hostentry = trim($hostentry);
            preg_match('/^((ssl|tls):\/\/)?([a-zA-Z0-9\.\-\[\]:]+?)(:(\d+))?$/', $hostentry, $hi);
            $prefix = '';
            $tls    = ($this->SMTPSecure === self::ENCRYPTION_STARTTLS);
            if (isset($hi[2]) && $hi[2] === 'ssl') { $prefix = 'ssl://'; $tls = false; }
            $host = isset($hi[3]) ? $hi[3] : 'localhost';
            $port = (isset($hi[5]) && ctype_digit($hi[5])) ? (int)$hi[5] : $this->Port;

            if (!$this->smtp->connect($prefix . $host, $port, $this->Timeout, $options ?? [])) continue;

            $hello = $this->Helo ?: $this->serverHostname();
            $this->smtp->hello($hello);

            if ($tls) {
                if (!$this->smtp->startTLS()) { $this->smtp->close(); continue; }
                $this->smtp->hello($hello);
            }

            if ($this->SMTPAuth) {
                if (!$this->smtp->authenticate($this->Username, $this->Password, $this->AuthType ?: 'LOGIN', $this->oauth)) {
                    $err = $this->smtp->getError();
                    $this->setError('SMTP authentication failed: ' . $err['error']);
                    $this->smtp->close();
                    continue;
                }
            }
            return true;
        }
        return false;
    }

    public function smtpClose() {
        if ($this->smtp !== null && $this->smtp->connected()) {
            $this->smtp->quit();
            $this->smtp->close();
        }
    }

    protected function getSMTPInstance() { return new SMTP(); }

    // ── Mail / Sendmail ────────────────────────────────────────
    protected function mailSend($header, $body) {
        $to = implode(', ', array_map([$this,'addrFormat'], $this->to));
        $params = (!empty($this->Sender) && static::validateAddress($this->Sender)) ? sprintf('-f%s', $this->Sender) : null;
        $result = $params ? @mail($to, $this->Subject, $body, $header, $params) : @mail($to, $this->Subject, $body, $header);
        if (!$result) throw new Exception('mail() returned false', self::STOP_CRITICAL);
        return true;
    }

    protected function sendmailSend($header, $body) {
        $sendmail = escapeshellcmd($this->Sendmail) . ' -oi -t';
        $proc = @popen($sendmail, 'w');
        if (!$proc) throw new Exception('Could not open sendmail', self::STOP_CRITICAL);
        fputs($proc, $header . "\r\n" . $body);
        $r = pclose($proc);
        if ($r !== 0) throw new Exception('Sendmail failed', self::STOP_CRITICAL);
        return true;
    }

    // ── Cabeceras ──────────────────────────────────────────────
    protected function createHeader() {
        $h  = '';
        $h .= 'Date: ' . ('' !== $this->MessageDate ? $this->MessageDate : self::rfcDate()) . "\r\n";
        if ($this->Sender) $h .= 'Return-Path: ' . trim($this->Sender) . "\r\n";
        $h .= 'From: ' . $this->addrFormat([$this->From, $this->FromName]) . "\r\n";
        if (!empty($this->ReplyTo)) {
            $rts = array_map([$this,'addrFormat'], array_values($this->ReplyTo));
            $h .= 'Reply-To: ' . implode(', ', $rts) . "\r\n";
        }
        if ($this->Mailer !== 'mail') $h .= 'To: ' . implode(', ', array_map([$this,'addrFormat'], $this->to)) . "\r\n";
        if (!empty($this->cc))  $h .= 'Cc: ' . implode(', ', array_map([$this,'addrFormat'], $this->cc)) . "\r\n";
        $h .= 'Subject: ' . $this->encodeHeader($this->Subject) . "\r\n";
        $this->lastMessageID = !empty($this->MessageID) ? $this->MessageID : '<' . $this->uniqueid . '@' . $this->serverHostname() . '>';
        $h .= 'Message-ID: ' . $this->lastMessageID . "\r\n";
        $h .= 'X-Mailer: PHPMailer ' . static::VERSION . "\r\n";
        if ($this->ConfirmReadingTo) $h .= 'Disposition-Notification-To: <' . trim($this->ConfirmReadingTo) . ">\r\n";
        foreach ($this->CustomHeader as $ch) $h .= trim($ch[0]) . ': ' . $this->encodeHeader(trim($ch[1])) . "\r\n";
        $h .= "MIME-Version: 1.0\r\n";
        $h .= 'Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet . "\r\n";
        $h .= 'Content-Transfer-Encoding: ' . $this->Encoding . "\r\n";
        return $h;
    }

    protected function setMessageType() { return 'plain'; }

    protected function addrFormat($addr) {
        if (empty($addr[1])) return $this->secureHeader($addr[0]);
        return $this->encodeHeader($this->secureHeader($addr[1]), 'phrase') . ' <' . $this->secureHeader($addr[0]) . '>';
    }

    public function encodeHeader($str, $position = 'text') {
        if (!preg_match('/[\x80-\xFF]/', $str)) return $str;
        return '=?' . $this->CharSet . '?B?' . base64_encode($str) . '?=';
    }

    protected function secureHeader($str)  { return trim(str_replace(["\r","\n"], '', $str)); }
    protected function serverHostname()    {
        if (!empty($this->Hostname)) return $this->Hostname;
        if (isset($_SERVER['SERVER_NAME'])) return $_SERVER['SERVER_NAME'];
        return function_exists('gethostname') && gethostname() ? gethostname() : 'localhost.localdomain';
    }
    public static function rfcDate()       { return date('D, j M Y H:i:s O'); }
    protected function generateId()        { return bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16)); }
    protected function setError($msg)      { $this->error_count++; $this->ErrorInfo = $msg; }

    // ── Utilidades ─────────────────────────────────────────────
    public function addCustomHeader($name, $value = null) {
        if (null === $value && strpos($name, ':') !== false) [$name, $value] = explode(':', $name, 2);
        $this->CustomHeader[] = [trim($name), trim($value ?? '')];
        return true;
    }
    public function clearAddresses()     { foreach ($this->to  as $t) unset($this->all_recipients[strtolower($t[0])]); $this->to  = []; }
    public function clearCCs()           { foreach ($this->cc  as $c) unset($this->all_recipients[strtolower($c[0])]); $this->cc  = []; }
    public function clearBCCs()          { foreach ($this->bcc as $b) unset($this->all_recipients[strtolower($b[0])]); $this->bcc = []; }
    public function clearReplyTos()      { $this->ReplyTo = []; }
    public function clearAllRecipients() { $this->to = []; $this->cc = []; $this->bcc = []; $this->all_recipients = []; }
    public function clearAttachments()   { $this->attachment = []; }
    public function clearCustomHeaders() { $this->CustomHeader = []; }
    public function isError()            { return $this->error_count > 0; }
    public function getLastMessageID()   { return $this->lastMessageID; }
    public function getToAddresses()     { return $this->to; }
    public function getSMTPInstance()    { return $this->smtp; }
}
