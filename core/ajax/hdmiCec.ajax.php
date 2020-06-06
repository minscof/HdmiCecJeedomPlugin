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
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');
    
    if (! isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }
    // action qui permet d'obtenir l'ensemble des eqLogic
    if (init('action') == 'getAll') {
        $eqLogics = eqLogic::byType('hdmiCec');
        // la liste des équipements
        $return = array();
        foreach ($eqLogics as $eqLogic) {
            $data['id'] = $eqLogic->getId();
            $data['humanSidebar'] = $eqLogic->getHumanName(true, false);
            $data['humanContainer'] = $eqLogic->getHumanName(true, true);
            $return[] = $data;
        }
        ajax::success($return);
    }
    // action qui permet d'effectuer la sauvegarde des données en asynchrone
    if (init('action') == 'saveStack') {
        $params = init('params');
        ajax::success(hdmiCec::saveStack($params));
    }
    
    // action qui permet d'effectuer la sauvegarde des données en asynchrone
    if (init('action') == 'synchronisation') {
        $params = init('params');
        $result = hdmiCec::synchronisation($params);
        if (! $result) {
            ajax::success();
        } else {
            ajax::error($result);
        }
    }
    
    // action qui permet d'effectuer la sauvegarde des données en asynchrone
    if (init('action') == 'detection') {
        $params = init('params');
        $result = hdmiCec::detection($params);
        if (! $result) {
            ajax::success();
        } else {
            ajax::error($result);
        }
    }
    
    if (init('action') == 'modelList') {
        $json = array();
        $id_periph = init('id_periph');
        if (! empty($id_periph)) {
            $handle = fopen("../../resources/" . $id_periph . ".txt", "r");
        } else {
            $handle = False;
        }
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                $buffer = rtrim($buffer);
                if ($buffer != "" and ! strpos($buffer, ";", 1)) {
                    $result = "Le fichier resources/" . $id_periph . ".txt est incorrect : le séparateur à utiliser est ; . Il est absent de cette ligne : " . $buffer;
                    log::add('hdmiCec', 'warning', $result);
                    ajax::error($result);
                    return;
                }
                list ($donnees['id'], $donnees['nom']) = explode(";", $buffer);
                // log::add('hdmiCec', 'debug', 'index='.$donnees['id'].' data='.$donnees['nom'].' ligne='.$buffer);
                $json[$donnees['id']][] = utf8_encode($donnees['nom']);
            }
            if (! feof($handle)) {
                $result = "Erreur: fgets() a échoué\n";
                ajax::error($result);
                return;
            }
            fclose($handle);
        }
        /*
         * switch ($id_periph) {
         * case 'RPI' :
         * $donnees['id']='raspberryPi1';
         * $donnees['nom']='Raspberry Pi 1';
         * $json[$donnees['id']][] = utf8_encode($donnees['nom']);
         * $donnees['id']='raspberryPi2';
         * $donnees['nom']='Raspberry Pi 2';
         * $json[$donnees['id']][] = utf8_encode($donnees['nom']);
         * $donnees['id']='raspberryPi3';
         * $donnees['nom']='Raspberry Pi 3';
         * $json[$donnees['id']][] = utf8_encode($donnees['nom']);
         * break;
         * case 'TV' :
         * $donnees['id']='sonyKDL-EX500';
         * $donnees['nom']='Sony KDL EX500';
         * $json[$donnees['id']][] = utf8_encode($donnees['nom']);
         * break;
         * case 'TUN' :
         * $donnees['id']='sonySTR-DN840';
         * $donnees['nom']='Sony STR DN840';
         * $json[$donnees['id']][] = utf8_encode($donnees['nom']);
         * break;
         * case 'AMP' :
         * $donnees['id']='sonySTR-DN840';
         * $donnees['nom']='Sony STR DN840';
         * $json[$donnees['id']][] = utf8_encode($donnees['nom']);
         * break;
         * case 'DVD' :
         * $donnees['id']='sonyDVD-1000';
         * $donnees['nom']='Sony DVD1000';
         * $json[$donnees['id']][] = utf8_encode($donnees['nom']);
         * break;
         * default :
         * }
         */
        log::add('hdmiCec', 'debug', 'modelList =' . json_encode($json));
        ajax::success(json_encode($json));
    }
    
    if (init('action') == 'confModel') {
        if (init('id') != '') {
            $eqlogic = hdmiCec::byId(init('id'));
            ajax::success($eqlogic->getConfiguration('model'));
        } else {
            ajax::success('');
        }
    }
    
    throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
    /* * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>