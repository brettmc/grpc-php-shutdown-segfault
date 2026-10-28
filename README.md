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

setup-php does not install ext-grpc from a package or from PECL on a hosted runner. It unpacks a
prebuilt PHP tarball from [shivammathur/php-ubuntu](https://github.com/shivammathur/php-ubuntu),
built by [shivammathur/php-builder](https://github.com/shivammathur/php-builder), and `grpc.so`
is inside it. The Provenance step shows the tarball, whether `grpc.so` was linked with
`-z nodelete`, and the compiler flags recorded in its debug info from the matching `-dbgsym`
tarball.

A job fails if any run exits non-zero. A failed job then opens one of the core files in gdb and
prints the faulting address, whether `grpc.so` is still loaded and the thread backtraces. A core
file is used because the crash did not happen in 20 runs under gdb.

## Results

From [run 40630867554](https://github.com/brettmc/grpc-php-shutdown-segfault/actions/runs/40630867554),
crashes out of 20 runs:

| runner         | PHP | default unload | `ZEND_DONT_UNLOAD_MODULES=1` |
|----------------|-----|----------------|------------------------------|
| `ubuntu-24.04` | 8.4 | 0              | 0                            |
| `ubuntu-24.04` | 8.5 | 0              | 0                            |
| `ubuntu-26.04` | 8.4 | 0              | 0                            |
| `ubuntu-26.04` | 8.5 | 16             | 0                            |

Every job had PHP 8.4.27 or 8.5.12 and ext-grpc 1.84.0. The `grpc.so` binaries are not the
same, though. It is 16664912 bytes on `ubuntu-26.04` and 16128336 bytes on `ubuntu-24.04`, so
the 26.04 runner gets a different build of the same version.

Skipping `dlclose()` stops the crash.

[Run 40633681019](https://github.com/brettmc/grpc-php-shutdown-segfault/actions/runs/40633681019/job/122635641154)
crashed 18 times out of 20 on `ubuntu-26.04` with PHP 8.5, and kept every core file. In the one
gdb opened:

- the main thread is in `_dl_close()`, inside `munmap()`
- `grpc.so` is no longer in the loaded libraries
- the faulting thread's program counter is in no mapped library

The core file names record the faulting thread. It was `event_engine` in 17 cores and
`grpc_global_timer` in one. Both are ext-grpc threads.

This fits the unload race described in
[grpc/grpc#42242](https://github.com/grpc/grpc/issues/42242).
