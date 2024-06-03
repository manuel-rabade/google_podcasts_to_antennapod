<?php

// argumentos cli
$html_file = $argv[1]; // archivo html del podcast que vamos a migrar
$rss_url = $argv[2]; // url del feed rss del podcast

// configuracion global
libxml_use_internal_errors(true);

// datos globales
$episodes = [ ]; // aqui se guarda el nombre de los episodios que ya escuche

// ------------------------------------------------------------------------------------------------
// flujo principal:
//
// 1 - agregar dispositivo y feed
// 2 - parsear el html del podcast para obtener los titulos de los episodios escuchados
// 3 - parsear el rss y buscar episodios escuchados
//   3.1 - normalizar informacion de episodios escuchados
//   3.2 - marcar episodio como escuchado

echo 'HTML: ' . $html_file . PHP_EOL;
echo 'RSS: ' . $rss_url . PHP_EOL;

// actualizamos dispositivo en opodsync
if (!update_device()) {
    echo 'Error al actualizar dispositivo' . PHP_EOL;
    exit;
}

// agregamos feed rss a opodsync
if (!add_feed($rss_url)) {
    echo 'Error al agregar feed: ' . $rss_url . PHP_EOL;
    exit;
}

// parseamos el html del podcast
$html = file_get_contents($html_file);
$doc = new DOMDocument();
$doc->loadHTML(@mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($doc);
$listitems = $xpath->query("//a[@role='listitem']"); // cada episodio esta pintando en un <a role='listitem'...
foreach ($listitems as $i) {
    $c = $xpath->query(".//span[contains(text(), 'Completed')]", $i); // si ya escuche el episodio, el listitem tiene un <span>Completed</span>
    if (count($c) > 0) {
        $data = $xpath->query(".//div[@role='presentation']", $i); // la data del episodio esta en varios <div role='presentation'... dentro del listitem
        $episodes[] = trim($data[1]->nodeValue); // el titulo del episodio
    }
}
echo 'Episodios escuchados: ' . count($episodes) . PHP_EOL;

// parseamos el rss y vamos haciendo match con los titulos de los episodios escuchados
$rss = simplexml_load_file($rss_url);
foreach ($rss->channel->item as $i) {
    $key = array_search(trim($i->title), $episodes); // buscamos episodio por titulo
    if ($key === false) {
        // si la busqueda por coincidencia exacta fallo, intentamos buscar por regexp eliminando el parentesis al final del titulo (changelog)
        $title = preg_replace('/\s*\([^)]+\)(?![^(]*\()/', '', $i->title);
        $matches = preg_grep ('/^'. preg_quote(trim($title), '/') . '/', $episodes);
        if (count($matches) > 1) {
            echo 'Multiples coincidencias por aproximacion: ' . $i->title . PHP_EOL;
            continue;
        } else if (count($matches) == 1) {
            $key = array_key_first($matches);
        }
    }
    if ($key !== false) {
        // obtenemos estrucutras intermedias de datos del episodio
        $enclosure = $i->enclosure->attributes();
        $itunes = $i->children('itunes', TRUE);
        // validamos los datos
        $data = filter_episode_data((string) $i->pubDate, (string) $enclosure['url'], (string) $itunes->duration);
        if ($data !== false) {
            // si los datos son validos subimos episodio a opodsync
            if (play_episode($rss_url, $data)) {
                // borramos episodio de memoria
                array_splice($episodes, $key, 1);
            } else {
                echo 'Error al marcar episodio como escuchado: ' . $i->title . PHP_EOL;
            }
        }
    }
}

// los episodios que quedaron en memoria son inconsistencias o tienen datos invalidos
echo 'Inconsistencias o errores: ' . count($episodes) . PHP_EOL;
if (count($episodes) > 0) {
    print_r($episodes);
}

// ------------------------------------------------------------------------------------------------
// filtro y normlizacion de datos del episodio

function filter_episode_data($date, $url, $duration) {
    $data = [ ];

    // validamos y formateamos fecha
    $time = strtotime($date);
    if ($time === false) {
        echo 'Fecha invalida: ' . $date . PHP_EOL;
        return false;
    }
    $data['timestamp'] = date(DATE_ISO8601, $time);

    // validamos url
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo 'URL invalida: ' . $url . PHP_EOL;
        return false;
    }
    $data['url'] = $url;

    // validamos y si es necesario convertimos duracion a segundos
    if (!ctype_digit($duration)) {
        $p = array_reverse(explode(':', $duration));
        if (preg_match('/^[0-9:]+$/', $duration) && (count($p) == 3 || count($p) == 2)) {
            $duration = $p[0];
            $duration += $p[1] * 60;
            $duration += isset($p[2]) ? $p[2] * 3600 : 0;
        } else {
            echo 'Duracion invalida: ' . $duration . PHP_EOL;
            return false;
        }
    }
    $data['seconds'] = $duration;

    //print_r($data);
    return $data;
}

// ------------------------------------------------------------------------------------------------
// marca episodio como escuchado en opodsync

function play_episode($rss, $data) {
    $url = getenv('OPODSYNC_URL') . '/api/2/episodes/' . getenv('OPODSYNC_USER') . '.json';
    $payload = json_encode([
        [
          'podcast' => $rss,
          'episode' => $data['url'],
          'device' => 'data_from_google_podcasts',
          'action' => 'play',
          'started' => 0,
          'position' => $data['seconds'],
          'total' =>  $data['seconds'],
          'timestamp' => $data['timestamp']
        ]
      ]);
    $status = opodsync_post($url, $payload);
    if ($status == 200) {
        return true;
    } else {
        return false;
    }
}

// ------------------------------------------------------------------------------------------------
// crea feed en opodsync

function add_feed($rss) {
    $url = getenv('OPODSYNC_URL') . '/api/2/subscriptions/' . getenv('OPODSYNC_USER') . '/data_from_google_podcasts.json';
    $payload = json_encode([ 'add' => [ $rss ] ]);
    $status = opodsync_post($url, $payload);
    if ($status == 200) {
        return true;
    } else {
        return false;
    }
}

// ------------------------------------------------------------------------------------------------
// actualiza dispositivo en opodsync

function update_device() {
    $url = getenv('OPODSYNC_URL') . '/api/2/devices/' . getenv('OPODSYNC_USER') . '/data_from_google_podcasts.json';
    $payload = json_encode([ "caption" => "Data from Google Podcasts", "type" => "other" ]);
    $status = opodsync_post($url, $payload);
    if ($status == 200) {
        return true;
    } else {
        return false;
    }
}

// ------------------------------------------------------------------------------------------------
// post a api de opodsync

function opodsync_post($url, $payload) {
    $request = curl_init();
    curl_setopt($request, CURLOPT_URL, $url);
    curl_setopt($request, CURLOPT_USERPWD, getenv('OPODSYNC_USER') . ':' . getenv('OPODSYNC_PASS'));
    curl_setopt($request, CURLOPT_POST, 1);
    curl_setopt($request, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);

    // debug
    file_put_contents('opodsync.log', "--------------------------------------------------------------------------------\n", FILE_APPEND);
    $data = sprintf("REQUEST: %s\n%s\nRESPONSE: %s\n%s\n\n", $url, $payload, $status, $response);
    file_put_contents('opodsync.log', $data, FILE_APPEND);

    return $status;
}

?>
