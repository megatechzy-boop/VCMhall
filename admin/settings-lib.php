<?php
declare(strict_types=1);

const TEMPLATE_VARIABLES = ['guest_name','event_type','event_date','booking_ref','amount','paid','balance','venue_name'];

function settings_schema_ready(PDO $db): bool
{
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') return (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN('settings','event_types')")->fetchColumn()===2;
    return (int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN('settings','event_types')")->fetchColumn()===2;
}
function settings_all(PDO $db): array { $out=[];foreach($db->query('SELECT setting_key,setting_value FROM settings')->fetchAll() as $row)$out[$row['setting_key']]=$row['setting_value'];return $out; }
function setting(PDO $db,string $key,string $default=''): string { $q=$db->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$q->execute([$key]);$value=$q->fetchColumn();return $value===false?$default:(string)$value; }
function setting_save(PDO $db,string $key,string $value,string $group): void {
    $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO settings(setting_key,setting_value,setting_group,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value,setting_group=excluded.setting_group,updated_at=CURRENT_TIMESTAMP':'INSERT INTO settings(setting_key,setting_value,setting_group) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_group=VALUES(setting_group)';
    $q=$db->prepare($sql);$q->execute([$key,$value,$group]);
}
function setting_bool(array $input,string $key): string { return isset($input[$key])?'1':'0'; }
function setting_url(string $value): string {
    $value=trim($value);if($value==='')return '';
    if(str_starts_with($value,'/')||str_starts_with($value,'#')||preg_match('/^[a-z0-9][a-z0-9._\/-]*(?:#[a-z0-9_-]+)?$/i',$value))return $value;
    if(filter_var($value,FILTER_VALIDATE_URL)&&in_array(parse_url($value,PHP_URL_SCHEME),['http','https'],true))return $value;
    throw new InvalidArgumentException('Enter a valid relative or HTTPS URL.');
}
function setting_template(string $value): string {
    preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/',$value,$matches);
    foreach($matches[1] as $variable)if(!in_array($variable,TEMPLATE_VARIABLES,true))throw new InvalidArgumentException('Unsupported template variable: {{'.$variable.'}}');
    if(preg_match('/\{\{[^}]*$/',$value))throw new InvalidArgumentException('Template contains an incomplete variable.');
    return $value;
}
function setting_upload(array $file,string $kind): ?string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||($file['size']??0)>5*1024*1024)throw new InvalidArgumentException('Image upload failed or exceeds 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($extensions[$mime]))throw new InvalidArgumentException('Only JPG, PNG and WebP images are allowed.');
    $directory=dirname(__DIR__).'/assets/popups';if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new RuntimeException('Upload directory could not be created.');
    $name=$kind.'-'.bin2hex(random_bytes(10)).'.'.$extensions[$mime];$target=$directory.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$target))throw new RuntimeException('Image could not be saved.');
    return 'assets/popups/'.$name;
}
