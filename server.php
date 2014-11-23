<?php
include 'vendor/autoload.php';

$conns = new \SplObjectStorage();

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);

$socket->on('connection', function ($conn) use ($conns) {
    $conns->attach($conn);
    $conn->on('data', function ($data) use ($conns, $conn) {
        //list($request) = (new React\Http\RequestHeaderParser())->parseRequest($data);
        //$query = $request->getQuery();
        $query = array();
        $query['count'] = 0;
        $query['time'] = time();
        $conns->attach($conn, $query);
    });
    $conn->on('end', function () use ($conns, $conn) {
        $conns->detach($conn);
    });
});

$loop->addPeriodicTimer(1, function () use ($conns) {
    echo 'pool => ' . count($conns) . PHP_EOL;
    foreach ($conns as $conn) {
        $data = $conns->offsetGet($conn);
        if (is_null($data)) continue;
        $data['count']++;
        echo ' => ' . $data['count'] . PHP_EOL;
        $conns->attach($conn, $data);
    }
    foreach ($conns as $key => $value) {
        $data = $conns->offsetGet($conn);
        if ($data['count'] < 10)  continue;
        $conns->detach($conn);
        $buffer = "HTTP/1.1 200 OK\r\n";
        $buffer .= "Content-Head: application/json\r\n";
        $buffer .= "\r\n";
        $buffer .= json_encode(['data' => $data, 'spent' => time() - $data['time']]);
        $buffer .= "\r\n\r\n";
        $conn->write($buffer);
        $conn->end();
    }
});

$socket->listen(1337);
$loop->run();
