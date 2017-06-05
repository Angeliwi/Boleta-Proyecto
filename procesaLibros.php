<?php
require_once("procesos/lib/xmlseclibs/XmlseclibsAdapter.php");
if($argv[2]=="VENTA")
{
	require_once("procesos/lib/IECV/IEV.php");
}
else if($argv[2]=="COMPRA")
{
	require_once("procesos/lib/IECV/IEC.php");
}
else if($argv[2]=="GUIA")
{
	require_once("procesos/lib/IECV/IEG.php");
}
else if ($argv[2]=="BOLETA")
{
    require_once("procesos/lib/IECV/IEB.php");
}
else
{
	escribeLog("NO SE ENCONTRO EL TIPO DE LIBRO ".$argv[2]);
	exit;
}

require_once("procesos/lib/IECV/ObjectAndXML.php");
require_once("procesos/lib/SII.php");

date_default_timezone_set('America/Santiago');

$carpeta=$argv[1];
//Leo directorio fuente
$fuente="data/libros/$carpeta/";
$fuente_cfg="procesos/config/$carpeta/config.ini";
$directorio = opendir($fuente);
$archivoConfig = parse_ini_file($fuente_cfg, true);
$archivoTemp=$archivoConfig["generales"]["ruta"].'\procesos\pejec.eje';
$debug=$archivoConfig["opcionales"]["debug"];
$arcFuente="";
$ficheroLog = "data/dte/$carpeta/log_".date("Ymd").".log";

    if(file_exists($archivoTemp))
    {
        if($debug==1)
        {
    		error_reporting(E_ALL);
    		ini_set('error_reporting', E_ALL);
    		ini_set('display_errors',1);
            echo "Procesando Libros....\n";    
        exit("Procesando Libros....\r");
        }
        else
        {
                $f=fopen($archivoTemp,'w+');
                fwrite($f,'ejecutandose');
                fclose($f);
        }
    }

//obtenemos un archivo y luego otro sucesivamente
while ($archivo = readdir($directorio)){
    //verificamos si es o no un archivo
    $arcFuente=$fuente.$archivo;
    if(strpos($arcFuente, ".log")==0){
		if (is_file($arcFuente)){
			//abrimos el archivo para comenzar con su proceso
			$fp= fopen($arcFuente,"r");
			$cabecera = fgets($fp);
			$array_archivo = explode(";",$cabecera);
			fclose($fp);
			$aArchivo=explode("_",$archivo);
			$tipoLibroFile = trim(strtoupper($aArchivo[1]));
			switch($tipoLibroFile){
				case "VENTA":
					if($archivoConfig["opcionales"]["debug"]==1)
                    {
						echo "Proceso Libro de Venta $arcFuente";
					}
					escribeLog("Proceso Libro de Venta $arcFuente");
					procLibroVenta($array_archivo[5]);
					break;
				case "COMPRA":
					if($archivoConfig["opcionales"]["debug"]==1)
                    {
						echo "Proceso Libro de Compra $arcFuente=";
					}
					escribeLog("Proceso Libro de Compra $arcFuente");
					procLibroCompra($array_archivo[5]);
					break;
				case "GUIA":
					if($archivoConfig["opcionales"]["debug"]==1)
                    {
						echo "Proceso Libro de Guias de Despacho";
					}
					escribeLog("Proceso Libro de Guias $arcFuente");
					procLibroGuias();
					break;
                case 'BOLETA':
                    if($archivoConfig["opcionales"]["debug"]==1)
                    {
                        echo "Proceso Libro de Boletas";
                    }
                    escribeLog("Proceso Libro de Boletas $arcFuente");
                    procLibroBoletas();
                    break;
				default:
					echo "No se encontro el tipo de libro ".$tipoLibroFile;
					escribeLog("No se encotro el tipo de libro ".$tipoLibroFile);
			}
			unlink($arcFuente);
			unlink($archivoTemp);
		}
	}
}

//*************************agregando el nuevo metodo para procesar el libro de boletas****************************************
	function procLibroBoletas()
	{
        global $arcFuente,$archivoConfig,$carpeta;
        $linea=0;
        $LibroSinMovimiento=false;
        $rut_emisor=$archivoConfig["contribuyente"]["RUT"];
        $rut_envia=$archivoConfig["contribuyente"]["RUTRL"];
        $FchResol=$archivoConfig["contribuyente"]["FECRESOL"];
        $NumResol=$archivoConfig["contribuyente"]["NUMRESOL"];
        $timezone = new DateTimeZone('America/Santiago'); 
        $date = new DateTime('', $timezone);
        $TmstFirma = $date->format('Y-m-d\TH:i:s');
        $fp = fopen($arcFuente,"r");
        $contenido_fichero = fread($fp, filesize($arcFuente));  
        $contenido_fichero=str_replace("\n","",$contenido_fichero);
        $contenido_fichero=str_replace("[","",$contenido_fichero);
        $array_lineas = explode("]",$contenido_fichero);

        for($l=0;$l<=count($array_lineas)-1;$l++)
        {
                if($l==0)
                {                   
                    if($debug==1)
                    {
                        echo "\tProceso Caratula\n";
                    }
                    escribeLog("Proceso Caratula ");
                    $array_caratula = explode(";",$array_lineas[$l]);

                }
                else if($l==1)
                {
                    if($debug==1)
                    {
                        echo "\tProceso Detalle\n";
                    }
                    escribeLog("Proceso Detalle ");

                    $array_linea_detalle = explode("~",str_replace(explode(",","\r,"),"",$array_lineas[$l]));                    

                    for($ld=0;$ld<=count($array_linea_detalle)-1;$ld++)
                    {

                        $array_valores_detalle[] = $array_linea_detalle[$ld];

                    }

                    for($d=0;$d<=count($array_valores_detalle)-1;$d++)
                    {
                        $array_detalle = explode(";",$array_valores_detalle[$d]);
                    }

                }
                else if($l==2)
                {
                    if($debug==1)
                    {
                        echo "\tProceso RESUMEN\n";
                    }
                    escribeLog("Proceso RESUMEN");
                    $array_linea_resumen = explode("~",str_replace(explode(",","\r,"),"",$array_lineas[$l]));

                    for($lr=0;$lr<=count($array_linea_resumen)-1;$lr++)
                    {
                        $array_valores_resumen[] = $array_linea_resumen[$lr];
                    }
            }

        }

    fclose($fp);

    if($l==0)
    {
        $LibroSinMovimiento=true;
    }
     /*PROCESO GENERADOR DEL DOCUMENTO*/
    $LIBRO = new IEB();
    $EnvioLibro = new EnvioLibro();
    $EnvioLibro->setCaratula();
    $EnvioLibro->Caratula->setRutEmisorLibro($rut_emisor);
    $EnvioLibro->Caratula->setRutEnvia($rut_envia);
    $EnvioLibro->Caratula->setPeriodoTributario($array_caratula[2]);
    $EnvioLibro->Caratula->setFchResol($FchResol);
    $EnvioLibro->Caratula->setNroResol($NumResol);
    $EnvioLibro->Caratula->setTipoLibro("ESPECIAL");//$array_caratula[5]);
    $EnvioLibro->Caratula->setTipoEnvio($array_caratula[6]);    
    //if($array_caratula[6]=="ESPECIAL"){
        $EnvioLibro->Caratula->setFolioNotificacion('1');
    //}
    if(!$LibroSinMovimiento)
    {

        //Resumen

        $EnvioLibro->setResumenPeriodo();
        for($lr=0;$lr<=count($array_valores_resumen)-1;$lr++)
        {
            $array_resumen = explode(";",$array_valores_resumen[$lr]);           

            //Resumen
            $Totales = new TotalesPeriodo;
            for($r=0;$r<=count($array_valores_resumen)-1;$r++)
            {

                $Totales->setTpoDoc(trim($array_resumen[0]));

                if(intval($array_resumen[1])>0){$Totales->setTotAnulado($array_resumen[1]);}

                $Totales_Servicio = new TotalesServicio;

                    if(intval($array_resumen[2])>0){$Totales_Servicio->setTpoServ($array_resumen[2]);}

                    if(intval($array_resumen[3])>0){$Totales_Servicio->setPeriodoDevengado($array_resumen[3]);}

                    $Totales_Servicio->setTotDoc($array_resumen[4]);

                    $Totales_Servicio->setTotMntExe(strval(round(($array_resumen[5]!="")?$array_resumen[5]:"0")));

                    $Totales_Servicio->setTotMntNeto(strval(round(($array_resumen[6]!="")?$array_resumen[6]:"0")));

                    $Totales_Servicio->setTasaIVA($array_resumen[7]);

                    $Totales_Servicio->setTotMntIVA(strval(round(($array_resumen[8]!="")?$array_resumen[8]:"0")));

                    $Totales_Servicio->setTotMntTotal(strval(round(($array_resumen[9]!="")?$array_resumen[9]:"0")));

                    if(intval($array_resumen[10]>0)){$Totales_Servicio->setTotMntNoFact($array_resumen[10]);}

                    if(intval($array_resumen[11]>0)){$Totales_Servicio->setTotMntPeriodo($array_resumen[11]);}

                    if(intval($array_resumen[12]>0)){$Totales_Servicio->setTotSaldoAnt($array_resumen[12]);}

                    if(intval($array_resumen[13]>0)){$Totales_Servicio->setTotVlrPagar($array_resumen[13]);}

                    if(intval($array_resumen[15]>0)){$Totales_Servicio->setTotTicket($array_resumen[15]);}

                $Totales->setTotalesServicio($Totales_Servicio);

            }

            $EnvioLibro->ResumenPeriodo->setTotalesPeriodos($Totales);     

        }



        for($lr=0;$lr<=count($array_valores_detalle)-1;$lr++)
        {

            $array_detalle = explode(";",$array_valores_detalle[$lr]);

            //Detalle

            $Detalle = new Detalle;

            for($r=0;$r<=count($array_valores_detalle)-1;$r++){

                $Detalle->setTpoDoc(trim($array_detalle[0]));$aDetalle["TpoDoc"][]=trim($array_detalle[0]);

                $Detalle->setFolioDoc($array_detalle[1]);$aDetalle["NroDoc"][]=trim($array_detalle[1]);

                if($array_detalle[2]!=""){ $Detalle->setAnulado($array_detalle[2]);}

                $Detalle->setTpoServ($array_detalle[3]);

                $Detalle->setFchEmiDoc($array_detalle[4]);

                if($array_detalle[5]!=""){$Detalle->setFchVencDoc($array_detalle[5]);}

                If($array_detalle[6]!=""){$Detalle->setPeriodoDesde($array_detalle[6]);}

                if($array_detalle[7]!=""){$Detalle->setPeriodoHasta($array_detalle[7]);}

                If($array_detalle[8]!=""){$Detalle->setCdgSIISucur($array_detalle[7]);}

                $Detalle->setRUTCliente($array_detalle[9]);

                If($array_detalle[10]!=""){$Detalle->setCodIntCli($array_detalle[10]);}

                $Detalle->setMntExe(strval(round(($array_detalle[11]!="")?$array_detalle[11]:"0")));

                if(intval($array_detalle[12]>0)){$Detalle->setMntTotal($array_detalle[12]);}

                if(intval($array_detalle[13])>0){$Detalle->setMntNoFact($array_detalle[13]);}

                if(intval($array_detalle[13]>0)){$Detalle->setMntPeriodo($array_detalle[13]);}

                if(intval($array_detalle[13]>0)){$Detalle->setSaldoAnt($array_detalle[13]);}

                $Detalle->setVlrPagar(strval(round(($array_detalle[14]!="")?$array_detalle[14]:"0")));

                if(intval($array_detalle[15]>0)){$Detalle->setTotTicketBoleta($array_detalle[15]);}

            }

            $EnvioLibro->setDetalle($Detalle);     



        }

    }
    $EnvioLibro->setTmstFirma($TmstFirma);
    $idLibro = "EnvLbrBoleta-".$array_caratula[2];
    $obj = new ObjectAndXML($idLibro, substr($rut_emisor,0,-2),"boleta");
    $obj->setStartElement("LibroBoleta");
    $obj->setId($idLibro);
    $LIBRO->setEnvioLibro($EnvioLibro);
    utf8_encode_deep($LIBRO);
    $recordsXML = $obj->objToXML($LIBRO);
    $IECV_TIMBRE = new DOMDocument();
    $IECV_TIMBRE->formatOutput = FALSE;
    $IECV_TIMBRE->preserveWhiteSpace = TRUE;
    $IECV_TIMBRE->load("procesos/xml_libros/".substr($rut_emisor,0,-2)."/boleta/".$obj->getId().".xml");
    $IECV_TIMBRE->encoding = "ISO-8859-1";
    $xmlTool = new FR3D\XmlDSig\Adapter\XmlseclibsAdapter();
    $pfx = file_get_contents(dirname(__FILE__) . "/certificado/".substr($rut_emisor,0,-2)."/".$archivoConfig["generales"]["certificado"]);
    openssl_pkcs12_read($pfx, $key,$archivoConfig["generales"]["clavefirma"] );  
    $xmlTool->setPrivateKey($key["pkey"]);
    $xmlTool->setpublickey($key["cert"]);
    $xmlTool->addTransform(FR3D\XmlDSig\Adapter\XmlseclibsAdapter::ENVELOPED);
    $xmlTool->sign($IECV_TIMBRE, "LIBRO");
    //ojo aca, crear carpeta boleta
    $IECV_TIMBRE->save("procesos/xml_libros/".substr($rut_emisor,0,-2)."/boleta/".$obj->getId().".xml");
    $xmlv = new DOMDocument();
    //crear la carpeta boleta plz 
    $xmlv->load("procesos/xml_libros/".substr($rut_emisor,0,-2)."/boleta/".$obj->getId().".xml");
    if($archivoConfig["opcionales"]["validacion"]==1)
    {
        if (!$xmlv->schemaValidate('procesos/validaciones/LibroBOLETA_v10.xsd')) 
        {
            libxml_display_errors();
            $msg="ERROR DE VALIDACION REVISE EL LOG";
        }
        else
        {
            $msg="SCHEMA OK LIBRO GENERADO";
        }
    }
    escribeLog("**EOP**|$msg");
        $model["RutEnvia"]=$rut_envia;
        $model["RutEmisor"]=$rut_emisor;
        $model["SetDTE_ID"]=$obj->getId();
        $model["clavefirma"]=$archivoConfig["generales"]["clavefirma"];
        $model["certificado"]=$archivoConfig["generales"]["certificado"];
        $model["tipoLibro"]="boleta";
        $model["carpeta"]=$carpeta;
        $model["ambiente"]=$archivoConfig["opcionales"]["ambiente"];
        $trackId=0;$intentos=1;
        
        while(intval($trackId)==0 and $intentos<=5)
        {
            escribeLog("Intentando subir libro de boleta a SII [".$intentos."]");
            $resultado = enviarIECVAlSii($model);
            $aRes=explode("|",$resultado);
            $trackId=$aRes[0];
            $intentos++;
            sleep(5);
        }
        $aRes=explode("|",$resultado);
        escribeLog("**EOP**|".$aRes[1]."|".$aRes[2]."-".$aRes[0]);


//                              *********************************fin proceso libro boletas************************************
}

function procLibroVenta($tipo_doc){

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
    for($l=0;$l<=count($array_lineas)-1;$l++){ //recorro las lineas para obtener los valores de cada una de ellas
        if($l==0){
            /*CARATULA*/    
            if($archivoConfig["opcionales"]["debug"]==1){
                echo "\tProceso Caratula\n";
            }
            escribeLog("Proceso Caratula ");
            $array_caratula = explode(";",$array_lineas[$l]);
        }else if($l==1){
            /*DETALLE*/
            if($archivoConfig["opcionales"]["debug"]==1){
                echo "\tProceso Detalle\n";
            }
            escribeLog("Proceso Detalle ");
            //parse por ~ para obtener array con cada linea de detalle
            $array_linea_detalle = explode("~",str_replace(explode(",","\r,"),"",$array_lineas[$l]));
			
            for($ld=0;$ld<=count($array_linea_detalle)-1;$ld++){
                $array_valores_detalle[] = $array_linea_detalle[$ld];
            }
            //Detalle
            for($d=0;$d<=count($array_valores_detalle)-1;$d++){
                $array_detalle = explode(";",$array_valores_detalle[$d]);
            }
        }else if($l==2){
            /*RESUMEN*/
            if($archivoConfig["opcionales"]["debug"]==1){
                echo "\tProceso RESUMEN\n";
            }
            escribeLog("Proceso RESUMEN");
            $array_linea_resumen = explode("~",str_replace(explode(",","\r,"),"",$array_lineas[$l]));
            for($lr=0;$lr<=count($array_linea_resumen)-1;$lr++){
                $array_valores_resumen[] = $array_linea_resumen[$lr];
            }
            
        }
    }
    fclose($fp);
    
    /*PROCESO GENERADOR DEL DOCUMENTO*/
    $LIBRO = new IEV();
    $EnvioLibro = new EnvioLibro();
    $EnvioLibro->setCaratula();
    $EnvioLibro->Caratula->setRutEmisorLibro($rut_emisor);
    $EnvioLibro->Caratula->setRutEnvia($rut_envia);
    $EnvioLibro->Caratula->setPeriodoTributario($array_caratula[2]);
    $EnvioLibro->Caratula->setFchResol($FchResol);
    $EnvioLibro->Caratula->setNroResol($NumResol);
    $EnvioLibro->Caratula->setTipoOperacion($tipo_doc);
    $EnvioLibro->Caratula->setTipoLibro($array_caratula[6]);
	
	
    if($array_caratula[6]=="ESPECIAL"){
        $EnvioLibro->Caratula->setFolioNotificacion('1');
    }
    $EnvioLibro->Caratula->setTipoEnvio($array_caratula[7]);
    
 
    //Resumen
    $EnvioLibro->setResumenPeriodo();

    for($lr=0;$lr<=count($array_valores_resumen)-1;$lr++){
        $array_resumen = explode(";",$array_valores_resumen[$lr]);
        //Resumen
        
        $Totales = new TotalesPeriodo;
        for($r=0;$r<=count($array_valores_resumen)-1;$r++){
            $Totales->setTpoDoc(trim($array_resumen[0]));
			if(intval($array_resumen[1])>0){
				$Totales->setTotDoc($array_resumen[1]);
			}else{
				escribeLog("**EOP**|15|La cantidad de documentos en el resumen no puede ser igual a 0");
				exit("**EOP**|15|La cantidad de documentos en el resumen no puede ser igual a 0");
			}
			if($array_resumen[3]!=""){
				$Totales->setTotMntExe(strval(round($array_resumen[3])));
			}else{
				$Totales->setTotMntExe("0");
			}
			
			if($array_resumen[4]!=""){
				$Totales->setTotMntNeto(strval(round($array_resumen[4])));
			}else{
				$Totales->setTotMntNeto("0");
			}
			
			if($array_resumen[5]!=""){
				$Totales->setTotMntIVA(strval(round($array_resumen[5])));
			}else{
				$Totales->setTotMntIVA("0");
			}
            if($array_resumen[0]==61){
                $Totales->setTotIVAFueraPlazo($array_resumen[6]);
            }
            $Totales->setTotMntTotal(strval(round($array_resumen[7])));
        }
        $EnvioLibro->ResumenPeriodo->setTotalesPeriodos($Totales);     
        
    }
    
    
    
    
    for($lr=0;$lr<=count($array_valores_detalle)-1;$lr++){
        $array_detalle = explode(";",$array_valores_detalle[$lr]);
        //Detalle
        
        $Detalle = new Detalle;
        for($r=0;$r<=count($array_valores_detalle)-1;$r++){
            $Detalle->setTpoDoc(trim($array_detalle[0]));
            $aDetalle["TpoDoc"][]=trim($array_detalle[0]);
            
            if($array_detalle[0]==56 or $array_detalle[0]==61){
                //$Detalle->setEmisor()
            }
            $Detalle->setNroDoc($array_detalle[1]);
            $aDetalle["NroDoc"][]=trim($array_detalle[1]);
            
            if($array_detalle[2]!=""){
                $Detalle->setAnulado($array_detalle[2]);
            }
            if($array_caratula[6]=="AJUSTE"){
                if(intval($array_caratula[3])>0){
                    $Detalle->setOperacion($array_detalle[3]);
                }
            }
            $Detalle->setTasaImp($array_detalle[4]);
            if(intval($array_detalle[5])>0){
                $Detalle->setNumInt($array_detalle[5]);
            }
            $Detalle->setFchDoc($array_detalle[6]);
            if(intval($array_detalle[7])>0){
                $Detalle->setCdgSIISucur($array_detalle[7]);
            }
            $Detalle->setRUTDoc($array_detalle[8]);
            $Detalle->setRznSoc(htmlentities($array_detalle[9],ENT_IGNORE));
            //$detalle->setnumid();
            //$detalle->setnacionalidad();
            if($array_detalle[0]==61 and $array_detalle[10]!=""){
                $Detalle->setTpoDocRef($array_detalle[10]);
                $Detalle->setFolioDocRef($array_detalle[11]);
            }
			
			if($array_detalle[12]!=""){
				$Detalle->setMntExe($array_detalle[12]);
			}else{
				$Detalle->setMntExe("0");
			}
			
			if($array_detalle[13]!=""){
				$Detalle->setMntNeto($array_detalle[13]);
			}else{
				$Detalle->setMntNeto("0");
			}
			
			if($array_detalle[14]!=""){
				$Detalle->setMntIVA($array_detalle[14]);
			}else{
				$Detalle->setMntIVA("0");
			}
			
			
            if(intval($array_detalle[15])>0){
                $Detalle->setIVAFueraPlazo($array_detalle[15]);
            }
			
			if($array_detalle[16]!=""){
				$Detalle->setMntTotal($array_detalle[16]);
			}else{
				$Detalle->setMntTotal("0");
			}

            if(intval($array_detalle[17])>0){
                $OtrosImp = new OtrosImp();
                $OtrosImp->setCodImp($array_detalle[17]);
                $OtrosImp->setTasaImp($array_detalle[18]);
                $OtrosImp->setMntImp($array_detalle[19]);
                $Detalle->setOtrosImp($OtrosImp);
            }
            
            if(intval($array_detalle[20])>0){
                $OtrosImp = new OtrosImp();
                $OtrosImp->setCodImp($array_detalle[20]);
                $OtrosImp->setTasaImp($array_detalle[21]);
                $OtrosImp->setMntImp($array_detalle[22]);
                $Detalle->setOtrosImp($OtrosImp);
            }
            
            if(intval($array_detalle[23])>0){
                $OtrosImp = new OtrosImp();
                $OtrosImp->setCodImp($array_detalle[23]);
                $OtrosImp->setTasaImp($array_detalle[24]);
                $OtrosImp->setMntImp($array_detalle[25]);
                $Detalle->setOtrosImp($OtrosImp);
            }
            
            if(intval($array_detalle[26])>0){
                $OtrosImp = new OtrosImp();
                $OtrosImp->setCodImp($array_detalle[26]);
                $OtrosImp->setTasaImp($array_detalle[27]);
                $OtrosImp->setMntImp($array_detalle[28]);
                $Detalle->setOtrosImp($OtrosImp);
            }
            
            if(intval($array_detalle[29])>0){
                $OtrosImp = new OtrosImp();
                $OtrosImp->setCodImp($array_detalle[29]);
                $OtrosImp->setTasaImp($array_detalle[30]);
                $OtrosImp->setMntImp($array_detalle[31]);
                $Detalle->setOtrosImp($OtrosImp);
            }
            
            if(intval($array_detalle[32])>0){
                $OtrosImp = new OtrosImp();
                $OtrosImp->setCodImp($array_detalle[32]);
                $OtrosImp->setTasaImp($array_detalle[33]);
                $OtrosImp->setMntImp($array_detalle[34]);
                $Detalle->setOtrosImp($OtrosImp);
            }
            
            
            if(intval($array_detalle[35])>0){
                $OtrosImp = new OtrosImp();
                $OtrosImp->setCodImp($array_detalle[35]);
                $OtrosImp->setTasaImp($array_detalle[36]);
                $OtrosImp->setMntImp($array_detalle[37]);
                $Detalle->setOtrosImp($OtrosImp);
            }
            
        }
        $EnvioLibro->setDetalle($Detalle);     
        
    }
    
    
   
    

    $EnvioLibro->setTmstFirma($TmstFirma);
    $idLibro = "EnvLbrVta-".$array_caratula[2];
    $obj = new ObjectAndXML($idLibro, substr($rut_emisor,0,-2),"venta");
    $obj->setStartElement("LibroCompraVenta");
    $obj->setId($idLibro);

    $LIBRO->setEnvioLibro($EnvioLibro);
    $recordsXML = $obj->objToXML($LIBRO);
    
    $IECV_TIMBRE = new DOMDocument();
    $IECV_TIMBRE->formatOutput = FALSE;
    $IECV_TIMBRE->preserveWhiteSpace = TRUE;
    $IECV_TIMBRE->load("procesos/xml_libros/".substr($rut_emisor,0,-2)."/venta/".$obj->getId().".xml");
    $IECV_TIMBRE->encoding = "ISO-8859-1";
    $xmlTool = new FR3D\XmlDSig\Adapter\XmlseclibsAdapter();

    $pfx = file_get_contents(dirname(__FILE__) . "/certificado/".substr($rut_emisor,0,-2)."/".$archivoConfig["generales"]["certificado"]);
    openssl_pkcs12_read($pfx, $key,$archivoConfig["generales"]["clavefirma"] );
    
    $xmlTool->setPrivateKey($key["pkey"]);
    $xmlTool->setpublickey($key["cert"]);
    $xmlTool->addTransform(FR3D\XmlDSig\Adapter\XmlseclibsAdapter::ENVELOPED);
    $xmlTool->sign($IECV_TIMBRE, "LIBRO");
    $IECV_TIMBRE->save("procesos/xml_libros/".substr($rut_emisor,0,-2)."/venta/".$obj->getId().".xml");

    $xmlv = new DOMDocument(); 
	$xmlv->load("procesos/xml_libros/".substr($rut_emisor,0,-2)."/venta/".$obj->getId().".xml");
	if($archivoConfig["opcionales"]["validacion"]==1){
		if (!$xmlv->schemaValidate('procesos/validaciones/LibroCV_v10.xsd')) {
			libxml_display_errors();
			exit("ERROR DE VALIDACION");
		}
	}
	
    $model["RutEnvia"]=$rut_envia;
    $model["RutEmisor"]=$rut_emisor;
    $model["SetDTE_ID"]=$obj->getId();
    $model["clavefirma"]=$archivoConfig["generales"]["clavefirma"];
    $model["certificado"]=$archivoConfig["generales"]["certificado"];
    $model["tipoLibro"]="venta";
    $model["carpeta"]=$carpeta;
    $model["ambiente"]=$archivoConfig["opcionales"]["ambiente"];
    $trackId=0;$intentos=1;

    while(intval($trackId)==0 and $intentos<=5){
        escribeLog("Intentando subir libro de Venta a SII [".$intentos."]");
        $resultado = enviarIECVAlSii($model);
        $aRes=explode("|",$resultado);
        $trackId=$aRes[0];
        $intentos++;
        sleep(5);
    }
    $aRes=explode("|",$resultado);
    escribeLog("**EOP**|".$aRes[1]."|".$aRes[2]."-".$aRes[0]);
}

function procLibroCompra($tipo_doc){

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
	
    for($l=0;$l<=count($array_lineas)-1;$l++){ //recorro las lineas para obtener los valores de cada una de ellas
        if($l==0){
            /*CARATULA*/    
            if($archivoConfig["opcionales"]["debug"]==1){
                echo "\tProceso Caratula\n";
            }
            escribeLog("Proceso Caratula ");
            $array_caratula = explode(";",$array_lineas[$l]);
        }else if($l==1){
             /*DETALLE*/
            if($archivoConfig["opcionales"]["debug"]==1){
                echo "\tProceso Detalle\n";
            }
            escribeLog("Proceso Detalle ");
            //parse por ~ para obtener array con cada linea de detalle
			$array_linea_detalle = explode("~",str_replace(explode(",","\r,"),"",$array_lineas[$l]));
            for($ld=0;$ld<=count($array_linea_detalle)-1;$ld++){
                $array_valores_detalle[] = $array_linea_detalle[$ld];
            }
            //Detalle
            for($d=0;$d<=count($array_valores_detalle)-1;$d++){
                $array_detalle = explode(";",$array_valores_detalle[$d]);
            }
            
        }else if($l==2){
           /*RESUMEN*/
            if($archivoConfig["opcionales"]["debug"]==1){
                echo "\tProceso RESUMEN\n";
            }
            escribeLog("Proceso RESUMEN");
            //parse por ~ para obtener array con cada linea de detalle
			$array_linea_resumen = explode("~",str_replace(explode(",","\r,"),"",$array_lineas[$l]));
            for($lr=0;$lr<=count($array_linea_resumen)-1;$lr++){
                $array_valores_resumen[] = $array_linea_resumen[$lr];
            }
        }
    }
    fclose($fp);
    
    /*PROCESO GENERADOR DEL DOCUMENTO*/
    $LIBRO = new IEC();
    $EnvioLibro = new EnvioLibro();
    $EnvioLibro->setCaratula();
	
	$EnvioLibro->Caratula->setRutEmisorLibro($rut_emisor);
    $EnvioLibro->Caratula->setRutEnvia($rut_envia);
    $EnvioLibro->Caratula->setPeriodoTributario($array_caratula[2]);
    $EnvioLibro->Caratula->setFchResol($FchResol);
    $EnvioLibro->Caratula->setNroResol($NumResol);
    $EnvioLibro->Caratula->setTipoOperacion($array_caratula[5]);
    $EnvioLibro->Caratula->setTipoLibro($array_caratula[6]);
    $EnvioLibro->Caratula->setTipoEnvio($array_caratula[7]);
    if($array_caratula[7]=="PARCIAL"){
        $EnvioLibro->Caratula->setNroSegmento($array_caratula[10]);
    }
    if($array_caratula[6]=="ESPECIAL"){
        $EnvioLibro->Caratula->setFolioNotificacion($array_caratula[8]);
    }
    if($array_caratula[9]!=""){
        $EnvioLibro->Caratula->setCodAutRec($array_caratula[9]);
        
    }
    
 
    //Resumen
    $EnvioLibro->setResumenPeriodo();
    for($lr=0;$lr<=count($array_valores_resumen)-1;$lr++){
        $array_resumen = explode(";",$array_valores_resumen[$lr]);
        //Resumen
        
        $Totales = new TotalesPeriodo;
        //for($r=0;$r<=count($array_valores_resumen)-1;$r++){
            
            $Totales->setTpoDoc(trim($array_resumen[0]));
            $Totales->setTotDoc($array_resumen[2]);
            if(intval($array_resumen[3])>0){
                $Totales->setTotAnulado($array_resumen[3]);
            }
            if(intval($array_resumen[4])>0){
                $Totales->setTotOpExe($array_resumen[4]);
            }
			if($array_resumen[5]>0){
				$Totales->setTotMntExe(strval(round($array_resumen[5])));
			}else{
				$Totales->setTotMntExe("0");
			}
            $Totales->setTotMntNeto(strval(round($array_resumen[6])));
            if(intval($array_resumen[7])>0){
                $Totales->setTotOpIVARec($array_resumen[7]);
            }
            $Totales->setTotMntIVA(strval(round($array_resumen[8])));
            
            if(intval($array_resumen[9])>0){
                $Totales->setTotOpActivoFijo($array_resumen[9]);
                $Totales->setTotMntActivoFijo($array_resumen[10]);
                $Totales->setTotMntIVAActivoFijo($array_resumen[11]);
            }
            
            for($lrc=12;$lrc<=24;$lrc=$lrc + 3){
                if(intval($array_resumen[$lrc])>0){
                    $TotIVANoRec = new TotIVANoRec();
                    $TotIVANoRec->setCodIVANoRec($array_resumen[$lrc]);
                    $TotIVANoRec->setTotOpIVANoRec($array_resumen[$lrc + 1]);
                    $TotIVANoRec->setTotMntIVANoRec($array_resumen[$lrc + 2]);
                    $Totales->setTotIVANoRec($TotIVANoRec);
                }
            }

            if(intval($array_resumen[27])>0){
                $Totales->setTotOpIVAUsoComun($array_resumen[27]);
                $Totales->setTotIVAUsoComun($array_resumen[28]);
                $Totales->setFctProp($array_resumen[29]);
                if(intval($array_resumen[30])>0){
                    $Totales->setTotCredIVAUsoComun($array_resumen[30]);
                }
            }
            
            for($lrc=31;$lrc<=107;$lrc=$lrc+4){
                if(intval($array_resumen[$lrc])>0){
                    $TotOtrosImp = new TotOtrosImp();
                    $TotOtrosImp->setCodImp($array_resumen[$lrc]);
                    $TotOtrosImp->setTotMntImp($array_resumen[$lrc + 1]);
                    if(intval($array_resumen[$lrc + 2])>0){
                        $TotOtrosImp->setFctImpAdic($array_resumen[$lrc + 2]);
                    }
                    if(intval($array_resumen[$lrc + 3])>0){
                        $TotOtrosImp->setTotCredImp($array_resumen[$lrc + 3]);
                    }
                    $Totales->setTotOtrosImp($TotOtrosImp);
                }
            }
            if(intval($array_resumen[111])>0){
                $Totales->setTotImpSinCredito($array_resumen[111]);
            }
            $Totales->setTotMntTotal($array_resumen[112]);
			

        //}
        $EnvioLibro->ResumenPeriodo->setTotalesPeriodos($Totales);     
    }
    
    
    
    //Detalle
    for($lr=0;$lr<=count($array_valores_detalle)-1;$lr++){
        $array_detalle = explode(";",$array_valores_detalle[$lr]);
        $Detalle = new Detalle;
            $Detalle->setTpoDoc(trim($array_detalle[0]));
            if($array_detalle[0]==56 or $array_detalle[0]==61){
                $Detalle->setEmisor("1");
            }
            $Detalle->setNroDoc($array_detalle[2]);
            if($array_detalle[3]!=""){
                $Detalle->setAnulado($array_detalle[3]);
            }
            if(intval($array_detalle[4])>0){
                $Detalle->setOperacion($array_detalle[4]);
            }
            if(intval($array_detalle[5])>0){
                $Detalle->setTpoImp($array_detalle[5]);
            }
			if(intval($array_detalle[6])>0){
				$Detalle->setTasaImp($array_detalle[6]);
			}
			
            if(intval($array_detalle[7])>0){
                $Detalle->setNumInt($array_detalle[7]);
            }
            $Detalle->setFchDoc($array_detalle[8]);
            if($array_detalle[9]!=""){
                $Detalle->setCdgSIISucur($array_detalle[9]);
            }
            $Detalle->setRUTDoc($array_detalle[10]);
            $Detalle->setRznSoc(htmlentities($array_detalle[11],ENT_IGNORE));
			if($array_detalle[12]!=""){
				$Detalle->setMntExe($array_detalle[12]);
			}else{
				$Detalle->setMntExe("0");
			}
			
			if($array_detalle[13]!=""){
				$Detalle->setMntNeto($array_detalle[13]);
			}else{
				$Detalle->setMntNeto("0");
			}
			
			if($array_detalle[14]!=""){
				$Detalle->setMntIVA($array_detalle[14]);
			}else{
				$Detalle->setMntIVA("0");
			}
			
            if(intval($array_detalle[15])>0){
                $Detalle->setMntActivoFijo($array_detalle[15]);
                $Detalle->setMntIVAActivoFijo($array_detalle[16]);
            }
            //Iva No Recuperable
            for($lrc=17;$lrc<=25;$lrc=$lrc+2){
                if(intval($array_detalle[$lrc])>0){
                    $IVANoRec = new IVANoRec();
                    $IVANoRec->setCodIVANoRec($array_detalle[$lrc]);
                    $IVANoRec->setMntIVANoRec($array_detalle[$lrc + 1]);
                    $Detalle->setIVANoRec($IVANoRec);
                }
            }
            if(intval($array_detalle[27])>0){
                $Detalle->setIVAUsoComun($array_detalle[27]);
            }
            
            for($suerte=28;intval($suerte)<=85;$suerte=$suerte +3 ){
                if(intval($array_detalle[$suerte])>0){
                    $OtrosImp2 = new OtrosImp();
                    $OtrosImp2->setCodImp($array_detalle[$suerte]);
					if(intval($array_detalle[$suerte + 1])>0){
						$OtrosImp2->setTasaImp($array_detalle[$suerte + 1]);
					}else{
						$OtrosImp2->setTasaImp("0");
					}
                    $OtrosImp2->setMntImp($array_detalle[$suerte + 2]);
                    $Detalle->setOtrosImp($OtrosImp2);
                }
            }
            if(intval($array_detalle[88])>0){
                $Detalle->setMntSinCred($array_detalle[88]);
            }
			
			if($array_detalle[89]!=""){
				$Detalle->setMntTotal($array_detalle[89]);
			}else{
				$Detalle->setMntTotal("0");
			}
            
			
            if(intval($array_detalle[90])>0){
                $Detalle->setIVANoRetenido($array_detalle[90]);
            }
            if(intval($array_detalle[91])>0){
                $Detalle->setTabPuros($array_detalle[91]);
            }
            if(intval($array_detalle[92])>0){
                $Detalle->setTabCigarrillos($array_detalle[92]);
            }
            if(intval($array_detalle[93])>0){
                $Detalle->setTabElaborado($array_detalle[93]);
            }
            if(intval($array_detalle[94])>0){
                $Detalle->setImpVehiculo($array_detalle[94]);
            }
        $EnvioLibro->setDetalle($Detalle);     
    }
 
 
    $EnvioLibro->setTmstFirma($TmstFirma);
    $idLibro = "EnvLbrCmp-".$array_caratula[2];
    $obj = new ObjectAndXML($idLibro, substr($rut_emisor,0,-2),"compra");
    $obj->setStartElement("LibroCompraVenta");
    $obj->setId($idLibro);

    $LIBRO->setEnvioLibro($EnvioLibro);
    $recordsXML = $obj->objToXML($LIBRO);

    $IECV_TIMBRE = new DOMDocument();
    $IECV_TIMBRE->formatOutput = FALSE;
    $IECV_TIMBRE->preserveWhiteSpace = TRUE;
    $IECV_TIMBRE->load("procesos/xml_libros/".substr($rut_emisor,0,-2)."/compra/".$obj->getId().".xml");
    $IECV_TIMBRE->encoding = "ISO-8859-1";
    $xmlTool = new FR3D\XmlDSig\Adapter\XmlseclibsAdapter();
    
    $pfx = file_get_contents(dirname(__FILE__) . "/certificado/".substr($rut_emisor,0,-2)."/".$archivoConfig["generales"]["certificado"]);
    openssl_pkcs12_read($pfx, $key,$archivoConfig["generales"]["clavefirma"] );
    
    $xmlTool->setPrivateKey($key["pkey"]);
    $xmlTool->setpublickey($key["cert"]);
    $xmlTool->addTransform(FR3D\XmlDSig\Adapter\XmlseclibsAdapter::ENVELOPED);
    $xmlTool->sign($IECV_TIMBRE, "LIBRO");
    $IECV_TIMBRE->save("procesos/xml_libros/".substr($rut_emisor,0,-2)."/compra/".$obj->getId().".xml");
	libxml_use_internal_errors(true);
	
	if($archivoConfig["opcionales"]["validacion"]==1){
		$xmlc = new DOMDocument(); 
		$xmlc->load("procesos/xml_libros/".substr($rut_emisor,0,-2)."/compra/".$obj->getId().".xml");

		if (!$xmlc->schemaValidate('procesos/validaciones/LibroCV_v10.xsd')) {
			libxml_display_errors();
			exit("ERROR DE VALIDACION");
		}
	}
		
	$model["RutEnvia"]=$rut_envia;
        $model["RutEmisor"]=$rut_emisor;
        $model["SetDTE_ID"]=$obj->getId();
        $model["clavefirma"]=$archivoConfig["generales"]["clavefirma"];
        $model["certificado"]=$archivoConfig["generales"]["certificado"];
        $model["tipoLibro"]="compra";
        $model["carpeta"]=$carpeta;
        $model["ambiente"]=$archivoConfig["opcionales"]["ambiente"];
        $trackId=0;$intentos=1;
        
        while(intval($trackId)==0 and $intentos<=5){
            escribeLog("Intentando subir libro de Compra a SII [".$intentos."]");
            $resultado = enviarIECVAlSii($model);
            $aRes=explode("|",$resultado);
            $trackId=$aRes[0];
            $intentos++;
            sleep(5);
        }
        $aRes=explode("|",$resultado);
        escribeLog("**EOP**|".$aRes[1]."|".$aRes[2]."-".$aRes[0]);
}

function procLibroGuias(){

    global $arcFuente,$archivoConfig,$carpeta;
        
    $linea=0;
    $rut_emisor=$archivoConfig["contribuyente"]["RUT"];
    $rut_envia=$archivoConfig["contribuyente"]["RUTRL"];
    $FchResol=$archivoConfig["contribuyente"]["FECRESOL"];
    $NumResol=$archivoConfig["contribuyente"]["NUMRESOL"];
    
    $timezone = new DateTimeZone('America/Santiago'); 
    $date = new DateTime('', $timezone);
    $TmstFirma = $date->format('Y-m-d\TH:i:s');
    
    echo "\n";
    $fp= fopen($arcFuente,"r");
    //OBTENGO EL FICHERO FUENTE COMPLETO
    $contenido_fichero = fread($fp, filesize($arcFuente));
    //Separo por lineas.
    $contenido_fichero=str_replace("\n","",$contenido_fichero);
    $contenido_fichero=str_replace("[","",$contenido_fichero);
    
	
    $array_lineas = explode("]",$contenido_fichero);
    for($l=0;$l<=count($array_lineas)-1;$l++){ //recorro las lineas para obtener los valores de cada una de ellas
        if($l==0){
            /*CARATULA*/    
            if($archivoConfig["opcionales"]["debug"]==1){
                echo "\tProceso Caratula\n";
            }
            escribeLog("Proceso Caratula [".$array_lineas[$l]."]");
            $array_caratula = explode(";",$array_lineas[$l]);
        }else if($l==1){
            /*DETALLE*/
            if($archivoConfig["opcionales"]["debug"]==1){
                echo "\tProceso Detalle\n";
            }
            escribeLog("Proceso Detalle [".$array_lineas[$l]."]");
            //parse por ~ para obtener array con cada linea de detalle
            $array_linea_detalle = explode("~",str_replace(explode(",","\r,"),"",$array_lineas[$l]));
			
            for($ld=0;$ld<=count($array_linea_detalle)-1;$ld++){
                $array_valores_detalle[] = trim($array_linea_detalle[$ld]);
            }
        }else if($l==2){
            /*RESUMEN*/
            if($archivoConfig["opcionales"]["debug"]==1){
                echo "\tProceso RESUMEN\n";
            }
            escribeLog("Proceso RESUMEN[".$array_lineas[$l]."]");
            //parse por ~ para obtener array con cada linea de detalle
            $array_linea_resumen = explode("~",str_replace(explode(",","\r,"),"",$array_lineas[$l]));
			
            for($lr=0;$lr<=count($array_linea_resumen)-1;$lr++){
                $array_valores_resumen[] = $array_linea_resumen[$lr];
            }
            
        }
    }
    fclose($fp);
    
    /*PROCESO GENERADOR DEL DOCUMENTO*/
    $LIBRO = new IEG();
    $EnvioLibro = new EnvioLibro();
    $EnvioLibro->setCaratula();
	$EnvioLibro->Caratula->setRutEmisorLibro($rut_emisor);
    $EnvioLibro->Caratula->setRutEnvia($rut_envia);
    $EnvioLibro->Caratula->setPeriodoTributario($array_caratula[2]);
    $EnvioLibro->Caratula->setFchResol($FchResol);
    $EnvioLibro->Caratula->setNroResol($NumResol);
	$EnvioLibro->Caratula->setTipoLibro($array_caratula[5]);
	
    $EnvioLibro->Caratula->setTipoEnvio($array_caratula[6]);
    if($array_caratula[6]=="PARCIAL"){
    	$EnvioLibro->Caratula->setNroSegmento($array_caratula[7]);
	}
    
    if($array_caratula[5]=="ESPECIAL"){
		if(intval($array_caratula[8])>0){
			$EnvioLibro->Caratula->setFolioNotificacion($array_caratula[8]);
		}else{
			$EnvioLibro->Caratula->setFolioNotificacion("1");
		}
    }
    
    
    //Resumen del Periodo
    $EnvioLibro->setResumenPeriodo();
    for($lr=0;$lr<=count($array_valores_resumen)-1;$lr++){
        $array_resumen = explode(";",$array_valores_resumen[$lr]);
        //Resumen
        $EnvioLibro->ResumenPeriodo->setTotFolAnulado(trim($array_resumen[0]));
        $EnvioLibro->ResumenPeriodo->setTotGuiaAnulada($array_resumen[1]);
        $EnvioLibro->ResumenPeriodo->setTotGuiaVenta($array_resumen[2]);
		if(intval($array_resumen[3])>0){
			$EnvioLibro->ResumenPeriodo->setTotMntGuiaVta($array_resumen[3]);
		}else{
			$EnvioLibro->ResumenPeriodo->setTotMntGuiaVta("0");
		}
		if(intval($array_resumen[4])>0){
			$EnvioLibro->ResumenPeriodo->setTotMntModificado($array_resumen[4]);
		}

        for($lrc=5;$lrc<=20;$lrc=$lrc + 3){
            if(intval($array_resumen[$lrc])>1){
                $TotTraslado = new TotTraslado();
                $TotTraslado->setTpoTraslado($array_resumen[$lrc]);
                $TotTraslado->setCantGuia($array_resumen[$lrc + 1]);
				if($array_resumen[$lrc + 2]!=""){
					$TotTraslado->setMntGuia($array_resumen[$lrc + 2]);
				}else{
					$TotTraslado->setMntGuia("0");
				}
                $EnvioLibro->ResumenPeriodo->setTotTraslado($TotTraslado);
            }
        }
    }
        
    
    //Detalle
    for($lr=0;$lr<=count($array_valores_detalle)-1;$lr++){
        $array_detalle = explode(";",$array_valores_detalle[$lr]);
        //Detalle
        $Detalle = new Detalle;
        $Detalle->setFolio(trim($array_detalle[0]));
        if($array_detalle[1]!=""){
            $Detalle->setAnulado($array_detalle[1]);
        }
        /*if($array_detalle[2]!=""){
            $Detalle->setOperacion($array_detalle[2]);
        }*/
        $Detalle->setTpoOper($array_detalle[2]);
        $Detalle->setFchDoc($array_detalle[3]);
        $Detalle->setRUTDoc($array_detalle[4]);
        $Detalle->setRznSoc(htmlentities($array_detalle[5],ENT_IGNORE));
		if(intval($array_detalle[6])>0){
			$Detalle->setMntNeto($array_detalle[6]);
		}
        if(intval($array_detalle[7])>0){
			$Detalle->setTasaImp($array_detalle[7]);
		}
        
		if(intval($array_detalle[8])>0){
			$Detalle->setIVA($array_detalle[8]);
		}
        
		if(intval($array_detalle[9])>0){
			$Detalle->setMntTotal($array_detalle[9]);
		}else{
			$Detalle->setMntTotal("0");
		}
        if($array_detalle[10]!=""){
            $Detalle->setMntModificado($array_detalle[10]);
        }
        if($array_detalle[11]!=""){
            $Detalle->setTpoDocRef($array_detalle[11]);
            $Detalle->setFolioDocRef($array_detalle[12]);
            $Detalle->setFchDocRef($array_detalle[13]);
        }
        $EnvioLibro->setDetalle($Detalle);     
    }
    
    $EnvioLibro->setTmstFirma($TmstFirma);
    $idLibro = "EnvLbrGuia-".$array_caratula[2];
    $obj = new ObjectAndXML($idLibro, substr($rut_emisor,0,-2),"guias");
    $obj->setStartElement("LibroGuia");
    $obj->setId($idLibro);

    $LIBRO->setEnvioLibro($EnvioLibro);
    $recordsXML = $obj->objToXML($LIBRO);

    $IECV_TIMBRE = new DOMDocument();
    $IECV_TIMBRE->formatOutput = FALSE;
    $IECV_TIMBRE->preserveWhiteSpace = TRUE;
    $IECV_TIMBRE->load("procesos/xml_libros/".substr($rut_emisor,0,-2)."/guias/".$obj->getId().".xml");
    $IECV_TIMBRE->encoding = "ISO-8859-1";
    $xmlTool = new FR3D\XmlDSig\Adapter\XmlseclibsAdapter();
    $pfx = file_get_contents(dirname(__FILE__) . "/certificado/".substr($rut_emisor,0,-2)."/".$archivoConfig["generales"]["certificado"]);
    openssl_pkcs12_read($pfx, $key,$archivoConfig["generales"]["clavefirma"] );
    
    $xmlTool->setPrivateKey($key["pkey"]);
    $xmlTool->setpublickey($key["cert"]);
    $xmlTool->addTransform(FR3D\XmlDSig\Adapter\XmlseclibsAdapter::ENVELOPED);
    $xmlTool->sign($IECV_TIMBRE, "LIBRO");
    $IECV_TIMBRE->save("procesos/xml_libros/".substr($rut_emisor,0,-2)."/guias/".$obj->getId().".xml");
	libxml_use_internal_errors(true);
	
	if($archivoConfig["opcionales"]["validacion"]==1){
		$xmlc = new DOMDocument(); 
		$xmlc->load("procesos/xml_libros/".substr($rut_emisor,0,-2)."/guias/".$obj->getId().".xml");

		if (!$xmlc->schemaValidate('procesos/validaciones/LibroGuia_v10.xsd')) {
			libxml_display_errors();
			exit("ERROR DE VALIDACION");
		}
	}
    $model["RutEnvia"]=$rut_envia;
    $model["RutEmisor"]=$rut_emisor;
    $model["SetDTE_ID"]=$obj->getId();
    $model["clavefirma"]=$archivoConfig["generales"]["clavefirma"];
    $model["certificado"]=$archivoConfig["generales"]["certificado"];
    $model["tipoLibro"]="guias";
    $model["carpeta"]=$carpeta;
    $model["ambiente"]=$archivoConfig["opcionales"]["ambiente"];
    
    $trackId=0;$intentos=1;

    while(intval($trackId)==0 and $intentos<=5){
        escribeLog("Intentando subir libro de Compra a SII [".$intentos."]");
        $resultado = enviarIECVAlSii($model);
        $aRes=explode("|",$resultado);
        $trackId=$aRes[0];
        $intentos++;
        sleep(5);
    }
    $aRes=explode("|",$resultado);
    escribeLog("**EOP**|".$aRes[1]."|".$aRes[2]."-".$aRes[0]);
}

function libxml_display_errors() {
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
		escribeLog(libxml_display_error($error));
    }
    libxml_clear_errors();
}

function escribeLog($texto){
    global $ficheroLog;
    file_put_contents($ficheroLog,"[".date("H:i:s")."]".$texto."\n",FILE_APPEND);
}

function utf8_encode_deep(&$input) {
    if (is_string($input)) {
        $input = utf8_encode($input);
    } else if (is_array($input)) {
        foreach ($input as &$value) {
            utf8_encode_deep($value);
        }

        unset($value);
    } else if (is_object($input)) {
        $vars = array_keys(get_object_vars($input));

        foreach ($vars as $var) {
            utf8_encode_deep($input->$var);
        }
    }
}

function libxml_display_error($error){
    
	$return = "|";
    switch ($error->level) {
        case LIBXML_ERR_WARNING:
            $return .= "$error->code|";
            break;
        case LIBXML_ERR_ERROR:
            $return .= "$error->code|";
            break;
        case LIBXML_ERR_FATAL:
            $return .= "$error->code|";
            break;
    }
    $return .= trim($error->message);
    if ($error->file) {
        $return .=    " en el archivo $error->file";
    }
    $return .= " en la liena $error->line\n";
	
    return $return;
}

function decimales($n){
    $aux = (string) $n;
    if(strpos( $aux, "." )>0){
        $decimal = substr( $aux, strpos( $aux, "." )+1,2 );
        //error_log("---FACELEC--- Obtengo numero $aux y saco la perte decimal $decimal");
        $ret=substr($aux,0,strpos($aux,"."));
        //error_log("---FACELEC--- Retorno numero $ret");
        return $ret;
    }else{
        //error_log("---FACELEC--- Obtengo numero sin decimales, retorno numero $aux");
        return $aux;
    }
}

