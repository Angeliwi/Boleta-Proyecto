   �   !                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              Arial                          yORIENTATION=0
PAPERSIZE=9
COPIES=1
DEFAULTSOURCE=15
PRINTQUALITY=360
COLOR=1
DUPLEX=1
YRESOLUTION=360
COLLATE=1
         
f1.fatotal                                                    Times New Roman                "@Z 999,999,999"               f2.decantidad                                                 Times New Roman                "@Z 9999,999.99"               p"Res.N� "+ALLTRIM(empres.dtenumres)+" del "+RIGHT(DTOC(empres.dtefecres),4)+"   Verifique documento: www.sii.cl"                                                 Times New Roman                
f2.devalor                                                    Times New Roman                "@Z 999,999,999"               Times New Roman                "Fecha:"                       dtoc(f1.fafecha)                                              Times New Roman                f2.dedespro                                                   Times New Roman                INT(f2.detotal+0.5)                                           Times New Roman                "@Z 999,999,999"               'vendedor.vecodigo+" "+vendedor.venombre                                                        Times New Roman                Sstdviared+"\procesos\firmas\T"+stdprenum+"_F"+alltrim(str(val(f1.fanumero)))+".png"                                             FILE(stdvialoc+"\dtefirma.png")                                 Times New Roman                "Timbre Electr�nico SII"       empres.folio5                                                 Times New Roman                empres.folio1                                                 Times New Roman                empres.folio2                                                 Times New Roman                empres.folio4                                                 Times New Roman                empres.folio6                                                 Times New Roman                empres.folio3                                                 Times New Roman                Times New Roman                "Boleta N�:"                   f1.fanumero                                                   Times New Roman                Times New Roman                8"Boleta de Venta y Servicio Autorizada por el SII seg�n"        TIME()                                                        Times New Roman                Times New Roman                "T O T A L          $"         Times New Roman                +"Verifique documento en www.sisgenchile.cl"                     Arial                          Times New Roman                Times New Roman                Times New Roman                Times New Roman                Times New Roman                dataenvironment                `Top = 43
Left = 122
Width = 629
Height = 500
DataSource = .NULL.
Name = "Dataenvironment"
                                 �PROCEDURE BeforeOpenTables
PUBLIC stdprenum
SET SYSFORMATS ON
abre("f1"      ,"r",1,1)
abre("vendedor","r",3,1)
abre("empres"  ,"r",4)
abre("clientes","r",5,1)
abre("f2"      ,"r",2,1)
SET FILTER TO f2.detdocto+f2.denumfac = stdclave
SET RELATION TO f2.detdocto+f2.denumfac INTO f1
SEEK stdclave IN 1
SEEK f1.facodvend IN 3
SEEK f1.farutcli IN 5
stdprenum = "39"

ENDPROC
PROCEDURE Destroy
USE IN 1
USE IN 2
USE IN 3
USE IN 4
USE IN 5

ENDPROC
                        ����    i  i                        �   %   �           �          �  U   7�  � G� � ��C� f1� r��� �� ��C� vendedor� r��� �� ��C� empres� r�� �� ��C� clientes� r��� �� ��C� f2� r��� �� G(�� � � � � �� G-(�� � � � ��� � E� �� �� E� �� � �� E� �� � �� T�  �� 39�� U	 	 STDPRENUM ABRE F2 DETDOCTO DENUMFAC STDCLAVE F1	 FACODVEND FARUTCLI0 	 Q� �	 Q� �	 Q� �	 Q� �	 Q� � U   BeforeOpenTables,     �� Destroy�    ��1 q a �������� � 3 � � � � � 2                       y        �  �      )   i                  