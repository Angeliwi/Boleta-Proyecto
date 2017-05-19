<?php
/*
*Proceso de boletas electronicas
*version 1.0
*12-05-2017
*
*/

		error_reporting(E_ALL);
		ini_set('error_reporting', E_ALL);
		ini_set('display_errors',1);
		require_once("procesos/lib/xmlseclibs/XmlseclibsAdapter.php");
		require_once("procesos/lib/BOLETA/Boleta.php");
		require_once("procesos/lib/ObjectAndXML.php");
		require_once("procesos/lib/SII.php");
		require_once("procesos/lib/Funciones.php");
		$codigo_interno = "";
		$contacto = "";
		$direccion_postal="";
		$comuna_postal = "";
		$ciudad_postal = "";
		

		$carpeta=$argv[1];
		$fuente_cfg="procesos/config/$carpeta/config.ini";
		$archivoConfig = parse_ini_file($fuente_cfg, true);
		$archivoTemp=$archivoConfig["generales"]["ruta"].'\procesos\pejec.eje';
		$debug=$archivoConfig["opcionales"]["debug"];
		$validacion=$archivoConfig["opcionales"]["validacion"];
		$arcFuente="";
		$ficheroLog = "data/dte/$carpeta/log_".date("Ymd").".log";
		$ficheroErrorLog = "data/dte/$carpeta/error.log";
		$correo_cliente="";

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
		//Leo directorio fuente
		$fuente="data/dte/$carpeta/";
		$directorio = opendir($fuente);
		$numDocs=0;

		//obteniendo file

		while ($archivo = readdir($directorio))
		{
		    //verificamos si es o no un archivo
		   		 $arcFuente=$fuente.$archivo;
			    if(strpos($arcFuente, ".log")==0)
			    { //para que no procese el archvio de log
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
							            switch(str_replace("[","",$array_archivo[0]))
							            {
							                case 39:
							                    if($debug){
							                        echo "Proceso boleta Electronica";
							                    }
							                    escribeLog("Proceso boleta Electronica",$ficheroLog);
							                    break;
											case 41:
							                    if($debug){
							                        echo "Proceso boleta Exenta Electronica";
							                    }
							                    escribeLog("Proceso boleta Exenta Electronica",$ficheroLog);
							                    break;
							              
							                default:
							                    $msg="No se encontro el tipo de documento ".str_replace("[","",$array_archivo[0])." Archivo Fuente: $arcFuente";
							                    //enviaMail("[facelecws]Error en el proceso", $msg, $carpeta);
							                    escribeLog("**ERROR**|entrada|".$msg,$ficheroErrorLog);
							                    exit($msg);
							            }
							            procDocumento(substr($array_archivo[0],1),$argv[2]);
				        }
			    }
		}

		while ($archivo = readdir($directorio))
		{
		    //verificamos si es o no un archivo
		    $arcFuenteDel=$fuente.$archivo;
		    if($debug){
		        echo "intentando eliminar archivo fuente ".$arcFuenteDel."\n";
		    }
		    if(strpos($arcFuenteDel, ".log")==0){ //para que no procese el archvio de log
		        if (is_file($arcFuenteDel)){
		            echo "elimino archivo $arcFuenteDel";
		            unlink($arcFuenteDel);
		        }
		    }
		}
		unlink($archivoTemp);
		
		function procDocumento($tipo_doc)
		{
			 global $arcFuente,$carpeta,$archivoConfig,$correo_cliente,$ficheroLog,$ficheroErrorLog,$debug,$validacion;
    
			$linea=0;
			$marcaFOLIO = 0;
			$date = new DateTime('', $fechaTimbre);
			$fechaTimbre = $date->format('Y-m-d\TH:i:s');
			  
			echo "\n";
			$fp= fopen($arcFuente,"r");
			//OBTENGO EL FICHERO FUENTE COMPLETO
			$contenido_fichero = fread($fp, filesize($arcFuente));
			//Separo por lineas.
			$contenido_fichero=str_replace("\n","",$contenido_fichero);
			$contenido_fichero=str_replace("[","",$contenido_fichero);
			$array_lineas = explode("]",$contenido_fichero);
		
				for($l=0;$l<=count($array_lineas)-1;$l++)
				{ //recorro las lineas para obtener los valores de cada una de ellas
				
						if($l==0)  //linea id doc o emisor
						{
							  
							if($debug)
							{
								echo "\tProceso Encabezado - identificacion documento\n";
							}
							
							$array_valores = explode(";",$array_lineas[$l]);
							
							if(!is_numeric(trim($array_valores[1])))
							{
								$msg="El folio debe ser numerico [".$array_valores[1]."]";
								escribeLog("**ERROR**|entrada|".$msg,$ficheroErrorLog);
								//exit("\t\t**ERROR** El folio debe ser numerico [".$array_valores[1]."]");
							}  
							escribeLog("\tFolio Documento:".$array_valores[1],$ficheroLog);
							escribeLog("\tCabecera [".trim($array_lineas[$l])."]",$ficheroLog);
							$tipo_documento=trim($array_valores[0]);
							$folio_documento=trim($array_valores[1]);
							$fecha_emision=trim($array_valores[2]);
							$indicador_de_servicio=trim($array_valores[3]);
							//$indicador_montos_netos=trim($array_valores[4]);
							$periodo_desde=$fechaTimbre;
							$periodo_hasta=$fechaTimbre;
							$fecha_vencimiento=$fechaTimbre;
						}
						else if($l==1) //Linea emisor
						{
							if($debug)
							{
								echo "\tProceso del Emisor\n";
							}
							escribeLog("\tEmisor [".trim($array_lineas[$l])."]",$ficheroLog);
							$array_valores = explode(";",$array_lineas[$l]);
							$cantVal=count($array_valores) -1;

							$rut_emisor=trim($array_valores[0]);
							$razon_social=trim($array_valores[1]);
							$giro=trim($array_valores[2]);
							$codigo_sucursal=trim($array_valores[3]);
							$direccion_sucursal=trim($array_valores[4]);			
							$comuna_origen=trim($array_valores[5]);
							$ciudad_origen=trim($array_valores[6]);					
							
						}
						else if($l==2) //linea receptor
						{				
							if($debug)
							{
								echo "\tProceso del Receptor\n";
							}
							escribeLog("\tReceptor [".trim($array_lineas[$l])."]",$ficheroLog);
							$array_valores = explode(";",$array_lineas[$l]);
							$cantVal=count($array_valores) - 1;
							
							$rut_receptor=trim($array_valores[0]);
							//$codigo_interno=trim($array_valores[1]);
							$razon_social_recep=trim($array_valores[2]);
							//$contacto=trim($array_valores[3]);
							$direccion_receptor=trim($array_valores[4]);
							$comuna_recep=trim($array_valores[5]);
							$ciudad_recep=trim($array_valores[6]);
							//$direccion_postal = trim($array_valores[7]);
							//$comuna_postal = trim($array_valores[8]);
							//$ciudad_postal = trim($array_valores[9]);
							
						}
						else if($l==3) //linea resumen totales
						{
							if($debug)	
							{
								echo "\tProceso Encabezado - resumen totales\n";
							}
							escribeLog("\tResumenTotales [".trim($array_lineas[$l])."]",$ficheroLog);
							$array_valores = explode(";",$array_lineas[$l]);
							
							//$montoNeto = trim($array_valores[0]);
							$montoExento = trim($array_valores[1]);
							//$iva = trim($array_valores[2]);
							$montoTotal = trim($array_valores[3]);
							//$montoNoFacturable = trim($array_valores[4]);
							//$totalPeriodo = trim($array_valores[5]);
							//$saldoAnterior = trim($array_valores[6]);
							//$valorAPagar = trim($array_valores[7]);
							
						}
						else if($l == 4) //lineas del detalle, ojo que mas abajo se hará este proceso
						{
							if($debug)
							{
								echo "\tProceso detalle\n";						
							}
							escribeLog("\tDetalle [".trim($array_lineas[$l])."]",$ficheroLog);
							//parse por ~ para obtener array con cada linea de detalle
							$array_linea_detalle = explode("~",$array_lineas[$l]);
							for($ld=0;$ld<=count($array_linea_detalle)-1;$ld++)
							{
								$array_valores_detalle[] = $array_linea_detalle[$ld];
							}
							
						}
						else if($l==5) //linea subtotales informativos
						{					
							if($debug)	
							{
								echo "\tProceso Subtotales informativos\n";
							}
							escribeLog("\tSubtotalesInformativos [".trim($array_lineas[$l])."]",$ficheroLog);
							 //parse por ~ para obtener array con cada linea de detalle
							$array_linea_si = explode("~",$array_lineas[$l]);
							
							for($lsi=0;$lsi<=count($array_linea_si)-1;$lsi++)
							{
								$array_valores_si[] = $array_linea_si[$lsi];
							}
							
						}
						else if($l==6) //linea descuento o recargo
						{					
							if($debug)
							{
								echo "\tProceso Descuento o Recargo\n";
							}
							escribeLog("\tDescuentos o Recargos [".trim($array_lineas[$l])."]",$ficheroLog);
							//parse por ~ para obtener array con cada linea de detalle
							$array_linea_dr = explode("~",$array_lineas[$l]);
							
							for($ldr=0;$ldr<=count($array_linea_dr)-1;$ldr++)
							{
								$array_valores_dr[] = $array_linea_dr[$ldr];
							}
							
						}
						else if($l==7) //linea referencias
						{					
							if($debug)
							{
								echo "\tProceso Referencias\n";
							}
							escribeLog("\tReferencias [".trim($array_lineas[$l])."]",$ficheroLog);
							$array_linea_referencia = explode("~",trim($array_lineas[$l]));
							
							for($lr=0;$lr<=count($array_linea_referencia)-1;$lr++)
							{
								$array_valores_referencia[] = $array_linea_referencia[$lr];
							}
						}
				}
				fclose($fp);
				
				//Generando el documento
				
				$Boleta = new Boleta();
				$Documento = new Documento();
				$Documento->setEncabezado();
				$Documento->Encabezado->setIdDoc();
				$Documento->Encabezado->IdDoc->setTipoDTE($tipo_documento);
				$Documento->Encabezado->IdDoc->setFolio($folio_documento);
				$Documento->Encabezado->IdDoc->setFchEmis($fecha_emision);
				$Documento->Encabezado->IdDoc->setIndServicio($indicador_de_servicio);
				//$Documento->Encabezado->IdDoc->setIndMntNeto($indicador_montos_netos);
				$Documento->Encabezado->IdDoc->setPeriodoDesde($periodo_desde);
				$Documento->Encabezado->IdDoc->setPeriodoHasta($periodo_hasta);
				$Documento->Encabezado->IdDoc->setFchVenc($fecha_vencimiento);
				
				$Documento->Encabezado->setEmisor();
				$Documento->Encabezado->Emisor->setRUTEmisor($rut_emisor);
				$Documento->Encabezado->Emisor->setRznSoc($razon_social);				
				$Documento->Encabezado->Emisor->setGiroEmis($giro);				
				$Documento->Encabezado->Emisor->setCdgSIISucur($codigo_sucursal);
				$Documento->Encabezado->Emisor->setDirOrigen($direccion_sucursal);
				$Documento->Encabezado->Emisor->setCmnaOrigen($comuna_origen);
				$Documento->Encabezado->Emisor->setCiudadOrigen($ciudad_origen);
				
				$Documento->Encabezado->setReceptor();
				$Documento->Encabezado->Receptor->setRUTRecep($rut_receptor);
				//$Documento->Encabezado->Receptor->setCdgIntRecep($codigo_interno);
				$Documento->Encabezado->Receptor->setRznSocRecep($razon_social_recep);
				//$Documento->Encabezado->Receptor->setContacto($contacto);
				$Documento->Encabezado->Receptor->setDirRecep($direccion_receptor);
				$Documento->Encabezado->Receptor->setCmnaRecep($comuna_recep);
				$Documento->Encabezado->Receptor->setCiudadRecep($ciudad_recep);
				//$Documento->Encabezado->Receptor->setDirPostal($direccion_postal);
				//$Documento->Encabezado->Receptor->setCmnaPostal($comuna_postal);
				//$Documento->Encabezado->Receptor->setCiudadPostal($ciudad_postal);
			
					//detalles array
						for($d=0;$d<=count($array_valores_detalle)-1;$d++)
						{
							$array_detalle = explode(";",$array_valores_detalle[$d]);
							$linea++;
							$detalle = new Detalle;
							/*$detalle->setCdgItem();
							$detalle->CdgItem->setTpoCodigo("Interna");//ESTA VA FIJO
							if(trim($array_detalle[2])!="")
							{
									$detalle->CdgItem->setVlrCodigo(trim($array_detalle[2]));
							}
							else
							{
									$detalle->CdgItem->setVlrCodigo("0");
							}
							*/
							$detalle->setNroLinDet("$linea");
							
							/*
							if(trim($array_detalle[1])!="")
							{
								$detalle->setTipoCodigo($array_detalle[1]);
							}
							*/
							
							if(intval($array_detalle[3]) == 1)
							{
								$detalle->setIndExe("1"); 
							}
							if(intval($array_detalle[4])==1)
							{
								$detalle->$setItemEspectaculo("1");
							}
							
							//$detalle->setRUTMandante($array_detalle[5]);
							
							$detalle->setNmbItem(htmlspecialchars(trim($array_detalle[6]),ENT_IGNORE));
							//$detalle->setInfoTicket($array_detalle[7]);
							if(trim($array_detalle[8])!="")
							{
								$detalle->setDscItem(trim($array_detalle[8]));
							}
							if(trim($array_detalle[18])!="")
							{
								$detalle->setQtyItem($array_detalle[18]);//ojo
							}							
							if(trim($array_detalle[10])!="")
							{
								$detalle->setUnmdItem(trim(substr($array_detalle[10],0,4)));
							}
							if(intval($array_detalle[20])>0)
							{
								$detalle->setPrcItem(trim(number_format($array_detalle[20],2,".","")));
							}							
							if(trim($array_detalle[12])!="")
							{
								$detalle->setDescuentoPct(trim($array_detalle[12]));
							}
							if(trim($array_detalle[13])!="")
							{
								$detalle->setDescuentoMonto(trim($array_detalle[13]));
							}							
							if(trim($array_detalle[14])!="")
							{
								$detalle->setRecargoPct(trim($array_detalle[14]));
							}							
							if(trim($array_detalle[15])!="")
							{
							$detalle->setRecargoMonto(trim($array_detalle[15]));
							}						
							$detalle->setMontoItem("".trim(round($array_detalle[25]))."");
														
							$Documento->setDetalle($detalle);
						}
						
						//Subtotales informativos
						$lineasi=0;
						for($si=0;$si<=count($array_valores_si)-1;$si++)
						{
							$array_detalle_si = explode(";",trim($array_valores_si[$si]));
							
							$lineasi++;
							if($array_detalle_si[0]>1)
							{ 
								$SubTotInfo = new SubTotInfo();
								$SubTotInfo->setNroSTI("$lineasi");
								$SubTotInfo->setGlosaSTI($array_detalle_dr[1]);
								$SubTotInfo->setOrdenSTI($array_detalle_dr[2]);
								$SubTotInfo->setSubTotNetoSTI($array_detalle_dr[3]);
								//$SubTotInfo->setSubTotIVASTI($array_detalle_dr[4]);
								$SubTotInfo->setSubTotAdicSTI($array_detalle_dr[5]);
								$SubTotInfo->setSubTotExeSTI($array_detalle_dr[6]);
								$SubTotInfo->setValSubtotSTI($array_detalle_dr[7]);
								$SubTotInfo->setLineasDeta($array_detalle_dr[8]);
								
								$Documento->setSubTotInfo($SubTotInfo);
							}
						
						}
						
						//Descuento o Recargo
						$lineaDr=0;
						for($dr=0;$dr<=count($array_valores_dr)-1;$dr++)
						{
							$array_detalle_dr = explode(";",trim($array_valores_dr[$dr]));
							
							$lineaDr++;
							if($array_detalle_dr[1]=="D" or $array_detalle_dr[1]=="R")
							{
								$DescuentoGlobal = new DscRcgGlobal();
								$DescuentoGlobal->setNroLinDR("$lineaDr");
								$DescuentoGlobal->setTpoMov($array_detalle_dr[1]);
								$DescuentoGlobal->setGlosaDR($array_detalle_dr[2]);
								$DescuentoGlobal->setTpoValor($array_detalle_dr[3]);
								$DescuentoGlobal->setValorDR($array_detalle_dr[4]);
								if($array_detalle_dr[4]!=""){
									$DescuentoGlobal->setIndExeDR($array_detalle_dr[5]);
								}
								$Documento->setDscRcgGlobal($DescuentoGlobal);
							}
						}
						
						//Referencias
						for($r=0;$r<=count($array_valores_referencia)-1;$r++)
						{
							$array_referencia = explode(";",$array_valores_referencia[$r]);
							if(trim($array_referencia[0])!="")
							{
								$referencia = new Referencia();
								$referencia->setNroLinRef(trim($array_referencia[0]));
								$referencia->setCodRef(trim($array_referencia[1]));
								$referencia->setRazonRef(trim($array_referencia[2]));
								$referencia->setCodVndor(trim($array_referencia[3]));
								$referencia->setCodCaja(trim($array_referencia[4]));								
								
								$Documento->setReferencia($referencia);
							}
						}
						
				$Documento->Encabezado->setTotales();
				
				switch($tipo_doc)
				{
					case "39":
					//$Documento->Encabezado->Totales->setMntNeto(trim(round($montoNeto)));
					if($montoExento!="")
					{
						$Documento->Encabezado->Totales->setMntExe(trim(round($montoExento)));
					}					
					//$Documento->Encabezado->Totales->setIVA(trim(round($iva)));
					$Documento->Encabezado->Totales->setMntTotal(trim(round($montoTotal)));
					/*$Documento->Encabezado->Totales->setMontoNF(trim(round($montoNoFacturable)));     
					$Documento->Encabezado->Totales->setTotalPeriodo(trim(round($totalPeriodo)));
					$Documento->Encabezado->Totales->setSaldoAnterior(trim(round($saldoAnterior)));
					$Documento->Encabezado->Totales->setVlrPagar(trim(round($valorAPagar)));
					*/
					break;
					
				}
				
				$pRutEmpresa   = substr($Documento->Encabezado->Emisor->getRUTEmisor(),0, -2);
				
				if($archivoConfig["opcionales"]["etapa"]=="C"){
					$array_referencia = explode(";",$array_valores_referencia[0]);//linea del SET
					if(trim($array_referencia[1])=="SET"){
						escribeLog("\tAmbiente Certificacion ".$array_referencia[6],$ficheroLog);
						$idDte = str_replace(" ","_",trim($array_referencia[6]));
					}
				}else if($archivoConfig["opcionales"]["etapa"]=="S"){
					escribeLog("\tAmbiente Certificacion Etapa Simulacion",$ficheroLog);
					$idDte = "T".$Documento->Encabezado->IdDoc->getTipoDte()."_F".$Documento->Encabezado->IdDoc->getFolio();
				}else{
					$idDte = "T".$Documento->Encabezado->IdDoc->getTipoDte()."_F".$Documento->Encabezado->IdDoc->getFolio();
				}
				
				$obj = new ObjectAndXML($idDte,$pRutEmpresa);

				$obj->setStartElement("BOLETA");
				$obj->setId($idDte);

				$Documento->setTmstFirma($fechaTimbre);   
				$Documento->setTED();
				$Documento->TED->setDD();
				$Documento->TED->DD->setRE($Documento->Encabezado->Emisor->getRUTEmisor());
				$Documento->TED->DD->setTD($Documento->Encabezado->IdDoc->getTipoDte());
				$Documento->TED->DD->setF($Documento->Encabezado->IdDoc->getFolio());
				$Documento->TED->DD->setFE($Documento->Encabezado->IdDoc->getFchEmis());
				$Documento->TED->DD->setRR($Documento->Encabezado->Receptor->getRUTRecep());
				$Documento->TED->DD->setRSR(utf8_decode(replaceSii(substr($Documento->Encabezado->Receptor->getRznSocRecep(),0,40))));
				$Documento->TED->DD->setMNT($Documento->Encabezado->Totales->getMntTotal());
				$Documento->TED->DD->setIT1(utf8_decode(replaceSii(substr($Documento->Detalle[0]->getNmbItem(),0,40))));
				$Documento->TED->DD->setTSTED($fechaTimbre);                

				$Boleta->setDocumento($Documento);
				utf8_encode_deep($Boleta);
				
				$recordsXML = $obj->objToXML($Boleta);
				
				  /********** OBTENER CAF, LLAVES PRIVADA Y PUBLICA DEL CAF **********/
					escribeLog("\tObteniendo CAF y Llave privada",$ficheroLog);
					$LCAFImport = new DOMDocument();
					$LCAFImport->formatOutput = FALSE;
					$LCAFImport->preserveWhiteSpace = TRUE;
					$LCAFImport->encoding = "ISO-8859-1";
					
					$archivoFolio=validaFolio($folio_documento,substr($rut_emisor,0,-2),$tipo_doc);
					if($archivoFolio!="ERROR" and $archivoFolio!="")
					{
						echo "Folio validado desde archivo $archivoFolio\n";
						if(!$LCAFImport->load("procesos/folios/".substr($rut_emisor,0,-2)."/".$archivoFolio)){
							$XMLFOLIO = utf8_encode(file_get_contents("procesos/folios/".substr($rut_emisor,0,-2)."/".$archivoFolio));
							if($LCAFImport->loadXML($XMLFOLIO)){
								$marcaFOLIO = 1;
								escribeLog("\t[MARCA=1]Folio validado desde archivo $archivoFolio",$ficheroLog);
								if($debug)
								{
									echo "Folio validado desde archivo $archivoFolio\n";
								}
							}

							}
							else
							{
								escribeLog("\tFolio validado desde archivo $archivoFolio",$ficheroLog);
								if($debug)
								{
									echo "[MARCA=0]Folio validado desde archivo $archivoFolio\n";         
								}
							}

					}
					else
					{
						escribeLog("**EOP**|11|No se pudo encontrar un CAF para el folio $folio_documento\n");
						exit("No se pudo encontrar un CAF para el folio $folio_documento \nSe detiene el proceso\n");
					}

					$CAF = $LCAFImport->getElementsByTagName("CAF")->item(0);
					$nodecaf = $LCAFImport->getElementsByTagName("CAF")->item(0);
					$priv_key = $LCAFImport->getElementsByTagName("RSASK")->item(0)->nodeValue;
					$CAF = $LCAFImport->saveXML($CAF);
					if($marcaFOLIO == 1){ 
						$CAF = utf8_decode($CAF);
					}
					/********** OBTENER CAF, LLAVES PRIVADA Y PUBLICA DEL CAF **********/     
					
					$DTE_TIMBRE = new DOMDocument();
					$DTE_TIMBRE->formatOutput = FALSE;
					$DTE_TIMBRE->preserveWhiteSpace = TRUE;
					$DTE_TIMBRE->load("procesos/xml_emitidos/".substr($rut_emisor,0,-2)."/".$obj->getId().".xml");
					$DTE_TIMBRE->encoding = "ISO-8859-1";
					
					
					$import = $DTE_TIMBRE->importNode($nodecaf, true);
					$TSTED = $DTE_TIMBRE->getElementsByTagName("TSTED")->item(0);
					//var_dump($TSTED);
					$TSTED->parentNode->insertBefore($import, $TSTED);
	$total = 0;
					//Detalle del timbre
					$DD2 = "<DD><RE>".$Documento->Encabezado->Emisor->getRUTEmisor()."</RE><TD>" . $tipo_documento ."</TD><F>" . $folio_documento . "</F><FE>".$fecha_emision."</FE><RR>" . $rut_receptor . "</RR><RSR>" .$Documento->TED->DD->getRSR(). "</RSR><MNT>" . trim(decimales($total)) . "</MNT><IT1>" . $Documento->TED->DD->getIT1() ."</IT1>$CAF<TSTED>". $fechaTimbre ."</TSTED></DD>";
					escribeLog("\tDetalle del timbre\n\t\t$DD2",$ficheroLog);
					$FRMT = buildSign($DD2, $priv_key); //se firma con la llave del servicio, esta funcion se encuntra en globals.php
					$fragment = $DTE_TIMBRE->createDocumentFragment();
					$fragment->appendXML("<FRMT algoritmo=\"SHA1withRSA\">$FRMT</FRMT>\n");
					$TED = $DTE_TIMBRE->getElementsByTagName("TED")->item(0);
					$TED->appendChild($fragment);
					
					$xmlTool = new FR3D\XmlDSig\Adapter\XmlseclibsAdapter();                    
					if(!file_exists("procesos/certificado/".substr($rut_emisor,0,-2)."/".$archivoConfig["generales"]["certificado"])){
							$msg="No se encontro ningun certificado valido para el emisor revise su archivo de configuracion[".$archivoConfig["generales"]["certificado"]."]";
							escribeLog("**EOP**|CERT|$msg",$ficheroLog);
							//enviaMail("[facelecws] Error en el proceso",$msg, $carpeta);
							//exit("No se encontro ningun certificado valido para el emisor");
					}
					$pfx = file_get_contents(dirname(__FILE__) . "/certificado/".substr($rut_emisor,0,-2)."/".$archivoConfig["generales"]["certificado"]);
					openssl_pkcs12_read($pfx, $key, $archivoConfig["generales"]["clavefirma"]);
					if(empty($key["pkey"])){
						$msg="Al parecer el certificado no es valido y que no contiene una llave privada. Contactese con el proveedor de la firma.\n";
						escribeLog("**EOP**|PKEY|$msg",$ficheroLog);
						//exit("Al parecer el certificado no es valido y que no contiene una llave privada. Contactese con el proveedor de la firma.\n");
					}
					$xmlTool->setPrivateKey($key["pkey"]);
					$xmlTool->setpublickey($key["cert"]);
					$xmlTool->addTransform(FR3D\XmlDSig\Adapter\XmlseclibsAdapter::ENVELOPED);
					
					$xmlTool->sign($DTE_TIMBRE, "DTE");
					$DTE_TIMBRE->save("procesos/xml_emitidos/".substr($rut_emisor,0,-2)."/".$obj->getId().".xml");
				   
					////genera imagen de firma
					//$url = "http://sisgenfe.visoft.cl/firma.php?caf=".$TED;
					$url = "http://localhost/firma.php?sisgen=".$archivoConfig["generales"]["ruta"]."&rut=".substr($rut_emisor,0,-2)."&id=".$obj->getId();
					escribeLog("\t\tA buscar imagen firma $url",$ficheroLog);
					$img = "procesos/firmas/T".$tipo_documento."_F".$folio_documento.".png";
					file_put_contents($img, file_get_contents($url));
					
					if(strpos($arcFuente, ".log")==0){
						unlink($arcFuente);
					}
					
					if($validacion){
					libxml_use_internal_errors(true);

					$xmlv = new DOMDocument(); 
					$xmlv->load("procesos/xml_emitidos/".substr($rut_emisor,0,-2)."/".$obj->getId().".xml"); 

					if (!$xmlv->schemaValidate('procesos/validaciones/schema_dte/DTE_v10.xsd')) {
						escribeLog("**ERROR**|schema|DOMDocument::schemaValidate() Generated Errors!\n".libxml_display_errors(),$ficheroLog);
						exit("Error de schema, para mas detalle revise el log\n");
					}
					}
					/*if(intval($noEnviar)==0){
						if(enviaDocumento($obj->getId().".xml",$carpeta,$correo_cliente,$ficheroLog)){
							escribeLog("**EOP**|Documento publicado correctamente ".date("H:i:s")."\n",$ficheroLog);
							escribeLog("**EOP**|Documento publicado correctamente ".date("H:i:s")."\n",$ficheroErrorLog);
						}else{
							escribeLog("**ERROR**||Se produjo un error al procesar el sobre\nProceso Finalizado ".date("H:i:s")."\n",$ficheroErrorLog);
						}
					}else{
						escribeLog("|0|No saltamas el envio",$ficheroLog);
					}
					*/
					escribeLog("**EOP**|0|Se publico correctamente el documento $folio_documento",$ficheroLog);
		
		
		}
		
		


