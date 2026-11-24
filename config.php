<?php
/**
 * Configuración del bot (sin datos sensibles: se puede subir a GitHub).
 * Las claves de la API de X van en el archivo .env (ver .env.example).
 */
return [

    // --- Cuándo publicar ---
    // Hora exacta (HH:MM) en la zona horaria indicada.
    // El cron se programa CADA HORA en ese mismo minuto (p. ej. 59 * * * *) y el bot
    // solo publica cuando en esta zona horaria es la hora indicada. Así funciona aunque
    // el servidor esté en UTC y con el cambio de horario. Ejecuta `php bot.php --cron`
    // para ver la línea exacta que debes poner en SiteGround.
    //
    // ⏱ Cuánto tarda: el cron arranca en los primeros segundos del minuto y publicar
    // lleva 1-3 s, así que con '23:59' el tuit sale hacia las 23:59:05 (~50 s de margen).
    // Si X falla, los reintentos se cortan antes de las 00:00 para no publicar ya en el
    // día siguiente. Si quieres margen para reintentar, usa '23:58' o antes.
    'timezone'         => 'Europe/Madrid',
    'hora_publicacion' => '23:55',

    // Día (MM-DD) en que {contador} vuelve a 1 cada año. '01-01' = cada 1 de enero.
    'inicio_anual' => '01-01',

    // --- Mensajes ---
    // Se elige una plantilla al azar cada día. Pon solo una si quieres siempre la misma.
    'plantillas' => [
        '¡Uf! Ya termina el {dia} de {Mes}. Un día más sin ataque de velociraptor. ¡Siempre alerta! #VelociraptorAttack',
        'Día {contador} sin ataque de velociraptor. {Dia_semana} {dia} de {Mes} superado {azar:🦖|🦕|🦴}. Quedan {dias_restantes} días de {anio}... #VelociraptorAttack',
        'Cierre de jornada, {dia} de {Mes}: 0 velociraptores avistados. Luna de hoy: {luna}. ¡No bajéis la guardia! #VelociraptorAttack',
    ],

    // Mensajes especiales para días concretos (formato MM-DD). Sustituyen a las plantillas.
    'especiales' => [
        '12-24' => 'Nochebuena y ni rastro de velociraptores. Que Papá Noel también vigile el tejado. 🎄🦖 #VelociraptorAttack',
        '12-31' => '¡{anio} completado! {contador} días sin un solo ataque de velociraptor. A por el año que viene. 🎉 #VelociraptorAttack',
        '10-31' => 'Halloween: si ves un velociraptor esta noche, probablemente sea un disfraz. Probablemente. 🎃 #VelociraptorAttack',
    ],
];
