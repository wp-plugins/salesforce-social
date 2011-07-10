<?php
/*  Copyright Corner Software Ltd 2011
 *
 */
if (!class_exists('XmlHandler')) {

    class XmlHandler {

        
        static function buildDbXml($api, $version, $transaction, $statusCode, $statusDescription, $statusMessage, $dbRowsArr, $pluralTag, $singularTag) {
            $res=array();
            $res[]=XmlHandler::xmlHeader($api, $version, $transaction, $statusCode, $statusDescription, $statusMessage);
            $res[]=XmlHandler::arrayToXml($dbRowsArr, $pluralTag, $singularTag);
            $res[]=XmlHandler::xmlFooter();
            return implode('', $res);
        }

        static function buildStatusXml($api, $version, $transaction, $statusCode, $statusDescription, $statusMessage, $contentXml) {
            $res=array();
            $res[]=XmlHandler::xmlHeader($api, $version, $transaction, $statusCode, $statusDescription, $statusMessage);
            if (!empty($contentXml)) {
                $res[]=$contentXml;
            }
            $res[]=XmlHandler::xmlFooter();
            return implode('', $res);
        }


        static function xmlHeader($api, $version, $transaction, $statusCode, $statusDescription, $statusMessage) {
            $res=array();
            $res[]='<?xml version="1.0" encoding="utf-8"?>';
            $res[]="\n<header>\n";
            $res[]="\t<api>$api</api>\n";
            $res[]="\t<version>$version</version>\n";
            $res[]="\t<transaction>$transaction</transaction>\n";
            $res[]="\t<status_code>$statusCode</status_code>\n";
            $res[]="\t<status_description>$statusDescription</status_description>\n";
            $res[]="\t<status_message>$statusMessage</status_message>\n";
            $res[]="</header>\n";
            $res[]="<body>\n";
            return implode('', $res);

        }

        static function xmlFooter() {
            $res[]="</body>";
            return implode('', $res);
        }

        // Array to XML
        static function arrayToXml($dbRowsArr, $pluralTag, $singularTag) {

            $res=array();
            $res[]="<$pluralTag>\n";
            foreach ($dbRowsArr as $key1 => $dbRowArr) {
                
                $res[]="\t<$singularTag>\n";

                if (is_array($dbRowArr)) {
                    foreach($dbRowArr as $key2 => $dbField) {
                        $res[]="\t\t<$key2>$dbField</$key2>\n";
                    }
                } else {
                    $res[]="\t\t<$key1>$dbRowArr</$key1>\n";
                }

                $res[]="\t</$singularTag>\n";

            }
            $res[]="</$pluralTag>\n";

            return implode('', $res);

        }

    }

}
?>
