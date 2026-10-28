# ext-grpc shutdown segfault on ubuntu-26.04

A minimal reproduction of [grpc/grpc#42242](https://github.com/grpc/grpc/issues/42242) on GitHub Actions.

## The problem

A PHP process that has made a gRPC call segfaults at exit when it runs on `ubuntu-26.04`, with
ext-grpc from [shivammathur/setup-php](https://github.com/shivammathur/setup-php). The script's
own work succeeds. The crash happens after it finishes.

ext-grpc starts background threads that are still running after `grpc_shutdown()`. PHP then
calls `dlclose()` on `grpc.so`, and a thread that wakes up afterwards jumps into unmapped code.

This was found when `ubuntu-latest` moved to 26.04 and
[opentelemetry-php](https://github.com/open-telemetry/opentelemetry-php) CI began failing with
exit code 139 after its unit tests had passed.

## What the workflow runs

[`repro.php`](repro.php) makes one unary call to a port with nothing listening, then exits. The
workflow runs it 20 times in each combination of:

- runner: `ubuntu-24.04` and `ubuntu-26.04`
- PHP: 8.4 and 8.5
- unload: PHP's default, and `ZEND_DONT_UNLOAD_MODULES=1`, which skips `dlclose()`

A job fails if any run exits non-zero. A failed job then runs the script under gdb and prints
the faulting address and the thread backtraces.

## Results

TODO
