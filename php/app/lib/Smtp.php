<?php
// Отправка писем через почтовый сервер (SMTP) без сторонних библиотек.
// Порт 465 — сразу защищённое соединение (Gmail, Яндекс, hoster.by), 587 — обычное, потом STARTTLS.
// Вход — AUTH LOGIN (ящик и пароль; для Gmail — «пароль приложения»).
//
//   (new Smtp($host, 465, $user, $pass))->send($from, 'Артфлора — заказы', ['a@b.by'], 'Тема', $html, $text);
final class Smtp
{
    /** @var resource|null */
    private $sock = null;

    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $pass,
        private int $timeout = 20,
    ) {}

    // $to — один или несколько адресов. $html может быть пустым: тогда письмо только текстом.
    public function send(string $from, string $fromName, array $to, string $subject, string $html, string $text): void
    {
        $to = array_values(array_filter(array_map('trim', $to)));
        if (!$to) throw new RuntimeException('не указан получатель');
        foreach ([$from, ...$to] as $addr) {
            if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) throw new RuntimeException("неверный адрес почты «{$addr}»");
        }
        try {
            $this->connect();
            $this->cmd('MAIL FROM:<' . $from . '>', 250);
            foreach ($to as $addr) $this->cmd('RCPT TO:<' . $addr . '>', [250, 251]);
            $this->cmd('DATA', 354);
            $this->write(self::dotStuff(self::message($from, $fromName, $to, $subject, $html, $text)) . "\r\n.");
            $this->expect(250);
            $this->cmd('QUIT', [221, 250]);
        } finally {
            if ($this->sock) fclose($this->sock);
            $this->sock = null;
        }
    }

    private function connect(): void
    {
        $secure = $this->port === 465;
        $ctx = stream_context_create(['ssl' => ['peer_name' => $this->host, 'verify_peer' => true, 'verify_peer_name' => true]]);
        $sock = @stream_socket_client(($secure ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) throw new RuntimeException("нет связи с почтовым сервером {$this->host}:{$this->port} ($errstr)");
        stream_set_timeout($sock, $this->timeout);
        $this->sock = $sock;
        $this->expect(220);
        $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
        $this->cmd($ehlo, 250);
        if (!$secure) {
            $this->cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('не удалось включить шифрование (STARTTLS)');
            }
            $this->cmd($ehlo, 250);
        }
        if ($this->user !== '') {
            // Ящик и пароль в тексте ошибки не показываем
            $this->cmd('AUTH LOGIN', 334, 'AUTH');
            $this->cmd(base64_encode($this->user), 334, 'AUTH');
            $this->cmd(base64_encode($this->pass), 235, 'AUTH');
        }
    }

    private function write(string $line): void
    {
        if (fwrite($this->sock, $line . "\r\n") === false) throw new RuntimeException('почтовый сервер оборвал связь');
    }

    // Ответ сервера: строки «250-…» продолжаются, «250 …» — последняя.
    private function expect(int|array $codes, string $what = ''): string
    {
        $reply = '';
        while (($line = fgets($this->sock, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        if ($reply === '') {
            $meta = stream_get_meta_data($this->sock);
            throw new RuntimeException($meta['timed_out'] ? 'почтовый сервер не отвечает' : 'почтовый сервер оборвал связь');
        }
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, (array) $codes, true)) {
            throw new RuntimeException(trim(($what !== '' ? "$what: " : '') . preg_replace('/\s+/', ' ', $reply)));
        }
        return $reply;
    }

    private function cmd(string $line, int|array $codes, ?string $shown = null): string
    {
        $this->write($line);
        return $this->expect($codes, $shown ?? strtok($line, ' '));
    }

    // Строка, начинающаяся с точки, в теле письма удваивается (иначе сервер примет её за конец письма).
    public static function dotStuff(string $body): string
    {
        return preg_replace('/^\./m', '..', $body);
    }

    // «=?UTF-8?B?…?=» для темы и имени отправителя
    public static function header(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    // Само письмо: заголовки и текст + HTML (multipart/alternative), всё в base64.
    public static function message(string $from, string $fromName, array $to, string $subject, string $html, string $text): string
    {
        $domain = substr(strrchr($from, '@'), 1) ?: 'localhost';
        $h = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::header($fromName) . " <$from>",
            'To: ' . implode(', ', $to),
            'Subject: ' . self::header($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domain>",
            'MIME-Version: 1.0',
        ];
        $part = fn (string $type, string $body) => "Content-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
        $text = str_replace(["\r\n", "\n"], ["\n", "\r\n"], $text);
        if ($html === '') return implode("\r\n", $h) . "\r\n" . $part('text/plain', $text);
        $b = 'b' . bin2hex(random_bytes(10));
        $h[] = "Content-Type: multipart/alternative; boundary=\"$b\"";
        return implode("\r\n", $h) . "\r\n\r\n"
            . "--$b\r\n" . $part('text/plain', $text) . "\r\n"
            . "--$b\r\n" . $part('text/html', $html) . "\r\n"
            . "--$b--";
    }
}
