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
        $null = null;
        $pool = [];
        do {
            pcntl_signal_dispatch();
            $arr = [$sock];
            $select = \socket_select($arr, $null, $null, 0);
            if ($select === false) {
                echo "\socket_select() failed: reason: " . \socket_strerror(\socket_last_error($sock)) . "\n";
                $this->error = \socket_last_error();
                \socket_close($sock);
                return false;
            }
            if ($select === 0) {
                echo "\r run ".time();
                usleep(100);
                continue;
            }
            if (($msgsock = \socket_accept($sock)) === false) {
                echo "\socket_accept() failed: reason: " . \socket_strerror(\socket_last_error($sock)) . "\n";
                break;
            }
            if (\socket_set_nonblock($msgsock) === false) {
                $this->error = \socket_last_error();
                \socket_close($msgsock);
                \socket_close($sock);
                return false;
            }
            echo "a client connect\n";
            $this->raise('connect', $msgsock);
            $pool[] = $msgsock;

            do {
                pcntl_signal_dispatch();
                $left = $pool;
                echo "before select "; var_dump($left);
                $select = \socket_select($left, $null, $null, 0);
                echo " after select "; var_dump($left);
                if ($select === false) {
                    echo "\socket_select() failed: reason: " . \socket_strerror(\socket_last_error($msgsock)) . "\n";
                    $this->error = \socket_last_error();
                    $this->closePool($pool);
                    \socket_close($sock);
                    return false;
                }
                if ($select === 0) {
                    echo "\r inner run ".time();
                    usleep(100);
                    continue;
                }
                foreach ($left as $msgsock) {
                    echo "$select selected\n";
                    if (false === (\socket_recv($msgsock, $buf, 2048, MSG_DONTWAIT))) {
                        echo "\socket_recv() failed: reason: " . \socket_strerror(\socket_last_error($msgsock)) . "\n";
                        break 2;
                    }
                    $action = $this->raise('receive', $msgsock, $buf);
                    if ($action === false) {
                        break;
                    } elseif ($action === true) {
                        continue;
                    }
                }
            } while (true);
            \socket_close($msgsock);
        } while (true);
        $this->closePool($pool);
    }
    private function closePool($pool)
    {
        foreach ($pool as $s) {
            \socket_close($s);
        }
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
