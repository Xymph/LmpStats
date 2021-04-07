<?php
// Analyze Doom-engine demos - main include
// Copyright (C) 2021 by Frans P. de Vries

define('VERSION', '0.7.2');
define('DEMOEND', 0x80);

function lmpStats($file, $game = null, $debug = 0, $zdoom9 = false)
{
	if (!$fp = @fopen($file, 'rb')) {
		echo "$file: cannot open\n";
		return false;
	}
	$ticlen = 4;
	$rver = $sver = 0;
	$seed = '';
	$cls1 = $cls2 = $cls3 = $cls4 = -1;

	$vers = readByte($fp);
	// vanilla Doom <= v1.2, Heretic, Hexen
	if ($vers >= 0 && $vers <= 9) {
		$comp = $insr = 0;
		$skll = $vers + 1;
		// 0x01-0x02
		$epis = readByte($fp);
		$miss = readByte($fp);
		// 0x03-0x06: players 1-4 present, Hexen classes
		$ply1 = readByte($fp);
		if ($game == 'X')
			$cls1 = readByte($fp);
		$ply2 = readByte($fp);
		if ($game == 'X')
			$cls2 = readByte($fp);
		$ply3 = readByte($fp);
		if ($game == 'X')
			$cls3 = readByte($fp);
		$ply4 = readByte($fp);
		if ($game == 'X')
			$cls4 = readByte($fp);
		$mode = $resp = $fast = $nomo = $view = 0;
		if ($game == 'H' || $game == 'X')
			$ticlen = 6;

	// Doom alpha v0.5
	} else if ($game == 'A' && $vers == 101) { // 'e'
		$epis = intval(fread($fp, 1));
		$skip = readByte($fp);
		if ($skip != 'm')	{
			echo "version $vers unexpected signature: $skip\n";
			return false;
		}
		$miss = intval(fread($fp, 1));
		$vers = -1;
		$skll = $mode = $resp = $fast = $nomo = $view = 0;
		$comp = $insr = 0;
		$ply1 = 1;
		$ply2 = $ply3 = $ply4 = 0;
		// 0x04: tics data

	// vanilla Doom v1.4-1.9 (= offsets), 1.11; Strife 1.01
	} else if ($vers >= 104 && $vers <= 109 || $vers == 111 || $vers == 101) {
		if ($vers == 111)
			$ticlen = 5;
		if ($vers == 101)
			$ticlen = 6;
		$comp = $insr = 0;
		// 0x01-0x03
		$skll = readByte($fp) + 1;
		if ($vers != 101)
			$epis = readByte($fp);
		else
			$epis = 0;
		if ($vers == 111)
			$expn = readByte($fp);
		$miss = readByte($fp);
		// 0x04: play mode: 0 = Single/coop, 1 = DM, 2 = AltDeath
		$mode = readByte($fp);
		// 0x05-0x07
		$resp = readByte($fp);
		$fast = readByte($fp);
		$nomo = readByte($fp);
		// 0x08: which player's point of view to use, zero-indexed (0 means player 1)
		$view = readByte($fp);
		// 0x09-0x0C: players 1-4 present
		$ply1 = readByte($fp);
		$ply2 = readByte($fp);
		$ply3 = readByte($fp);
		$ply4 = readByte($fp);
		// 0x0D: tics data

	// Boom/MBF v2.00-2.03 / 2.10-2.14, CDoom v2.05-2.07
	} else if (($vers >= 200 && $vers <= 203) ||
	           ($vers >= 205 && $vers <= 207) ||
	           ($vers >= 210 && $vers <= 214)) {
		if (($vers >= 205 && $vers <= 207) || $vers == 214)
			$ticlen = 5;
		$sign = fread($fp, 6);
		if ($sign != 'CDOOMC' && (ord($sign[0]) != 0x1D ||
		    (ord($sign[4]) != 0xE6 && ord($sign[5]) != 0xE6))) {
			echo "version $vers unexpected signature: $sign\n";
			return false;
		}
		// 0x07: compatibility (0 or 1)
		$comp = readByte($fp);
		// 0x08-0x0A
		$skll = readByte($fp) + 1;
		$epis = readByte($fp);
		$miss = readByte($fp);
		// 0x0B: play mode: 0 = Single/coop, 1 = DM, 2 = AltDeath
		$mode = readByte($fp);
		// 0x0C: console player: 0 = 1st, 1 = 2nd, etc.
		$view = readByte($fp);
		// 0x0D-0x12: expansion
		$skip = fread($fp, 6);
		// 0x13-0x15
		$resp = readByte($fp);
		$fast = readByte($fp);
		$nomo = readByte($fp);
		// 0x16: demo insurance (0 or 1)
		$insr = readByte($fp);
		// 0x17-0x1A:	random seed
		$seed = unpack('N', fread($fp, 4));
		$seed = sprintf('%08X', $seed[1]);
		// 0x1B-0x4C: expansion
		$skip = fread($fp, 50);
		// 0x4D-0x50: players 1-4 present
		$ply1 = readByte($fp);
		$ply2 = readByte($fp);
		$ply3 = readByte($fp);
		$ply4 = readByte($fp);
		// 0x51-0x6C: future expansion
		$skip = fread($fp, 28);
		// 0x6D: tics data

	// ZDoom v1.11-1.12
	} else if (chr($vers) == 'Z') {
		if (fread($fp, 3) == 'DEM') {
			$rver = readByte($fp) * 100;
			$rver += readByte($fp);
			echo "unsupported ZDoom version: $rver\n";
		} else {
			echo "invalid ZDoom version\n";
		}
		return false;

	// ZDoom v1.14+
	} else if (chr($vers) == 'F') {
		require_once __DIR__.'/lmpstats.zdoom.php';
		fseek($fp, 0);
		return lmpZDoom($fp, $debug, $zdoom9);

	// Legacy v1.29-1.44+
	} else if ($vers >= 129 && $vers <= 144) {
		require_once __DIR__.'/lmpstats.legacy.php';
		fseek($fp, 0);
		return lmpLegacy($fp, $debug);

	// Eternity v2.55: v3.29-4.0+
	} else if ($vers == 255) {
		$sign = fread($fp, 6);
		if (strncmp($sign, "ETERN", 5) != 0) {
			echo "version $vers unexpected signature: $sign\n";
			return false;
		}
		// 0x07-0x0A: real version
		$rver = unpack('V', fread($fp, 4));
		$rver = $rver[1];
		// 0x0B: sub-version
		$sver = readByte($fp);
		// 0x0C: compatibility (0 or 1)
		$comp = readByte($fp);
		// 0x0D-0x0F
		$skll = readByte($fp) + 1;
		$epis = readByte($fp) + 1;
		$miss = readByte($fp);
		// 0x10: play mode: 0 = Single/coop, 1 = DM, 2 = AltDeath
		$mode = readByte($fp);
		// 0x11: console player: 0 = 1st, 1 = 2nd, etc.
		$view = readByte($fp);
		// 0x12-0x15:	DM flags
		if ($rver >= 335)
			$skip = fread($fp, 4);
		// 0x16-0x1D:	map name
		if ($rver >= 329)
			$skip = fread($fp, 8);
		// 0x1E-0x23: expansion
		$skip = fread($fp, 6);
		// 0x24-0x26
		$resp = readByte($fp);
		$fast = readByte($fp);
		$nomo = readByte($fp);
		// 0x27: demo insurance (0 or 1)
		$insr = readByte($fp);
		// 0x28-0x2B:	random seed
		$seed = unpack('N', fread($fp, 4));
		$seed = sprintf('%08X', $seed[1]);
		// 0x2C-0x5D: expansion
		$skip = fread($fp, 50);
		// 0x5E-0x61: players 1-4 present
		$ply1 = readByte($fp);
		$ply2 = readByte($fp);
		$ply3 = readByte($fp);
		$ply4 = readByte($fp);
		// version dependent tic length
		if ($rver >= 335)
			$ticlen += 1; // actions
		if ($rver >= 333)
			$ticlen += 3; // longtic + look
		else if ($rver >= 329)
			$ticlen += 1; // old look
		if ($rver >= 340)
			$ticlen += 1; // fly
		if ($rver >= 401)
			$ticlen += 5; // item/weapon/slot

	} else {
		echo "version $vers\n";
		return false;
	}
	$plys = $ply1 + $ply2 + $ply3 + $ply4;

	// tics data
	debugLog(ftell($fp), $debug, 1, 'START');
	$foot = '';
	$tics = 0;
	while ($tic = fread($fp, $ticlen)) {
		if ($vers >= 0 && ord($tic[0]) == DEMOEND) {
			debugLog(ftell($fp)-strlen($tic), $debug, 1, 'DEMOEND');
			$foot = substr($tic, 1);
			break;
		}
		$tics++;
		debugLog(ftell($fp)-strlen($tic), $debug, 2, 'TIC: '.$tics);
	}
	$foot .= fread($fp, 4*1024);

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
		'epis' => $epis,
		'miss' => $miss,
		'mode' => $mode,
		'resp' => $resp,
		'fast' => $fast,
		'nomo' => $nomo,
		'comp' => $comp,
		'insr' => $insr,
		'seed' => $seed,
		'view' => $view,
		'ply1' => $ply1,
		'ply2' => $ply2,
		'ply3' => $ply3,
		'ply4' => $ply4,
		'plys' => $plys,
		'cls1' => $cls1,
		'cls2' => $cls2,
		'cls3' => $cls3,
		'cls4' => $cls4,
		'long' => $ticlen,
		'tics' => $tics,
		'tsec' => $tsec,
		'mins' => $mins,
		'secs' => $secs,
		'foot' => $foot,
	);
}

function readByte($fp)
{
	return ord(fread($fp, 1));
}

function debugLog($i, $debug, $lev, $cmd, $par = false)
{
	if ($debug >= $lev) {
		printf("%6d %05X - %s", $i, $i, $cmd);
		if (!$par)
			echo "\n";
	}
}

function debugPar($debug, $lev, $par, $nl = true)
{
	if ($debug >= $lev) {
		echo ": $par";
		if ($nl)
			echo "\n";
	}
}

// vim:set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2:
