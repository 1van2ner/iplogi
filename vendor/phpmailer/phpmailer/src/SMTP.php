<?php
namespace PHPMailer\PHPMailer;

class SMTP {
    const VERSION  = '6.9.1';
    const CRLF     = "\r\n";
    const DEFAULT_PORT = 25;
    const MAX_LINE_LENGTH = 998;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;

    public $Version    = '6.9.1';
    public $SMTP_CONN  = null;
    public $error      = ['error'=>'','detail'=>'','smtp_code'=>'','smtp_code_ex'=>''];
    public $helo_rply  = null;
    public $do_debug   = 0;
    public $Debugoutput = 'echo';
    public $do_verp    = false;
    public $Timeout    = 300;
    public $Timelimit  = 300;
    public $last_reply = '';
    protected $server_caps = null;

    public function connect($host, $port = null, $timeout = 30, $options = []) {
        $this->setError('');
        if ($this->connected()) $this->close();
        if (empty($port)) $port = self::DEFAULT_PORT;
        $errno = 0; $errstr = '';
        if (!empty($options)) {
            $ctx = stream_context_create(['ssl' => $options]);
            $connection = @stream_socket_client("$host:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        } else {
            $connection = @stream_socket_client("$host:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        }
        if (is_resource($connection)) {
            $this->SMTP_CONN = $connection;
            stream_set_timeout($this->SMTP_CONN, $timeout, 0);
            $this->get_lines(); // Read greeting
            return true;
        }
        $this->setError('Failed to connect to server', $errno, $errstr);
        return false;
    }

    public function startTLS() {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) return false;
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        return (bool)stream_socket_enable_crypto($this->SMTP_CONN, true, $crypto);
    }

    public function authenticate($username, $password, $authtype = 'LOGIN', $OAuth = null) {
        if (empty($authtype)) $authtype = 'LOGIN';
        switch ($authtype) {
            case 'PLAIN':
                if (!$this->sendCommand('AUTH PLAIN', 'AUTH PLAIN ' . base64_encode("\0$username\0$password"), 235)) return false;
                break;
            case 'LOGIN':
            default:
                if (!$this->sendCommand('AUTH LOGIN', 'AUTH LOGIN', 334)) return false;
                if (!$this->sendCommand('Username', base64_encode($username), 334)) return false;
                if (!$this->sendCommand('Password', base64_encode($password), 235)) return false;
                break;
        }
        return true;
    }

    public function connected() { return is_resource($this->SMTP_CONN) && !feof($this->SMTP_CONN); }

    public function close() {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply   = null;
        if (is_resource($this->SMTP_CONN)) {
            fclose($this->SMTP_CONN);
            $this->SMTP_CONN = null;
        }
    }

    public function data($msg_data) {
        if (!$this->sendCommand('DATA', 'DATA', 354)) return false;
        $lines = explode("\n", str_replace(["\r\n","\r"], "\n", $msg_data));
        foreach ($lines as $line) {
            if (!empty($line) && $line[0] === '.') $line = '.' . $line;
            $this->client_send($line . self::CRLF);
        }
        return $this->sendCommand('DATA END', '.', 250);
    }

    public function hello($host = '') {
        if ($this->sendCommand('EHLO', "EHLO $host", 250)) {
            $this->parseCapabilities($this->last_reply);
            return true;
        }
        return $this->sendCommand('HELO', "HELO $host", 250);
    }

    public function mail($from)         { return $this->sendCommand('MAIL FROM', "MAIL FROM:<$from>", 250); }
    public function recipient($address, $dsn = '') { return $this->sendCommand('RCPT TO', "RCPT TO:<$address>", [250,251]); }
    public function reset()             { return $this->sendCommand('RSET', 'RSET', 250); }
    public function noop()              { return $this->sendCommand('NOOP', 'NOOP', 250); }
    public function setVerp($enabled)   { $this->do_verp = $enabled; }
    public function setDebugLevel($level = 0)   { $this->do_debug = $level; }
    public function setDebugOutput($method = 'echo') { $this->Debugoutput = $method; }
    public function setTimeout($timeout = 0)     { $this->Timeout = $timeout; }
    public function setTimelimit($tl = 0)        { $this->Timelimit = $tl; }
    public function getError()                   { return $this->error; }
    public function getServerInfo()              { return $this->server_caps; }
    public function getServerExt($name)          { return isset($this->server_caps[$name]) ? $this->server_caps[$name] : null; }

    public function quit($close_on_error = true) {
        $ok = $this->sendCommand('QUIT', 'QUIT', 221);
        if ($ok || $close_on_error) $this->close();
        return $ok;
    }

    public function client_send($data, $command = '') {
        if (is_resource($this->SMTP_CONN)) return fwrite($this->SMTP_CONN, $data);
        return false;
    }

    protected function sendCommand($command, $commandstring, $expect) {
        if (!$this->connected()) { $this->setError("Called $command without being connected"); return false; }
        $this->client_send($commandstring . self::CRLF);
        $this->last_reply = $this->get_lines();
        $matches = [];
        if (preg_match('/^(\d{3})[ -]/', $this->last_reply, $matches)) {
            $code = (int)$matches[1];
            if (!in_array($code, (array)$expect)) {
                $this->setError("$command command failed", '', $code);
                return false;
            }
        }
        return true;
    }

    protected function parseCapabilities($reply) {
        $this->server_caps = [];
        foreach (explode("\n", $reply) as $line) {
            if (preg_match('/^[0-9]{3}[ -]([A-Z0-9][A-Z0-9-]*)(?:[ =](.*))?$/', trim($line), $m)) {
                $this->server_caps[$m[1]] = isset($m[2]) ? explode(' ', $m[2]) : true;
            }
        }
    }

    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '') {
        $this->error = ['error'=>$message,'detail'=>$detail,'smtp_code'=>$smtp_code,'smtp_code_ex'=>$smtp_code_ex];
    }

    protected function get_lines() {
        if (!is_resource($this->SMTP_CONN)) return '';
        $data = '';
        stream_set_timeout($this->SMTP_CONN, $this->Timeout);
        while (is_resource($this->SMTP_CONN) && !feof($this->SMTP_CONN)) {
            $str = @fgets($this->SMTP_CONN, 515);
            if ($str === false) break;
            $data .= $str;
            if (isset($str[3]) && ($str[3] === ' ' || $str[3] === "\r" || $str[3] === "\n")) break;
            $info = stream_get_meta_data($this->SMTP_CONN);
            if ($info['timed_out']) break;
        }
        return $data;
    }

    public function errorHandler($errno, $errmsg, $errfile = '', $errline = 0) {
        $this->setError('Connection failed.', $errno, $errmsg);
    }
}
