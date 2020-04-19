<?php
/**
 * Object used to manipulate strings
 *
 * PHP 5
 *
 * OWR - OpenWebReader
 *
 * Copyright (c) 2009, Pierre-Alain Mignot
 *
 * Home page: http://openwebreader.org
 *
 * E-Mail: contact@openwebreader.org
 *
 * All Rights Reserved
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 * @author Pierre-Alain Mignot <contact@openwebreader.org>
 * @copyright Copyright (c) 2009, Pierre-Alain Mignot
 * @license http://www.gnu.org/copyleft/gpl.html
 * @package OWR
 */
namespace OWR;
/**
 * This object is usefull for string conversion
 * @package OWR
 */
class Strings
{
    /**
     * Converts M$ chars to _normals_
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @param string $str the string to convert
     * @return string the converted string
     */
    static public function toNormal($str)
    {
        return !$str ? $str : strtr((string) $str, array (
            "\xc2\x80" => "\xe2\x82\xac",
            "\xc2\x82" => "\xe2\x80\x9a",
            "\xc2\x83" => "\xc6\x92",
            "\xc2\x84" => "\xe2\x80\x9e",
            "\xc2\x85" => "\xe2\x80\xa6",
            "\xc2\x86" => "\xe2\x80\xa0",
            "\xc2\x87" => "\xe2\x80\xa1",
            "\xc2\x88" => "\xcb\x86",
            "\xc2\x89" => "\xe2\x80\xb0",
            "\xc2\x8a" => "\xc5\xa0",
            "\xc2\x8b" => "\xe2\x80\xb9",
            "\xc2\x8c" => "\xc5\x92",
            "\xc2\x8e" => "\xc5\xbd",
            "\xc2\x91" => "\xe2\x80\x98",
            "\xc2\x92" => "\xe2\x80\x99",
            "\xc2\x93" => "\xe2\x80\x9c",
            "\xc2\x94" => "\xe2\x80\x9d",
            "\xc2\x95" => "\xe2\x80\xa2",
            "\xc2\x96" => "\xe2\x80\x93",
            "\xc2\x97" => "\xe2\x80\x94",
            "\xc2\x98" => "\xcb\x9c",
            "\xc2\x99" => "\xe2\x84\xa2",
            "\xc2\x9a" => "\xc5\xa1",
            "\xc2\x9b" => "\xe2\x80\xba",
            "\xc2\x9c" => "\xc5\x93",
            "\xc2\x9e" => "\xc5\xbe",
            "\xc2\x9f" => "\xc5\xb8"
        ));
    }

    /**
     * Converts HTML entities to XML
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @param string $str The string to convert
     * @param bool $reverse reverse XML entities to HTML
     * @param bool $html must-we htmlentitize the string ?
     * @return string the converted string
     */
    static public function toXML($str, $reverse=false, $html=true)
    {
        if(!$str) return $str;

        $str = (string) $str;

        if($html)
        {
            $str = htmlentities($str, ENT_QUOTES, 'UTF-8');
            $map = array(
                "&quot;" => "&#34;",
                "&amp;" => "&#38;",
                "&apos;" => "&#39;",
                "&lt;" => "&#60;",
                "&gt;" => "&#62;",
                "&nbsp;" => "&#160;",
                "&iexcl;" => "&#161;",
                "&cent;" => "&#162;",
                "&pound;" => "&#163;",
                "&curren;" => "&#164;",
                "&yen;" => "&#165;",
                "&brvbar;" => "&#166;",
                "&sect;" => "&#167;",
                "&uml;" => "&#168;",
                "&copy;" => "&#169;",
                "&ordf;" => "&#170;",
                "&laquo;" => "&#171;",
                "&not;" => "&#172;",
                "&shy;" => "&#173;",
                "&reg;" => "&#174;",
                "&macr;" => "&#175;",
                "&deg;" => "&#176;",
                "&plusmn;" => "&#177;",
                "&sup2;" => "&#178;",
                "&sup3;" => "&#179;",
                "&acute;" => "&#180;",
                "&micro;" => "&#181;",
                "&para;" => "&#182;",
                "&middot;" => "&#183;",
                "&cedil;" => "&#184;",
                "&sup1;" => "&#185;",
                "&ordm;" => "&#186;",
                "&raquo;" => "&#187;",
                "&frac14;" => "&#188;",
                "&frac12;" => "&#189;",
                "&frac34;" => "&#190;",
                "&iquest;" => "&#191;",
                "&Agrave;" => "&#192;",
                "&Aacute;" => "&#193;",
                "&Acirc;" => "&#194;",
                "&Atilde;" => "&#195;",
                "&Auml;" => "&#196;",
                "&Aring;" => "&#197;",
                "&AElig;" => "&#198;",
                "&Ccedil;" => "&#199;",
                "&Egrave;" => "&#200;",
                "&Eacute;" => "&#201;",
                "&Ecirc;" => "&#202;",
                "&Euml;" => "&#203;",
                "&Igrave;" => "&#204;",
                "&Iacute;" => "&#205;",
                "&Icirc;" => "&#206;",
                "&Iuml;" => "&#207;",
                "&ETH;" => "&#208;",
                "&Ntilde;" => "&#209;",
                "&Ograve;" => "&#210;",
                "&Oacute;" => "&#211;",
                "&Ocirc;" => "&#212;",
                "&Otilde;" => "&#213;",
                "&Ouml;" => "&#214;",
                "&times;" => "&#215;",
                "&Oslash;" => "&#216;",
                "&Ugrave;" => "&#217;",
                "&Uacute;" => "&#218;",
                "&Ucirc;" => "&#219;",
                "&Uuml;" => "&#220;",
                "&Yacute;" => "&#221;",
                "&THORN;" => "&#222;",
                "&szlig;" => "&#223;",
                "&agrave;" => "&#224;",
                "&aacute;" => "&#225;",
                "&acirc;" => "&#226;",
                "&atilde;" => "&#227;",
                "&auml;" => "&#228;",
                "&aring;" => "&#229;",
                "&aelig;" => "&#230;",
                "&ccedil;" => "&#231;",
                "&egrave;" => "&#232;",
                "&eacute;" => "&#233;",
                "&ecirc;" => "&#234;",
                "&euml;" => "&#235;",
                "&igrave;" => "&#236;",
                "&iacute;" => "&#237;",
                "&icirc;" => "&#238;",
                "&iuml;" => "&#239;",
                "&eth;" => "&#240;",
                "&ntilde;" => "&#241;",
                "&ograve;" => "&#242;",
                "&oacute;" => "&#243;",
                "&ocirc;" => "&#244;",
                "&otilde;" => "&#245;",
                "&ouml;" => "&#246;",
                "&divide;" => "&#247;",
                "&oslash;" => "&#248;",
                "&ugrave;" => "&#249;",
                "&uacute;" => "&#250;",
                "&ucirc;" => "&#251;",
                "&uuml;" => "&#252;",
                "&yacute;" => "&#253;",
                "&thorn;" => "&#254;",
                "&yuml;" => "&#255;",
                "&OElig;" => "&#338;",
                "&oelig;" => "&#339;",
                "&Scaron;" => "&#352;",
                "&scaron;" => "&#353;",
                "&Yuml;" => "&#376;",
                "&fnof;" => "&#402;",
                "&circ;" => "&#710;",
                "&tilde;" => "&#732;",
                "&Alpha;" => "&#913;",
                "&Beta;" => "&#914;",
                "&Gamma;" => "&#915;",
                "&Delta;" => "&#916;",
                "&Epsilon;" => "&#917;",
                "&Zeta;" => "&#918;",
                "&Eta;" => "&#919;",
                "&Theta;" => "&#920;",
                "&Iota;" => "&#921;",
                "&Kappa;" => "&#922;",
                "&Lambda;" => "&#923;",
                "&Mu;" => "&#924;",
                "&Nu;" => "&#925;",
                "&Xi;" => "&#926;",
                "&Omicron;" => "&#927;",
                "&Pi;" => "&#928;",
                "&Rho;" => "&#929;",
                "&Sigma;" => "&#931;",
                "&Tau;" => "&#932;",
                "&Upsilon;" => "&#933;",
                "&Phi;" => "&#934;",
                "&Chi;" => "&#935;",
                "&Psi;" => "&#936;",
                "&Omega;" => "&#937;",
                "&alpha;" => "&#945;",
                "&beta;" => "&#946;",
                "&gamma;" => "&#947;",
                "&delta;" => "&#948;",
                "&epsilon;" => "&#949;",
                "&zeta;" => "&#950;",
                "&eta;" => "&#951;",
                "&theta;" => "&#952;",
                "&iota;" => "&#953;",
                "&kappa;" => "&#954;",
                "&lambda;" => "&#955;",
                "&mu;" => "&#956;",
                "&nu;" => "&#957;",
                "&xi;" => "&#958;",
                "&omicron;" => "&#959;",
                "&pi;" => "&#960;",
                "&rho;" => "&#961;",
                "&sigmaf;" => "&#962;",
                "&sigma;" => "&#963;",
                "&tau;" => "&#964;",
                "&upsilon;" => "&#965;",
                "&phi;" => "&#966;",
                "&chi;" => "&#967;",
                "&psi;" => "&#968;",
                "&omega;" => "&#969;",
                "&thetasym;" => "&#977;",
                "&upsih;" => "&#978;",
                "&piv;" => "&#982;",
                "&ensp;" => "&#8194;",
                "&emsp;" => "&#8195;",
                "&thinsp;" => "&#8201;",
                "&zwnj;" => "&#8204;",
                "&zwj;" => "&#8205;",
                "&lrm;" => "&#8206;",
                "&rlm;" => "&#8207;",
                "&ndash;" => "&#8211;",
                "&mdash;" => "&#8212;",
                "&lsquo;" => "&#8216;",
                "&rsquo;" => "&#8217;",
                "&sbquo;" => "&#8218;",
                "&ldquo;" => "&#8220;",
                "&rdquo;" => "&#8221;",
                "&bdquo;" => "&#8222;",
                "&dagger;" => "&#8224;",
                "&Dagger;" => "&#8225;",
                "&bull;" => "&#8226;",
                "&hellip;" => "&#8230;",
                "&permil;" => "&#8240;",
                "&prime;" => "&#8242;",
                "&Prime;" => "&#8243;",
                "&lsaquo;" => "&#8249;",
                "&rsaquo;" => "&#8250;",
                "&oline;" => "&#8254;",
                "&frasl;" => "&#8260;",
                "&euro;" => "&#8364;",
                "&image;" => "&#8465;",
                "&weierp;" => "&#8472;",
                "&real;" => "&#8476;",
                "&trade;" => "&#8482;",
                "&alefsym;" => "&#8501;",
                "&larr;" => "&#8592;",
                "&uarr;" => "&#8593;",
                "&rarr;" => "&#8594;",
                "&darr;" => "&#8595;",
                "&harr;" => "&#8596;",
                "&crarr;" => "&#8629;",
                "&lArr;" => "&#8656;",
                "&uArr;" => "&#8657;",
                "&rArr;" => "&#8658;",
                "&dArr;" => "&#8659;",
                "&hArr;" => "&#8660;",
                "&forall;" => "&#8704;",
                "&part;" => "&#8706;",
                "&exist;" => "&#8707;",
                "&empty;" => "&#8709;",
                "&nabla;" => "&#8711;",
                "&isin;" => "&#8712;",
                "&notin;" => "&#8713;",
                "&ni;" => "&#8715;",
                "&prod;" => "&#8719;",
                "&sum;" => "&#8721;",
                "&minus;" => "&#8722;",
                "&lowast;" => "&#8727;",
                "&radic;" => "&#8730;",
                "&prop;" => "&#8733;",
                "&infin;" => "&#8734;",
                "&ang;" => "&#8736;",
                "&and;" => "&#8743;",
                "&or;" => "&#8744;",
                "&cap;" => "&#8745;",
                "&cup;" => "&#8746;",
                "&int;" => "&#8747;",
                "&there4;" => "&#8756;",
                "&sim;" => "&#8764;",
                "&cong;" => "&#8773;",
                "&asymp;" => "&#8776;",
                "&ne;" => "&#8800;",
                "&equiv;" => "&#8801;",
                "&le;" => "&#8804;",
                "&ge;" => "&#8805;",
                "&sub;" => "&#8834;",
                "&sup;" => "&#8835;",
                "&nsub;" => "&#8836;",
                "&sube;" => "&#8838;",
                "&supe;" => "&#8839;",
                "&oplus;" => "&#8853;",
                "&otimes;" => "&#8855;",
                "&perp;" => "&#8869;",
                "&sdot;" => "&#8901;",
                "&lceil;" => "&#8968;",
                "&rceil;" => "&#8969;",
                "&lfloor;" => "&#8970;",
                "&rfloor;" => "&#8971;",
                "&lang;" => "&#9001;",
                "&rang;" => "&#9002;",
                "&loz;" => "&#9674;",
                "&spades;" => "&#9824;",
                "&clubs;" => "&#9827;",
                "&hearts;" => "&#9829;",
                "&diams;" => "&#9830;"
                );

            $str = strtr($str, $reverse ? array_flip($map) : $map);
        }
        else
        {
            if($reverse)
            {
                $replace = array (
                    '#34' => '&quot;',
                    '#38' => '&amp;',
                    '#39' => '&apos;',
                    '#60' => '&lt;',
                    '#62' => '&gt;',
                    '#160' => '&nbsp;',
                    '#161' => '&iexcl;',
                    '#162' => '&cent;',
                    '#163' => '&pound;',
                    '#164' => '&curren;',
                    '#165' => '&yen;',
                    '#166' => '&brvbar;',
                    '#167' => '&sect;',
                    '#168' => '&uml;',
                    '#169' => '&copy;',
                    '#170' => '&ordf;',
                    '#171' => '&laquo;',
                    '#172' => '&not;',
                    '#173' => '&shy;',
                    '#174' => '&reg;',
                    '#175' => '&macr;',
                    '#176' => '&deg;',
                    '#177' => '&plusmn;',
                    '#178' => '&sup2;',
                    '#179' => '&sup3;',
                    '#180' => '&acute;',
                    '#181' => '&micro;',
                    '#182' => '&para;',
                    '#183' => '&middot;',
                    '#184' => '&cedil;',
                    '#185' => '&sup1;',
                    '#186' => '&ordm;',
                    '#187' => '&raquo;',
                    '#188' => '&frac14;',
                    '#189' => '&frac12;',
                    '#190' => '&frac34;',
                    '#191' => '&iquest;',
                    '#192' => '&Agrave;',
                    '#193' => '&Aacute;',
                    '#194' => '&Acirc;',
                    '#195' => '&Atilde;',
                    '#196' => '&Auml;',
                    '#197' => '&Aring;',
                    '#198' => '&AElig;',
                    '#199' => '&Ccedil;',
                    '#200' => '&Egrave;',
                    '#201' => '&Eacute;',
                    '#202' => '&Ecirc;',
                    '#203' => '&Euml;',
                    '#204' => '&Igrave;',
                    '#205' => '&Iacute;',
                    '#206' => '&Icirc;',
                    '#207' => '&Iuml;',
                    '#208' => '&ETH;',
                    '#209' => '&Ntilde;',
                    '#210' => '&Ograve;',
                    '#211' => '&Oacute;',
                    '#212' => '&Ocirc;',
                    '#213' => '&Otilde;',
                    '#214' => '&Ouml;',
                    '#215' => '&times;',
                    '#216' => '&Oslash;',
                    '#217' => '&Ugrave;',
                    '#218' => '&Uacute;',
                    '#219' => '&Ucirc;',
                    '#220' => '&Uuml;',
                    '#221' => '&Yacute;',
                    '#222' => '&THORN;',
                    '#223' => '&szlig;',
                    '#224' => '&agrave;',
                    '#225' => '&aacute;',
                    '#226' => '&acirc;',
                    '#227' => '&atilde;',
                    '#228' => '&auml;',
                    '#229' => '&aring;',
                    '#230' => '&aelig;',
                    '#231' => '&ccedil;',
                    '#232' => '&egrave;',
                    '#233' => '&eacute;',
                    '#234' => '&ecirc;',
                    '#235' => '&euml;',
                    '#236' => '&igrave;',
                    '#237' => '&iacute;',
                    '#238' => '&icirc;',
                    '#239' => '&iuml;',
                    '#240' => '&eth;',
                    '#241' => '&ntilde;',
                    '#242' => '&ograve;',
                    '#243' => '&oacute;',
                    '#244' => '&ocirc;',
                    '#245' => '&otilde;',
                    '#246' => '&ouml;',
                    '#247' => '&divide;',
                    '#248' => '&oslash;',
                    '#249' => '&ugrave;',
                    '#250' => '&uacute;',
                    '#251' => '&ucirc;',
                    '#252' => '&uuml;',
                    '#253' => '&yacute;',
                    '#254' => '&thorn;',
                    '#255' => '&yuml;',
                    '#338' => '&OElig;',
                    '#339' => '&oelig;',
                    '#352' => '&Scaron;',
                    '#353' => '&scaron;',
                    '#376' => '&Yuml;',
                    '#402' => '&fnof;',
                    '#710' => '&circ;',
                    '#732' => '&tilde;',
                    '#913' => '&Alpha;',
                    '#914' => '&Beta;',
                    '#915' => '&Gamma;',
                    '#916' => '&Delta;',
                    '#917' => '&Epsilon;',
                    '#918' => '&Zeta;',
                    '#919' => '&Eta;',
                    '#920' => '&Theta;',
                    '#921' => '&Iota;',
                    '#922' => '&Kappa;',
                    '#923' => '&Lambda;',
                    '#924' => '&Mu;',
                    '#925' => '&Nu;',
                    '#926' => '&Xi;',
                    '#927' => '&Omicron;',
                    '#928' => '&Pi;',
                    '#929' => '&Rho;',
                    '#931' => '&Sigma;',
                    '#932' => '&Tau;',
                    '#933' => '&Upsilon;',
                    '#934' => '&Phi;',
                    '#935' => '&Chi;',
                    '#936' => '&Psi;',
                    '#937' => '&Omega;',
                    '#945' => '&alpha;',
                    '#946' => '&beta;',
                    '#947' => '&gamma;',
                    '#948' => '&delta;',
                    '#949' => '&epsilon;',
                    '#950' => '&zeta;',
                    '#951' => '&eta;',
                    '#952' => '&theta;',
                    '#953' => '&iota;',
                    '#954' => '&kappa;',
                    '#955' => '&lambda;',
                    '#956' => '&mu;',
                    '#957' => '&nu;',
                    '#958' => '&xi;',
                    '#959' => '&omicron;',
                    '#960' => '&pi;',
                    '#961' => '&rho;',
                    '#962' => '&sigmaf;',
                    '#963' => '&sigma;',
                    '#964' => '&tau;',
                    '#965' => '&upsilon;',
                    '#966' => '&phi;',
                    '#967' => '&chi;',
                    '#968' => '&psi;',
                    '#969' => '&omega;',
                    '#977' => '&thetasym;',
                    '#978' => '&upsih;',
                    '#982' => '&piv;',
                    '#8194' => '&ensp;',
                    '#8195' => '&emsp;',
                    '#8201' => '&thinsp;',
                    '#8204' => '&zwnj;',
                    '#8205' => '&zwj;',
                    '#8206' => '&lrm;',
                    '#8207' => '&rlm;',
                    '#8211' => '&ndash;',
                    '#8212' => '&mdash;',
                    '#8216' => '&lsquo;',
                    '#8217' => '&rsquo;',
                    '#8218' => '&sbquo;',
                    '#8220' => '&ldquo;',
                    '#8221' => '&rdquo;',
                    '#8222' => '&bdquo;',
                    '#8224' => '&dagger;',
                    '#8225' => '&Dagger;',
                    '#8226' => '&bull;',
                    '#8230' => '&hellip;',
                    '#8240' => '&permil;',
                    '#8242' => '&prime;',
                    '#8243' => '&Prime;',
                    '#8249' => '&lsaquo;',
                    '#8250' => '&rsaquo;',
                    '#8254' => '&oline;',
                    '#8260' => '&frasl;',
                    '#8364' => '&euro;',
                    '#8465' => '&image;',
                    '#8472' => '&weierp;',
                    '#8476' => '&real;',
                    '#8482' => '&trade;',
                    '#8501' => '&alefsym;',
                    '#8592' => '&larr;',
                    '#8593' => '&uarr;',
                    '#8594' => '&rarr;',
                    '#8595' => '&darr;',
                    '#8596' => '&harr;',
                    '#8629' => '&crarr;',
                    '#8656' => '&lArr;',
                    '#8657' => '&uArr;',
                    '#8658' => '&rArr;',
                    '#8659' => '&dArr;',
                    '#8660' => '&hArr;',
                    '#8704' => '&forall;',
                    '#8706' => '&part;',
                    '#8707' => '&exist;',
                    '#8709' => '&empty;',
                    '#8711' => '&nabla;',
                    '#8712' => '&isin;',
                    '#8713' => '&notin;',
                    '#8715' => '&ni;',
                    '#8719' => '&prod;',
                    '#8721' => '&sum;',
                    '#8722' => '&minus;',
                    '#8727' => '&lowast;',
                    '#8730' => '&radic;',
                    '#8733' => '&prop;',
                    '#8734' => '&infin;',
                    '#8736' => '&ang;',
                    '#8743' => '&and;',
                    '#8744' => '&or;',
                    '#8745' => '&cap;',
                    '#8746' => '&cup;',
                    '#8747' => '&int;',
                    '#8756' => '&there4;',
                    '#8764' => '&sim;',
                    '#8773' => '&cong;',
                    '#8776' => '&asymp;',
                    '#8800' => '&ne;',
                    '#8801' => '&equiv;',
                    '#8804' => '&le;',
                    '#8805' => '&ge;',
                    '#8834' => '&sub;',
                    '#8835' => '&sup;',
                    '#8836' => '&nsub;',
                    '#8838' => '&sube;',
                    '#8839' => '&supe;',
                    '#8853' => '&oplus;',
                    '#8855' => '&otimes;',
                    '#8869' => '&perp;',
                    '#8901' => '&sdot;',
                    '#8968' => '&lceil;',
                    '#8969' => '&rceil;',
                    '#8970' => '&lfloor;',
                    '#8971' => '&rfloor;',
                    '#9001' => '&lang;',
                    '#9002' => '&rang;',
                    '#9674' => '&loz;',
                    '#9824' => '&spades;',
                    '#9827' => '&clubs;',
                    '#9829' => '&hearts;',
                    '#9830' => '&diams;',
                );
            }
            else
            {
                $replace = array(
                    "quot" => "&#34;",
                    "amp" => "&#38;",
                    "apos" => "&#39;",
                    "lt" => "&#60;",
                    "gt" => "&#62;",
                    "nbsp" => "&#160;",
                    "iexcl" => "&#161;",
                    "cent" => "&#162;",
                    "pound" => "&#163;",
                    "curren" => "&#164;",
                    "yen" => "&#165;",
                    "brvbar" => "&#166;",
                    "sect" => "&#167;",
                    "uml" => "&#168;",
                    "copy" => "&#169;",
                    "ordf" => "&#170;",
                    "laquo" => "&#171;",
                    "not" => "&#172;",
                    "shy" => "&#173;",
                    "reg" => "&#174;",
                    "macr" => "&#175;",
                    "deg" => "&#176;",
                    "plusmn" => "&#177;",
                    "sup2" => "&#178;",
                    "sup3" => "&#179;",
                    "acute" => "&#180;",
                    "micro" => "&#181;",
                    "para" => "&#182;",
                    "middot" => "&#183;",
                    "cedil" => "&#184;",
                    "sup1" => "&#185;",
                    "ordm" => "&#186;",
                    "raquo" => "&#187;",
                    "frac14" => "&#188;",
                    "frac12" => "&#189;",
                    "frac34" => "&#190;",
                    "iquest" => "&#191;",
                    "Agrave" => "&#192;",
                    "Aacute" => "&#193;",
                    "Acirc" => "&#194;",
                    "Atilde" => "&#195;",
                    "Auml" => "&#196;",
                    "Aring" => "&#197;",
                    "AElig" => "&#198;",
                    "Ccedil" => "&#199;",
                    "Egrave" => "&#200;",
                    "Eacute" => "&#201;",
                    "Ecirc" => "&#202;",
                    "Euml" => "&#203;",
                    "Igrave" => "&#204;",
                    "Iacute" => "&#205;",
                    "Icirc" => "&#206;",
                    "Iuml" => "&#207;",
                    "ETH" => "&#208;",
                    "Ntilde" => "&#209;",
                    "Ograve" => "&#210;",
                    "Oacute" => "&#211;",
                    "Ocirc" => "&#212;",
                    "Otilde" => "&#213;",
                    "Ouml" => "&#214;",
                    "times" => "&#215;",
                    "Oslash" => "&#216;",
                    "Ugrave" => "&#217;",
                    "Uacute" => "&#218;",
                    "Ucirc" => "&#219;",
                    "Uuml" => "&#220;",
                    "Yacute" => "&#221;",
                    "THORN" => "&#222;",
                    "szlig" => "&#223;",
                    "agrave" => "&#224;",
                    "aacute" => "&#225;",
                    "acirc" => "&#226;",
                    "atilde" => "&#227;",
                    "auml" => "&#228;",
                    "aring" => "&#229;",
                    "aelig" => "&#230;",
                    "ccedil" => "&#231;",
                    "egrave" => "&#232;",
                    "eacute" => "&#233;",
                    "ecirc" => "&#234;",
                    "euml" => "&#235;",
                    "igrave" => "&#236;",
                    "iacute" => "&#237;",
                    "icirc" => "&#238;",
                    "iuml" => "&#239;",
                    "eth" => "&#240;",
                    "ntilde" => "&#241;",
                    "ograve" => "&#242;",
                    "oacute" => "&#243;",
                    "ocirc" => "&#244;",
                    "otilde" => "&#245;",
                    "ouml" => "&#246;",
                    "divide" => "&#247;",
                    "oslash" => "&#248;",
                    "ugrave" => "&#249;",
                    "uacute" => "&#250;",
                    "ucirc" => "&#251;",
                    "uuml" => "&#252;",
                    "yacute" => "&#253;",
                    "thorn" => "&#254;",
                    "yuml" => "&#255;",
                    "OElig" => "&#338;",
                    "oelig" => "&#339;",
                    "Scaron" => "&#352;",
                    "scaron" => "&#353;",
                    "Yuml" => "&#376;",
                    "fnof" => "&#402;",
                    "circ" => "&#710;",
                    "tilde" => "&#732;",
                    "Alpha" => "&#913;",
                    "Beta" => "&#914;",
                    "Gamma" => "&#915;",
                    "Delta" => "&#916;",
                    "Epsilon" => "&#917;",
                    "Zeta" => "&#918;",
                    "Eta" => "&#919;",
                    "Theta" => "&#920;",
                    "Iota" => "&#921;",
                    "Kappa" => "&#922;",
                    "Lambda" => "&#923;",
                    "Mu" => "&#924;",
                    "Nu" => "&#925;",
                    "Xi" => "&#926;",
                    "Omicron" => "&#927;",
                    "Pi" => "&#928;",
                    "Rho" => "&#929;",
                    "Sigma" => "&#931;",
                    "Tau" => "&#932;",
                    "Upsilon" => "&#933;",
                    "Phi" => "&#934;",
                    "Chi" => "&#935;",
                    "Psi" => "&#936;",
                    "Omega" => "&#937;",
                    "alpha" => "&#945;",
                    "beta" => "&#946;",
                    "gamma" => "&#947;",
                    "delta" => "&#948;",
                    "epsilon" => "&#949;",
                    "zeta" => "&#950;",
                    "eta" => "&#951;",
                    "theta" => "&#952;",
                    "iota" => "&#953;",
                    "kappa" => "&#954;",
                    "lambda" => "&#955;",
                    "mu" => "&#956;",
                    "nu" => "&#957;",
                    "xi" => "&#958;",
                    "omicron" => "&#959;",
                    "pi" => "&#960;",
                    "rho" => "&#961;",
                    "sigmaf" => "&#962;",
                    "sigma" => "&#963;",
                    "tau" => "&#964;",
                    "upsilon" => "&#965;",
                    "phi" => "&#966;",
                    "chi" => "&#967;",
                    "psi" => "&#968;",
                    "omega" => "&#969;",
                    "thetasym" => "&#977;",
                    "upsih" => "&#978;",
                    "piv" => "&#982;",
                    "ensp" => "&#8194;",
                    "emsp" => "&#8195;",
                    "thinsp" => "&#8201;",
                    "zwnj" => "&#8204;",
                    "zwj" => "&#8205;",
                    "lrm" => "&#8206;",
                    "rlm" => "&#8207;",
                    "ndash" => "&#8211;",
                    "mdash" => "&#8212;",
                    "lsquo" => "&#8216;",
                    "rsquo" => "&#8217;",
                    "sbquo" => "&#8218;",
                    "ldquo" => "&#8220;",
                    "rdquo" => "&#8221;",
                    "bdquo" => "&#8222;",
                    "dagger" => "&#8224;",
                    "Dagger" => "&#8225;",
                    "bull" => "&#8226;",
                    "hellip" => "&#8230;",
                    "permil" => "&#8240;",
                    "prime" => "&#8242;",
                    "Prime" => "&#8243;",
                    "lsaquo" => "&#8249;",
                    "rsaquo" => "&#8250;",
                    "oline" => "&#8254;",
                    "frasl" => "&#8260;",
                    "euro" => "&#8364;",
                    "image" => "&#8465;",
                    "weierp" => "&#8472;",
                    "real" => "&#8476;",
                    "trade" => "&#8482;",
                    "alefsym" => "&#8501;",
                    "larr" => "&#8592;",
                    "uarr" => "&#8593;",
                    "rarr" => "&#8594;",
                    "darr" => "&#8595;",
                    "harr" => "&#8596;",
                    "crarr" => "&#8629;",
                    "lArr" => "&#8656;",
                    "uArr" => "&#8657;",
                    "rArr" => "&#8658;",
                    "dArr" => "&#8659;",
                    "hArr" => "&#8660;",
                    "forall" => "&#8704;",
                    "part" => "&#8706;",
                    "exist" => "&#8707;",
                    "empty" => "&#8709;",
                    "nabla" => "&#8711;",
                    "isin" => "&#8712;",
                    "notin" => "&#8713;",
                    "ni" => "&#8715;",
                    "prod" => "&#8719;",
                    "sum" => "&#8721;",
                    "minus" => "&#8722;",
                    "lowast" => "&#8727;",
                    "radic" => "&#8730;",
                    "prop" => "&#8733;",
                    "infin" => "&#8734;",
                    "ang" => "&#8736;",
                    "and" => "&#8743;",
                    "or" => "&#8744;",
                    "cap" => "&#8745;",
                    "cup" => "&#8746;",
                    "int" => "&#8747;",
                    "there4" => "&#8756;",
                    "sim" => "&#8764;",
                    "cong" => "&#8773;",
                    "asymp" => "&#8776;",
                    "ne" => "&#8800;",
                    "equiv" => "&#8801;",
                    "le" => "&#8804;",
                    "ge" => "&#8805;",
                    "sub" => "&#8834;",
                    "sup" => "&#8835;",
                    "nsub" => "&#8836;",
                    "sube" => "&#8838;",
                    "supe" => "&#8839;",
                    "oplus" => "&#8853;",
                    "otimes" => "&#8855;",
                    "perp" => "&#8869;",
                    "sdot" => "&#8901;",
                    "lceil" => "&#8968;",
                    "rceil" => "&#8969;",
                    "lfloor" => "&#8970;",
                    "rfloor" => "&#8971;",
                    "lang" => "&#9001;",
                    "rang" => "&#9002;",
                    "loz" => "&#9674;",
                    "spades" => "&#9824;",
                    "clubs" => "&#9827;",
                    "hearts" => "&#9829;",
                    "diams" => "&#9830;"
                );
            }
            $str = preg_replace_callback("/&(\w+);/", function ($matches) use ($replace) { return (isset($replace[$matches[1]]) ? $replace[$matches[1]] : $matches[0]);}, $str);
            // we must keep '<', '>' and quotes
            $str = preg_replace('/&(?!amp;|#[0-9]+;)/', '&amp;', $str);
        }

        return $str;
    }

    /**
     * Multi-byte equivalent of php wordwrap() function
     * WARNING : this method is NOT tag safe
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     * @param string $str the string to wordwrap
     * @param int $width the width to cut
     * @param string $break on what character to break
     * @param int $lines maximum number of lines to return, optionnal
     * @return string the wordwraped string
     */
    static public function mb_wordwrap($str, $width = 75, $break = "\n", $lines = null)
    {
        if(isset($lines))
            $lines = (int) $lines;
        $str = preg_split('/([\x20\r\n\t]++|\xc2\xa0)/sSX', $str, -1, PREG_SPLIT_NO_EMPTY);
        $length = 0;
        $return = '';
        $nbLines = 1;
        foreach($str as $val)
        {
            $val .= ' ';
            $tmp = mb_strlen($val, 'UTF-8');
            $length += $tmp;
            if($length >= $width)
            {
                $return .= $break.$val;
                ++$nbLines;
                if(isset($lines) && $nbLines > $lines) return $return;
                $length = $tmp;
            }
            else $return .= $val;
        }

        return $return;
    }

    /**
    * Truncates a string to the length of $length and replaces the last characters
    * with the ending if the text is longer than length.
    *
    * Checked out from CakePHP Framework, many thanks to them.
    *
    * ### Options:
    *
    * - `ending` Will be used as Ending and appended to the trimmed string
    * - `exact` If false, $text will not be cut mid-word
    * - `html` If true, HTML tags would be handled correctly
    *
    * @param string  $text String to truncate.
    * @param integer $length Length of returned string, including ellipsis.
    * @param array $options An array of html attributes and options.
    * @return string Truncated string.
    * @access public
    * @static
    * @link http://book.cakephp.org/view/1469/Text#truncate-1625
    */
    static public function truncate($text, $length = 100, array $options = array('html' => true, 'exact' => false, 'ending' => '[…]'))
    {
        extract($options);
        $encoding = mb_detect_encoding($text, 'UTF-8, ISO-8859-1, ISO-8859-15, Windows-1252', true);

        if($html)
        {
            if (mb_strlen(preg_replace('/<.*?>/', '', $text), $encoding) <= $length)
                return $text;

            $totalLength = mb_strlen(strip_tags($ending), $encoding);
            $openTags = array();
            $truncate = '';

            preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);
            foreach ($tags as $tag)
            {
                if(!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2]))
                {
                    if(preg_match('/<[\w]+[^>]*>/s', $tag[0]))
                    {
                        array_unshift($openTags, $tag[2]);
                    }
                    elseif(preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag))
                    {
                        $pos = array_search($closeTag[1], $openTags);
                        if($pos !== false)
                        {
                            array_splice($openTags, $pos, 1);
                        }
                    }
                }
                $truncate .= $tag[1];

                $contentLength = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $tag[3]), $encoding);
                if ($contentLength + $totalLength > $length)
                {
                    $left = $length - $totalLength;
                    $entitiesLength = 0;
                    if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE))
                    {
                        foreach($entities[0] as $entity)
                        {
                            if($entity[1] + 1 - $entitiesLength <= $left)
                            {
                                $left--;
                                $entitiesLength += mb_strlen($entity[0], $encoding);
                            }
                            else break;
                        }
                    }

                    $truncate .= mb_substr($tag[3], 0 , $left + $entitiesLength, $encoding);
                    break;
                }
                else
                {
                    $truncate .= $tag[3];
                    $totalLength += $contentLength;
                }

                if ($totalLength >= $length)
                    break;
            }
            $truncate = preg_replace('/\&[0-9a-z]{0,8}$/i', '', $truncate);
        }
        else
        {
            if(mb_strlen($text, $encoding) <= $length)
                return $text;
            else
                $truncate = mb_substr($text, 0, $length - mb_strlen($ending, $encoding), $encoding);
        }

        if (!$exact)
        {
            $spacepos = mb_strrpos($truncate, ' ', $encoding);
            if (isset($spacepos))
            {
                if ($html)
                {
                    $bits = mb_substr($truncate, $spacepos, null, $encoding);
                    preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
                    if (!empty($droppedTags))
                    {
                        foreach($droppedTags as $closingTag) {
                            if(!in_array($closingTag[1], $openTags))
                                array_unshift($openTags, $closingTag[1]);
                        }
                    }
                }
                $truncate = mb_substr($truncate, 0, $spacepos, $encoding);
            }
        }

        $truncate .= ' ' . $ending;

        if ($html)
        {
            foreach($openTags as $tag)
                $truncate .= '</'.$tag.'>';
        }

        return  $truncate;
    }
}
