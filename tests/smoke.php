<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use SPFPU\Core\Validation;
$assert=static function(bool $condition,string $message):void{if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}};
$assert(Validation::password('Passw123')===null,'valid password rejected');
$assert(Validation::password('password1')!==null,'uppercase requirement missing');
$assert(Validation::date('2024-02-29'),'leap date rejected');
$assert(!Validation::date('2025-02-29'),'invalid calendar date accepted');
echo "Smoke checks passed.\n";
