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
        'Parte del {dia} de {Mes}: puertas cerradas, ventanas comprobadas, cocina despejada de raptores. Mañana, más. {azar:🦖|🔦|🚪} #VelociraptorAttack',
        'Se acaba el {Dia_semana} y sigo sin noticias de velociraptores. Llevamos {contador} días así. ¿Estrategia o están esperando? 🤔 #VelociraptorAttack',
        'Recordatorio nocturno: los velociraptores cazan en grupo y abren puertas. Hoy, {dia} de {Mes}, no lo han intentado. Seguimos. 🦖 #VelociraptorAttack',
        'Día {dia_del_anio} de {anio} cerrado sin incidentes jurásicos. Faltan {dias_restantes} para acabar el año. La vigilancia continúa. 👀 #VelociraptorAttack',
        '{Dia_semana}, {dia} de {Mes}. Ni un arañazo en la puerta, ni un chillido en el jardín. Buenas noches... de momento. {luna} #VelociraptorAttack',
    ],

    // Mensajes especiales para días concretos (formato MM-DD). Sustituyen a las plantillas.
    // Cada uno puede ser un texto o una lista de textos (se elige uno al azar).
    'especiales' => [
        // Festivos nacionales (fecha fija)
        '01-01' => 'Primer día de {anio} y ningún velociraptor ha aprovechado la resaca general. Buen comienzo. 🥂 #VelociraptorAttack',
        '01-06' => 'Los Reyes Magos han pasado y no han traído ningún velociraptor. Ni de juguete. Mejor así. 👑 #VelociraptorAttack',
        '05-01' => 'Día del Trabajo: hasta los velociraptores se han tomado el día libre. Un ataque menos. 🛠️ #VelociraptorAttack',
        '08-15' => 'Quince de agosto, medio país en la playa y ningún velociraptor en la arena. Verano tranquilo. 🏖️ #VelociraptorAttack',
        '10-12' => 'Fiesta Nacional sin rastro de velociraptores en el desfile. Seguimos vigilando. 🇪🇸 #VelociraptorAttack',
        '11-01' => 'Todos los Santos: hoy recordamos a los que no estuvieron alerta. Nosotros seguimos aquí. 🕯️ #VelociraptorAttack',
        '12-06' => 'Día de la Constitución. Artículo no escrito: todo ciudadano tiene derecho a vivir sin ataques de velociraptor. 📜 #VelociraptorAttack',
        '12-08' => 'Inmaculada y puente cumplido sin un solo velociraptor. Así da gusto. 🌉 #VelociraptorAttack',
        '12-25' => 'Navidad sin velociraptores bajo el árbol. El mejor regalo es seguir de una pieza. 🎁🦖 #VelociraptorAttack',

        // Comunitat Valenciana (fecha fija)
        '03-19' => 'Sant Josep: con tanta mascletà y tanta cremà, ningún velociraptor se ha atrevido a acercarse. Bona nit! 🔥 #VelociraptorAttack',
        '06-24' => 'Nit de Sant Joan: fogueres encendidas, buen remedio contra velociraptores. Ni uno a la vista. 🔥🌊 #VelociraptorAttack',
        '10-09' => 'Nou d\'Octubre, Dia de la Comunitat Valenciana. Ni un velociraptor en todo el territorio. Visca! 🦇 #VelociraptorAttack',

        // Otros días señalados
        '12-24' => 'Nochebuena y ni rastro de velociraptores. Que Papá Noel también vigile el tejado. 🎄🦖 #VelociraptorAttack',
        '12-24' => 'Nochebuena y ni rastro de velociraptores. Que Papá Noel también vigile el tejado. 🎄🦖 #VelociraptorAttack',
        '12-31' => '¡{anio} completado! {contador} días sin un solo ataque de velociraptor. A por el año que viene. 🎉 #VelociraptorAttack',
        '10-31' => 'Halloween: si ves un velociraptor esta noche, probablemente sea un disfraz. Probablemente. 🎃 #VelociraptorAttack',
        '10-21' => 'Hoy ha sido mi cumpleaños 🎂 así que si ha habido un ataque de velociraptor igual no me he enterado. Cuidado ahí fuera. #VelociraptorAttack',
    ],

    // Festivos móviles: días respecto al Domingo de Pascua (se calcula cada año).
    // '-2' = Viernes Santo (nacional), '+1' = Lunes de Pascua (Comunitat Valenciana).
    'especiales_pascua' => [
        '-2' => 'Viernes Santo tranquilo: procesiones sí, velociraptores no. 🕯️ #VelociraptorAttack',
        '0'  => 'Domingo de Pascua sin velociraptores. Los únicos huevos de hoy, los de la mona. 🥚 #VelociraptorAttack',
        '+1' => 'Lunes de Pascua: mona comida, cometa volada y cero velociraptores en el campo. 🪁 #VelociraptorAttack',
    ],
];
