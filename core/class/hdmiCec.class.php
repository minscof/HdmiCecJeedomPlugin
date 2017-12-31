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

/**
 * ***************************** Includes ******************************
 */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class hdmiCec extends eqLogic
{

    /**
     * ***************************** Attributs ******************************
     */
    /* Ajouter ici toutes vos variables propre à votre classe */
    
    /**
     * *************************** Methode static ***************************
     */
    
    /*
     * // Fonction exécutée automatiquement toutes les minutes par Jeedom
     * public static function cron() {
     *
     * }
     */
    
    /*
     * // Fonction exécutée automatiquement toutes les heures par Jeedom
     * public static function cronHourly() {
     *
     * }
     */
    
    /*
     * // Fonction exécutée automatiquement tous les jours par Jeedom
     * public static function cronDayly() {
     *
     * }
     */
    
    /**
     * ************************* Methode d'instance *************************
     */
    public static function health() {
        $return = array();
        $statusDaemon = false;
        $statusDaemon = (hdmiCec::deamon_info()['state']=='ok'?true:false);
        $libVer = config::byKey('daemonVer', 'hdmiCec');
        if ($libVer == '') {
            $libVer = '{{inconnue}}';
        }
        
        $return[] = array(
            'test' => __('Daemon', __FILE__),
            'result' => ($statusDaemon) ? $libVer : __('NOK', __FILE__),
            'advice' => ($statusDaemon) ? '' : __('Indique si la daemon est opérationel avec sa version', __FILE__),
            'state' => $statusDaemon
        );
        return $return;
    }

    public function execCmd($_cmd)
    {
        $ip = config::byKey('hdmiCecIp', 'hdmiCec', '0');
        $port = config::byKey('hdmiCecPort', 'hdmiCec', '0');
        $user = config::byKey('hdmiCecUser', 'hdmiCec', '0');
        $pass = config::byKey('hdmiCecPassword', 'hdmiCec', '0');
        if (! $connection = ssh2_connect($ip, $port)) {
            log::add('hdmiCec', 'error', 'connexion SSH KO');
            return;
        } else {
            if (! ssh2_auth_password($connection, $user, $pass)) {
                log::add('hdmiCec', 'error', 'Authentification SSH KO');
                return;
            } else {
                foreach ($_cmd as $cmd) {
                    log::add('hdmiCec', 'info', 'Commande par SSH (' . $cmd . ') sur ' . $ip);
                    $execmd = "echo '" . $pass . "' | sudo -S " . $cmd;
                    $result = ssh2_exec($connection, $execmd);
                }
                $closesession = ssh2_exec($connection, 'exit');
                stream_set_blocking($closesession, true);
                stream_get_contents($closesession);
                return $result;
            }
        }
        return;
    }
    
    public static function deamon_info()
    {
        //TODO adapt for remote daemon...
        $return = array();
        $return['log'] = 'hdmiCec_log';
        $return['state'] = 'nok';
        $count = trim(shell_exec('ps ax | grep "hdmiCec_server" | grep -v "grep" | wc -l'));
        if ($count >= 1) {
            $return['state'] = 'ok';
        }
        $return['launchable'] = 'ok';
        /*
         * if (config::byKey('nodeGateway', 'hdmiCec') == 'none' || config::byKey('nodeGateway', 'hdmiCec') == '') {
         * $return['launchable'] = 'nok';
         * $return['launchable_message'] = __('Le port n\'est pas configuré', __FILE__);
         * }
         */
        return $return;
    }

    public static function deamon_start($_debug = false)
    {
        self::deamon_stop();
        $shell = realpath(dirname(__FILE__)).'/../../resources/hdmiCec_server.py';
        $string = file_get_contents($shell);
        $matches=array(0,'inconnu');
        preg_match("/__version__='([0-9.]+)/mis", $string, $matches);
        config::save('daemonVer', 'Version '.$matches[1],  'hdmiCec');
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        log::add('hdmiCec', 'info', 'Lancement du daemon hdmiCec '.$matches[1]);
        
        log::add('hdmiCec', 'debug', 'nom complet du daemon hdmiCec : ' . $shell);
        // TODO il faut lancer le serveur hdmi sur la machine Ip définie, pas uniquement en local
        $cmd = 'nice -n 19 /usr/bin/python ' . $shell .' '. config::byKey('hdmiCecPort', 'hdmiCec', '0') . ' ' . config::byKey('hdmiCecOsdName', 'hdmiCec', 'Jeedom') . ' ' . config::byKey('deviceCecType', 'hdmiCec', '0') . ' ' . config::byKey('internalAddr', 'core', 'xxx.yyy.zzz.vvvv') . ' ' . config::byKey('api', 'core', 'xxxxxxxxx');
        // le sudo semble poser pbm
        $result = exec('nohup sudo ' . $cmd . ' >> ' . log::getPathToLog('hdmiCec_log') . ' 2>&1 &');
        // $result='error';
        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
            log::add('hdmiCec', 'error', 'échec lancement du daemon :' . $result);
            return false;
        }
        
        $i = 0;
        while ($i < 30) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            sleep(1);
            $i ++;
        }
        if ($i >= 30) {
            log::add('hdmiCec', 'error', 'Impossible de lancer le daemon hdmiCec, vérifiez les logs', 'unableStartDeamon');
            return false;
        }
        message::removeAll('hdmiCec', 'unableStartDeamon');
        // mettre une gestion d'event pour gérer le statut de daemon
        //config::save('daemon', '1', 'hdmiCec');
        log::add('hdmiCec', 'info', 'hdmiCec lancé '.$matches[1]);
        return true;
    }

    public static function deamon_stop()
    {
        $opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Accept-language: en\r\n" . "Cookie: foo=bar\r\n"
            )
        );
        $context = stream_context_create($opts);
        // pour éviter des logs intempestifs quand on cherche à arrêter un serveur déjà arrêté.. @
        @$file = file_get_contents('http://' . config::byKey('hdmiCecIp', 'hdmiCec', '0') . ':' . config::byKey('hdmiCecPort', 'hdmiCec', '0') . '/stop', false, $context);
        log::add('hdmiCec', 'info', 'Arrêt du service hdmiCec');
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            sleep(2);
            exec('sudo kill $(ps aux | grep "cecHdmi_server" | grep -v "grep" | awk \'{print $2}\')');
        }
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            sleep(2);
            exec('sudo kill -9 $(ps aux | grep "cecHdmi_server" | awk \'{print $2}\')');
        }
        //config::save('daemon', '0', 'hdmiCec');
    }

    public static function dependancy_info()
    {
        $return = array();
        $return['log'] = 'hdmiCec_dep';
        $return['progress_file'] = '/tmp/hdmiCec_dep';
        $cecHdmi = realpath(dirname(__FILE__) . '/../../resources/cecHdmi');
        $cecClient = true;
        // TODO tester lexecution de cec-client
        // $a = exec('cec-client -l');
        // $b = print_r($a,true);
        // log::add('hdmiCec', 'info', 'retour de cec-client='.$b);
        if (is_dir($cecHdmi) && ($cecClient) && (config::searchKey('hdmiCecIp', 'hdmiCec')) && (config::searchKey('hdmiCecPort', 'hdmiCec'))) {
            $return['state'] = 'ok';
        } else {
            $return['state'] = 'nok';
        }
        return $return;
    }

    public static function dependancy_install()
    {
        log::add('hdmiCec', 'info', 'Installation des dépendances libCEC');
        $resource_path = realpath(dirname(__FILE__) . '/../../resources');
        passthru('/bin/bash ' . $resource_path . '/install.sh ' . $resource_path . ' > ' . log::getPathToLog('hdmiCec_dep') . ' 2>&1 &');
        if (! config::searchKey('hdmiCecIp', 'hdmiCec'))
            config::save('hdmiCecIp', '127.0.0.1', 'hdmiCec');
        if (! config::searchKey('hdmiCecPort', 'hdmiCec'))
            config::save('hdmiCecPort', '6000', 'hdmiCec');
        if (! config::searchKey('deviceCecType', 'hdmiCec'))
            config::save('deviceCecType', 'RecordingDevice', 'hdmiCec');
    }

    public static function synchronisation()
    {
        log::add('hdmiCec', 'debug', 'synchronisation du daemon cecHdmi');
        if (self::deamon_info()['state'] == 'nok')
            return "Il faut démarrer le serveur pour pouvoir lancer une synchronisation.";
        $resource_path = realpath(dirname(__FILE__) . '/../../resources');
        sleep(1);
        return "Problème : cette fonction n'est pas encore disponible dans cette version du plugin. ";
    }

    public static function detection()
    {
        log::add('hdmiCec', 'info', '******** Début du scan de la connexion hdmi ********');
        if (self::deamon_info()['state'] == 'nok')
            return "Il faut démarrer le serveur pour pouvoir lancer une détection.";
        $count = 0;
        $opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Accept-language: en\r\n" . "Cookie: foo=bar\r\n"
            )
        );
        $context = stream_context_create($opts);
        if (! $file = file_get_contents('http://' . config::byKey('hdmiCecIp', 'hdmiCec', '0') . ':' . config::byKey('hdmiCecPort', 'hdmiCec', '0') . '/scan', false, $context)) {
            return "Problème de détection : pas de réponse du serveur - vérifier que votre serveur est bien démarré ou regarder ses logs";
        }
        $result = json_decode($file);
        $count = 0;
        foreach ($result as $equipment) {
            $count ++;
            $a = '';
            $vendor = '';
            $logicalAddress = '';
            $physicalAddress = '';
            $osdName = '';
            $cecVersion = '';
            foreach ($equipment as $key => $value) {
                $a .= $key . '=' . $value . ' ';
                switch ($key) {
                    case 'vendor':
                        $vendor = $value;
                        break;
                    case 'logicalAddress':
                        $logicalAddress = $value;
                        break;
                    case 'physicalAddress':
                        $physicalAddress = $value;
                        break;
                    case 'osdName':
                        $osdName = $value;
                        break;
                    case 'cecVersion':
                        $cecVersion = $value;
                        break;
                    case 'power':
                        $power = $value;
                }
            }
            log::add('hdmiCec', 'debug', 'Equipement trouvé : ' . $a);
            self::saveEquipment($vendor, $logicalAddress, $physicalAddress, $osdName, $cecVersion, $power);
        }
        log::add('hdmiCec', 'info', '******** Fin du scan de la connexion hdmi - nombre d\'équipements trouvés = ' . $count . ' ********');
    }

    public static function saveEquipment($vendor, $logicalAddress, $physicalAddress, $osdName, $cecVersion, $power)
    {
        log::add('hdmiCec', 'debug', 'Début saveEquipment =' . $logicalAddress);
        $vendor = init('vendor', $vendor);
        $logicalAddress = init('logicalAddress', $logicalAddress);
        $physicalAddress = init('physicalAddress', $physicalAddress);
        $osdName = init('osdName', $osdName);
        $cecVersion = init('cecVersion', $cecVersion);
        // $id = $osdName.'-'.$vendor.'-'.$logicalAddress.'-'.$physicalAddress.'-'.$cecVersion;
        $id = $logicalAddress;
        log::add('hdmiCec', 'debug', 'adresse logique de l\'équipement détecté : ' . $id);
        $elogic = self::byLogicalId($id, 'hdmiCec');
        if (is_object($elogic)) {
            log::add('hdmiCec', 'debug', 'Equipement déjà existant - mise à jour des informations de l\'équipement détecté : ' . $id);
            $save = false;
            if ($elogic->getConfiguration('vendor', '') != $vendor) {
                $elogic->setConfiguration('vendor', $vendor);
                $save = true;
            }
            if ($elogic->getConfiguration('logicalAddress', '') != $logicalAddress) {
                $elogic->setConfiguration('logicalAddress', $logicalAddress);
                $save = true;
            }
            if ($elogic->getConfiguration('physicalAddress', '') != $physicalAddress) {
                $elogic->setConfiguration('physicalAddress', $physicalAddress);
                $save = true;
            }
            if ($elogic->getConfiguration('osdName', '') != $osdName) {
                $elogic->setConfiguration('osdName', $osdName);
                $save = true;
            }
            if ($elogic->getConfiguration('cecVersion', '') != $cecVersion) {
                $elogic->setConfiguration('cecVersion', $cecVersion);
                $save = true;
            }
            $statusCmd = $elogic->getCmd(null, 'status');
            log::add('hdmiCec', 'debug', 'Valeur du status de l\'équipement existant :' . $statusCmd->getValue() . ' - power=' . $power);
            if ($statusCmd->getValue() != $value = self::convertStatus($power)) {
                log::add('hdmiCec', 'debug', 'Mise à jour du status de l\'équipement existant :' . self::convertStatus($power) . ' au lieu de ' . $statusCmd->getValue());
                $statusCmd->setValue(self::convertStatus($power));
                $statusCmd->save();
            }
            if ($save) {
                // log::add('hdmiCec', 'debug', 'Avant mise à jour d\'un équipement existant :' . $elogic->getName());
                $elogic->save();
                log::add('hdmiCec', 'debug', 'Après mise à jour d\'un équipement existant :' . $elogic->getName());
            } else {
                log::add('hdmiCec', 'debug', 'Aucune mise à jour à apporter à cet équipement existant :' . $elogic->getName());
            }
        } else {
            $equipment = new hdmiCec();
            $equipment->setEqType_name('hdmiCec');
            $equipment->setLogicalId($id);
            $equipment->setConfiguration('vendor', $vendor);
            $equipment->setConfiguration('logicalAddress', $logicalAddress);
            $equipment->setConfiguration('physicalAddress', $physicalAddress);
            $equipment->setConfiguration('osdName', $osdName);
            $equipment->setConfiguration('cecVersion', $cecVersion);
            $name = $osdName . ' - ' . $vendor;
            $newName = $name;
            log::add('hdmiCec', 'debug', 'Choix a priori du nom de cet équipement :' . $name);
            $i = 1;
            while (self::byObjectNameEqLogicName(__('Aucun', __FILE__), $newName)) {
                $newName = $name . ' - ' . $i ++;
            }
            $equipment->setName($newName);
            log::add('hdmiCec', 'debug', 'Choix du nom de cet équipement :' . $newName);
            $equipment->setIsEnable(true);
            $equipment->setIsVisible(true);
            $equipment->save();
            log::add('hdmiCec', 'debug', 'Ajout d\'un nouvel équipement :' . $equipment->getName() . ' - LogicalId=' . $id);
        }
    }

    public static function convertStatus($value)
    {
        switch ($value) {
            case 'On':
            case 'on':
                return 2;
                ;
                break;
            case 'Standby':
            case 'standby':
            case 'Standby/Mute':
                return 1;
                break;
            case 'Off':
            case 'off':
            case 'unknown':
                return 0;
                break;
            default:
                log::add('hdmiCec', 'warning', 'Valeur du status imprévue : ' . $value . ' - conversion numérique à -1 - il faudrait corriger le plugin.');
                return - 1;
                break;
        }
    }

    public static function event()
    {
        $value = init('value');
        log::add('hdmiCec', 'debug', 'Received : ' . $value);
        
        $event = json_decode($value, true);
        // $a = print_r($event,true);
        // log::add('hdmiCec','debug','Dump event='.$a);
        $changed = false;
        if (! isset($event["logicalAddress"])) {
            log::add('hdmiCec', 'warning', 'Evénement reçu sans information nommée logicalAddress. Impossible de le traiter : ' . $value);
            return;
        }
        
        if (! $eqLogic = eqLogic::byLogicalId($event["logicalAddress"], 'hdmiCec')) {
            log::add('hdmiCec', 'warning', 'Evénement reçu pour un équipement : ' . $event["logicalAddress"] . ' inexistant : abandon de l\'événement. Vérifier vos équipements HDMI');
            return;
        }
        
        foreach ($event as $key => $value) {
            if ($key == 'logicalAddress')
                continue;
            log::add('hdmiCec', 'debug', 'Decoded received frame for: ' . $eqLogic->getName() . ' logicalid: ' . $eqLogic->getLogicalId() . ' - ' . $key . '=' . $value);
            $cmd = $eqLogic->getCmd(null, $key);
            if (is_object($cmd)) {
                if ($key == 'status') {
                    // si c'est un passage à On et que c'est un vrai (pas le controleur jeedom)Tuner lié à un autre équipement, alors le On est dégradé en Standby, et c'est un start to transmit qui le fera passer à On
                    if (($value == 'On') and (strpos($eqLogic->getLogicalId(), 'Tuner') !== false) and ($eqLogic->getConfiguration('osdName') != config::byKey('hdmiCecOsdName', 'hdmiCec', 'Jeedom'))) {
                        // c'est un vrai tuner
                        log::add('hdmiCec', 'debug', 'C\'est un vrai Tuner (pas Jeedom) - on examine le status');
                        $configuration = '"physicalAddress":"' . $eqLogic->getConfiguration("physicalAddress") . '"';
                        $eqLogicBounds = eqLogic::byTypeAndSearhConfiguration('hdmiCec', $configuration);
                        foreach ($eqLogicBounds as $eqLogicBound) {
                            if ($eqLogic->getName() == $eqLogicBound->getName())
                                continue;
                            log::add('hdmiCec', 'debug', 'On passe le status à Standby au lieu de On car équipement lié trouvé - nom: ' . $eqLogicBound->getName() . ' LogicalId=' . $eqLogicBound->getLogicalId());
                            $status = 'Standby';
                        }
                    }
                    $value = self::convertStatus($value);
                }
                //TODO il semble que l'instruction $cmd->execCmd(null,2) ne représente pas toujours l'ancienne valeur
                log::add('hdmiCec', 'debug', 'Comparaison avec la situation précédente - maintenant : ' . $value . ' et avant : ' . $cmd->execCmd(null, 2));
                
                if ($value != $cmd->execCmd(null, 2)) {
                    $cmd->setCollectDate('');
                    $cmd->event($value);
                    $changed = true;
                }
                
                // traitement des cas particuliers
                
                // passe à On ou Standby les équipements à Off qui émettent des événements ( TV => standby, autres équipements On)
                // Off veut dire éteint, c'est different de Standby (en veille)
                if (! (($key == 'status') and ($value == self::convertStatus("Off")))) {
                    log::add('hdmiCec', 'debug', 'Ce n\'est pas un passage à Off vérif status actuel for: ' . $eqLogic->getName() . ' - ' . $key . '=' . $value);
                    if ($key != 'status') {
                        if ($cmd = $eqLogic->getCmd(null, 'status')) {
                            log::add('hdmiCec', 'debug', 'Ce n\'est pas une commande de changement de status et voici le status actuel = ' . $cmd->execCmd(null, 2));
                            if ($cmd->execCmd(null, 2) == self::convertStatus('Off')) {
                                
                                log::add('hdmiCec', 'debug', 'On passe par déduction l\'équipement de Off à Standby puisqu\'il dialogue. Il est erroné de penser qu\'il est on. On pourrait lancer un polling pour lever le doute.');
                                $cmd->event(self::convertStatus('Standby'));
                                $changed = true;
                                
                                // Est-ce qu'il a un équipement lié à passer en standby ?$eqType_name = 'hdmiCec';
                                $configuration = '"physicalAddress":"' . $eqLogic->getConfiguration("physicalAddress") . '"';
                                $eqLogicBounds = eqLogic::byTypeAndSearhConfiguration('hdmiCec', $configuration);
                                foreach ($eqLogicBounds as $eqLogicBound) {
                                    if ($eqLogic->getName() == $eqLogicBound->getName())
                                        continue;
                                    log::add('hdmiCec', 'debug', 'Equipement trouvé lié - nom: ' . $eqLogicBound->getName() . ' LogicalId=' . $eqLogicBound->getLogicalId());
                                    if ($cmd = $eqLogicBound->getCmd(null, 'status')) {
                                        log::add('hdmiCec', 'debug', 'Cet équipement lié a ce status = ' . $cmd->execCmd(null, 2));
                                        if ($cmd->execCmd(null, 2) == self::convertStatus('Off')) {
                                            log::add('hdmiCec', 'debug', 'On passe aussi l\'équipement lié à Standby');
                                            $cmd->event(self::convertStatus('Standby'));
                                            $changed = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // si status passe à Standby et qu'il existe equipement lié, les passer à Standby sauf si c'est un tuner qui passe à Standby (cas particulier du cas particulier...)
                if ((($key == 'status') and ($value == self::convertStatus('Standby')) and (strpos($eqLogic->getLogicalId(), 'Tuner') === false))) {
                    log::add('hdmiCec', 'debug', 'Un appareil autre qu\'un Tuner vient de passer à Standby ' . $eqLogic->getName() . ' - ' . $key . '=' . $value);
                    
                    $configuration = '"physicalAddress":"' . $eqLogic->getConfiguration("physicalAddress") . '"';
                    $eqLogicBounds = eqLogic::byTypeAndSearhConfiguration('hdmiCec', $configuration);
                    foreach ($eqLogicBounds as $eqLogicBound) {
                        if ($eqLogic->getName() == $eqLogicBound->getName())
                            continue;
                        log::add('hdmiCec', 'debug', ' équipement différent trouvé lié - nom: ' . $eqLogicBound->getName() . ' LogicalId=' . $eqLogicBound->getLogicalId());
                        
                        if ($cmd = $eqLogicBound->getCmd(null, 'status')) {
                            log::add('hdmiCec', 'debug', 'Cet équipement lié a ce status = ' . $cmd->execCmd(null, 2));
                            if ($cmd->execCmd(null, 2) != self::convertStatus('Standby')) {
                                log::add('hdmiCec', 'debug', 'Equipement lié passé aussi à Standby');
                                $cmd->event(self::convertStatus('Standby'));
                                $changed = true;
                            }
                        }
                    }
                } else {
                    log::add('hdmiCec', 'debug', 'Ce n\'est pas un passage à Standby: ' . $key . '=' . $value . ' - ou c\'est un Tuner :' . $eqLogic->getLogicalId() . ' - strpos =' . strpos($eqLogic->getLogicalId(), "Tuner"));
                }
                
                // passe le tuner à on et l'ampli sur tuner si même adresse physique ampli et tuner
                log::add('hdmiCec', 'debug', 'Recherche du mot clé Tuner dans LogicalId de équipement :' . $eqLogic->getLogicalId() . ' (0=vrai): ' . strpos($eqLogic->getLogicalId(), 'Tuner'));
                if ((($key == 'info') and ($value == 'started to transmit') and (strpos($eqLogic->getLogicalId(), 'Tuner') !== false))) {
                    log::add('hdmiCec', 'debug', 'Un tuner vient d etre allumé ' . $eqLogic->getName() . ' - ' . $key . '=' . $value);
                    
                    if ($cmd->execCmd(null, 2) != self::convertStatus('On')) {
                        log::add('hdmiCec', 'debug', 'Tuner qui émet, on le passe à On');
                        $cmd->event(self::convertStatus('On'));
                        $changed = true;
                    }
                    
                    if ($eqLogicAudio = eqLogic::byLogicalId('Audio', 'hdmiCec')) {
                        if ($eqLogicAudio->getConfiguration("physicalAddress") == $eqLogic->getConfiguration("physicalAddress")) {
                            log::add('hdmiCec', 'debug', 'ampli trouvé lié au tuner');
                            
                            if ($cmd = $eqLogicAudio->getCmd(null, 'status')) {
                                log::add('hdmiCec', 'debug', 'L ampli a ce status actuel = ' . $cmd->execCmd(null, 2));
                                if ($cmd->execCmd(null, 2) == self::convertStatus('Standby')) {
                                    log::add('hdmiCec', 'debug', 'on le passe à On');
                                    $cmd->event(self::convertStatus('On'));
                                    $changed = true;
                                }
                            }
                            // passer l'input de l'ampli à Tuner !
                            if ($cmd = $eqLogicAudio->getCmd(null, 'input')) {
                                if ($cmd->execCmd(null, 2) != 'Tuner') {
                                    log::add('hdmiCec', 'debug', 'on passe entrée ampli sur Tuner');
                                    $cmd->event('Tuner');
                                    $changed = true;
                                }
                            }
                        } else {
                            log::add('hdmiCec', 'debug', 'aucun ampli est lié au tuner');
                        }
                    }
                }
                
                // inversement passer le tuner sur standby si l'entrée choisie pour l'ampli n'est pas tuner
                if ((($key == 'input') and ($value != 'Tuner') and ($eqLogic->getLogicalId() == 'Audio'))) {
                    log::add('hdmiCec', 'debug', 'Un ampli vient de passer sur une entrée différente de Tuner ' . $eqLogic->getName() . ' - ' . $key . '=' . $value);
                    // par sécurité on vérifie que l'ampli est bien à On
                    if ($cmd = $eqLogic->getCmd(null, 'status')) {
                        log::add('hdmiCec', 'debug', 'L ampli a ce status actuel = ' . $cmd->execCmd(null, 2));
                        if ($cmd->execCmd(null, 2) != self::convertStatus('On')) {
                            log::add('hdmiCec', 'debug', 'on passe l\'ampli à On');
                            $cmd->event(self::convertStatus('On'));
                            $changed = true;
                        }
                    }
                    
                    $configuration = '"physicalAddress":"' . $eqLogic->getConfiguration("physicalAddress") . '"';
                    $eqLogicBounds = eqLogic::byTypeAndSearhConfiguration('hdmiCec', $configuration);
                    foreach ($eqLogicBounds as $eqLogicBound) {
                        if ($eqLogic->getName() == $eqLogicBound->getName())
                            continue;
                        log::add('hdmiCec', 'debug', 'Equipement lié trouvé - nom: ' . $eqLogicBound->getName() . ' LogicalId=' . $eqLogicBound->getLogicalId());
                        if (strpos($eqLogicBound->getLogicalId(), 'Tuner') !== false) {
                            log::add('hdmiCec', 'debug', 'Tuner trouvé lié à ampli ' . $eqLogicBound->getName());
                            
                            if ($cmd = $eqLogicBound->getCmd(null, 'status')) {
                                log::add('hdmiCec', 'debug', 'Le tuner a ce status = ' . $cmd->execCmd(null, 2));
                                if ($cmd->execCmd(null, 2) != self::convertStatus('Standby')) {
                                    log::add('hdmiCec', 'debug', 'Equipement lié passé à Standby');
                                    $cmd->event(self::convertStatus('Standby'));
                                    $changed = true;
                                }
                            }
                        } else {
                            log::add('hdmiCec', 'debug', 'Equipement trouvé lié à ampli différent d\'un Tuner. On ne fait rien');
                        }
                    }
                }
            } else {
                log::add('hdmiCec', 'warning', 'Cmd not found for the received frame for: ' . $eqLogic->getName() . ' - ' . $key . '=' . $value);
            }
        }
        
        if ($changed) {
            $eqLogic->refreshWidget();
        }
    }

    /**
     * ************************ Pile de mise à jour *************************
     */
    
    /*
     * fonction permettant d'initialiser la pile
     * plugin: le nom de votre plugin
     * action: l'action qui sera utilisé dans le fichier ajax du plugin
     * callback: fonction appelé coté client(JS) pour mettre à jour l'affichage
     */
    public function initStackData()
    {
        log::add('hdmiCec', 'debug', '!!!!!!!!!!!!!!!  initStackData is called ');
        nodejs::pushUpdate('hdmiCec::initStackDataEqLogic', array(
            'plugin' => 'hdmiCec',
            'action' => 'saveStack',
            'callback' => 'displayEqLogic'
        ));
    }

    /*
     * fonnction permettant d'envoyer un nouvel équipement pour sauvegarde et affichage,
     * les données sont envoyé au client(JS) pour être traité de manière asynchrone
     * Entrée:
     * - $params: variable contenant les paramètres eqLogic
     */
    public function stackData($params)
    {}

    /*
     * fonction appelé pour la sauvegarde asynchrone
     * Entrée:
     * - $params: variable contenant les paramètres eqLogic
     */
    public function saveStack($params)
    {
        // inserer ici le traitement pour sauvegarde de vos données en asynchrone
    }

    /* fonction appelé avant le début de la séquence de sauvegarde */
    public function preSave()
    {}

    /*
     * fonction appelé pendant la séquence de sauvegarde avant l'insertion
     * dans la base de données pour une mise à jour d'une entrée
     */
    public function preUpdate()
    {
        $this->setCategory('multimedia', 1);
    }

    /*
     * fonction appelé pendant la séquence de sauvegarde après l'insertion
     * dans la base de données pour une mise à jour d'une entrée
     */
    public function postUpdate()
    {}

    /*
     * fonction appelé pendant la séquence de sauvegarde avant l'insertion
     * dans la base de données pour une nouvelle entrée
     */
    public function preInsert()
    {}

    /*
     * fonction appelé pendant la séquence de sauvegarde après l'insertion
     * dans la base de données pour une nouvelle entrée
     */
    public function postInsert()
    {}

    /* fonction appelée après la fin de la séquence de sauvegarde */
    public function postSave()
    {
        
        // log::add ('hdmiCec','debug','postsave: '.$this->getId());
        
        // création de la commande info sur le status
        $status = $this->getCmd(null, 'status');
        if (! is_object($status)) {
            $status = new hdmiCecCmd();
            $status->setLogicalId('status');
            $status->setIsVisible(1);
            $status->setIsHistorized(1);
            $status->setName(__('Etat', __FILE__));
        }
        $status->setType('info');
        $status->setSubType('numeric');
        $status->setEventOnly(1);
        $status->setEqLogic_id($this->getId());
        $status->save();
        // $status->setUnite('');
        // $status->setConfiguration('onlyChangeEvent',1);
        // TODO est-il nécessaire ? il crée artificiellement un événement à chaque fois qu'on fait une sauvegarde...
        // $status->event("initialisé éteint");
        
        $info = $this->getCmd(null, 'info');
        if (! is_object($info)) {
            $info = new hdmiCecCmd();
            $info->setLogicalId('info');
            $info->setIsVisible(1);
            $info->setName(__('Info', __FILE__));
        }
        $info->setType('info');
        $info->setSubType('string');
        $info->setEventOnly(1);
        $info->setEqLogic_id($this->getId());
        $info->save();
        
        $on = $this->getCmd(null, 'On');
        if (! is_object($on)) {
            $on = new hdmiCecCmd();
            $on->setLogicalId('On');
            $on->setIsVisible(1);
            $on->setName(__('On', __FILE__));
        }
        $on->setType('action');
        // $status->setUnite('');
        // $status->setConfiguration('onlyChangeEvent',1);
        $on->setSubType('other');
        $on->setConfiguration('request', 'on');
        $on->setEqLogic_id($this->getId());
        $on->save();
        
        $off = $this->getCmd(null, 'Off');
        if (! is_object($off)) {
            $off = new hdmiCecCmd();
            $off->setLogicalId('Off');
            $off->setIsVisible(1);
            $off->setName(__('Off', __FILE__));
        }
        $off->setType('action');
        // $status->setUnite('');
        // $status->setConfiguration('onlyChangeEvent',1);
        $off->setSubType('other');
        $off->setConfiguration('request', 'off');
        $off->setEqLogic_id($this->getId());
        $off->save();
        
        // création de la commande info sur input uniquement pour la TV et l'ampli (AUDIO)
        if (in_array($this->getConfiguration('logicalAddress'), array(
            'TV',
            'Audio'
        ))) {
            $input = $this->getCmd(null, 'input');
            if (! is_object($input)) {
                $input = new hdmiCecCmd();
                $input->setLogicalId('input');
                $input->setIsVisible(1);
                $input->setName(__('Entrée', __FILE__));
            }
            $input->setType('info');
            $input->setEventOnly(1);
            // $status->setUnite('');
            // $status->setConfiguration('onlyChangeEvent',1);
            $input->setSubType('string');
            $input->setEqLogic_id($this->getId());
            $input->save();
            // $input->event("initialisée vide");
            
            $channel1 = $this->getCmd(null, 'channel1');
            if (! is_object($channel1)) {
                $channel1 = new hdmiCecCmd();
                $channel1->setLogicalId('channel1');
                $channel1->setIsVisible(1);
                $channel1->setName(__('channel1', __FILE__));
            }
            $channel1->setType('action');
            $channel1->setSubType('other');
            $channel1->setConfiguration('request', 'channel1');
            $channel1->setEqLogic_id($this->getId());
            $channel1->save();
            
            $mute = $this->getCmd(null, 'mute');
            if (! is_object($mute)) {
                $mute = new hdmiCecCmd();
                $mute->setLogicalId('mute');
                $mute->setIsVisible(1);
                $mute->setName(__('mute', __FILE__));
            }
            $mute->setType('action');
            $mute->setSubType('other');
            $mute->setConfiguration('request', 'mute');
            $mute->setEqLogic_id($this->getId());
            $mute->save();
            
            $unmute = $this->getCmd(null, 'unMute');
            if (! is_object($unmute)) {
                $unmute = new hdmiCecCmd();
                $unmute->setLogicalId('unMute');
                $unmute->setIsVisible(1);
                $unmute->setName(__('unMute', __FILE__));
            }
            $unmute->setType('action');
            $unmute->setSubType('other');
            $unmute->setConfiguration('request', 'unMute');
            $unmute->setEqLogic_id($this->getId());
            $unmute->save();
            
            $Up = $this->getCmd(null, 'Up');
            if (! is_object($Up)) {
                $Up = new hdmiCecCmd();
                $Up->setLogicalId('Up');
                $Up->setIsVisible(1);
                $Up->setName(__('Up', __FILE__));
            }
            $Up->setType('action');
            $Up->setSubType('other');
            $Up->setConfiguration('request', 'Up');
            $Up->setEqLogic_id($this->getId());
            $Up->save();
            
            $Down = $this->getCmd(null, 'Down');
            if (! is_object($Down)) {
                $Down = new hdmiCecCmd();
                $Down->setLogicalId('Down');
                $Down->setIsVisible(1);
                $Down->setName(__('Down', __FILE__));
            }
            $Down->setType('action');
            $Down->setSubType('other');
            $Down->setConfiguration('request', 'Down');
            $Down->setEqLogic_id($this->getId());
            $Down->save();
            
            $volUp = $this->getCmd(null, 'volUp');
            if (! is_object($volUp)) {
                $volUp = new hdmiCecCmd();
                $volUp->setLogicalId('volUp');
                $volUp->setIsVisible(1);
                $volUp->setName(__('volUp', __FILE__));
            }
            $volUp->setType('action');
            $volUp->setSubType('other');
            $volUp->setConfiguration('request', 'volUp');
            $volUp->setEqLogic_id($this->getId());
            $volUp->save();
            
            $volDown = $this->getCmd(null, 'volDown');
            if (! is_object($volDown)) {
                $volDown = new hdmiCecCmd();
                $volDown->setLogicalId('volDown');
                $volDown->setIsVisible(1);
                $volDown->setName(__('volDown', __FILE__));
            }
            $volDown->setType('action');
            $volDown->setSubType('other');
            $volDown->setConfiguration('request', 'volDown');
            $volDown->setEqLogic_id($this->getId());
            $volDown->save();
            
            $inputSelect = $this->getCmd(null, 'inputSelect');
            if (! is_object($inputSelect)) {
                $inputSelect = new hdmiCecCmd();
                $inputSelect->setLogicalId('inputSelect');
                $inputSelect->setIsVisible(1);
                $inputSelect->setName(__('inputSelect', __FILE__));
            }
            $inputSelect->setType('action');
            $inputSelect->setSubType('other');
            $inputSelect->setConfiguration('request', 'inputSelect');
            $inputSelect->setEqLogic_id($this->getId());
            $inputSelect->save();
            
            $play = $this->getCmd(null, 'play');
            if (! is_object($play)) {
                $play = new hdmiCecCmd();
                $play->setLogicalId('play');
                $play->setIsVisible(1);
                $play->setName(__('play', __FILE__));
            }
            $play->setType('action');
            $play->setSubType('other');
            $play->setConfiguration('request', 'play');
            $play->setEqLogic_id($this->getId());
            $play->save();
            
            $pause = $this->getCmd(null, 'pause');
            if (! is_object($pause)) {
                $pause = new hdmiCecCmd();
                $pause->setLogicalId('pause');
                $pause->setIsVisible(1);
                $pause->setName(__('pause', __FILE__));
            }
            $pause->setType('action');
            $pause->setSubType('other');
            $pause->setConfiguration('request', 'pause');
            $pause->setEqLogic_id($this->getId());
            $pause->save();
            
            $selectAV = $this->getCmd(null, 'selectAV');
            if (! is_object($selectAV)) {
                $selectAV = new hdmiCecCmd();
                $selectAV->setLogicalId('selectAV');
                $selectAV->setIsVisible(1);
                $selectAV->setName(__('selectAV', __FILE__));
            }
            $selectAV->setType('action');
            $selectAV->setSubType('other');
            $selectAV->setConfiguration('request', 'selectAV');
            $selectAV->setEqLogic_id($this->getId());
            $selectAV->save();
        }
        
        // création des commandes keyPressed et keyReleased uniquement pour Jeedom, Audio , Recorder x et Tuner x
        if (($this->getConfiguration('osdName') == config::byKey('hdmiCecOsdName', 'hdmiCec', 'Jeedom')) or (in_array($this->getConfiguration('logicalAddress'), array(
            'Audio',
            'Recorder 1',
            'Recorder 2',
            'Recorder 3',
            'Tuner 1',
            'Tuner 2',
            'Tuner 3'
        )))) {
            $keyPressed = $this->getCmd(null, 'keyPressed');
            if (! is_object($keyPressed)) {
                $keyPressed = new hdmiCecCmd();
                $keyPressed->setLogicalId('keyPressed');
                $keyPressed->setIsVisible(1);
                $keyPressed->setName(__('Touche appuyée', __FILE__));
            }
            $keyPressed->setType('info');
            $keyPressed->setEventOnly(1);
            // $status->setUnite('');
            // $status->setConfiguration('onlyChangeEvent',1);
            $keyPressed->setSubType('string');
            $keyPressed->setEqLogic_id($this->getId());
            $keyPressed->save();
            
            $keyReleased = $this->getCmd(null, 'keyReleased');
            if (! is_object($keyReleased)) {
                $keyReleased = new hdmiCecCmd();
                $keyReleased->setLogicalId('keyReleased');
                $keyReleased->setIsVisible(1);
                $keyReleased->setName(__('Touche relachée', __FILE__));
            }
            $keyReleased->setType('info');
            $keyReleased->setEventOnly(1);
            // $status->setUnite('');
            // $status->setConfiguration('onlyChangeEvent',1);
            $keyReleased->setSubType('string');
            $keyReleased->setEqLogic_id($this->getId());
            $keyReleased->save();
        }
        
        log::add('hdmiCec', 'debug', '!!!!  cmp ' . $this->getConfiguration('osdName') . ' and ' . config::byKey('hdmiCecOsdName', 'hdmiCec', 'Jeedom'));
        // création des commandes sur le controleur Jeedom uniquement OSD et inputSet
        if ($this->getConfiguration('osdName') == config::byKey('hdmiCecOsdName', 'hdmiCec', 'Jeedom')) {
            log::add('hdmiCec', 'debug', '!!!!  this is jeedom');
            $osd = $this->getCmd(null, 'osd');
            if (! is_object($osd)) {
                $osd = new hdmiCecCmd();
                $osd->setLogicalId('OSD');
                $osd->setIsVisible(1);
                $osd->setName(__('OSD', __FILE__));
            }
            $osd->setType('action');
            $osd->setSubType('message');
            $osd->setConfiguration('request', 'osd');
            $osd->setEqLogic_id($this->getId());
            $osd->save();
            
            $input = $this->getCmd(null, 'setInput');
            if (! is_object($input)) {
                $input = new hdmiCecCmd();
                $input->setLogicalId('setInput');
                $input->setIsVisible(1);
                $input->setName(__('setInput', __FILE__));
            }
            $input->setType('action');
            $input->setSubType('message');
            $input->setConfiguration('request', 'setInput');
            $input->setEqLogic_id($this->getId());
            $input->save();
            
            $transmit = $this->getCmd(null, 'transmit');
            if (! is_object($transmit)) {
                $transmit = new hdmiCecCmd();
                $transmit->setLogicalId('transmit');
                $transmit->setIsVisible(1);
                $transmit->setName(__('transmit', __FILE__));
            }
            $transmit->setType('action');
            $transmit->setSubType('message');
            $transmit->setConfiguration('request', 'transmit');
            $transmit->setEqLogic_id($this->getId());
            $transmit->save();
            
            $startPolling = $this->getCmd(null, 'startPolling');
            if (! is_object($startPolling)) {
                $startPolling = new hdmiCecCmd();
                $startPolling->setLogicalId('startPolling');
                $startPolling->setIsVisible(1);
                $startPolling->setName(__('startPolling', __FILE__));
            }
            $startPolling->setType('action');
            $startPolling->setSubType('other');
            $startPolling->setConfiguration('request', 'startPolling');
            $startPolling->setEqLogic_id($this->getId());
            $startPolling->save();
            
            $stopPolling = $this->getCmd(null, 'stopPolling');
            if (! is_object($stopPolling)) {
                $stopPolling = new hdmiCecCmd();
                $stopPolling->setLogicalId('stopPolling');
                $stopPolling->setIsVisible(1);
                $stopPolling->setName(__('stopPolling', __FILE__));
            }
            $stopPolling->setType('action');
            $stopPolling->setSubType('other');
            $stopPolling->setConfiguration('request', 'stopPolling');
            $stopPolling->setEqLogic_id($this->getId());
            $stopPolling->save();
            
            $scan = $this->getCmd(null, 'scan');
            if (! is_object($scan)) {
                $scan = new hdmiCecCmd();
                $scan->setLogicalId('scan');
                $scan->setIsVisible(1);
                $scan->setName(__('scan', __FILE__));
                log::add('hdmiCec', 'debug', 'postsave: add scan command ' . $this->getId());
            }
            $scan->setType('action');
            $scan->setSubType('other');
            $scan->setConfiguration('request', 'scan');
            $scan->setEqLogic_id($this->getId());
            log::add('hdmiCec', 'debug', 'postsave: upd scan command ' . $this->getId());
            $scan->save();
            
            $dump = $this->getCmd(null, 'dump');
            if (! is_object($dump)) {
                $dump = new hdmiCecCmd();
                $dump->setLogicalId('dump');
                $dump->setIsVisible(1);
                $dump->setName(__('dump', __FILE__));
                log::add('hdmiCec', 'debug', 'postsave: add dump command ' . $this->getId());
            }
            $dump->setType('action');
            $dump->setSubType('other');
            $dump->setConfiguration('request', 'dump');
            $dump->setEqLogic_id($this->getId());
            log::add('hdmiCec', 'debug', 'postsave: upd dump command ' . $this->getId());
            $dump->save();
        }
    }

    /* fonction appelé avant l'effacement d'une entrée */
    public function preRemove()
    {}

    /* fonnction appelé après l'effacement d'une entrée */
    public function postRemove()
    {}

    public function toHtml1($_version = 'dashboard')
    {
        if ($this->getIsEnable() != 1) {
            return '';
        }
        if (! $this->hasRight('r')) {
            return '';
        }
        $_version = jeedom::versionAlias($_version);
        
        return template_replace($replace, getTemplate('core', $_version, 'eqLogic', 'hdmiCec'));
    }
    
    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
     * public function toHtml($_version = 'dashboard') {
     *
     * }
     */
    
    /* * **********************Getteur Setteur*************************** */
}

define('Channel1', '21');
define('ChannelUp', '30');
define('ChannelDown', '31');
define('InputSelect', '34');
define('VolUp', '41');
define('VolDown', '42');
define('Mute', '43');
define('Play', '44');
define('Pause', '46');
define('UnMute', '65');
define('SelectAV', '69');

class hdmiCecCmd extends cmd
{

    /**
     * ***************************** Attributs ******************************
     */
    /* Ajouter ici toutes vos variables propre à votre classe */
    
    /**
     * *************************** Methode static ***************************
     */
    
    /**
     * ************************* Methode d'instance *************************
     */
    
    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
     * public function dontRemoveCmd() {
     * return true;
     * }
     */
    public function execute($_options = array())
    {
        // $b=print_r($_options,true);
        // log::add ('hdmiCec','debug','Commande reçue à exécuter : '.$this->getConfiguration('request').' de type '.$this->type.' paramètres ='.$b);
        $hdmiCec = $this->getEqLogic();
        $action = $this->getConfiguration('request');
        switch ($action) {
            case "channel1":
                $action = 'transmit';
                $_options['title'] = 'TV';
                $_options['message'] = '44:' . Channel1;
                break;
            case "Up":
                $action = 'transmit';
                $_options['title'] = 'TV';
                $_options['message'] = '44:' . ChannelUp;
                break;
            case "Down":
                $action = 'transmit';
                $_options['title'] = 'TV';
                $_options['message'] = '44:' . ChannelDown;
                break;
            case "volUp":
                $action = 'transmit';
                $_options['title'] = 'TV';
                $_options['message'] = '44:' . VolUp;
                break;
            case "volDown":
                $action = 'transmit';
                $_options['title'] = 'TV';
                $_options['message'] = '44:' . VolDown;
                break;
            case "mute":
                $action = 'transmit';
                $_options['title'] = 'TV';
                $_options['message'] = '44:' . Mute;
                break;
            case "unMute":
                $action = 'transmit';
                $_options['title'] = 'TV';
                $_options['message'] = '44:' . UnMute;
                // $_options['message'] = '44:65';
                break;
            case "inputSelect":
                $action = 'transmit';
                $_options['title'] = 'TV';
                $_options['message'] = '44:' . InputSelect;
                break;
            case "selectAV":
                $action = 'transmit';
                $_options['title'] = 'TV';
                $_options['message'] = '44:' . SelectAV;
                break;
        }
        $b = print_r($_options, true);
        log::add('hdmiCec', 'debug', 'Commande reçue à exécuter : ' . $this->getConfiguration('request') . ' de type ' . $this->type . ' paramètres =' . $b);
        // $cmd = '/usr/bin/python ' .dirname(__FILE__) . '/../../3rdparty/executer_action.py '. $ip .' '. $port . ' '.$user.' '.$pass.' '.$device.' '.$action;
        // log::add('hdmiCec','debug','Commande reçue : ' . $action);
        $opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Accept-language: en\r\n" . "Cookie: foo=bar\r\n"
            )
        );
        $context = stream_context_create($opts);
        // pour éviter des logs intempestifs quand on cherche à arrêter un serveur déjà arrêté.. @
        if (isset($_options['title'])) {
            @$file = file_get_contents('http://' . config::byKey('hdmiCecIp', 'hdmiCec', '0') . ':' . config::byKey('hdmiCecPort', 'hdmiCec', '0') . '/' . rawurlencode($action) . '?address=' . rawurlencode($_options['title'] . '&parameter=' . rawurlencode($_options['message'])), false, $context);
        } else {
            @$file = file_get_contents('http://' . config::byKey('hdmiCecIp', 'hdmiCec', '0') . ':' . config::byKey('hdmiCecPort', 'hdmiCec', '0') . '/' . rawurlencode($action) . '?address=' . rawurlencode($hdmiCec->getLogicalId()), false, $context);
        }
        
        log::add('hdmiCec', 'debug', 'Execution de la commande  terminée ');
    }

/**
 * *************************** Getteur/Setteur **************************
 */
}

?>
