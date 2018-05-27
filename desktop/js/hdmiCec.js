
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



/*
 * Fonction pour l'ajout de commande, appelée automatiquement par plugin.template
 */

/* Fonction appelé pour mettre l'affichage du tableau des commandes de votre eqLogic
 * _cmd: les détails de votre commande
 */
/* global jeedom */


$("#sel_periphType").change(function() {
	// alert($(.li_eqLogic.active).getAttribute["data-eqLogic_id"]);
	nodeId = $('.li_eqLogic.active').attr("data-eqLogic_id");
	//test2();
	$.ajax({// fonction permettant de faire de l'ajax
		type : "POST", // méthode de transmission des données au fichier php
		url : "plugins/hdmiCec/core/ajax/hdmiCec.ajax.php", // url du fichier
															// php
		data : {
			action : "modelList",
			id_periph : $(this).val(),
		},
		dataType : 'json',
		global : false,
		error : function(request, status, error) {
			handleAjaxError(request, status, error);
		},
		success : function(data) { // si l'appel a bien fonctionné
			if (data.state != 'ok') {
				$('#div_alert').showAlert({
					message : data.result,
					level : 'danger'
				});
				return;
			}
			var options = '';
			/*for ( var i in data.result) {
				var value = data.result[i]['value'];
				options += '<option value="' + i + '">' + value + '</option>';
			}*/

			$.each(JSON.parse(data.result), function(index, value) {
				options += '<option value="'+ index +'">'+ value +'</option>';
            });
			$("#sel_model").html(options);
			modifyWithoutSave = false;
			$.ajax({// fonction permettant de faire de l'ajax
				type : "POST", // méthode de transmission des données au
								// fichier php
				url : "plugins/hdmiCec/core/ajax/hdmiCec.ajax.php", // url du
																	// fichier
																	// php
				data : {
					action : "confModel",
					id : nodeId,
				},
				dataType : 'json',
				global : false,
				error : function(request, status, error) {
					handleAjaxError(request, status, error);
				},
				success : function(data) { // si l'appel a bien fonctionné
					if (data.state != 'ok') {
						$('#div_alert').showAlert({
							message : data.result,
							level : 'danger'
						});
						return;
					}
					$("#sel_model").value(data.result);
					modifyWithoutSave = false;
				}
			});
		}
	});
});



$('.eqLogicAttr[data-l1key=configuration][data-l2key=model]').on('change',function(){
	if (!$(this).value()) return;
	// alert('onchange model='+$(this).value());
	if ($(this).value()){
		$('#img_hdmiCecModel').attr('src','plugins/hdmiCec/core/template/images/'+$(this).value()+'.jpg');
	}
});

/*
$('.eqLogicAttr[data-l1keyOLP=configuration][data-l2key=periphTypeOLD]').on('change', function() {
	if (!$(this).value()) return;
	alert('onchange periph='+$(this).value());
	
    var val = $(this).val(); // on récupère la valeur du périphérique

    if(val != '') { 
        $.ajax({
            url: 'plugins/hdmiCec/core/ajax/hdmiCec.ajax.php',
            data: {
            	action: "modelList",
            	id_periph: val 
            },
            dataType: 'json',
            error: function (request, status, error) {
            	handleAjaxError(request, status, error);
            },
            success: function(data) {
            	if (data.state != 'ok') {
                	$('#div_alert').showAlert({message: data.result, level: 'danger'});
                	return;
                }
            	//attention il faut sauvegarder la valeur sélectionnée avant de vider la liste !
            	eq=$('.eqLogicAttr[data-eqLogic_id]').val();
            	test2();
            	save = $('.eqLogicAttr[data-l1key=configuration][data-l2key=model] option:selected').val();
            
            	alert('valeur choisie avant effacement='+save);
            	$('.eqLogicAttr[data-l1key=configuration][data-l2key=model]').empty(); // on vide la liste des modèles
                $.each(JSON.parse(data.result), function(index, value) {
                	$('.eqLogicAttr[data-l1key=configuration][data-l2key=model]').append('<option value="'+ index +'">'+ value +'</option>');
                });
                //on restaure
                //on force la mise à jour de l'image
                
                if ($('.eqLogicAttr[data-l1key=configuration][data-l2key=model]').value()) {
                	//alert('image='+$('.eqLogicAttr[data-l1key=configuration][data-l2key=model]').value())
                	$('#img_hdmiCecModel').attr('src','plugins/hdmiCec/core/template/images/'+$('.eqLogicAttr[data-l1key=configuration][data-l2key=model]').value()+'.jpg');
                }
                test2();
            }
        });
    }
});
*/
  

function test2(){
        console.trace();
    }


function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" ></span>';
    tr += '</td>';
    tr += '<td>';
    tr += '<span class="cmdAttr form-control input-sm" data-l1key="name">';
    tr += '</td>';
    tr += '<td class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType();
	tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
	tr += '</td>';
    tr += '<td>';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
    if (init(_cmd.type) == 'info') {
	  	tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    };
    tr += '<span class="expertModeVisible"><label class="checkbox-inline"><input type="checkbox" data-size="mini" data-label-text="{{Inverser}}" class="cmdAttr" data-l1key="display" data-l2key="invertBinary" /></label></span>';
    //tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width : 40%;display : inline-block;"> ';
    //tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width : 40%;display : inline-block;">';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
        $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}

