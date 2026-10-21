<?php

/*
| Local development router for PHP's built-in server, used by .claude/launch.json ("api").
| Equivalent to `php artisan serve`, but lets you pass -d flags (e.g. extension=intl) to the
| process that actually handles requests. Never use in production.
|   php -d extension=intl -S 127.0.0.1:8000 -t backend/public backend/dev-server.php
*/

chdir(__DIR__.'/public');

return require __DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
