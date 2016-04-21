/**
 * Created by gerard on 11.04.16.
 */
const REQUEST_URL = 'processRequest.php';
const START_DOWNLOAD = 'startdownload';
const CANCEL_DOWNLOAD = 'canceldownload';
const GET_PROGRESS = 'getprogress';
const GET_LOG = 'getlog';
const OK = 'OK';
const ERROR = 'ERROR';
var ioBusy = false;
var xmlhttp;
var checkinterval;
function setXMLHTTP()
{
	if( window.XMLHttpRequest )
	{ // code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else
	{ // code for IE6, IE5
		xmlhttp = new ActiveXObject( "Microsoft.XMLHTTP" );
	}
	xmlhttp.onreadystatechange = function ()
	{
		console.log( "onreadystatechange" );

		if( xmlhttp.status === 404 )
		{
			alert( 'Not Found' );
			ioBusy = false;
			return;
		}

		if( xmlhttp.readyState == 4 && xmlhttp.status == 200 )
		{
			console.log( xmlhttp );
			ioBusy = false;
			if( xmlhttp.response )
			{
				parseResponse( xmlhttp.response )
			}
			else
			{
				alert( 'No valid response!, please try again' )
			}
		}
	};
}

function sendRequest( request )
{

	if( xmlhttp == null )
	{
		setXMLHTTP();
	}
	var action = request.action;
	request = JSON.stringify( request );

	xmlhttp.open( "POST", REQUEST_URL );
	xmlhttp.setRequestHeader( "Content-Type", "application/json" );
	try
	{
		xmlhttp.send( request );
	}
	catch( e )
	{
		addLog( 'Request not send ' + action + '' + e )
		console.error( e );
	}
}
function checkProgress()
{
	var request = {};
	request.action = GET_PROGRESS;
	sendRequest( request );

}
function getLog()
{
	var request = {};
	request.action = GET_LOG;
	sendRequest( request );

}
function parseResponse( resp )
{
	var response = JSON.parse( resp );
	if( response.status != OK )
	{
		if( response.action == GET_PROGRESS )
		{
			clearInterval( checkinterval );
			addLog( response.data );
		}
		else
		{
			addLog( response.action + " not executed " + ":" + response.data );
		}
		return;
	}
	switch( response.action )
	{
		case START_DOWNLOAD:

			if( checkinterval )
			{
				clearInterval( checkinterval );
			}
			checkinterval = setInterval( checkProgress, 1000 );
			addLog( response.status + ":" + response.data );
			break;
		case CANCEL_DOWNLOAD:
			addLog( response.action + "  executed " + ":" + response.data );
			break;
		case GET_PROGRESS:
			if( response.data != null && response.data.indexOf( "EXECUTION_COMPLETE" ) >= 0 )
			{
				if(checkinterval)
				{
					addLog( response.data );
					clearInterval( checkinterval );
				}
			}
			else
			{
				if( !checkinterval )
				{
					checkinterval =setInterval( checkProgress, 1000 );
				}
				addLog( response.data );
			}

			break;
		case GET_LOG:
			setLog( response.data );
			break;
		default:
			addLog( 'Action unknown >' + response.action + ":" + response.data );
			break;
	}

}
function startDownLoad()
{
	clearInterval( checkinterval );
	var request = {};
	request.action = START_DOWNLOAD;
	request.youtubeurl = document.getElementById( "youtubeurl" ).value;
	sendRequest( request );
}
function cancelDownLoad()
{
	clearInterval( checkinterval );
	var request = {};
	request.action = CANCEL_DOWNLOAD;
	sendRequest( request );
}
function addLog( msg )
{
	var log = document.getElementById( "log" );
	log.value += getFormattedTime() + " > " + msg + "\n";
	log.scrollTop = log.scrollHeight;
}
function setLog( msg )
{
	var log = document.getElementById( "log" );
	log.value = msg;
}
function clearLog()
{
	var log = document.getElementById( "log" );
	log.value = "";
}
function getFormattedTime()
{
	var date = new Date();
	var hrs = date.getHours()
	hrs < 10 ? hrs = "0" + hrs : hrs = "" + hrs;
	var mins = date.getMinutes();
	mins < 10 ? mins = "0" + mins : mins = "" + mins;
	var secs = date.getSeconds();
	secs < 10 ? secs = "0" + secs : secs = "" + secs;

	return hrs + ":" + mins + ":" + secs ;
}
