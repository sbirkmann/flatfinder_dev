<?php
$lat=50.3766421; $lon=7.7489434; $rad=10000;
$tag='["shop"~"supermarket|convenience"]';
$query = "[out:json][timeout:25];(nwr{$tag}(around:{$rad},{$lat},{$lon}););out center;";
echo $query . "\n";
$res = file_get_contents('https://overpass-api.de/api/interpreter', false, stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => 'data='.urlencode($query)]]));
echo substr($res, 0, 200)."\n";
