<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servidor TCP del listener GPS (TK905)
    |--------------------------------------------------------------------------
    |
    | tcp_host / tcp_port son los que escucha el proceso `php artisan gps:listen`.
    | tcp_public_host es la IP/host PÚBLICO que se muestra al usuario para
    | configurar el dispositivo TK905 (comando adminip). En desarrollo cae a
    | tcp_host; en producción define GPS_TCP_PUBLIC_HOST con la IP pública real.
    |
    */

    'tcp_host'        => env('GPS_TCP_HOST', '0.0.0.0'),
    'tcp_port'        => env('GPS_TCP_PORT', 5001),
    'tcp_public_host' => env('GPS_TCP_PUBLIC_HOST', env('GPS_TCP_HOST', '0.0.0.0')),

];
