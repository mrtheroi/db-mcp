<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->tmp = sys_get_temp_dir().'/memry-hook-'.bin2hex(random_bytes(6));
    File::makeDirectory($this->tmp.'/bin', 0755, true);
    File::put($this->tmp.'/config.json', json_encode(['url' => 'https://memry.test', 'token' => 'secret-token']));
});

afterEach(function () {
    File::deleteDirectory($this->tmp);
});

function stubCurl(string $tmp, string $body = '', int $exitCode = 0): void
{
    $script = "#!/usr/bin/env bash\n"
        ."printf '%s\\n' \"\$@\" > '{$tmp}/curl-args'\n"
        ."cat > '{$tmp}/curl-stdin'\n"
        .'printf %s '.escapeshellarg($body)."\n"
        ."exit {$exitCode}\n";

    File::put($tmp.'/bin/curl', $script);
    chmod($tmp.'/bin/curl', 0755);
}

function gitRepo(string $path): string
{
    File::makeDirectory($path, 0755, true);
    Process::path($path)->run(['git', 'init', '-q'])->throw();

    return $path;
}

function runHook(string $tmp, ?string $stdinCwd, ?string $workingDir = null, ?string $config = null): ProcessResult
{
    return Process::path($workingDir ?? $tmp)
        ->env([
            'PATH' => $tmp.'/bin:'.getenv('PATH'),
            'MEMRY_CONFIG' => $config ?? $tmp.'/config.json',
        ])
        ->input(json_encode(['session_id' => 'abc', 'cwd' => $stdinCwd, 'source' => 'startup']))
        ->run([base_path('hooks/claude-code/session-start.sh')]);
}

test('it prints the protocol block and the context body using the git top-level as project', function () {
    $repo = gitRepo($this->tmp.'/MyProject');
    File::makeDirectory($repo.'/src/deep', 0755, true);
    stubCurl($this->tmp, "## Latest session\nDid things");

    $result = runHook($this->tmp, $repo.'/src/deep');

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toBe(<<<'TXT'
## memry memory (project: MyProject)
memry is available through the `db-memory` MCP tools, alongside Engram.
- Use get-memory with an id to read a memory from the context below in full, and search-memory to find older ones.
- Save decisions, bug fixes and discoveries with save-memory (project "MyProject", with a topic_key for evolving topics).
- Before ending the session, save a summary with session-summary (project "MyProject").

## Latest session
Did things
TXT."\n");
});

test('it falls back to the cwd basename outside a git repository', function () {
    $dir = $this->tmp.'/PlainFolder';
    File::makeDirectory($dir);
    stubCurl($this->tmp, 'body');

    $result = runHook($this->tmp, $dir);

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toStartWith("## memry memory (project: PlainFolder)\n");
});

test('it sends the token through stdin so it never appears in the process arguments', function () {
    $dir = $this->tmp.'/Project';
    File::makeDirectory($dir);
    stubCurl($this->tmp, 'body');

    runHook($this->tmp, $dir);

    $args = explode("\n", trim(File::get($this->tmp.'/curl-args')));

    expect(array_filter($args, fn (string $arg) => str_contains($arg, 'secret-token')))->toBeEmpty()
        ->and($args)->toContain('@-')
        ->and(trim(File::get($this->tmp.'/curl-stdin')))->toBe('Authorization: Bearer secret-token');
});

test('it requests the context with a timeout and an encoded project, without printing the token', function () {
    $dir = $this->tmp.'/My Project';
    File::makeDirectory($dir);
    stubCurl($this->tmp, 'body');

    $result = runHook($this->tmp, $dir);

    $args = explode("\n", trim(File::get($this->tmp.'/curl-args')));
    $maxTime = array_search('--max-time', $args, true);

    expect($result->exitCode())->toBe(0)
        ->and($maxTime)->not->toBeFalse()
        ->and($args[$maxTime + 1])->toBe('3')
        ->and($args)->toContain('https://memry.test/api/context?project=My%20Project')
        ->and($result->output())->not->toContain('secret-token');
});

test('it prints nothing and never calls curl without a usable config', function (?string $contents) {
    $config = $this->tmp.'/other-config.json';
    if ($contents !== null) {
        File::put($config, $contents);
    }
    stubCurl($this->tmp, 'body');

    $result = runHook($this->tmp, gitRepo($this->tmp.'/MyProject'), config: $config);

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toBe('')
        ->and(File::exists($this->tmp.'/curl-args'))->toBeFalse();
})->with([
    'missing file' => [null],
    'without token' => ['{"url": "https://memry.test"}'],
    'without url' => ['{"token": "secret-token"}'],
    'invalid json' => ['not json'],
]);

test('it prints nothing and exits zero when the request fails', function (int $curlExitCode) {
    stubCurl($this->tmp, 'partial', $curlExitCode);

    $result = runHook($this->tmp, gitRepo($this->tmp.'/MyProject'));

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toBe('');
})->with([
    'http error' => [22],
    'timeout' => [28],
]);

test('it uses the working directory when stdin has no cwd', function (?string $stdinCwd) {
    $dir = $this->tmp.'/FromPwd';
    File::makeDirectory($dir);
    stubCurl($this->tmp, 'body');

    $result = runHook($this->tmp, $stdinCwd, workingDir: $dir);

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toStartWith("## memry memory (project: FromPwd)\n");
})->with([
    'empty cwd' => [''],
    'null cwd' => [null],
]);
