=== Bitid Authentication ===
Contributors: puggan
Tags: Authentication
Requires at least: 3.0.1
Tested up to: 3.9.1
Stable tag: 0.0.4-20140710
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==
= Bitid Authentication =
extends wordpress default authentication with the bitid-protocol

== Installation ==
1. check if the server has the "GMP PHP extension", if not see if you (or the server admins) can install it.
2. Upload it to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==
= What is bitid? =
Bitid is a authentication protocol, where the secret never levave the user.
This is done by the server sending a task to the client, and the client mathematicly prove that it has the secret.
Read more at https://github.com/bitid/bitid

= How do i use bitid? =
You install a bitcoin in your phone, for exemple mycelium or schildbach.
(There are at the current time no clients in the android market, they are both in a testing phase)
There is also a client for Desktop computers, can be found at https://github.com/antonio-fr/SimpleBitID

== Changelog ==
* 0.0.0-20140702 First upload
* 0.0.1-20140702 BitID authentication working, but admin UI is still missing.
* 0.0.2-20140705 No longer use direct file acess, insted using wordpress functions, see http://ottopress.com/2010/dont-include-wp-load-please/
* 0.0.3-20140710 No longer require php-option output_buffering to work.
* 0.0.4-20140710 There is now a UI for adding bitid-addresses to your own account

== Upgrade Notice ==
* There is now a UI for adding bitid-addresses to your own account
* Also fixed a few bug regarding the database-prefix
= Comming features =
* Admin UI for adding/removing bitid-addresses to other users.
* Add adress using bitid-protocol/QR-Code.
* Admin-options:
* * bitid-placment: above, below, left, right, none, replace
* * unknowned addresses: ignore, register, add

== Screenshots ==
1. Exemple login using bitid

== reference ==
This project is built ontop of code made in other projects:

* bitid-protocol @ https://github.com/LaurentMT/bitid (forked from https://github.com/bitid/bitid )
* bitid-php @ https://github.com/conejoninja/bitid-php
* phpeec @ https://github.com/mdanter/phpecc
