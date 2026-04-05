<?php
$overpassQuery = <<<EOT
[out:json][timeout:45];(
nw["shop"~"supermarket|convenience|bakery|clothes|shoes|boutique|hairdresser"](around:2000,50.3766421,7.7489434);
nw["amenity"~"school|kindergarten|college|restaurant|cafe|pharmacy|doctors|clinic|bank|atm|cinema|theatre|museum|library|fuel|parking|hospital"](around:2000,50.3766421,7.7489434);
nw["leisure"~"park|fitness_centre|sports_centre|playground"](around:2000,50.3766421,7.7489434);
nw["tourism"~"hotel|guest_house|motel"](around:2000,50.3766421,7.7489434);
nw["public_transport"="stop_position"](around:2000,50.3766421,7.7489434);
);out center;
EOT;

$res = file_get_contents('https://overpass-api.de/api/interpreter', false, stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'timeout' => 50, 'content' => 'data='.urlencode($overpassQuery)]]));
echo substr($res, 0, 500)."\n";
