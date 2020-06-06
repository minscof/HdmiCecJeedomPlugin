<?php

/*
 * This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function hdmiCec_install() {
    exec('sudo apt-get -y install cmake libudev-dev libxrandr-dev python3-dev swig');
    exec('sudo apt-get -y install python-pip libevent-dev python-all-dev ');
	exec('sudo apt-get -y install libcec-dev');
    exec('sudo pip install greenlet');
    exec('sudo pip install gevent');
    exec('sudo pip install gevent-socketio');
    exec('sudo pip install cec');
    exec('sudo pip install regex');
    exec('sudo apt-get remove python-pip libevent-dev python-all-dev ');
}

function hdmiCec_update() {
    exec('sudo apt-get -y install python-pip libevent-dev python-all-dev ');
	exec('sudo apt-get -y install libcec-dev');
    exec('sudo pip install greenlet');
    exec('sudo pip install gevent');
    exec('sudo pip install gevent-socketio');
    exec('sudo pip install cec');
    exec('sudo pip install regex');
    exec('sudo apt-get remove python-pip libevent-dev python-all-dev ');
}

function hdmiCec_remove() {
    // test if these modules were installed before
    exec('sudo apt-get install python-pip');
    exec('sudo pip remove greenlet');
    exec('sudo pip remove gevent');
    exec('sudo pip remove gevent-socketio');
    exec('sudo pip remove cec');
    exec('sudo pip remove regex');
    exec('sudo apt-get remove python-pip');
	exec('sudo apt-get remove libcec-dev');
}
?>