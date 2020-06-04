#!/usr/bin/php
<?php
$conf_file = 'render.conf';
`./mapcrafter/mapcrafter_markers -c $conf_file`;
$conf = parse_ini_file($conf_file, false, INI_SCANNER_RAW);

$mappath = $conf['input_dir'];
$mapname = basename($mappath);
$outputdir = $conf['output_dir'];
$serverpath = 'server';
$cachetime = 60*60*24;

$output = array(
	'overworld' => array(),
	'nether' => array(),
	'end' => array()
);

if(!is_dir($outputdir . "/static/markers/players")) {
	mkdir($outputdir . "/static/markers/players");
}

$players = json_decode(file_get_contents($serverpath . "/whitelist.json"));
$playerfiles = glob($mappath . "/playerdata/*.dat");

foreach($players as $player) {
	$uuid = $player->uuid;
	
	$playerfile = $mappath . "/playerdata/" . $uuid . ".dat";
	if(!in_array($playerfile, $playerfiles)) {
		continue;
	}
	
	$pos = explode(",", trim(`python playerpos.py $playerfile`));
	if(count($pos) != 4) {
		continue;
	}
	
	$dim = null;
	switch($pos[3]) {
		case 0:
			$dim = 'overworld';
			break;
		case -1:
			$dim = 'nether';
			break;
		case 1:
			$dim = 'end';
			break;
	}
	if($dim == null) {
		continue;
	}
	
	$uuid_nodash = str_ireplace('-', '', $uuid);
	$playerdata = file_get_contents("https://sessionserver.mojang.com/session/minecraft/profile/" . $uuid_nodash);
	if($playerdata === false) {
		continue;
	}
	$playerdata = json_decode($playerdata);
	$name = $playerdata->name;
	
	$output[$dim][] = array(
		"pos" => array((int)$pos[0], (int)$pos[2], (int)$pos[1]),
		"name" => $name
	);
	
	$playerimg = $outputdir . "/static/markers/players/" . $name . ".png";
	if(file_exists($playerimg) && filemtime($playerimg) > time() - $cachetime) {
		continue;
	}
	
	$properties = json_decode(base64_decode($playerdata->properties[0]->value));
	$src = @imagecreatefrompng($properties->textures->SKIN->url);
	if(!$src) {
		if(!file_exists($playerimg)) {
			copy($outputdir . "/static/markers/player.png", $playerimg);
		}
		
		continue;
	}

	$img = imagecreatetruecolor(16, 32);
	imagealphablending($img, false);
	imagesavealpha($img, true);

	imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 0, 255, 127));

	imagecopy($img, $src, 4, 0, 8, 8, 8, 8);                      //Head
	imagecopy($img, $src, 4, 8, 20, 20, 8, 12);                   //Body
	imagecopy($img, $src, 0, 8, 44, 20, 4, 12);                   //Arm-L
	imagecopyresampled($img, $src, 12, 8, 47, 20, 4, 12, -4, 12); //Arm-R
	imagecopy($img, $src, 4, 20, 4, 20, 4, 12);                   //Leg-L
	imagecopyresampled($img, $src, 8, 20, 7, 20, 4, 12, -4, 12);  //Leg-R

	// Enable alpha blending so hat blends with face.
	imagealphablending($img, true);
	imagecopy($img, $src,   4, 0, 40, 8, 8, 8);    //Hat

	$img_big = imagecreatetruecolor(16, 32);
	imagealphablending($img_big, false);
	imagesavealpha($img_big, true);
	imagecopyresampled($img_big, $img, 0, 0, 0, 0, 16, 32, 16, 32);
	imagepng($img_big, $playerimg);
}

$output_overworld = json_encode($output['overworld']);
$output_nether = json_encode($output['nether']);
$output_end = json_encode($output['end']);

$output = <<<EOF
var MAPCRAFTER_MARKERS = [
	{
		"id": "spawn",
		"name": "Spawn",
		"icon": "home.png",
		"iconSize": [32, 32],
		"markers": {
			"$mapname": [
				{"pos": [183, -142, 64], "title" : "Spawn<br>(183, -142, 64)"},
			],
		},
	},
	{
		"id": "players",
		"name": "Players",
		"createMarker": function(ui, groupInfo, markerInfo) {
			var latlng = ui.mcToLatLng(markerInfo.pos[0], markerInfo.pos[1], markerInfo.pos[2]);
			var options = {
				"icon": L.icon({
					"iconUrl": "static/markers/players/" + markerInfo.name + ".png",
					"iconSize": [16, 32],
					"iconAnchor": [8, 32],
					"popupAnchor": [0, -32],
				}),
				"title": markerInfo.name,
			};
			var marker = L.marker(latlng, options);
			marker.bindPopup(markerInfo.name).openPopup();
			return marker;
		},
		"markers": {
			"$mapname": $output_overworld,
			"{$mapname}_nether": $output_nether,
			"{$mapname}_end": $output_end, 
		},
	},
];

EOF;

file_put_contents($outputdir . "/markers.js", $output);
