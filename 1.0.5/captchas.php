<?php
/**
 * @author Corner Software Ltd
 */

    if (!isset($table_prefix)){
        global $confroot;
        FindWPConfig(dirname(dirname(__FILE__)));
        include_once $confroot."/wp-config.php";
        include_once $confroot."/wp-load.php";
    }


    $dat = $_GET['dat'];
    $encNumero1 = strval(substr($dat,0,3));
    $encOperation = strval(substr($dat,3,1));
    $encNumero2 = strval(substr($dat,4,1));
    $key = get_option(SFSoc::WEB_TO_LEAD_CAPTCHA_KEY_OPTIONS_NAME);
    $numero1 = SFSoc::decodeToNumber($encNumero1, $key);
    $operation = SFSoc::decodeToNumber($encOperation, $key);

    $opStr = "-";
    if ($operation > 1) {
        $opStr = "+";
    }

    $numero2 = SFSoc::decodeToNumber($encNumero2, $key);

    $tx = rand(6, 15);
    $ty = rand(7, 10);

    $x1 = rand(0, 150);
    $y1 = rand(0, 30);

    $x2 = rand(0, 150);
    $y2 = rand(0, 30);

    $x3 = rand(0, 150);
    $y3 = rand(0, 30);

//    $im  = imagecreatetruecolor(150, 30);
    $im = imagecreate(235, 40);

//    $bgc = imagecolorallocate($im, 0, 0, 0);
    // ref http://www.thecaptcha.com/
//    $bgc = imagecolorallocate($im, 255, 0, 255);

    $bgcol=$_GET['bgcol']; //128;
    $bglocol=$_GET['bglocol']; //120;
    $bghicol=$_GET['bghicol']; //136;
    $bgc = imagecolorallocate($im, $bgcol, $bgcol, $bgcol);

    /*
    $im_acolour[] = imagecolorallocate($im, 255, mt_rand(230, 240), mt_rand(230, 240));
    $im_acolour[] = imagecolorallocate($im, 255, mt_rand(230, 240), mt_rand(230, 240));
    $im_acolour[] = imagecolorallocate($im, 255, mt_rand(160, 220), mt_rand(160, 220));
    $im_bcolour[] = imagecolorallocate($im, 255-mt_rand(50, 100), mt_rand(0, 50), mt_rand(0, 50));
    $im_bcolour[] = imagecolorallocate($im, 255-mt_rand(50, 100), mt_rand(0, 50), mt_rand(0, 50));
    $im_bcolour[] = imagecolorallocate($im, 255-mt_rand(50, 100), mt_rand(0, 50), mt_rand(0, 50));
*/
    $txtlocol=$_GET['txtlocol']; //230;
    $txthicol=$_GET['txthicol']; //240;

    $im_acolour[] = imagecolorallocate($im, mt_rand($txtlocol, $txthicol), mt_rand($txtlocol, $txthicol), mt_rand($txtlocol, $txthicol));
    $im_acolour[] = imagecolorallocate($im, mt_rand($txtlocol, $txthicol), mt_rand($txtlocol, $txthicol), mt_rand($txtlocol, $txthicol));
    $im_acolour[] = imagecolorallocate($im, mt_rand($txtlocol, $txthicol), mt_rand($txtlocol, $txthicol), mt_rand($txtlocol, $txthicol));

    $im_bcolour[] = imagecolorallocate($im, mt_rand($bglocol, $bghicol), mt_rand($bglocol, $bghicol), mt_rand($bglocol, $bghicol));
    $im_bcolour[] = imagecolorallocate($im, mt_rand($bglocol, $bghicol), mt_rand($bglocol, $bghicol), mt_rand($bglocol, $bghicol));
    $im_bcolour[] = imagecolorallocate($im, mt_rand($bglocol, $bghicol), mt_rand($bglocol, $bghicol), mt_rand($bglocol, $bghicol));

    // Add coloured areas to background
    $ba= $_GET['ba'];
    if (!empty($ba)) {
        for ($i = 0; $i <= 10; $i++) {
            ImageFilledEllipse($im, $i*20+mt_rand(4, 26), mt_rand(0, 39), $i*20-mt_rand(4, 26), mt_rand(0, 39), $im_bcolour[mt_rand(0, 2)]);
        }
        for ($i = 0; $i <= 10; $i++) {
            ImageFilledRectangle($im, $i*20+mt_rand(4, 26), mt_rand(0, 39), $i*20-mt_rand(4, 26), mt_rand(0, 39), $im_bcolour[mt_rand(0, 2)]);
        }
    }

    // Add random lines
    $rl= $_GET['rl'];
    if (!empty($rl)) {
        for ($i = 0; $i <= 10; $i++) {
            imageline($im, $i*20+mt_rand(4, 26), 0, $i*20-mt_rand(4, 26), 39, $im_acolour[mt_rand(0, 2)]);
        }
        for ($i = 0; $i <= 10; $i++) {
            imageline($im, $i*20+mt_rand(4, 26), 39, $i*20-mt_rand(4, 26), 0, $im_acolour[mt_rand(0, 2)]);
        }
    }


    $tc  = imagecolorallocate($im, 255, 255, 255);


    //imagestring($im, 6, $tx, $ty, $numero1.$opStr.$numero2."=?", $tc);
    $captcha_word = $numero1.$opStr.$numero2."=?";
    //imagestring($im, rand(3,6), $tx, $ty, $captcha_word, $tc);

    $ang = 0;
    for($i = 0; $i <= 6; $i++) {
        if ($i <> 3) {
            $ang = mt_rand(-20, 20);
        } else {
            $ang = 0;
        }
//        imagettftext($im, mt_rand(24, 28), $ang, $i*mt_rand(30, 36)+mt_rand(2,4), mt_rand(32, 36), $im_acolour[mt_rand(0, 1)], mt_rand(1, 3).'.ttf', $captcha_word{$i});
        imagettftext($im, mt_rand(24, 28), $ang, $i*mt_rand(30, 36)+mt_rand(2,4), mt_rand(32, 36), $im_acolour[mt_rand(0, 1)], mt_rand(1, 3).'.ttf', $captcha_word{$i});
    }
/*
    // Extra line
    $line_colour = imagecolorallocate( $im, 100, 100, 200 );
    imagesetthickness ( $im, 1.5 );
    imageline( $im, $x1, $y1, $x2, $y2, $line_colour );
    imageline( $im, $x2, $y2, $x3, $y3, $line_colour );
    imageline( $im, $x3, $y3, $tx, $ty, $line_colour );
*/
    imagepng($im);

    header ("Content-type: image/png");
    imagepng($im);
    imagedestroy($im);

    function FindWPConfig($dirrectory){
        global $confroot;
        foreach(glob($dirrectory."/*") as $f){
                if (basename($f) == 'wp-config.php' ){
                        $confroot = str_replace("\\", "/", dirname($f));
                        return true;
                }

                if (is_dir($f)){
                        $newdir = dirname(dirname($f));
                }

        }

        if (isset($newdir) && $newdir != $dirrectory){
            if (FindWPConfig($newdir)){
                return false;
            }
        }

        return false;
    }


?>





