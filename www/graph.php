<?php //graph.php

include "_functions.php";
$qryTime = 0; $renderTime = microtime(true);

$device = (isset($_GET['device']))?cleanSqlString(trim($_GET['device'])):""; //file data to add
if ($device=="") $device = (isset($_POST['device']))?cleanSqlString(trim($_POST['device'])):""; //file data to add

$type = (isset($_GET['type']))?cleanSqlString(trim($_GET['type'])):""; //file data to add
if ($type=="") $type = (isset($_POST['type']))?cleanSqlString(trim($_POST['type'])):""; //file data to add

$timeframe = (isset($_GET['timeframe']))?cleanSqlString(trim($_GET['timeframe'])):""; //file data to add
if ($timeframe=="") $timeframe = (isset($_POST['timeframe']))?cleanSqlString(trim($_POST['timeframe'])):"day"; //file data to add

$gridMax = (isset($_GET['gridmax']))?cleanSqlString(trim($_GET['gridmax'])):""; //file data to add
if ($gridMax=="") $gridMax = (isset($_POST['gridmax']))?cleanSqlString(trim($_POST['gridmax'])):""; //file data to add

$interfaceNum = (isset($_GET['interfacenum']))?cleanSqlString(trim($_GET['interfacenum'])):""; //"all" will be all interfaces, default will be 10.
if ($interfaceNum=="") $interfaceNum = (isset($_POST['interfacenum']))?cleanSqlString(trim($_POST['interfacenum'])):""; //file data to add

//timeframe = day, week, month, all

$chartName = "DrChartSmith";
$jsData = '[{x: "2014-07-09 11:55:06", y: 100},{"time": "2014-07-09 11:50:03"},{"time": "2014-07-09 11:45:10", "value": 90}]';
$filter = "";
$yTitle = ""; //The data we are measuring. X is the time
$ySuffix = ""; //ms for milliseconds
$gridThreshold = ""; //make "maximum: 100," or "minimum: 0, maximum: 100,"
$xMax = "maximum: new Date(),"; //Date('".date("Y-m-d H:i:s")."'),";

switch ($timeframe) {
	case "day":
		$filter="AND ts >= NOW() - INTERVAL 24 HOUR";
		$timeframe = "Timeframe: The past day";
		break;
		
	case "week":
		$filter="AND ts >= NOW() - INTERVAL 1 WEEK";
		$timeframe = "Timeframe: The past week";
		break;
		
	case "month":
		$filter="AND ts >= NOW() - INTERVAL 1 MONTH";
		$timeframe = "Timeframe: The past month";
		break;
		
	case "3month":
		$filter="AND ts >= NOW() - INTERVAL 3 MONTH";
		$timeframe = "Timeframe: The past 3 months";
		break;
	
	case "6month":
		$filter="AND ts >= NOW() - INTERVAL 6 MONTH";
		$timeframe = "Timeframe: The past 6 months";
		break;
	
	case "year":
		$filter="AND ts >= NOW() - INTERVAL 1 YEAR";
		$timeframe = "Timeframe: The past year";
		break;
		
	case "all":
		$filter="AND ts >= NOW() - INTERVAL 1 YEAR";
		$timeframe = "Timeframe: All Time";
		break;
		
	default: //1 day
		$filter = "AND ts >= NOW() - INTERVAL 1 DAY";
		$timeframe = "Timeframe: The past 24 hours";
}

//get the data
$jsData = array(); $data = "";
switch ($type) {
	case "ping":
		$qryTime = microtime(true);
		$table = getSqlArray("SELECT ts,value FROM history_ping WHERE device='$device' $filter;");
		$qryTime = microtime(true) - $qryTime;
		//put the data in javascript format. $jsData=json_encode($table);
		foreach ($table as $row) {
			$data .= '{x: new Date("'.$row["ts"].'")';
			if ($row["value"]!="unreachable") $data .= ',y: '.floatval($row["value"]).'';
			$data .= '},';
		}
		//$jsData = "[".substr($jsData,0,-1)."]";
		$jsData[] = '{
			name: "Ping Response",
			showInLegend: false,
			type: "area",
			markerSize: 0,
			toolTipContent: "{x}<br><font color={color}>{name}</font>:  <b>{y} ms</b>",
			dataPoints: ['.substr($data,0,-1).']
		}';
		
		
		$chartName = "DingusPingusChart";
		$yTitle = "Response Time";
		$ySuffix = " ms";
		break;
	
	case "if":
		$qryTime = microtime(true);
		$qry = "SELECT ifIndex,ifDescr,(ifInOctetsDiff + ifOutOctetsDiff) AS bw  from device_iftable where device='$device' AND ifOperStatus='up' ORDER BY bw DESC Limit 20;";
		if ($interfaceNum=="all") $qry = "SELECT ifIndex,ifDescr from device_iftable where device='$device' AND ifOperStatus='up' ORDER BY ifIndex ASC;";
		$tIfNames = getSqlArray($qry);
		$ifNames=array();
		foreach($tIfNames as $row) $ifNames[$row["ifIndex"]] = $row["ifDescr"];

		//$table = getSqlArray("SELECT ts,ifIndex,ifInOctetsDiff,ifOutOctetsDiff FROM history_if WHERE device='$device' $filter;");
		$table = getSqlArray("SELECT ts,ifIndex,TRUNCATE(ifInOctetsDiff * 8 / 1024 / 1024,2) AS ifInOctetsDiff,TRUNCATE(ifOutOctetsDiff * 8 / 1024 / 1024,2) as ifOutOctetsDiff ,TRUNCATE(ifHCInOctetsDiff * 8 / 1024 / 1024,2) AS ifHCInOctetsDiff,TRUNCATE(ifHCOutOctetsDiff * 8 / 1024 / 1024,2) as ifHCOutOctetsDiff FROM history_if WHERE device='$device' $filter;");
		$qryTime = microtime(true) - $qryTime;
		
		//put the data into a json array
		$cnt=0; $data = array(); $hasData = false;
		foreach ($table as $row) {
			if (!array_key_exists($row["ifIndex"],$data)) {$data[$row["ifIndex"]]["In"] = ""; $data[$row["ifIndex"]]["Out"] = "";}
			//$data[$row["ifIndex"]]["ifInOctetsDiff"] .= '{x: new Date("'.$row["ts"].'"), y: '.(round($row["ifInOctetsDiff"] * 8 /1024/1024,2)).'},';
			//$data[$row["ifIndex"]]["ifOutOctetsDiff"] .= '{x: new Date("'.$row["ts"].'"), y: '.(round($row["ifOutOctetsDiff"] * 8 /1024/1024,2)).'},';
			$y = ($row["ifHCInOctetsDiff"]!="")?",y: ".$row["ifHCInOctetsDiff"]:0;
			if ($y==0) $y = ($row["ifInOctetsDiff"]!="")?",y: ".$row["ifInOctetsDiff"]:"";
			$data[$row["ifIndex"]]["In"] .= '{x: new Date("'.$row["ts"].'")'.$y.'},';
			
			$y = ($row["ifHCOutOctetsDiff"]!="")?",y: ".$row["ifHCOutOctetsDiff"]:0;
			if ($y==0) $y = ($row["ifOutOctetsDiff"]!="")?",y: ".$row["ifOutOctetsDiff"]:"";
			$data[$row["ifIndex"]]["Out"] .= '{x: new Date("'.$row["ts"].'")'.$y.'},';
			
			if ($y!="") $hasData = true;
			$cnt++;
		}
		
		if (!$hasData) {
			echo '<br><center>No Data Recorded</center>'; exit;
		}
		
		foreach($data as $key => $row) { //the key in this case is the ifIndex
			if ($key > 0 && array_key_exists($key,$ifNames)) {
				foreach($row as $key2 => $tuple) { //$key2 will = "ifInOctetsDiff" or "ifOutOctetsDiff" ...
					$jsData[] = '{
						name: "'.$ifNames[$key].' '.$key2.'",
						showInLegend: true,
						legendMarkerType: "square",
						type: "stackedArea",
						markerSize: 0,
						toolTipContent: "{x}<br><font color={color}>{name}</font>:  <b>{y} Mbps</b>",
						dataPoints: ['.substr($row[$key2],0,-1).']
					}';
				}
			}
		}

		$chartName = "cheetahChart";
		$yTitle = "If Throughput";
		$ySuffix = " Mbps";
		
		
		//foreach ($data as $key => $val) {
		//}
		break;
		
	case "cpu":
		$qryTime = microtime(true);
		$table = getSqlArray("SELECT ts,Load1,Load5,Load15 FROM history_cpu WHERE device='$device' $filter;");
		$qryTime = microtime(true) - $qryTime;
		$gridMax = 100;
		//put the data in javascript format. $jsData=json_encode($table);
		$data = "";
		foreach ($table as $row) {
			$data .= '{x: new Date("'.$row["ts"].'")';
			$data .= ',y: '.(floatval($row["Load1"])*100).'';
			$data .= '},';
		}
		$jsData[] = '{
			name: "CPU Load",
			showInLegend: false,
			type: "area",
			markerSize: 5,
			toolTipContent: "{x}<br><font color={color}>{name}</font>:  <b>{y}%</b>",
			dataPoints: ['.substr($data,0,-1).']
		}';
		
		$chartName = "PrCPUChart";
		$yTitle = "CPU Load";
		$ySuffix = "%";
		break;
		
	case "mem":
		$qryTime = microtime(true);
		$table = getSqlArray("SELECT ts,AvailSwap,AvailReal,Buffered,Cached FROM history_mem WHERE device='$device' $filter;");
		$gridMax = getSqlValue("SELECT TotalReal FROM device_mem WHERE device='$device' LIMIT 1");
		$qryTime = microtime(true) - $qryTime;
		//put the data in javascript format. $jsData=json_encode($table);
		$data = array("RAM Free"=>"","RAM Used"=>"");
		foreach ($table as $row) {
			$data["RAM Free"] .= '{x: new Date("'.$row["ts"].'"), y: '.(floatval($row["AvailReal"])).'},';
			$data["RAM Used"] .= '{x: new Date("'.$row["ts"].'"), y: '.(floatval($gridMax) - floatval($row["AvailReal"])).'},';

		}

		$jsData[] = '{
			name: "RAM Used",
			showInLegend: true,
			legendMarkerType: "square",
			type: "stackedArea",
			markerSize: 0,
			toolTipContent: "{x}<br><font color={color}>{name}</font>:  <b>{y} Kb</b>",
			color :"rgba(211,19,14,.8)",
			dataPoints: ['.substr($data["RAM Used"],0,-1).']
		}';
		$jsData[] = '{
			name: "RAM Free",
			showInLegend: true,
			legendMarkerType: "square",
			type: "stackedArea",
			markerSize: 0,
			color :"rgba(19,211,14,.8)",
			dataPoints: ['.substr($data["RAM Free"],0,-1).']
		}';

		$chartName = "PinkyAndTheBrainChart";
		$yTitle = "Used Memory";
		$ySuffix = " Kb";
		break;
	
	case "test":
		$gridMax = getSqlValue("SELECT TotalReal FROM device_mem WHERE device='$device' LIMIT 1");
		$table = getSqlArray("SELECT UNIX_TIMESTAMP(ts) as x,AvailReal as y FROM history_mem WHERE device='$device' $filter;");
		$jsData[] = substr(json_encode($table),1,-1);
		echo $jsData[0];
		
		$chartName = "PinkyAndTheBrainChart";
		$yTitle = "Used Memory";
		$ySuffix = " Kb";
		
		break;
		
	default:
		exit;
}



//sleep(5); //test, sleep 5 seconds
//echo "TEST-TABLE After 5 second wait<br><br>";
/* Some variables for the chart rendering
See:  http://canvasjs.com/docs/charts/basics-of-creating-html5-chart/labels-index-labels/
var chart = new CanvasJS.Chart("chartContainer",
	{
		theme: "theme0",
		title:{text: "Ping History", fontSize: , FontFamily:, fontWeight:, fontStyle:,  },
		zoomEnabled: true,
		exportEnabled: true,
		exportFileName: "test",
		interactivityEnabled: true,
		animationEnabled: true,
		backgroundColor: "green",
		
		
		axisX:{ //also applies to axisY:, axisY2
			//Axis Elements
			title: "'.$timeframe.'"
			titleFontFamily: "Tahoma", //Verdana, Arial
			titleFontColor: "black", //"#006400"
			titleFontSize: 12,
			titleFontWeight: "normal", //lighter, bold, bolder
			titleFontStyle: "normal", //"italic", "oblique"
			margin: 2,
			lineColor: "black",
			lineThickness: 1,
			
			//Labels & Index Labels, index labels are inside the graph above the point
			labelAngle: 0, //45, -45
			labelFontFamily: "Tahoma",
			labelFontColor: "black",
			labelFontWeight: "lighter",
			labelFontSize: 10,
			labelFontStyle: "normal",
			labelAutoFit: false,
			labelWrap: true,
			labelMaxWidth: 100, //default is auto
			
			//Tick marks, grid lines, interlacing
			tickLength: 5,
			tickColor: "#EFEEEF",
			tickThickness: 1,
			gridColor: "#EFEFEF",
			gridThickness: 1,
			interval: 15,
			interlacedColor: "#FAFAFF"
			//valueFormatString: "MMM DD,YYYY HH:mm"
			
			minimum: -10, //minimum line number
			maximum: 100, //the max
		},
		
		axisY:{
			title: "round trip (ms)",
			suffix: " ms",
			lineThickness: 1,
		},
		
		toolTip: {
			content: function(e){
			  var content;
			  var d = e.entries[0].dataPoint.x;
			  content = d + " <b>"+e.entries[0].dataPoint.y + " ms</b>"  ;
			  return content; }
		},
		
		legend: {
			fontSize: 12, //default is auto calculated
			fontFamily: "monospace, sans-serif",
			fontColor: "black",
			fontWeight: "normal",
			fontStyle: "normal",
			verticalAlign: "bottom", //top, center, bottom
			horizontalAlign: "right", //left, center, right
			
			itemmouseover: function(e){alert( "Mouse moved over the legend item");},
			itemmousemove:
			itemmouseout:
			itemclick: function(e){alert( "clicked item type: " + e.dataSeries.type );},
					//for more data series types see: http://canvasjs.com/docs/charts/chart-options/data/
					
			//to hide/unhide data series: will use for network traffic:
			itemclick: function (e) {
                //console.log("legend click: " + e.dataPointIndex);
                //console.log(e);
                if (typeof (e.dataSeries.visible) === "undefined" || e.dataSeries.visible) {
                    e.dataSeries.visible = false;
                } else {
                    e.dataSeries.visible = true;
                }

                chart.render();
			}
		}
		
		
		data: [
		{
			type: "line",
			dataPoints: '.$jsData.'
		}
		]
	});
*/

//format the grid max value
if ($gridMax != "") $gridThreshold = "maximum: $gridMax,";

echo '<div id="'.$chartName.'" style="width:100%; height:100%;"></div>'; //fill the container

echo '
<script type="text/javascript" src="js/canvasjs.min.js"></script>
<script type="text/javascript">
//window.onload = renderChart();
renderChart();

function renderChart() {
	var '.$chartName.' = new CanvasJS.Chart("'.$chartName.'",
	{
		theme: "theme0",
		//title:{text: "THE Graph" },
		zoomEnabled: true,
		exportEnabled: true,
		exportFileName: "'.$device."_".$type.'",
		interactivityEnabled: true,
		animationEnabled: true,
		
		axisX:{
			'.$xMax.'
			title: "'.$timeframe.'",
			titleFontFamily: "Tahoma",
			titleFontColor: "black",
			titleFontSize: 14,
			titleFontWeight: "bold", //lighter, bold, bolder
			titleFontStyle: "normal", //"italic", "oblique"
			lineColor: "black",
			lineThickness: 1,
			
			//Labels & Index Labels, index labels are inside the graph above the point
			labelFontFamily: "Tahoma",
			labelFontColor: "black",
			labelFontWeight: "lighter",
			labelFontSize: 12,
			labelFontStyle: "normal",
			valueFormatString: "h:mm TT / DDD MMM D, YYYY",
			//labelAutoFit: true,
			//labelWrap: true,
			
			//Tick marks, grid lines, interlacing
			tickLength: 5,
			tickColor: "#343434",
			tickThickness: 1,
			gridColor: "#EFEFEF",
			gridThickness: 1
		},
		
		axisY:{
			title: "'.$yTitle.'",
			suffix: "'.$ySuffix.'",
			'.$gridThreshold.'
			titleFontFamily: "Tahoma",
			titleFontColor: "black",
			titleFontSize: 12,
			titleFontWeight: "bold", //lighter, bold, bolder
			titleFontStyle: "normal", //"italic", "oblique"
			lineColor: "black",
			lineThickness: 1,
			
			//Labels & Index Labels, index labels are inside the graph above the point
			labelFontFamily: "Tahoma",
			labelFontColor: "black",
			labelFontWeight: "lighter",
			labelFontSize: 12,
			labelFontStyle: "normal",
			labelAutoFit: true,
			labelWrap: true,
			
			//Tick marks, grid lines, interlacing
			tickLength: 5,
			tickColor: "#343434",
			tickThickness: 1,
			gridColor: "#EFEFEF",
			gridThickness: 1,
			interlacedColor: "#FCFCFF"
		},
		
		toolTip: {
			animationEnabled: true,
			contents: function(e){
			  var content;
			  var d = e.entries[0].dataPoint.x;
			  var m_names = new Array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
			  var d_names = new Array("Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat");

				var curr_date = d.getDate();
				var curr_month = d.getMonth();
				var curr_year = d.getFullYear();
				var dd = curr_date + "-" + m_names[curr_month] + "-" + curr_year;


			  content = dd + "<br> {label}: <b>"+e.entries[0].dataPoint.y + "'.$ySuffix.'</b><br>{y} / #total = #percent%"  ;
			  return content; }
		},
		
		legend: {
			fontSize: 12,
			fontFamily: "Tahoma",
			fontColor: "black",
			fontWeight: "lighter",
			fontStyle: "normal",
			verticalAlign: "bottom", //top, center, bottom
			horizontalAlign: "center", //left, center, right
			//to hide/unhide data series: will use for network traffic:
			cursor:"pointer",
			itemclick: function (e) {
                if (typeof(e.dataSeries.visible) === "undefined" || e.dataSeries.visible){
					e.dataSeries.visible = false;
				  }
				  else{
					e.dataSeries.visible = true;
				  }
                '.$chartName.'.render();
			}
		},
		
		data: [';
		$c=1;
		foreach($jsData as $d) {
			echo $d;
			if ($c < count($jsData)) echo ","; $c++;
		}
	echo ']
	});

	'.$chartName.'.render();
}

</script> 
';
$renderTime = microtime(true)-$renderTime;
echo "<!--Query Time = $qryTime Seconds. Render Time = $renderTime Seconds -->";


//echo '</script> <div id="chartContainer" style="width:800px; height:400px;"></div>';

?>