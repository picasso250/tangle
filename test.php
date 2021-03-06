<?php

require 'Server.php';

use Tangle\Server;

$s = new Server();
if (isset($argv[1])) {
    $s->address = $argv[1];
}
if (isset($argv[2])) {
    $s->port = intval($argv[2]);
}
echo "Listen on $s->address:$s->port\n";
$s->on('connect', function ($msgsock) {
    /* Send instructions. */
    $msg = "\nWelcome to the PHP Test Server. \n" .
        "To quit, type 'quit'. To shut down the server type 'shutdown'.\n";
    \socket_write($msgsock, $msg, strlen($msg));
});
$s->on('receive', function ($msgsock, $buf) {
    if (!$buf = trim($buf)) {
        return true;
    }
    if ($buf == 'quit') {
        return false;
    }
    $talkback = "PHP: You said '$buf'.\n";
    \socket_write($msgsock, $talkback, strlen($talkback));
    echo "$buf\n";
});
if ($s->start() === false) {
    echo "failed: reason: " . \socket_strerror($s->error) . "\n";
    exit(1);
}
