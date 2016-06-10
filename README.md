Redis write test using Symfony 3
================================

Run `composer install` first.

Run the `bin/console test:redis <message_count> <message_size> [process_count]` command to send `<message_count>`
messages to a Redis hash, each having `<message_size>` KB (default 10), using `[process_count]` parallel processes
(default 10).

The app will spawn the processes until it reaches the upper limit. Then it will check if all the data is saved in Redis
and compute the time spent from the first save, till the last one.

Example:

`bin/console test:redis 100 10 5`

will save 100 messages x 10KB each, using 5 processes.
