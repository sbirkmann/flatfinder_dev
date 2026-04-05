<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = Illuminate\Http\Request::create('/api/ai/generate', 'POST', ['prompt' => 'Test']);
$res = app()->handle($req);
print_r($res->getContent());
echo "\nStatus code: " . $res->getStatusCode() . "\n";
