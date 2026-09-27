<?php
// Execute with PHP CLI. Never migrates or writes the research database.
require dirname(__DIR__).'/vendor/autoload.php';
$root = dirname(__DIR__);
$out = $root.'/storage/app/private/thesis-evidence';
if (!is_dir($out)) mkdir($out, 0770, true);
$environment = array_merge(getenv(), [
    'APP_ENV'=>'testing', 'APP_CONFIG_CACHE'=>$out.'/nonexistent-config.php',
    'DB_CONNECTION'=>'sqlite', 'DB_DATABASE'=>':memory:', 'DB_URL'=>'',
    'CACHE_STORE'=>'array', 'SESSION_DRIVER'=>'array', 'MAIL_MAILER'=>'array',
    'QUEUE_CONNECTION'=>'sync', 'BCRYPT_ROUNDS'=>'4',
]);
foreach ($environment as $key=>$value) putenv($key.'='.$value);
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$verification = $app->make(App\Services\ThesisEvidenceVerification::class);
$before = $verification->fingerprint();
$started = gmdate('c');
if (is_file($out.'/junit.xml')) unlink($out.'/junit.xml');
$command = [PHP_BINARY, $root.'/vendor/phpunit/phpunit/phpunit', '--configuration', $root.'/phpunit.xml', '--do-not-cache-result', '--log-junit', $out.'/junit.xml', '--testdox'];
$process = proc_open($command, [0=>['pipe','r'], 1=>['file',$out.'/tests.log','w'], 2=>['file',$out.'/tests-errors.log','w']], $pipes, $root, $environment);
if (!is_resource($process)) throw new RuntimeException('Could not start PHPUnit.');
fclose($pipes[0]);
$exit = proc_close($process);
$tests = [];
if (is_file($out.'/junit.xml')) {
    $xml = simplexml_load_file($out.'/junit.xml', SimpleXMLElement::class, LIBXML_NONET);
    foreach ($xml->xpath('//testcase') as $case) {
        $name = (string)$case['class'].'::'.(string)$case['name'];
        $status = isset($case->failure) || isset($case->error) ? 'FAIL' : (isset($case->skipped) ? 'UNTESTED' : 'PASS');
        $tests[$name] = ['status'=>$status, 'assertions'=>(int)$case['assertions']];
    }
}
$unchanged = $before === $verification->fingerprint();
$run = ['generated_at'=>gmdate('c'), 'started_at'=>$started, 'source_sha256'=>$before, 'source_unchanged_during_run'=>$unchanged,
    'environment'=>'testing; SQLite :memory:; mail=array', 'command'=>'php scripts/verify-thesis-evidence.php', 'exit_code'=>$exit,
    'status'=>$exit === 0 && $unchanged && $tests ? 'PASS' : 'FAIL', 'reason'=>'Isolated automated assertions; not live research observations or browser visual certification.',
    'total'=>count($tests), 'passed'=>count(array_filter($tests, fn ($r) => $r['status']==='PASS')),
    'failed'=>count(array_filter($tests, fn ($r) => $r['status']==='FAIL')), 'skipped'=>count(array_filter($tests, fn ($r) => $r['status']==='UNTESTED')), 'tests'=>$tests];
if (!$unchanged) $run['source_sha256'] = 'SOURCE_CHANGED_DURING_RUN';
file_put_contents($verification->path(), json_encode($run, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(array_diff_key($run, ['tests'=>true]), JSON_PRETTY_PRINT).PHP_EOL;
exit($exit === 0 && $unchanged && $tests ? 0 : 1);
