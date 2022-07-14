<?php
// Analyze Doom Legacy demos
// Copyright (C) 2021 by Frans P. de Vries

define('MAXPLAYERS', 32);
define('NUMWEAPONS', 9);

// tic flags
define('ZT_FWD', 0x01);
define('ZT_SIDE', 0x02);
define('ZT_ANGLE', 0x04);
define('ZT_BUTTONS', 0x08);
define('ZT_AIMING', 0x10);
define('ZT_CHAT', 0x20); // unused
define('ZT_EXTRADATA', 0x40);
// ExtraData types
define('XD_NAMEANDCOLOR', 0x01);
define('XD_WEAPONPREF', 0x02);
define('XD_EXIT', 0x03);
define('XD_QUIT', 0x04);
define('XD_KICK', 0x05);
define('XD_NETVAR', 0x06);
define('XD_SAY', 0x07);
define('XD_MAP', 0x08);
define('XD_EXITLEVEL', 0x09);
define('XD_LOADGAME', 0x0A);
define('XD_SAVEGAME', 0x0B);
define('XD_PAUSE', 0x0C);
define('XD_ADDPLAYER', 0x0D);
define('XD_ADDBOT', 0x0E);
define('XD_USEARTEFACT', 0x0F);

function lmpLegacy($fp, $debug = 0)
{
	$vers = readByte($fp);
	if ($vers < 129 || $vers > 144) {
		echo "unexpected Legacy version: $vers\n";
		return false;
	}
	$rver = $sver = $mply = 0;
	$mapn = '';

	// check for v1.44+ signature
	if ($vers == 144) {
		if (($sign = fread($fp, 2)) != 'DL') {
			echo "unexpected Legacy version $vers signature: $sign\n";
			return false;
		}
		if (($sign = readByte($fp)) != 1) {
			echo "unexpected Legacy version $vers format: $sign\n";
			return false;
		}
		// 0x04: version
		$rver = readByte($fp);
		// 0x05: rec_version == version
		$skip = readByte($fp);
		// 0x06: sub-version
		$sver = readByte($fp);
	}

	// 0x01-0x03 / 144: 0x07-0x09
	$skll = readByte($fp) + 1;
	$epis = readByte($fp);
	$miss = readByte($fp);
	// 0x04 / 0x0A: Play mode: 0 = Single/coop, 1 = DM, 2 = AltDeath
	$mode = readByte($fp);
	// 0x05-0x07 / 0x0B-0x0D
	$resp = readByte($fp);
	$fast = readByte($fp);
	$nomo = readByte($fp);
	// 0x08 / 0x0E: which player's point of view to use, zero-indexed (0 means player 1)
	$view = readByte($fp);
	// 0x09 / 0x0F: time limit v1.x-1.27
	$skip = readByte($fp);
	if ($vers >= 131) // v1.31+
		// 0x0A / 0x10: multiplayer
		$mply = readByte($fp);
	// 0x0A-29 / 131: 0x0B-0x2A / 144: 0x11-0x30 : player present?
	$players = array();
	for ($n = 0; $n < MAXPLAYERS; $n++)
		if (readByte($fp) > 0)
			$players[$n] = 1;
		else
			$players[$n] = 0;
	$ply1 = $players[0];
	$ply2 = $players[1];
	$ply3 = $players[2];
	$ply4 = $players[3];
	if ($ply1 == 0) $ply1 = 1;

	// skip v1.44+ settings
	if ($vers == 144) {
		// settings
		$skip = fread($fp, ($rver >= 147 ? 64 : 32));
		if (readByte($fp) != 0x55) {
			echo "missing Legacy version $vers sync mark: ".(ftell($fp)-1)."\n";
			return false;
		}
	}

	// tics data
	$tics = 0;
	while ($tic = fread($fp, 1)) {
		$tic = ord($tic[0]);
		if ($tic == DEMOEND) {
			debugLog(ftell($fp)-1, $debug, 1, 'DEMOEND');
			break;
		}
		// log ExtraData at lower debug level
		debugLog(ftell($fp)-1, $debug, ($tic & ZT_EXTRADATA ? 1 : 2), 'TIC', true);
		$data = sprintf('0x%02X', $tic);

		// parse tic flags
		if ($tic & ZT_FWD) {
			$skip = readByte($fp);
			if ($debug >= 2)
				$data .= ' F1';
		}
		if ($tic & ZT_SIDE) {
			$skip = readByte($fp);
			if ($debug >= 2)
				$data .= ' S1';
		}
		if ($tic & ZT_ANGLE) {
			if ($vers >= 125) { // v1.25+
				$skip = fread($fp, 2);
				if ($debug >= 2)
					$data .= ' A2';
			} else {
				$skip = readByte($fp);
				if ($debug >= 2)
					$data .= ' A1';
			}
		}
		if ($tic & ZT_BUTTONS) {
			$skip = readByte($fp);
			if ($debug >= 2)
				$data .= ' B1';
		}
		if ($tic & ZT_AIMING) {
			if ($vers >= 128) { // v1.28+
				$skip = fread($fp, 2);
				if ($debug >= 2)
					$data .= ' M2';
			} else {
				$skip = readByte($fp);
				if ($debug >= 2)
					$data .= ' M1';
			}
		}
		if ($tic & ZT_CHAT) {
			$skip = readByte($fp);
			if ($debug >= 2)
				$data .= ' C1';
		}

		if ($tic & ZT_EXTRADATA) {
			$xlen = readByte($fp);
			$data .= sprintf(' X %2d', $xlen);
			$xtra = fread($fp, $xlen);

			// parse ExtraData types
			$i = 0;
			while ($i < $xlen) {
				switch (ord($xtra[$i])) {

					case XD_NAMEANDCOLOR:
						// 1-byte color + player name + skin name
						$plyn = readString($xtra, $i+2);
						$skin = readString($xtra, $i+2+strlen($plyn)+1);
						$data .= " - $plyn - $skin";
						$i += 1 + strlen($plyn) + 1 + strlen($skin) + 1; // color, names
						break;

					case XD_WEAPONPREF:
						// weapon prefs
						$data .= ' - WeapPrefs';
						$i += 1 + NUMWEAPONS + 1; // original switch, priority, autoaim
						break;

					case XD_NETVAR:
						// netvar: 2-byte id + string
						$data .= ' - NetVar';
						$skip = readString($xtra, $i+3);
						$i += 2 + strlen($skip) + 1; // id, value
						break;

					case XD_SAY:
						// say: player num + text 
						$text = readString($xtra, $i+2);
						$data .= ' - Say '.ord($xtra[$i+1]).': '.$text;
						$i += 1 + strlen($text) + 1; // num, name
						break;

					case XD_MAP:
						// skill + nomonsters + map name
						$mapn = readString($xtra, $i+3);
						$data .= ' - '.$mapn;
						$i += 2 + strlen($mapn) + 1; // options, name
						break;

					case XD_ADDPLAYER:
						// options + map name
						$len = ($vers == 144 ? 5 : 10);
						$mapn = readString($xtra, $i+1+$len);
						$data .= ' - '.$mapn;
						$i += $len + strlen($mapn) + 1; // options, name
						break;

					case XD_USEARTEFACT:
						// use artifact: 1 byte
						$data .= ' - UseArtif';
						$i += 1; // artifact
						break;

					default:
						$data .= ' - Unsupported: '.ord($xtra[$i]);
						break;
				}
				$i++; // type
			}
		}

		// log ExtraData at lower debug level
		debugPar($debug, ($tic & ZT_EXTRADATA ? 1 : 2), $data);
		$tics++;
	}
	if (!($foot = fread($fp, 1024)))
		$foot = '';

	// extract from map name
	if (preg_match("/^[A-Z][A-Z][A-Z](\d\d)$/i", $mapn, $match)) {
		if ($epis == 0)
			$epis = 1;
		if ($miss == 0)
			$miss = intval($match[1]);
	} elseif (preg_match("/^[A-Z](\d)M(\d\d?)$/i", $mapn, $match)) {
		if ($epis == 0)
			$epis = $match[1];
		if ($miss == 0)
			$miss = $match[2];
	}
	$plys = $ply1 + $ply2 + $ply3 + $ply4;

	// compute tics & time
	$tics /= $plys;
	$tsec = round($tics / 35, 2);
	$mins = intval($tsec / 60);
	$secs = round($tsec - $mins * 60, 2);

	return array(
		'vers' => $vers,
		'rver' => $rver,
		'sver' => $sver,
		'skll' => $skll,
		'mapn' => $mapn,
		'epis' => $epis,
		'miss' => $miss,
		'mode' => $mode,
		'mply' => $mply,
		'resp' => $resp,
		'fast' => $fast,
		'nomo' => $nomo,
		'comp' => 0,
		'insr' => 0,
		'seed' => '',
		'view' => $view,
		'ply1' => $ply1,
		'ply2' => $ply2,
		'ply3' => $ply3,
		'ply4' => $ply4,
		'plys' => $plys,
		'cls1' => -1,
		'cls2' => -1,
		'cls3' => -1,
		'cls4' => -1,
		'long' => 0,
		'tics' => $tics,
		'tsec' => $tsec,
		'mins' => $mins,
		'secs' => $secs,
		'foot' => $foot,
	);
}

function readString($xtra, $i)
{
	$str = '';
	while ($xtra[$i] != "\0") {
		$str .= $xtra[$i++];
	}
	return $str;
}

// vim:set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2:
