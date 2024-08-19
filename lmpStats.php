#!/usr/bin/php
<?php
	// LMP (Doom-engine demo) statistics
	// Copyright (C) 2021-2024 by Frans P. de Vries
	require_once __DIR__.'/lmpstats.inc.php';

	$usage = "Usage: {$argv[0]} [-d <level 1/2>] [-H|X|A] [-cl] [-z9] LMP-file\n";

	// check input options & parameters
	$game = null;
	$classic = $zdoom9 = false;
	$debug = 0;
	while (isset($argv[1]) && $argv[1][0] == '-') {
		if ($argv[1] == '-H') {
			$game = 'H';
		} elseif ($argv[1] == '-X') {
			$game = 'X';
		} elseif ($argv[1] == '-A') {
			$game = 'A';
		} elseif ($argv[1] == '-cl') {
			$classic = true;
		} elseif ($argv[1] == '-z9') {
			$zdoom9 = true;
		} elseif ($argv[1] == '-d') {
			if (ctype_digit($argv[2])) {
				$debug = intval($argv[2]);
				unset($argv[1]);
				$argc--;
				$argv = array_merge($argv);
			} else {
				echo $usage;
				exit(1);
			}
		} else {
			echo $usage;
			exit(1);
		}
		unset($argv[1]);
		$argc--;
		$argv = array_merge($argv);
	}
	if ($argc != 2) {
		echo $usage;
		exit(1);
	}

	$lmp = lmpStats($argv[1], $game, $debug, $classic, $zdoom9);
	echo "{$argv[1]} :\n";
	// show class 'legend'
	if ($game == 'X')
		echo 'classes: '.print_r(array('Fighter', 'Cleric', 'Mage'), true);
	print_r($lmp);

// vim:set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2:
