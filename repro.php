<?php

// Make one unary call to a port with nothing listening. That is enough to
// start ext-grpc's background threads, which are still alive at shutdown.

$channel = new Grpc\Channel('127.0.0.1:1', [
    'credentials' => Grpc\ChannelCredentials::createInsecure(),
]);
$deadline = Grpc\Timeval::now()->add(new Grpc\Timeval(100_000));
$call = new Grpc\Call($channel, '/repro.Service/Method', $deadline);

$event = $call->startBatch([
    Grpc\OP_SEND_INITIAL_METADATA => [],
    Grpc\OP_SEND_MESSAGE => ['message' => ''],
    Grpc\OP_SEND_CLOSE_FROM_CLIENT => true,
    Grpc\OP_RECV_INITIAL_METADATA => true,
    Grpc\OP_RECV_STATUS_ON_CLIENT => true,
]);

echo 'grpc ', phpversion('grpc'), ', call status ', $event->status->code, PHP_EOL;
