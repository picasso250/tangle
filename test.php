<?php

require 'Server.php';

use Tangle\Server;

$s = new Server();
$s->on('connect', function ($msgsock) {
	/* Send instructions. */
    $msg = "\nWelcome to the PHP Test Server. \n" .
        "To quit, type 'quit'. To shut down the server type 'shutdown'.\n";
    \socket_write($msgsock, $msg, strlen($msg));
});
if ($s->start() === false) {
	echo "failed: reason: " . \socket_strerror(\socket_last_error($sock)) . "\n";
	exit(1);
}
