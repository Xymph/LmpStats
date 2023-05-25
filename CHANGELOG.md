## Changelog

This project adheres to [Semantic Versioning](https://semver.org/) and [Keep a Changelog](https://keepachangelog.com/).

### Version 0.12.0 - 2023-05-25
#### Added
* Support for PrBoom+um v2.5.1.7

### Version 0.11.1 - 2023-02-13

#### Changed
* Set default players count to 1

#### Fixed
* Fixed episode check in PrBoom+um v2.6+ format

### Version 0.11.0 - 2022-07-15

#### Added
* Support for MBF21 v2.21

#### Changed
* Some [PSR-12](https://www.php-fig.org/psr/psr-12/)-related code formatting updates

### Version 0.10.0 - 2021-12-31

#### Added
* Support for PrBoom+um v2.6+

### Version 0.9.2 - 2021-11-08

#### Changed
* Define constants outside functions to prevent PHP notices on repeated invocations

### Version 0.9.1 - 2021-05-19

#### Changed
* List TASMBF, Chase & Timer among supported formats

### Version 0.9.0 - 2021-05-17

#### Added
* Doom Classic support with -cl option
* Doom64 EX v1.4 (and possibly other v1.x releases) support

#### Changed
* Return minimal version array for ZDoom v1.11-1.12 & ZDaemon v1.09+ instead of showing an "unsupported" message

#### Fixed
* Adjust ticrate for Doom64 EX

### Version 0.8.0 - 2021-05-15

#### Added
* Support for Doom64 EX v2.5+, RUDE, TASDoom 
* ZDaemon v1.09+ support for the version number only

### Version 0.7.3 - 2021-05-12

#### Changed
* Accept MBF v2.04 in version byte check
* Add v2.0.13 to ZDoom versions list

### Version 0.7.2 - 2021-04-07

#### Fixed
* Prevent constant warning for multiple invocations

### Version 0.7.1 - 2021-03-27

#### Changed
* Merge LMP_versions.txt into README.md
* Correct info about ZDoom versions
* Use readByte function for ordinal file reads

### Version 0.7.0 - 2021-03-26

* Initial release

#### Added
* CDoom support

### Version 0.6.0

#### Added
* Doom Legacy support

### Version 0.5.0

#### Added
* ZDoom-family support

### Version 0.4.0

#### Added
* Eternity Engine support

### Version 0.3.0

#### Added
* Boom/MBF support

### Version 0.2.0

#### Added
* Heretic, Hexen, Strife, Doom alpha support

### Version 0.1.0 - 2021-02-22

#### Created
* Doom initial support

