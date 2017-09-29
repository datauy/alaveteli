<?php

  $id = '';
  $comando = 'exit';
  $cumplimiento = null;
  $category_id = null;
  if (isset($_GET['id'])) {
    $id = $_GET['id'];
    if (isset($_GET['option'])) {
      $comando = $_GET['option'];
    }
  }

  if($comando=='export'){
    $dbconn = pg_connect("host=localhost dbname=quesabes_production user=quesabes_user password=quesabesf0i")
    or die('Could not connect: ' . pg_last_error());

    //GET ALL requests matching filters
    $queryAllRequest = "SELECT i.id, i.title, i.created_at, i.updated_at, i.described_state, i.last_public_response_at, 
			u.name as solicitante, p.name as organismo, 'http://quesabes.org/request/'||i.url_title as url 
			FROM info_requests i, users u, public_bodies p 
			where i.user_id = u.id and i.public_body_id = p.id and i.id ";

    if(is_numeric($id)){
	$queryAllRequest .= " = " . $id;
    }else{
	$queryAllRequest .= " > 0";
    }

    if(isset($_GET['state'])){
	if($_GET['state']!="all"){
		$queryAllRequest .= " and i.described_state like '%" . $_GET['state'] . "%'";	
	}
    }

    if(isset($_GET['query']) && $_GET['query']!=""){
	$queryArray = explode("+",$_GET['query']);
	$query = "";
	if(count($queryArray)>1){
		foreach ($queryArray as $word) {
			$query .= $word . " ";
		}
		$query = trim($query);
	}else{
		$query = $_GET['query'];	
	}
	$queryAllRequest .= " and ( ";

	$queryAllRequest .= " lower(i.title) like '%" . strtolower($query) . "%'";
	$queryAllRequest .= " or lower(p.name) like '%" . strtolower($query) . "%'";
	$queryAllRequest .= " or lower(u.name) like '%" . strtolower($query) . "%'";
	$queryAllRequest .= " or i.id in (select m.info_request_id from incoming_messages m 
			      where lower(m.cached_attachment_text_clipped) like '%".strtolower($query)."%'
			      or lower(m.cached_main_body_text_folded) like '%".strtolower($query)."%'
			      or lower(m.cached_main_body_text_unfolded) like '%".strtolower($query)."%'
			      or lower(m.subject) like '%".strtolower($query)."%'	
			      )";
	$queryAllRequest .= " or i.id in (select m.info_request_id from outgoing_messages m 
			      where lower(m.body) like '%".strtolower($query)."%'
			      )";
	$queryAllRequest .= " or i.id in (select c.info_request_id from comments c 
			      where lower(c.body) like '%".strtolower($query)."%'
			      )";

	$queryAllRequest .= " ) ";
    }
	
    if(isset($_GET['date_before']) && $_GET['date_before']!=""){
	$dateBefore = $_GET['date_before'];
	$dateBeforeArray = explode("%2F",$dateBefore);
	if(count($dateBeforeArray)==3){
		$dateBeforeString = $dateBeforeArray[2] . "-" . $dateBeforeArray[1] . "-" . $dateBeforeArray[0];
		$queryAllRequest .= " and i.created_at <= '" . $dateBeforeString . "'";
	}
    }

    if(isset($_GET['date_after']) && $_GET['date_after']!=""){
	$dateAfter = $_GET['date_after'];
	$dateAfterArray = explode("%2F",$dateAfter);
	if(count($dateAfterArray)==3){
		$dateAfterString = $dateAfterArray[2] . "-" . $dateAfterArray[1] . "-" . $dateAfterArray[0];
		$queryAllRequest .= " and i.created_at >= '" . $dateAfterString . "'";
	}
    }

    $queryAllRequest .= ";";
    //echo $queryAllRequest;
    $result = pg_query($queryAllRequest) or die('Query failed: ' . pg_last_error());
    $resultArray = pg_fetch_all($result);
    $resultHeaders = array("ID solicitud", "Título", "Fecha de creación", "Última actualización", "Estado actual", "Última respuesta pública", 
			   "Solicitado por", "Organismo", "URL");
    pg_free_result($result);
    pg_close($dbconn);

    if(isset($_GET['format'])){
    	if($_GET['format']=='csv'){
		array_unshift($resultArray,$resultHeaders);
		array_to_csv_download($resultArray,"quesabes_export.csv");	        
	}
	
    	if($_GET['format']=='xml'){
		// creating object of SimpleXMLElement
		$xml_data = new SimpleXMLElement('<?xml version="1.0"?><data></data>');
		array_to_xml($resultArray,$xml_data);
		//saving generated xml file; 
		$tmpHandle = tmpfile();
		$metaDatas = stream_get_meta_data($tmpHandle);
		$tmpFilename = $metaDatas['uri'];
		$file = $xml_data->asXML($tmpFilename);
		header('Content-type: text/xml');
		header('Content-Disposition: attachment; filename="quesabes_export.xml";');
		echo file_get_contents($tmpFilename);
		fclose($tmpHandle);
		        
	}

    	if($_GET['format']=='odt'){
		array_to_odt($resultArray,$resultHeaders);	        
	}

    	if($_GET['format']=='ods'){
		array_to_ods($resultArray,$resultHeaders);	        
	}


    }
    
  }

	function array_to_csv_download($array, $filename = "quesabes_export.csv", $delimiter=";") {
		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="'.$filename.'";');

		// open the "output" stream
		// see http://www.php.net/manual/en/wrappers.php.php#refsect2-wrappers.php-unknown-unknown-unknown-descriptioq
		$f = fopen('php://output', 'w');

		foreach ($array as $line) {
			fputcsv($f, $line, $delimiter);
		}
	} 

	function array_to_xml( $data, &$xml_data ) {
	    foreach( $data as $key => $value ) {
		if( is_array($value) ) {
		    if( is_numeric($key) ){
		        $key = 'solicitud'; //dealing with <0/>..<n/> issues
		    }
		    $subnode = $xml_data->addChild($key);
		    array_to_xml($value, $subnode);
		} else {
		    $xml_data->addChild("$key",htmlspecialchars("$value"));
		}
	     }
	}

	function array_to_odt($data,$headers){
		include 'phpodt-0.3.3/phpodt.php';

		$odt = ODT::getInstance();

		$table = new Table('table1');
		$table->createColumns(3);
		$table->addHeader($headers);
		$table->addRows($data);

		$tmpHandle = tmpfile();
		$metaDatas = stream_get_meta_data($tmpHandle);
		$tmpFilename = $metaDatas['uri'];

		$odt->output($tmpFilename);

		header('Content-type: application/vnd.oasis.opendocument.text');
		header('Content-Disposition: attachment; filename="quesabes_export.odt";');
		echo file_get_contents($tmpFilename);
		fclose($tmpHandle);    	
	}

	function array_to_ods($data,$headers){
		// Load libraries 
		require_once 'ods-0.0.3/src/ods.php'; 
		require_once 'ods-0.0.3/src/odsDraw.php'; 
		require_once 'ods-0.0.3/src/odsFontFace.php'; 
		require_once 'ods-0.0.3/src/odsStyle.php'; 
		require_once 'ods-0.0.3/src/odsTable.php'; 
		require_once 'ods-0.0.3/src/odsTableCell.php'; 
		require_once 'ods-0.0.3/src/odsTableColumn.php'; 
		require_once 'ods-0.0.3/src/odsTableRow.php'; 

		// Create Ods object 
		$ods  = new ods(); 

		// Create table named 'table 1' 
		$table = new odsTable('Solicitudes'); 

		// Create the first row 
		$rowHeaders   = new odsTableRow(); 

		// Add table headers 
		foreach ($headers as $header) {
			$rowHeaders->addCell( new odsTableCellString($header) ); 
		}
		
		// Attach rowHeaders to table 
		$table->addRow($rowHeaders);

		// Add data to headers. Create each row and each cell
		foreach ($data as $line) {
			$rowData   = new odsTableRow();
			foreach ($line as $cell) {
				$rowData->addCell( new odsTableCellString($cell) ); 
			}
			$table->addRow($rowData);
		}

		// Attach table to ods 
		$ods->addTable($table); 

		// Download the file 
		$ods->downloadOdsFile("quesabes_export.ods");   	
	}   

	

?>


<?php if ($comando=='form'): ?>
  <style>
	body {
	  font-size: 0.929em;
	  font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
	}

	.btn-primary {
	    color: #fff;
	    background-color: #337ab7;
	    border-color: #2e6da4;
	}
	.btn {
	    display: inline-block;
	    padding: 6px 12px;
	    margin-bottom: 0;
	    font-size: 14px;
	    font-weight: 400;
	    line-height: 1.42857143;
	    text-align: center;
	    white-space: nowrap;
	    vertical-align: middle;
	    -ms-touch-action: manipulation;
	    touch-action: manipulation;
	    cursor: pointer;
	    -webkit-user-select: none;
	    -moz-user-select: none;
	    -ms-user-select: none;
	    user-select: none;
	    background-image: none;
	    border: 1px solid transparent;
	    border-radius: 4px;
	}
  </style>
  <div id="form">
	<form action='./export.php' method='get'>
		<input type='hidden' id='optionField' name='option' value='export'>
		<input type='hidden' id='idField' name='id' value='all'>
		<input type='hidden' id='stateField' name='state' value=''>
		<input type='hidden' id='queryField' name='query' value=''>
		<input type='hidden' id='dateAfterField' name='date_after' value=''>
		<input type='hidden' id='dateBeforeField' name='date_before' value=''>
		<input type="radio" name="format" value="csv"> .CSV&nbsp;
		<input type="radio" name="format" value="ods"> .ODS&nbsp;
		<input type="radio" name="format" value="xml"> .XML
		<input class='btn btn-primary' name='commit' type='submit' value='Exportar'>
	</form>
  </div>

  <script type="text/javascript">
	var getParamsFromURL = function( queryString ) {
	    var params = {}, queries, temp, i, l;
	    queries = queryString.split("&");
	    for ( i = 0, l = queries.length; i < l; i++ ) {
		temp = queries[i].split('=');
		params[temp[0]] = temp[1];
	    }
	    return params;
	};

	var getStateFromURL = function( queryString ) {
	    var urlParts = queryString.split("/");
	    var onlyState = urlParts[urlParts.length-1].split("?");
	    return onlyState[0];
	};

	var url = (window.location != window.parent.location)
            ? document.referrer
            : document.location;
	var params = getParamsFromURL(url);
	var query = params['query'];
	var date_after = params['request_date_after'];
	var date_before = params['request_date_before'];
	var state = getStateFromURL(url);
	
	document.getElementById('stateField').value=state;
	if(query){document.getElementById('queryField').value=query};
	if(date_after){document.getElementById('dateAfterField').value=date_after};
	if(date_before){document.getElementById('dateBeforeField').value=date_before};
  </script>
<?php endif ?>
