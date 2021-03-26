**LmpStats** is a simple library to collect statistics from demo files (usually with the .lmp extension) for *Doom-engine* games and related source ports.

Primary statistics are the demo version, map slot, skill, and length in game tics and time. Additional stats are gameplay flags, players present, and more depending on the format.

This library is intentionally kept basic and command-line oriented, with errors/messages merely echoed.  Conversion to a class with exceptions et al, if you so desire, is left as an exercise for the reader. :-)

## Installation

Requirements: [PHP](https://www.php.net)

## Supported demo formats

* Doom: v1.0-v1.9, v1.11, v0.5 alpha
* Heretic
* Hexen
* Strife: v1.01
* Boom/MBF: v2.00-2.03, v2.10-2.14
* CDoom: v2.05-2.07
* Doom Legacy: v1.29+
* Eternity Engine: v3.29+
* ZDoom-family: v1.14+

## Usage

**lmpStats.php** is a command-line script to invoke the library on a demo file, and also shows how to include **lmpstats.inc.php** in a script and use its results.

    lmpStats.php [-d <level 1/2>] [-H|X|A] [-z9] LMP-file

The debug flag (`-d`) shows all tics and messages at level `2`, or only special tics at level `1`, with tic addressing from the start of the file.  For the ZDoom-family, tic addresses are relative to the start of the (uncompressed) body chunk, which is saved in `/tmp/body.lmp` at both levels for hex-dump comparison.

The game flag is needed to distinguish Heretic (`-H`), Hexen (`-X`) and Doom v0.5 alpha (`-A`) from version-less Doom v1.0-1.2 demos.

The ZDoom v2.0.9x (`-z9`) flag is needed for versions 2.0.94-96 to handle the bug where demo command `DEM_INVUSE` was changed from 1 to 4 bytes without incrementing DEMOGAMEVERSION.

## Statistics

The return array contains the following entries, if available:

* **`vers`**: LMP version (see below)
* **`rver`**: real version for Eternity and ZDoom-family
* **`sver`**: sub-version for Eternity or minimum version for ZDoom-family
* **`skll`**: [skill level](https://doomwiki.org/wiki/Skill_level)
* **`epis`**: episode number (always 1 for Doom II)
* **`miss`**: mission or map number
* **`mapn`**: map name for Doom Legacy and ZDoom-family
* **`mode`**: multiplayer mode: 1 = deathmatch, 2 = altdeath, 0 = single- or cooperative
* **`mply`**: multiplayer flag for Doom Legacy
* **`resp`**: respawning monsters flag
* **`fast`**: fast monsters flag
* **`nomo`**: no monsters flag
* **`comp`**: compatibility flag for Boom/MBF and Eternity
* **`insr`**: demo insurance flag for Boom/MBF and Eternity
* **`seed`**: random seed for Boom/MBF, Eternity and ZDoom-family
* **`view`**: which player's point of view, aka console player
* **`ply1`**: player 1 present (1/0)
* **`ply2`**: player 2 present (1/0)
* **`ply3`**: player 3 present (1/0)
* **`ply4`**: player 4 present (1/0)
* **`plys`**: total number of players
* **`cls1`**: class of player 1 (Hexen: 0-2, otherwise -1; class name for ZDoom-family)
* **`cls2`**: class of player 2
* **`cls3`**: class of player 3
* **`cls4`**: class of player 4
* **`long`**: [tic](https://doomwiki.org/wiki/Tic) length in bytes (0 for dynamic tics in Doom Legacy and ZDoom-family)
* **`tics`**: number of tics
* **`tsec`**: total demo length in seconds
* **`mins`**: minutes portion of demo length
* **`secs`**: seconds portion of demo length
* **`foot`**: footer string, e.g. in PrBoom+ and Doom Legacy

The Hexen class numbers are 0 = Fighter, 1 = Cleric, and 2 = Mage.

## Demo versions

LMP_versions.txt provides the list of version bytes currently recognized by LmpStats.

ZDoom_versions.txt provides a list of version numbers in the ZDoom-family.

## Sources

* [Demo specification](https://doomwiki.org/wiki/Demo#Technical_information) on the [Doom Wiki](https://doomwiki.org/)
* [The unofficial LMP format description](http://web.archive.org/web/20090920220417/http://demospecs.planetquake.gamespy.com/lmp/lmp.html)
* [Demo version bytes list](https://www.doomworld.com/forum/topic/120007-specifications-for-source-port-demo-formats/?tab=comments#comment-2265059)
* [Boom / MBF demo header format](https://www.doomworld.com/forum/topic/72033-boom-mbf-demo-header-format/)
* Analysis of [CDoom sources](https://sourceforge.net/projects/cdoom207/files/)
* Analysis of [Doom Legacy sources](https://sourceforge.net/projects/doomlegacy/files/)
* Analysis of [Eternity Engine sources](https://github.com/team-eternity/eternity)
* [ZDoom demo specification](https://zdoom.org/wiki/Demo) on the [ZDoom wiki](https://zdoom.org/wiki/)
* Analysis of [ZDoom sources](https://zdoom.org/files/zdoom/)

## License

This project is licensed under the MIT license. See [LICENSE.md](LICENSE.md) for details.
