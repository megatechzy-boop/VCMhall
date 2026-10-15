<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');

function booking_root_dir(): string
{
    return is_file(__DIR__ . '/booking-config.php') ? __DIR__ : dirname(__DIR__);
}

function booking_config(): array
{
    $file = booking_root_dir() . '/booking-config.php';
    if (!is_file($file)) {
        throw new RuntimeException('Booking configuration is missing.');
    }
    $config = require $file;
    if (!is_array($config)) {
        throw new RuntimeException('Booking configuration is invalid.');
    }
    return $config;
}

function booking_db(): PDO
{
    $fullConfig = booking_config();
    $sqlite = $fullConfig['sqlite'] ?? null;
    if (is_array($sqlite) && is_string($sqlite['path'] ?? null) && $sqlite['path'] !== '') {
        $newDatabase = !is_file($sqlite['path']);
        $db = new PDO('sqlite:' . $sqlite['path'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys = ON');
        if ($newDatabase) {
            $schema = file_get_contents(booking_root_dir() . '/schema-sqlite.sql');
            if ($schema === false) throw new RuntimeException('Local SQLite schema is missing.');
            $db->exec($schema);
        }
        return $db;
    }
    $config = $fullConfig['mysql'] ?? [];
    $host = $config['host'] ?? '';
    $port = (int) ($config['port'] ?? 3306);
    $name = $config['name'] ?? '';
    $user = $config['user'] ?? '';
    $password = $config['password'] ?? '';
    if (!is_string($host) || $host === '' || !is_string($name) || $name === '' || !is_string($user) || $user === ''
        || !is_string($password) || $port < 1 || $port > 65535) {
        throw new RuntimeException('MySQL booking configuration is incomplete.');
    }
    $db = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $db->exec("SET time_zone = '+05:30'");
    return $db;
}

function booking_setting(PDO $db, string $key, string $default = ''): string
{
    try { $query=$db->prepare('SELECT setting_value FROM settings WHERE setting_key=?'); $query->execute([$key]); $value=$query->fetchColumn(); return $value===false?$default:(string)$value; }
    catch (Throwable $error) { return $default; }
}
function booking_setting_save(PDO $db, string $key, string $value, string $group): void
{
    $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
        ? 'INSERT INTO settings(setting_key,setting_value,setting_group,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value,setting_group=excluded.setting_group,updated_at=CURRENT_TIMESTAMP'
        : 'INSERT INTO settings(setting_key,setting_value,setting_group) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_group=VALUES(setting_group)';
    $query=$db->prepare($sql);$query->execute([$key,$value,$group]);
}
function booking_email_crypto_key(array $config): string
{
    $material=(string)($config['admin_password_hash']??'');
    if(strlen($material)<40||!function_exists('openssl_encrypt'))throw new RuntimeException('Secure email-key storage is unavailable.');
    return hash('sha256','VCMhall Resend secret v1|'.$material,true);
}
function booking_encrypt_resend_key(string $apiKey,array $config): string
{
    $nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt($apiKey,'aes-256-gcm',booking_email_crypto_key($config),OPENSSL_RAW_DATA,$nonce,$tag);
    if($cipher===false)throw new RuntimeException('The Resend key could not be secured.');
    return 'enc:v1:'.base64_encode($nonce.$tag.$cipher);
}
function booking_decrypt_secret(string $stored,array $config): string
{
    if(!str_starts_with($stored,'enc:v1:'))return '';
    $raw=base64_decode(substr($stored,7),true);if($raw===false||strlen($raw)<29)return '';
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',booking_email_crypto_key($config),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
    return is_string($plain)?$plain:'';
}
function booking_whatsapp_token(?PDO $db,array $config): string
{
    if(!$db)return '';
    return booking_decrypt_secret(booking_setting($db,'whatsapp_access_token_enc'),$config);
}
function booking_resend_key(PDO $db,array $config): string
{
    return booking_decrypt_secret(booking_setting($db,'resend_api_key_enc'),$config);
}
function booking_send_email_message(PDO $db,array $config,string $to,string $subject,string $body,string $replyOverride = ''): array
{
    $from=booking_setting($db,'email_sender_email',(string)(($config['email_from']??'')?:'bookings@venutaihall.com'));
    $fromName=trim(preg_replace('/[\\r\\n]/','',booking_setting($db,'email_sender_name',(string)($config['email_from_name']??'Late Venutai Chavan Multipurpose Hall'))));
    $reply=filter_var($replyOverride,FILTER_VALIDATE_EMAIL)?$replyOverride:booking_setting($db,'email_reply_to',(string)($config['email_reply_to']??''));
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL))return ['sent'=>false,'provider'=>'','message'=>'A valid recipient and sender email are required.'];
    $apiKey=booking_resend_key($db,$config);
    if($apiKey!==''){
        $payload=['from'=>($fromName!==''?$fromName.' ':'').'<'.$from.'>','to'=>[$to],'subject'=>$subject,'text'=>$body];
        if(filter_var($reply,FILTER_VALIDATE_EMAIL))$payload['reply_to']=$reply;
        $context=stream_context_create(['http'=>['method'=>'POST','header'=>"Authorization: Bearer {$apiKey}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",'content'=>json_encode($payload,JSON_THROW_ON_ERROR),'timeout'=>20,'ignore_errors'=>true]]);
        $response=@file_get_contents('https://api.resend.com/emails',false,$context);$status=0;
        foreach(($http_response_header??[]) as $header)if(preg_match('/^HTTP\/\S+\s+(\d{3})/i',$header,$match))$status=(int)$match[1];
        if($response!==false&&$status>=200&&$status<300){$decoded=json_decode($response,true)?:[];return ['sent'=>true,'provider'=>'resend','message'=>'Resend accepted the email.','id'=>(string)($decoded['id']??'')];}
        error_log('Resend email request failed with HTTP '.$status.'.');return ['sent'=>false,'provider'=>'resend','message'=>'Resend could not accept the email. Check the API key, sending permission, and verified sender domain.'];
    }
    $headers=['From: '.mb_encode_mimeheader($fromName,'UTF-8').' <'.$from.'>','Content-Type: text/plain; charset=UTF-8'];if(filter_var($reply,FILTER_VALIDATE_EMAIL))$headers[]='Reply-To: '.$reply;
    $sent=@mail($to,$subject,$body,$headers);return ['sent'=>$sent,'provider'=>'server','message'=>$sent?'Accepted by the website mail server.':'The website mail server could not send this email.'];
}

function booking_today(): string
{
    return (new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
}

function booking_valid_date(string $date): bool
{
    $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $value !== false && $value->format('Y-m-d') === $date && $date >= booking_today();
}

function booking_hall_for_guests(?int $guests): string
{
    return $guests !== null && $guests >= 1 && $guests <= 350 ? 'small' : 'big';
}

function booking_send_email(PDO $db, int $id, array $row): void
{
    $config=booking_config();$to=booking_setting($db,'venue_email','')?: (string)(($config['email_to']??'')?:'bookings@venutaihall.com');
    $lines=['Reference: '.$id,'Name: '.$row['name'],'Phone: '.$row['phone'],'Email: '.$row['email'],'Event: '.$row['event_type'],'Hall: '.ucfirst($row['hall']??'big').' Hall','Date: '.$row['event_date'],'Guests: '.($row['guests']??''),'Message: '.$row['message']];
    if(!empty($row['call_back_time']))$lines[]='Call back: '.$row['call_back_time'];if(!empty($row['event_time']))$lines[]='Event time: '.$row['event_time'];
    $subject='New venue enquiry #'.$id;$body="New venue enquiry\n\n".implode("\n",$lines)."\n\nConfirm or cancel this request in the admin panel. The date is not blocked until confirmation.";
    try{$result=booking_send_email_message($db,$config,$to,$subject,$body,(string)($row['email']??''));$sent=$result['sent'];}catch(Throwable $error){error_log('Venue email failed: '.$error->getMessage());$sent=false;$result=['provider'=>'','message'=>'Email sending failed.'];}
    $update=$db->prepare('UPDATE bookings SET email_state=? WHERE id=?');$update->execute([$sent?($result['provider']==='resend'?'accepted_by_resend':'accepted_by_mail_server'):'failed',$id]);
}
