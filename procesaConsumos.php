<?php

		error_reporting(E_ALL);
		ini_set('error_reporting', E_ALL);
		ini_set('display_errors',1);
		require_once("procesos/lib/xmlseclibs/XmlseclibsAdapter.php");
		require_once("procesos/lib/CONSUMO/ConsumoFolios.php");
		require_once("procesos/lib/CONSUMO/DocumentoConsumoFolios.php");
		require_once("procesos/lib/CONSUMO/ObjectAndXML.php");
		require_once("procesos/lib/SII.php");
		require_once("procesos/lib/Funciones.php");


		$carpeta=$argv[1];
		$fuente_cfg="procesos/config/$carpeta/config.ini";
		$archivoConfig = parse_ini_file($fuente_cfg, true);
		$archivoTemp=$archivoConfig["generales"]["ruta"].'\procesos\pejec.eje';
		$debug=$archivoConfig["opcionales"]["debug"];
		$validacion=$archivoConfig["opcionales"]["validacion"];
		$arcFuente="";
		$ficheroLog = "data/cFolios/$carpeta/log_".date("Ymd").".log";
		$ficheroErrorLog = "data/cFolios/$carpeta/error.log";
		//Leo directorio fuente
		$fuente="data/cFolios/$carpeta/";
		$directorio = opendir($fuente);
		$numDocs=0;
		$correlativo = 1;
		
		if(file_exists($archivoTemp))
		{
		    exit("Procesando documentos....\r");
		}
		else
		{
		    $f=fopen($archivoTemp,'w+');
		    fwrite($f,'ejecutandose');
		    fclose($f);
		}

			date_default_timezone_set('America/Santiago');
		

		while ($archivo = readdir($directorio))
		{
		    
		   		 $arcFuente=$fuente.$archivo;
			    if(strpos($arcFuente, ".log")==0)
			    {
				        if (is_file($arcFuente))
				        {
				            escribeLog("Proceso iniciado ".date("H:i:s")."\n",$ficheroLog);
				                    //abrimos el archivo para comenzar con su proceso
				            echo "Procesando archivo ".$arcFuente."\t\n";
				            $numDocs++;
				            $fp= fopen($arcFuente,"r");
				            $cabecera = fgets($fp);
				            $array_archivo = explode(";",$cabecera);
				            fclose($fp);
							            if($debug)
							            {
							               echo "Proceso consumo folios";
							            }
							             escribeLog("Proceso consumo folios",$ficheroLog);
							            procConsumoFolios(substr($array_archivo[0],1),$argv[2]);
				        }
			    }
			    unlink($arcFuente);
				unlink($archivoTemp);
		}

		function procConsumoFolios($tipo_doc)
		{

	    global $arcFuente,$archivoConfig,$carpeta;
	    
	    $linea=0;
	    $rut_emisor=$archivoConfig["contribuyente"]["RUT"];
	    $rut_envia=$archivoConfig["contribuyente"]["RUTRL"];
	    $FchResol=$archivoConfig["contribuyente"]["FECRESOL"];
	    $NumResol=$archivoConfig["contribuyente"]["NUMRESOL"];
	    
	    $timezone = new DateTimeZone('America/Santiago'); 
	    $date = new DateTime('', $timezone);
	    $TmstFirma = $date->format('Y-m-d\TH:i:s');

	    	$fp= fopen($arcFuente,"r");
		    //OBTENGO EL FICHERO FUENTE COMPLETO
		    $contenido_fichero = fread($fp, filesize($arcFuente));
		    //Separo por lineas.
		    $contenido_fichero=str_replace("\n","",$contenido_fichero);
		    $contenido_fichero=str_replace("[","",$contenido_fichero);
		    	
		    $array_lineas = explode("]",$contenido_fichero);

		     for($l=0;$l<=count($array_lineas)-1;$l++)
		     { //recorro las lineas para obtener los valores de cada una de ellas
		        if($l==0)
		        {
		            /*CARATULA*/    
		            if($archivoConfig["opcionales"]["debug"]==1)
		            {
		                echo "\tProceso Caratula consumo folios \n";
		        	}
		            escribeLog("Proceso Caratula consumo folios ");
		            $array_caratula = explode(";",$array_lineas[$l]);
		        }
		        else if($l==1)
		        {
		            /*resumen bo*/
		            if($archivoConfig["opcionales"]["debug"]==1){
		                echo "\tProceso resumen boletas\n";
		        }
		            escribeLog("Proceso resumen boletas ");
		            //parse por ~ para obtener array con cada linea de detalle
		            $array_linea_detalle = explode("~",str_replace(explode(",","\r,"),"",$array_lineas[$l]));
					
		            for($ld=0;$ld<=count($array_linea_detalle)-1;$ld++){
		                $array_valores_detalle[] = $array_linea_detalle[$ld];
		            }
		            //Detalle
		            for($d=0;$d<=count($array_valores_detalle)-1;$d++){
		                $array_detalle = explode(";",$array_valores_detalle[$d]);
		            }
		        }
		    }
		    fclose($fp);

		    $RCOF = new ConsumoFolios();
		    $consumo = new DocumentoConsumoFolios();
		    $consumo ->setCaratula();
		    $consumo->Caratula->setRutEmisor($rut_emisor);
		    $consumo->Caratula->setRutEnvia($rut_envia);		    
		    $consumo->Caratula->setFchResol($FchResol);
		    $consumo->Caratula->setNroResol($NumResol);
		    $consumo->Caratula->setFchInicio($array_caratula[4]);
		    $consumo->Caratula->setFchFinal($array_caratula[5]);		    
		    $consumo->Caratula->setCorrelativo($array_caratula[8]);
		    $consumo->Caratula->setSecEnvio($array_caratula[6]);
		    $consumo->Caratula->setTmstFirmaEnv($TmstFirma);

		    $consumo->setResumen();

		    $consumo->Resumen->setTipoDocumento($array_detalle[0]);
		    $consumo->Resumen->setMntNeto($array_detalle[1]);
		    $consumo->Resumen->setMntIva($array_detalle[2]);
		    $consumo->Resumen->setTasaIVA($array_detalle[3]);
		    $consumo->Resumen->setMntExento($array_detalle[4]);
		    $consumo->Resumen->setMntTotal($array_detalle[5]);
		    $consumo->Resumen->setFoliosEmitidos($array_detalle[6]);
		    $consumo->Resumen->setFoliosAnulados($array_detalle[7]);
		    $consumo->Resumen->setFoliosUtilizados($array_detalle[8]);
		    
		    		   
			if (intval($array_detalle[9])>0)
			{
			 	$rangoUtil = new RangoUtilizados();			 				 	
			 	$consumo->Resumen->setRangoUtilizados();
			 	$consumo->Resumen->RangoUtilizados->setInicial($array_detalle[9]);
			 	$consumo->Resumen->RangoUtilizados->setFinal($array_detalle[10]);
			 				 	
			}
			
		

			if (intval($array_detalle[11])>0)
			{
				$rangoAnul = new RangoAnulados();
				$consumo->Resumen->setRangoAnulados();
				$consumo->Resumen->RangoAnulados->setInicial($array_detalle[11]);				
				$consumo->Resumen->RangoAnulados->setFinal($array_detalle[12]);
				
			}		    
		    //$consumo -> setResumen();
		    //$consumo->setTmstFirma($TmstFirma);
		    $idConsumo = "RCOF-".$array_caratula[2];
		    $obj = new ObjectAndXML($idConsumo, substr($rut_emisor,0,-2),"ConsumoFolios");
		    $obj->setStartElement("ConsumoFolios");
		    $obj->setId($idConsumo);

		   $RCOF->setDocumentoConsumoFolios($consumo);
    		$recordsXML = $obj->objToXML($RCOF);

		    $RCOF_TIMBRE = new DOMDocument();
		    $RCOF_TIMBRE->formatOutput = FALSE;
		    $RCOF_TIMBRE->preserveWhiteSpace = TRUE;
		    $RCOF_TIMBRE->load("procesos/xml_respuestas/".substr($rut_emisor,0,-2)."/".$obj->getId().".xml");
		    
		    $RCOF_TIMBRE->encoding = "ISO-8859-1";
		    $xmlTool = new FR3D\XmlDSig\Adapter\XmlseclibsAdapter();
		    
		    $pfx = file_get_contents(dirname(__FILE__) . "/certificado/".substr($rut_emisor,0,-2)."/".$archivoConfig["generales"]["certificado"]);
		    openssl_pkcs12_read($pfx, $key,$archivoConfig["generales"]["clavefirma"] );
		    
		    $xmlTool->setPrivateKey($key["pkey"]);
		    $xmlTool->setpublickey($key["cert"]);
		    $xmlTool->addTransform(FR3D\XmlDSig\Adapter\XmlseclibsAdapter::ENVELOPED);
		    $xmlTool->sign($RCOF_TIMBRE, "RCOF");
		    $RCOF_TIMBRE->save("procesos/xml_respuestas/".substr($rut_emisor,0,-2)."/".$obj->getId().".xml");
		    
			libxml_use_internal_errors(true);
		

}
