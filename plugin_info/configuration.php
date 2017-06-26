<?php
/* This file is part of Jeedom.
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
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<!--------------------- EXEMPLE DE PAGE DE CONFIGURATION ------------------------------>
<form class="form-horizontal">
    <fieldset>
    	<div class="form-group">
            <label class="col-lg-4 control-label">{{Adresse IP du serveur connecté HDMI}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="hdmiCecIp" value="127.0.0.1" placeholder="{{adr Ip}}"/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Port du serveur}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="hdmiCecPort" value="6000" placeholder="{{n° port}}"/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Nom (OSD)}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="hdmiCecOsdName" value="Jeedom" placeholder="{{nom à afficher (OSD)}}"/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Adresse logique CEC}}</label>
            <div class="col-lg-2">
                <select class="configKey form-control" data-l1key="deviceCecType">
                    <option value="RecordingDevice">Recording Device</option>
                    <option value="TunerDevice">Tuner Device</option>
                    <option value="PaybackDevice">Payback Device</option>
                    <option value="AudioSystemDevice">Audio System Device</option>
                </select>
                <!--
                	<option value="Recorder 1">Recorder 1</option>
                    <option value="Recorder 2">Recorder 2</option>
                    <option value="Tuner 1">Tuner 1</option>
                    <option value="Playback 1">Playback 1</option>
                    <option value="Audio">Audio</option>
                    <option value="Tuner 2">Tuner 2</option>
                    <option value="Tuner 3">Tuner 3</option>
                    <option value="Playback 2">Playback 2</option>
                    <option value="Recorder 3">Recorder 3</option>
                    <option value="Tuner 4">Tuner 4</option>
                    <option value="Playback 3">Playback 3</option>
                    <option value="Reserved 1">Reserved 1</option>
                    <option value="Reserved 2">Reserved 2</option>
                    <option value="Free use">Free use</option>
                 -->
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Inclusion automatique}}</label>
            <div class="col-lg-2">
            	<input type="checkbox" class="configKey form-control bootstrapSwitch" data-label-text="{{Activer}}" data-l1key="inclusionEnable" checked/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{}}</label>
            <div class="col-lg-2">
              <a class="btn btn-default" id="bt_synchdmiCec"><i class="fa fa-retweet"></i> Synchroniser</a>
          </div>
      </div>
      <div class="form-group">
            <label class="col-lg-4 control-label">{{}}</label>
            <div class="col-lg-2">
              <a class="btn btn-default" id="bt_detecthdmiCec"><i class="fa fa-retweet"></i> Détection</a>
          </div>
      </div>
  </fieldset>
</form>

<script>
$('#bt_synchdmiCec').on('click',function(){
 	$.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/hdmiCec/core/ajax/hdmiCec.ajax.php", // url du fichier php
            data: {
                action: "synchronisation",
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
         		$('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: '{{Synchronisation réussie}}', level: 'success'});
        }
    });
});
$('#bt_detecthdmiCec').on('click',function(){
 	$.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/hdmiCec/core/ajax/hdmiCec.ajax.php", // url du fichier php
            data: {
                action: "detection",
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: '{{Détection terminée : consulter les logs (pas ceux du démon, mais du plugin) pour connaître le résultat}}', level: 'success'});
        }
    });
});

</script>

<!--------------------- EXEMPLE DE PAGE DE CONFIGURATION ------------------------------>