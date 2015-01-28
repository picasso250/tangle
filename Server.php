<?php

namespace Tangle;

class Server
{
    public $num = 8;
    public $address = '0.0.0.0';
    public $port = 8089;

    public function start()
    {

        /* Turn on implicit output flushing so we see what we're getting
         * as it comes in. */
        ob_implicit_flush();

        if (($sock = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            $this->error = \socket_last_error();
            return false;
        }
        if (\socket_set_nonblock($sock) === false) {
            $this->error = \socket_last_error();
            return false;
        }
        if (\socket_bind($sock, $this->address, $this->port) === false) {
            $this->error = \socket_last_error();
            return false;
        }
        if (\socket_listen($sock, 5) === false) {
            $this->error = \socket_last_error();
            return false;
        }
        pcntl_signal(SIGINT, function ($signo) use($sock) {
            echo "u ctrl+c";
            \socket_close($sock);
            exit();
        });
        do {
            pcntl_signal_dispatch();
            $arr = [$sock];
            if (!\socket_select($arr, $arr, $arr, 0)) {
                continue;
            }
            if (($msgsock = \socket_accept($sock)) === false) {
                echo "\socket_accept() failed: reason: " . \socket_strerror(\socket_last_error($sock)) . "\n";
                break;
            }
            echo "a client connect\n";
            $this->raise('connect', $msgsock);

            do {
                if (false === ($buf = \socket_read($msgsock, 2048, PHP_NORMAL_READ))) {
                    echo "\socket_read() failed: reason: " . \socket_strerror(\socket_last_error($msgsock)) . "\n";
                    break 2;
                }
                $action = $this->raise('receive', $msgsock, $buf);
                if ($action === false) {
                    break;
                } elseif ($action === true) {
                    continue;
                } elseif ($action) {
                    eval($action);
                }
            } while (true);
            \socket_close($msgsock);
        } while (true);

        \socket_close($sock);
    }
    private $events;
    public function raise($event)
    {
        if (isset($this->events[$event])) {
            $args = func_get_args();
            array_shift($args);
            return call_user_func_array($this->events[$event], $args);
        }
    }
    public function on($event, $callback)
    {
        $this->events[$event] = $callback;
    }
}
