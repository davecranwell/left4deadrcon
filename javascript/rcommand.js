$(function(){

	$('#serverinfo li input[@type=hidden]').css("position","absolute").css("left","-5000px");

	$('#serverinfo li').click(
		function(){
			$(this).siblings().removeClass("selected");			
			$(this).addClass("selected");
		}
	);	
	
	$('#menu a').click(function(){
		$('.tabbedcontent').hide();
		$('#'+$(this).attr("class")).show();
		$('#menu li').removeClass("selected");
		$(this).parent().addClass("selected");
		return false;
	});
	
	$('#console h2').toggle(
		function(){
			$(this).addClass("selected");
			$('#consolelog').slideDown(100);
		},
		function(){
			$(this).removeClass("selected");
			$('#consolelog').slideUp(100);
		}
	);
	

	$('form.command').submit(function(event){
		//disable all other forms
		$('form.command input.submit').attr("disabled","disabled");
				
		//find this command important info
		thisCommandField=$(this).find('input.command, select.command');
		thisCommandPrefix=$(thisCommandField).siblings('input.prefix');
		
		//apply working symbol
		$(thisCommandField).addClass("working");

		//check for command prefix
		if(thisCommandPrefix.length){
			prefix=$(thisCommandPrefix).val();
		}else{
			prefix="";
		}		
		
		//report client command to console
		consoleData = {"command": prefix+thisCommandField.val()};
		reportToConsole(consoleData);
			
		$.post('rpc/runcommand.php', {prefix: prefix, command: thisCommandField.val()}, commandResponse, 'json');
		return false;
	});
	
	//getserver info once
	updateServerStatus();
	//repeat update at 60s intervals
	serverStatusInterval = setInterval(updateServerStatus, 60000);
});

function updateServerStatus(){
	$('#menu li a.serverinfo').siblings('span').addClass("working-grey");
	$.getJSON("rpc/serverstatus.php", function(data){
		$('#menu li span').removeClass("working-grey");
		$('#serverinfo p').empty();
		$('#serverinfo li.ip p').append(data.ip);
		$('#serverinfo li.hostname p').append(data.hostname);
		$('#serverinfo li.hostname input.command').attr("value", data.hostname);
		$('#serverinfo li.map p').append(data.map);
		$('#serverinfo li.map select.command option[@value='+data.map+']').attr("selected","selected");
		$('#serverinfo li.difficulty p').append(data.difficulty);
		$('#serverinfo li.difficulty select.command option[@value='+data.difficulty+']').attr("selected","selected");
		
		//populate user table
		$('#playerlist tbody').empty();
		if(data.playerinfo.length){
			$.each(data.playerinfo, function(i,player){
				$('#playerlist tbody').append('<tr><td>'+ player.id+'</td><td>'+ player.name+'</td><td>'+ player.uniqid+'</td><td>'+ player.connected+'</td><td>'+ player.ping+'</td><td>'+ player.state+'</td><td>'+ player.ip+'</td></tr>');
			});
		}else{
			$('#playerlist tbody').append('<tr><td colspan="7">No players connected</td></tr>');
			$('#playerlist tbody tr td').css('text-align', 'center');
		}
	});
}

function commandResponse(data){
	//enable all other forms
	$('form.command input.submit').removeAttr("disabled");
	$('input.command, select.command').removeClass("working");
	reportToConsole(data);
}

function reportToConsole(data){
	if(data.responseSuccess != null){
		//it is the response returning from the server
		if(data.responseText.length){
			$('#consolelog').append('<div class="' +data.responseSuccess+ '">' + data.responseText.replace(/\n/g,"<br />") + '</div>');
		}else{
			$('#consolelog').append('<div class="' +data.responseSuccess+ '">Command run</div>');
		}
	}else{
		//it was the command going to server
		$('#consolelog').append('<div class="client">' + data.command+ '</div>');
	}
	
	$('#consolelog').scrollTop(100000); //faking scrollHeight as it inexplicably doesn't want to work
	
	return false;
}
