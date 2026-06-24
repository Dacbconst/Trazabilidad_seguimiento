function generarPass(){
	var password = document.getElementById("pass");
	long = 8;
	var caracteres = "abcdefghijkmnpqrtuvwxyzABCDEFGHIJKLMNPQRTUVWXYZ2346789";
	var pass = "";
	for (i=0; i<long; i++) pass += caracteres.charAt(Math.floor(Math.random()*caracteres.length));
	password.value = pass;
}

function generarUser(){
	var e = document.getElementById("inputCuenta");
	
	var nombre = document.getElementById("firstname").value;
	var apellido = document.getElementById("lastname").value;
	var user = document.getElementById("user");
	
	if (e==null) {
		user.value = "";
	}
	
	var subCadenaCuenta = "", usuario = "";
	
	var size = 3;
	
	var separador = " ", // un espacio en blanco
	arregloDeSubCadenas = nombre.split(separador); // SEPARA EL NOMBRE EN CADENAS INDIVIDUALES

	// IMPRIME LA PRIMERA LETRA DE CADA CADENA 
	for (x = 0; x < arregloDeSubCadenas.length; x++) {
		subCadenaNombre = arregloDeSubCadenas[x].substring(0, 1);
	}
	
	usuario = subCadenaNombre + apellido;
	
	user.value = usuario.toLowerCase();
	
	if (nombre.trim().length == 0 || apellido.trim().length == 0) { 
		user.value = "";
	}				
}

function reinicar_panel_usuario(){
    $("#id").val("");
	$("#firstname").val("");
	$("#lastname").val("");
	$("#user").val("");
	$("#pass").val("");
	$("#inputCuenta").val(0);
	$("#inputAcceso").val(0);
	$("#inputTipo").val(0);
	$("#inputCorreo").val("");
	$("#inputCorreoSupervisor").val("");
}

jQuery(document).ready(function () {
	$( "#guardar-usuario").submit(function( event ) {
		
		var firstname = $('#firstname').val();
		var lastname = $('#lastname').val();
		var user = $('#user').val();
		var pass = $('#pass').val();
		var inputCuenta = $('#inputCuenta').val();
		var inputAcceso = $('#inputAcceso').val();
		var inputTipo = $('#inputTipo').val();
		var inputCorreo = $('#inputCorreo').val();
		var inputCorreoSupervisor = $('#inputCorreoSupervisor').val();
		var inputPerfil = $('#inputPerfil').val();
		var inputHoraIngreso = $('#inputHoraIngreso').val();
		var inputHoraSalida = $('#inputHoraSalida').val();
		
		if (firstname.trim()!="" && lastname.trim()!="" && user.trim()!="" && pass.trim()!="" && inputCuenta.trim()!="" && inputTipo.trim()!="" && inputCorreo.trim()!="" && inputCorreoSupervisor.trim()!="" && inputAcceso.trim()!="") {
			
			var number = 1 + Math.floor(Math.random() * 6);
			
			//formdata=$( "#guardar-usuario" ).serialize();
			
			$.ajax({ type: "POST", url: "/App/XploraEcuador/includes/process_report.php",   
				data: {
					'save2' : 1,
					'firstname' : firstname,
					'lastname' : lastname,
					'user' : user,
					'pass' : pass,
					'inputCuenta' : inputCuenta,
					'inputTipo' : inputTipo,
					'inputAcceso' : inputAcceso,
					'inputCorreo' : inputCorreo,
					'inputCorreoSupervisor' : inputCorreoSupervisor,
					'inputPerfil' : inputPerfil,
					'inputHoraIngreso' : inputHoraIngreso,
					'inputHoraSalida' : inputHoraSalida
				},
				
				/*data: formdata  ,*/
				beforeSend: function(){
				  $("#loading").css("display","block");
				},
				success:function(data) {
					//$(".test").html(data);
					$("#loading").css("display","none");
					
					if (data != "") {
						var id = parseInt(data);
						
						switch(id) {
							case 1:
								alert('Usuario Existente');
								$("#user").val(user + number);
								break;
							case 2:
								alert('Usuario Guardado Exitosamente');
								window.location.replace("/App/XploraEcuador/mantenimiento_usuarios/index.php");
								break;
							case 3:
								alert('Error al Guardar');
								break;
							case 4:
								//reinicar_panel_usuario();
								alert('Error Envio Correo');
								break;
						}
					}
				}     
			});
			event.preventDefault();
		} else {
			alert('Debe Llenar el Formulario');
		}
	});
	$( "#editar-usuario").submit(function( event ) {
		
		var id = $('#id').val();
		var firstname = $('#firstname').val();
		var lastname = $('#lastname').val();
		var user = $('#user').val();
		var pass = $('#pass').val();
		var inputCuenta = $('#inputCuenta').val();
		var inputAcceso = $('#inputAcceso').val();
		var inputTipo = $('#inputTipo').val();
		var inputCorreo = $('#inputCorreo').val();
		var inputCorreoSupervisor = $('#inputCorreoSupervisor').val();
		var inputPerfil = $('#inputPerfil').val();
		var inputHoraIngreso = $('#inputHoraIngreso').val();
		var inputHoraSalida = $('#inputHoraSalida').val();
		
		if (firstname.trim()!="" && lastname.trim()!="" && user.trim()!="" && inputCuenta.trim()!="" && inputTipo.trim()!="" && inputCorreo.trim()!="" && inputCorreoSupervisor.trim()!="") {
			console.log(id + "-" + firstname + "-" + lastname + "-" + user + "-" + pass + "-" + inputCuenta + "-" + inputTipo + "-" + inputCorreo + "-" + inputCorreoSupervisor);
			var number = 1 + Math.floor(Math.random() * 6);
			
			//formdata=$( "#guardar-usuario" ).serialize();
			
			$.ajax({ type: "POST", url: "/App/XploraEcuador/includes/process_report.php",   
				data: {
					'edit' : 1,
					'id' : id,
					'firstname' : firstname,
					'lastname' : lastname,
					'user' : user,
					'pass' : pass,
					'inputCuenta' : inputCuenta,
					'inputTipo' : inputTipo,
					'inputAcceso' : inputAcceso,
					'inputCorreo' : inputCorreo,
					'inputCorreoSupervisor' : inputCorreoSupervisor,
					'inputPerfil' : inputPerfil,
					'inputHoraIngreso' : inputHoraIngreso,
					'inputHoraSalida' : inputHoraSalida
				},
				
				/*data: formdata  ,*/
				beforeSend: function(){
				  $("#loading").css("display","block");
				},
				success:function(data) {
					//$(".test").html(data);
					$("#loading").css("display","none");
					if (data != ""){
						var id = parseInt(data);
						switch(id) {
							case 1:
								alert('Usuario Guardado Exitosamente');
								window.location.replace("/App/XploraEcuador/mantenimiento_usuarios/index.php");
								break;
							case 2:
								alert('Error al Guardar');
								break;
							case 3:
								alert('Error Envio Correo');
								break;
							default:
								alert('Error');
								break;
						}
					}
				},
				error: function(XMLHttpRequest, textStatus, errorThrown) { 
					alert("Status: " + textStatus); alert("Error: " + errorThrown); 
				}     
			});
			event.preventDefault();
		} else {
			alert('Debe Llenar el Formulario');
		}
	});
});

function cargarAccesos(id_usuario, tipo_usuario) {
	$.ajax({
		type: "POST", 
		url: "/App/XploraEcuador/includes/get/getAccesos.php",
		data: jQuery.param({
			id_usuario: id_usuario,
			tipo_usuario: tipo_usuario
		}),
		success:function(data) {
			if (data != ""){
				$("ul.acceso").html(data);
				$("ul.acceso input[type=checkbox]").each(function() {
					$(this).change(function() {
						var line_acceso = "";
						$("ul.acceso input[type=checkbox]").each(function() {
							if($(this).is(":checked")) {
								if (line_acceso.length<=0) {
									line_acceso += $("+ span", this).text();
								} else {
									line_acceso += "-" + $("+ span", this).text();
								}
							}
						});
						if ($("+ span", this).text()=== "Todas las cuentas") {
							line_acceso = "Todas2";
							$("#inputAcceso").val(line_acceso);
							$('ul.acceso input[type=checkbox]').prop('checked', false);
							$('#checkTodas').prop('checked', true);
						} else {
							$('#checkTodas').prop('checked', false);
							
							if (line_acceso.indexOf("Todas las cuentas") != -1) {
								line_acceso = "";
								$("#inputAcceso").val(line_acceso);
							} else {
								$("#inputAcceso").val(line_acceso);
							}
						}
					});
				});
			}
		}
	});
}

$(document).ready(function() {
	
	var id_usuario = $('#id').val();
	var tipo_usuario = $('#inputTipo').val();
	
	cargarAccesos(id_usuario, tipo_usuario);
	
	$("#inputPerfil").change(function(){
		var perfil = $("#inputPerfil").val();
		if (perfil==="EXTERNO") {
			$('#horario').hide();
		} else {
			$('#horario').show();
		}				
	});
	
	$("#inputTipo").change(function () {
		$("#inputTipo option:selected").each(function () {
			tipo_usuario = $(this).val();
			cargarAccesos(id_usuario, tipo_usuario);			
		});
	});
	
	$("ul.cuenta input[type=checkbox]").each(function() {
		$(this).change(function() {
			var line_cuenta = "";
			$("ul.cuenta input[type=checkbox]").each(function() {
				if($(this).is(":checked")) {
					if (line_cuenta.length<=0) {
						line_cuenta += $("+ span", this).text();
					} else {
						line_cuenta += "-" + $("+ span", this).text();
					}
				}
			});
			if ($("+ span", this).text()=== "Todas las cuentas") {
				line_cuenta = "Todas2";
				$("#inputCuenta").val(line_cuenta);
				$('ul.cuenta input[type=checkbox]').prop('checked', false);
				$('#checkTodas').prop('checked', true);
			} else {
				$('#checkTodas').prop('checked', false);
				
				if (line_cuenta.indexOf("Todas las cuentas") != -1) {
					line_cuenta = "";
					$("#inputCuenta").val(line_cuenta);
				} else {
					$("#inputCuenta").val(line_cuenta);
				}
			}
		});
	});
});