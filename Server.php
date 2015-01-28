<?php

namespace Tangle;

class Server
{
    public $num = 8;
    public $address = '0.0.0.0';
    public $port = 8089;

    private $pool = [];
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
            echo "u ctrl+c\n";
            \socket_close($sock);
            exit();
        });
        $null = null;
        $this->pool[] = $sock;
        do {
            pcntl_signal_dispatch();
            $left = $this->pool;
            $select = \socket_select($left, $null, $null, 0);
            if ($select === false) {
                echo "\socket_select() failed: reason: " . \socket_strerror(\socket_last_error($sock)) . "\n";
                $this->error = \socket_last_error();
                $this->closePool();
                return false;
            }
            if ($select === 0) {
                echo "\r run ".time();
                usleep(100);
                continue;
            }
            foreach ($left as $msgsock) {
                if ($msgsock === $sock) {
                    echo "main sock\n";
                    if (($msgsock = \socket_accept($sock)) === false) {
                        echo "\socket_accept() failed: reason: " . \socket_strerror(\socket_last_error($sock)) . "\n";
                        break;
                    }
                    if (\socket_set_nonblock($msgsock) === false) {
                        $this->error = \socket_last_error();
                        $this->closePool();
                        return false;
                    }
                    echo "a client connect\n";
                    $this->raise('connect', $msgsock);
                    $this->pool[] = $msgsock;
                } else {
                    echo "pool sock\n";
                    if (false === (\socket_recv($msgsock, $buf, 2048, MSG_DONTWAIT))) {
                        echo "\socket_recv() failed: reason: " . \socket_strerror(\socket_last_error($msgsock)) . "\n";
                        break 2;
                    }
                    $action = $this->raise('receive', $msgsock, $buf);
                    if ($action === false) {
                        $this->removeChild($msgsock);
                    }
                }
            }
        } while (true);
        $this->closePool($pool);
    }
    private function removeChild($socket)
    {
        reset($this->pool);
        while (($key = key($this->pool)) !== null) {
            if (current($this->pool) === $socket) {
                echo "delete\n";
                socket_close($socket);
                unset($this->pool[$key]);
                break;
            }
            next($this->pool);
        }
    }
    private function closePool()
    {
        foreach ($this->pool as $s) {
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
