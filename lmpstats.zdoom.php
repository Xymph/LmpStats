<?php
// Analyze ZDoom-family demos
// Copyright (C) 2021 by Frans P. de Vries

function lmpZDoom($fp, $debug = 0, $zdoom9 = false)
{
	$form = fread($fp, 4);
	if (strncmp($form, "FORM", 4) != 0) {
		echo "unexpected IFF signature: $form\n";
		return false;
	}
	// total form size
	$tsiz = unpack('N', fread($fp, 4));
	$tsiz = $tsiz[1];
	if ($debug >= 2)
		echo "total size     : $tsiz\n";
	$form = fread($fp, 4);
	if (strncmp($form, "ZDEM", 4) != 0) {
		echo "unexpected ZDoom chunk: $form\n";
		return false;
	}
	$ply1 = $ply2 = $ply3 = $ply4 = 0;
	$cls1 = $cls2 = $cls3 = $cls4 = -1;
	$resp = $fast = $nomo = 0;
	$seed = '';

	// deathmatch flags
	define('DF_NO_MONSTERS',      0x1000);
	define('DF_MONSTERS_RESPAWN', 0x2000);
	define('DF_FAST_MONSTERS',    0x8000);

	// process all chunks
	$compressed = $multiplayer = false;
	$body = null;
	while ($type = fread($fp, 4)) {
		switch ($type) {

		// header chunk
		case 'ZDHD':
			list($csiz, $rsiz) = chunkSize($fp, $type, $debug);
			$chnk = fread($fp, $rsiz);

			// real version
			$rver = unpack('n', substr($chnk, 0, 2));
			$rver = $rver[1];
			if ($debug >= 2)
				printf("header version : %05X\n", $rver);
			// minimum version
			$sver = unpack('n', substr($chnk, 2, 2));
			$sver = $sver[1];
			if ($debug >= 2)
				printf("minimum version: %05X\n", $sver);

			// map name; format change in v2.6.0:
			$i = 4;
			if ($rver >= 0x219) {
				$mapn = readString($chnk, $i);
				if ($rver == 0x219)
					$i += 8 - strlen($mapn);
				else // >= 0x21A, v2.8.0+
					$i++;
			} else {
				$mapn = rtrim(substr($chnk, $i, 8));
				$i += 8;
			}

			// random seed
			$seed = unpack('N', substr($chnk, $i, 4));
			$seed = sprintf('%08X', $seed[1]);
			$i += 4;
			// which player's point of view to use, zero-indexed (0 means player 1)
			$view = ord(substr($chnk, $i, 1));
			break;
		
		// variables chunk
		case 'VARS':
			list($csiz, $rsiz) = chunkSize($fp, $type, $debug);
			$chnk = fread($fp, $rsiz);

			$skll = -1;
			if (preg_match("~\\\skill\\\(\d)~", $chnk, $match))
				$skll = $match[1];
			$skll++;

			$mode = 0;
			if (preg_match("~\\\deathmatch\\\(\d)~", $chnk, $match))
				$mode = $match[1];

			if (preg_match("~\\\dmflags\\\(\d)~", $chnk, $match)) {
				if ($match[1] & DF_NO_MONSTERS)
					$nomo = 1;
				if ($match[1] & DF_MONSTERS_RESPAWN)
					$resp = 1;
				if ($match[1] & DF_FAST_MONSTERS)
					$fast = 1;
			}
			break;
		
		// weapons chunk
		case 'WEAP':
			list($csiz, $rsiz) = chunkSize($fp, $type, $debug);
			$chnk = fread($fp, $rsiz);
			break;
		
		// user info chunk(s)
		case 'UINF':
			list($csiz, $rsiz) = chunkSize($fp, $type, $debug);
			$chnk = fread($fp, $rsiz);

			$clss = -1;
			if (preg_match("~\\\playerclass\\\(\w+)~i", $chnk, $match))
				$clss = $match[1];

			// player index
			switch (ord(substr($chnk, 0, 1))) {
				case 0: $ply1 = 1; $cls1 = $clss; break;
				case 1: $ply2 = 1; $cls2 = $clss; break;
				case 2: $ply3 = 1; $cls3 = $clss; break;
				case 3: $ply4 = 1; $cls4 = $clss; break;
			}
			break;
		
		// multiplayer chunk
		case 'NETD':
			list($csiz, $rsiz) = chunkSize($fp, $type, $debug);
			if ($rsiz > 0)
				$chnk = fread($fp, $rsiz);

			$multiplayer = true;
			break;
		
		// compression chunk
		case 'COMP':
			list($csiz, $rsiz) = chunkSize($fp, $type, $debug);

			// size of uncompressed body
			$bsiz = unpack('N', fread($fp, 4));
			$bsiz = $bsiz[1];
			if ($bsiz > 0)
				$compressed = true;
			break;
		
		// tics body chunk
		case 'BODY':
			list($csiz, $rsiz) = chunkSize($fp, $type, $debug);

			$body = fread($fp, $csiz);
			if ($rsiz > $csiz)
				$padd = fread($fp, $rsiz - $csiz);
			break;
		
		// unknown chunk
		default:
			echo "other ZDoom chunk: $type\n";
			list($csiz, $rsiz) = chunkSize($fp, $type, $debug);
			$chnk = fread($fp, $rsiz);
		}
	}

	// decompress body if necessary
	if ($compressed && $body) {
		$body = @zlib_decode($body);
		if ($body === false) {
			echo "error unzipping body\n";
			return false;
		}
		if ($debug >= 1) {
			echo "tics body size : ".strlen($body);
			if (strlen($body) != $bsiz)
				echo "\t!= COMP size $bsiz";
			echo "\n";
			file_put_contents('/tmp/body.lmp', $body);
		}
	} else {
		if ($debug >= 1)
			echo "tics body size : ".strlen($body)."\n";
	}

	// extract from map name
	if (preg_match("/^[A-Z][A-Z][A-Z](\d\d)$/i", $mapn, $match)) {
		$epis = 1;
		$miss = intval($match[1]);
	} else if (preg_match("/^[A-Z](\d)M(\d\d?)$/i", $mapn, $match)) {
		$epis = $match[1];
		$miss = $match[2];
	} else {
		$epis = $miss = 0;
	}
	$plys = $ply1 + $ply2 + $ply3 + $ply4;

	// types of demo commands
	define('DEM_BAD', 0);
	define('DEM_USERCMD', 1);
	define('DEM_USERCMDCLONE', 2); // v1
	//define('DEM_EMPTYUSERCMD', 2); // v2.0.47j+
	//define('DEM_STUFFTEXT', 3); // v1, never used
	define('DEM_MUSICCHANGE', 4);
	define('DEM_PRINT', 5);
	define('DEM_CENTERPRINT', 6);
	define('DEM_STOP', 7);
	define('DEM_UINFCHANGED', 8);
	define('DEM_SINFCHANGED', 9);
	define('DEM_GENERICCHEAT', 10);
	define('DEM_GIVECHEAT', 11);
	define('DEM_SAY', 12);
	define('DEM_DROPPLAYER', 13);
	define('DEM_CHANGEMAP', 14);
	define('DEM_SUICIDE', 15);
	define('DEM_ADDBOT', 16);
	define('DEM_KILLBOTS', 17);
	// types of v1.23+ demo commands
	define('DEM_INVSEL', 18);
	//define('DEM_INVUSEALL', 18); // v2.0.96+
	define('DEM_INVUSE', 19);
	define('DEM_PAUSE', 20);
	define('DEM_SAVEGAME', 21);
	define('DEM_WEAPSEL', 22); // < v2.0.96
	define('DEM_WEAPSLOT', 23); // < v2.0.96
	define('DEM_WEAPNEXT', 24); // < v2.0.96
	define('DEM_WEAPPREV', 25); // < v2.0.96
	define('DEM_SUMMON', 26);
	define('DEM_FOV', 27);
	define('DEM_MYFOV', 28);
	define('DEM_CHANGEMAP2', 29);
	define('DEM_SLOTSCHANGE', 30); // < v2.0.96
	define('DEM_SLOTCHANGE', 31); // < v2.0.96
	define('DEM_RUNSCRIPT', 32);
	define('DEM_SINFCHANGEDXOR', 33);
	define('DEM_INVDROP', 34); // v2.0.94/6+
	define('DEM_WARPCHEAT', 35);
	define('DEM_CENTERVIEW', 36);
	define('DEM_SUMMONFRIEND', 37);
	define('DEM_SPRAY', 38); // v2.1.0+
	define('DEM_CROUCH', 39);
	define('DEM_RUNSCRIPT2', 40); // v2.1.5+
	define('DEM_CHECKAUTOSAVE', 41); // v2.1.7+
	define('DEM_DOAUTOSAVE', 42);
	define('DEM_MORPHEX', 43);
	define('DEM_SUMMONFOE', 44); // v2.2.0+
	define('DEM_WIPEON', 45); // = v2.2.0
	define('DEM_WIPEOFF', 46); // = v2.2.0
	define('DEM_TAKECHEAT', 47); // v2.2.0+
	define('DEM_ADDCONTROLLER', 48); // v2.3.0+
	define('DEM_DELCONTROLLER', 49);
	define('DEM_KILLCLASSCHEAT', 50);
	define('DEM_CONVERSATION', 51); // v2.3.0-v2.4.0
	define('DEM_SUMMON2', 52); // v2.3.0+
	define('DEM_SUMMONFRIEND2', 53);
	define('DEM_SUMMONFOE2', 54);
	define('DEM_ADDSLOTDEFAULT', 55);
	define('DEM_ADDSLOT', 56);
	define('DEM_SETSLOT', 57);
	define('DEM_SUMMONMBF', 58); // v2.4.0+
	define('DEM_CONVREPLY', 59); // v2.4.1+
	define('DEM_CONVCLOSE', 60);
	define('DEM_CONVNULL', 61);
	define('DEM_RUNSPECIAL', 62); // v2.6.0+
	define('DEM_SETPITCHLIMIT', 63);
	define('DEM_ADVANCEINTER', 64);
	define('DEM_RUNNAMEDSCRIPT', 65);
	define('DEM_REVERTCAMERA', 66);
	define('DEM_SETSLOTPNUM', 67);
	define('DEM_REMOVE', 68); // v2.8.0+
	define('DEM_FINISHGAME', 69); // v2.8.0+
	// types of LZ v3.83+ / GZ v2.4.0+ demo commands
	define('DEM_NETEVENT', 70);
	define('DEM_MDK', 71);
	define('DEM_SETINV', 72); // GZ v3.0.0+

	// flags in demo commands
	define('UCMDF_BUTTONS', 0x01);
	define('UCMDF_PITCH', 0x02);
	define('UCMDF_YAW', 0x04);
	define('UCMDF_FORWARDMOVE', 0x08);
	define('UCMDF_SIDEMOVE', 0x10);
	define('UCMDF_UPMOVE', 0x20);
	define('UCMDF_IMPULSE', 0x40);
	define('UCMDF_ROLL', 0x40);
	define('UCMDF_MORE', 0x80);
	define('UCMDF2_ROLL', 0x01);
	define('UCMDF2_USE', 0x02);
	// slots v2+
	define('NUM_WEAPON_SLOTS', 10);

	// decompile tics body
	$tics = $i = 0;
	while ($i < strlen($body)) {
		$cmd = '';
		switch (ord($body[$i])) {

		case DEM_STOP:	
			debugLog($i, $debug, 1, 'STOP');
			break 2;

		case DEM_USERCMD:
			debugLog($i, $debug, 2, 'USERCMD');
			$i++;
			$flags = ord($body[$i]);
			if ($rver < 0x117 && $flags & UCMDF_MORE) { // v1-1.22
				$i++;
				$flags2 = ord($body[$i]);
			} else {
				$flags2 = 0;
			}
			if ($flags) {
				if ($flags & UCMDF_BUTTONS) {
					$i++;
					if ($rver >= 0x20C) { // ZDoom v2.3.0+ / GZDoom v1.1.05+
						if (ord($body[$i]) & 0x80) {
							$i++;
							if (ord($body[$i]) & 0x80) {
								$i++;
								if (ord($body[$i]) & 0x80) {
									$i++;
								}
							}
						}
					}
				}
				if ($flags & UCMDF_PITCH)
					$i += 2;
				if ($flags & UCMDF_YAW)
					$i += 2;
				if ($flags & UCMDF_FORWARDMOVE)
					$i += 2;
				if ($flags & UCMDF_SIDEMOVE)
					$i += 2;
				if ($flags & UCMDF_UPMOVE)
					$i += 2;
				if ($rver < 0x117) { // v1-1.22
					if ($flags & UCMDF_IMPULSE)
						$i++;
					if ($flags2) {
						if ($flags2 & UCMDF2_ROLL)
							$i += 2;
						if ($flags2 & UCMDF2_USE)
							$i++;
					}
				} else { // v1.23, v2+
					if ($flags & UCMDF_ROLL)
						$i += 2;
				}
			}
			$tics++;
			break;

		case DEM_USERCMDCLONE: // v1+
		//case DEM_EMPTYUSERCMD: // v2.0.47j+
			if ($rver >= 0x201) {
				debugLog($i, $debug, 2, 'UCEMPTY');
				$tics++;
			} else {
				debugLog($i, $debug, 2, 'UCCLONE', true);
				$i++;
				debugPar($debug, 2, ord($body[$i]));
				$tics += ord($body[$i]) + 1;
			}
			break;

		case DEM_MUSICCHANGE:
			$cmd = 'MUSICCH';
		case DEM_PRINT:
			if ($cmd == '')
				$cmd = 'PRINT';
		case DEM_CENTERPRINT:
			if ($cmd == '')
				$cmd = 'CENTPRT';
		case DEM_UINFCHANGED:
			if ($cmd == '')
				$cmd = 'UINFCHG';
		case DEM_SINFCHANGED:
			if ($cmd == '')
				$cmd = 'SINFCHG';
		case DEM_SINFCHANGEDXOR:
			if ($cmd == '')
				$cmd = 'SINFXOR';
		case DEM_GIVECHEAT:
			if ($cmd == '')
				$cmd = 'GIVECHT';
		case DEM_CHANGEMAP:
			if ($cmd == '')
				$cmd = 'CHNGMAP';
		case DEM_SUMMON: // v2
			if ($cmd == '')
				$cmd = 'SUMMON';
		case DEM_SUMMONFRIEND: // v2.0.96+
			if ($cmd == '')
				$cmd = 'SUMFRND';
		case DEM_SPRAY: // v2.1.0+
			if ($cmd == '')
				$cmd = 'SPRAY';
		case DEM_MORPHEX: // v2.1.7+
			if ($cmd == '')
				$cmd = 'MORPHEX';
		case DEM_TAKECHEAT: // v2.2.0+
			if ($cmd == '')
				$cmd = 'TAKECHT';
		case DEM_SUMMONFOE: // v2.2.0+
			if ($cmd == '')
				$cmd = 'SUMFOE';
		case DEM_SUMMON2: // v2.3.0+
			if ($cmd == '')
				$cmd = 'SUMMON2';
		case DEM_SUMMONFRIEND2: // v2.3.0+
			if ($cmd == '')
				$cmd = 'SUMFRD2';
		case DEM_SUMMONFOE2: // v2.3.0+
			if ($cmd == '')
				$cmd = 'SUMFOE2';
		case DEM_KILLCLASSCHEAT: // v2.3.0+
			if ($cmd == '')
				$cmd = 'KILLCLC';
		case DEM_SUMMONMBF: // v2.4.0+
			if ($cmd == '')
				$cmd = 'SUMMBF';
		case DEM_MDK: // LZ v3.83+ / GZ v2.4.0+
			if ($cmd == '')
				$cmd = 'MDK';
			debugLog($i, $debug, 1, $cmd, true);
			$i++;
			if ($rver >= 0x201 && ($cmd == 'SINFCHG' || $cmd == 'SINFXOR')) { // v2+
				$leng = ord($body[$i]) & 0x3F;
				$type = ord($body[$i]) >> 6;
				$i++;
				$name = '';
				for ($n = 0; $n < $leng; $n++)
					$name .= $body[$i+$n];
				debugPar($debug, 1, $name);
				$i += $leng;
				if ($cmd == 'SINFXOR') { // v2.0.96+
					$i++; // singlebit
				} else {
					switch ($type) {
						case 0: $i += 1; break; // CVAR_Bool
						case 1: $i += 4; break; // CVAR_Int
						case 2: $i += 4; break; // CVAR_Float
						case 3:                 // CVAR_String
							$skip = readString($body, $i);
							break;
					}
				}
			} else {
				$inf = readString($body, $i);
				debugPar($debug, 1, $inf);
				if ($cmd == 'GIVECHT' || $cmd == 'TAKECHT' ||
				    $cmd == 'SUMMON2' || $cmd == 'SUMFRD2' || $cmd == 'SUMFOE2') {
					if ($rver >= 0x204) // v2.1.0+
						$i += 2; // quantity
					else
						$i++; // quantity
				}
			}
			break;

		case DEM_SAY:
			$cmd = 'SAY  ';
		case DEM_ADDBOT: // v2
			if ($cmd == '')
				$cmd = 'ADDBT';
			debugLog($i, $debug, 1, $cmd . ' ' . ord($body[$i+1]), true);
			$i += 2;
			$say = readString($body, $i);
			debugPar($debug, 1, $say);
			break;

		case DEM_GENERICCHEAT:
			$cmd = 'GENCT';
		case DEM_DROPPLAYER:
			if ($cmd == '')
				$cmd = 'DRPPL';
		case DEM_WEAPSEL: // v2-v2.0.63
			if ($cmd == '')
				$cmd = 'WPSEL';
		case DEM_WEAPSLOT: // v2-v2.0.63
			if ($cmd == '')
				$cmd = 'WPSLT';
		case DEM_FOV: // v2
			if ($cmd == '')
				$cmd = 'FOV';
		case DEM_MYFOV: // v2
			if ($cmd == '')
				$cmd = 'MYFOV';
		case DEM_ADDCONTROLLER: // v2.3.0+
			if ($cmd == '')
				$cmd = 'ADDCT';
		case DEM_DELCONTROLLER: // v2.3.0+
			if ($cmd == '')
				$cmd = 'DELCT';
			debugLog($i, $debug, 1, $cmd . ' ' . ord($body[$i+1]));
			$i++;
			break;

		case DEM_INVSEL: // v2
			$cmd = 'INVSEL ';
		case DEM_INVUSE: // v2
			if ($cmd == '')
				$cmd = 'INVUSE ';
		case DEM_INVDROP: // v2
			if ($cmd == '')
				$cmd = 'INVDRP ';
		case DEM_WARPCHEAT: // v2.0.96+
			if ($cmd == '')
				$cmd = 'WARPCH ';
			if ($rver >= 0x203 || $cmd == 'WARPCH ' || // v2.0.98+
			    ($cmd == 'INVUSE ' && $zdoom9)) { // v2.0.90-96 with flag
				debugLog($i, $debug, 1, $cmd);
				$i += 4;
			} else {
				debugLog($i, $debug, 1, $cmd, true);
				$i++;
				debugPar($debug, 1, ord($body[$i]));
			}
			break;

		case DEM_SAVEGAME: // v2
			debugLog($i, $debug, 1, 'SAVGAME', true);
			$i++;
			$file = readString($body, $i);
			debugPar($debug, 1, $file, false);
			$i++;
			$desc = readString($body, $i);
			debugPar($debug, 1, $desc);
			break;

		case DEM_NETEVENT: // LZ v3.83+ / GZ v2.4.0+
			$cmd = 'NETEVNT';
		case DEM_SETINV: // LZ v3.83+ / GZ v3.0.0+
			if ($cmd == '')
				$cmd = 'SETINV ';
			debugLog($i, $debug, 1, $cmd, true);
			$i++;
			$str = readString($body, $i);
			debugPar($debug, 1, $str);
			if ($cmd == 'NETEVNT')
				$i += 14;
			else // 'SETINV'
				$i += 5;
			break;

		case DEM_SLOTCHANGE: // v2-v2.0.63
			debugLog($i, $debug, 1, 'SLTSCHG', true);
			$i++;
			debugPar($debug, 1, ord($body[$i]));
			$i += 2 + ord($body[$i+1]);
			break;

		case DEM_SLOTSCHANGE: // v2-v2.0.63
			debugLog($i, $debug, 1, 'SLTSCHG');
			$i++;
			$i += NUM_WEAPON_SLOTS / 2 + NUM_WEAPON_SLOTS;
			break;

		case DEM_RUNSCRIPT: // v2.0.48+
			$cmd = 'RUNSCR';
		case DEM_RUNSCRIPT2: // v2.1.5+
			if ($cmd == '')
				$cmd = 'RUNSCR2';
			debugLog($i, $debug, 1, $cmd);
			$i++;
			$i += 3 + ord($body[$i+2]) * 4;
			break;

		case DEM_SUICIDE: // v1+
			$cmd = 'SUICIDE';
		case DEM_KILLBOTS: // v1.23+
			if ($cmd == '')
				$cmd = 'KILLBOT';
		case DEM_PAUSE: // v2.0.24+
			if ($cmd == '')
				$cmd = 'PAUSE';
		case DEM_WEAPNEXT: // < v2.0.96
			if ($cmd == '')
				$cmd = 'WEAPNXT';
		case DEM_WEAPPREV: // < v2.0.96
			if ($cmd == '')
				$cmd = 'WEAPPRV';
		case DEM_CENTERVIEW: // v2.0.96+
			if ($cmd == '')
				$cmd = 'CENTRVW';
		case DEM_CROUCH: // v2.0.96+
			if ($cmd == '')
				$cmd = 'CROUCH';
		case DEM_WIPEON: // = v2.2.0
			if ($cmd == '')
				$cmd = 'WIPEON';
		case DEM_WIPEOFF: // = v2.2.0
			if ($cmd == '')
				$cmd = 'WIPEOFF';
		case DEM_CHECKAUTOSAVE: // v2.3.0+
			if ($cmd == '')
				$cmd = 'CHKAUSV';
		case DEM_DOAUTOSAVE: // v2.3.0+
			if ($cmd == '')
				$cmd = 'DOAUSAV';
			debugLog($i, $debug, 1, $cmd);
			break;

		case DEM_SETSLOT: // v2.3.0+
			debugLog($i, $debug, 1, 'SETSLOT');
			$i += 2;
			$count = ord($body[$i]);
			for ($n = $count; $n > 0; $n--)
				$i++;
				if (ord($body[$i]) & 0x80)
					$i++;
			break;

		case DEM_CONVREPLY: // v2.4.1+
			debugLog($i, $debug, 1, 'CONVRPY');
			$i += 3;
			break;

		case DEM_SETPITCHLIMIT: // v2.6.0+
			debugLog($i, $debug, 1, 'PITCHLM');
			$i += 2;
			break;

		default:
			printf("Unexpected command at %6d %05X: %d\n", $i, $i, ord($body[$i]));
			break;
		}
		$i++;
	}

	// compute tics & time
	$tics /= $plys;
	$tsec = round($tics / 35, 2);
	$mins = intval($tsec / 60);
	$secs = round($tsec - $mins * 60, 2);

	return array(
		'vers' => ord('Z'),
		'rver' => $rver,
		'sver' => $sver,
		'skll' => $skll,
		'mapn' => $mapn,
		'epis' => $epis,
		'miss' => $miss,
		'mode' => $mode,
		'resp' => $resp,
		'fast' => $fast,
		'nomo' => $nomo,
		'comp' => 0,
		'insr' => 0,
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
		'long' => 0,
		'tics' => $tics,
		'tsec' => $tsec,
		'mins' => $mins,
		'secs' => $secs,
	);
}

function chunkSize($fp, $type, $debug)
{
	$size = unpack('N', fread($fp, 4));
	$size = $size[1];
	if ($debug >= 2)
		echo "$type chunk size: $size\n";
	// chunk size, real size
	return array($size, $size + ($size & 1));
}

function readString($body, &$i)
{
	$str = '';
	while ($body[$i] != "\0") {
		$str .= $body[$i++];
	}
	return $str;
}

// vim:set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2:
