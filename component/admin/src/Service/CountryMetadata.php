<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

final class CountryMetadata
{
    private const CODE_PAIRS = 'AD:AND,AE:ARE,AF:AFG,AG:ATG,AI:AIA,AL:ALB,AM:ARM,AO:AGO,AQ:ATA,AR:ARG,AS:ASM,AT:AUT,AU:AUS,AW:ABW,AX:ALA,AZ:AZE,BA:BIH,BB:BRB,BD:BGD,BE:BEL,BF:BFA,BG:BGR,BH:BHR,BI:BDI,BJ:BEN,BL:BLM,BM:BMU,BN:BRN,BO:BOL,BQ:BES,BR:BRA,BS:BHS,BT:BTN,BV:BVT,BW:BWA,BY:BLR,BZ:BLZ,CA:CAN,CC:CCK,CD:COD,CF:CAF,CG:COG,CH:CHE,CI:CIV,CK:COK,CL:CHL,CM:CMR,CN:CHN,CO:COL,CR:CRI,CU:CUB,CV:CPV,CW:CUW,CX:CXR,CY:CYP,CZ:CZE,DE:DEU,DJ:DJI,DK:DNK,DM:DMA,DO:DOM,DZ:DZA,EC:ECU,EE:EST,EG:EGY,EH:ESH,ER:ERI,ES:ESP,ET:ETH,FI:FIN,FJ:FJI,FK:FLK,FM:FSM,FO:FRO,FR:FRA,GA:GAB,GB:GBR,GD:GRD,GE:GEO,GF:GUF,GG:GGY,GH:GHA,GI:GIB,GL:GRL,GM:GMB,GN:GIN,GP:GLP,GQ:GNQ,GR:GRC,GS:SGS,GT:GTM,GU:GUM,GW:GNB,GY:GUY,HK:HKG,HM:HMD,HN:HND,HR:HRV,HT:HTI,HU:HUN,ID:IDN,IE:IRL,IL:ISR,IM:IMN,IN:IND,IO:IOT,IQ:IRQ,IR:IRN,IS:ISL,IT:ITA,JE:JEY,JM:JAM,JO:JOR,JP:JPN,KE:KEN,KG:KGZ,KH:KHM,KI:KIR,KM:COM,KN:KNA,KP:PRK,KR:KOR,KW:KWT,KY:CYM,KZ:KAZ,LA:LAO,LB:LBN,LC:LCA,LI:LIE,LK:LKA,LR:LBR,LS:LSO,LT:LTU,LU:LUX,LV:LVA,LY:LBY,MA:MAR,MC:MCO,MD:MDA,ME:MNE,MF:MAF,MG:MDG,MH:MHL,MK:MKD,ML:MLI,MM:MMR,MN:MNG,MO:MAC,MP:MNP,MQ:MTQ,MR:MRT,MS:MSR,MT:MLT,MU:MUS,MV:MDV,MW:MWI,MX:MEX,MY:MYS,MZ:MOZ,NA:NAM,NC:NCL,NE:NER,NF:NFK,NG:NGA,NI:NIC,NL:NLD,NO:NOR,NP:NPL,NR:NRU,NU:NIU,NZ:NZL,OM:OMN,PA:PAN,PE:PER,PF:PYF,PG:PNG,PH:PHL,PK:PAK,PL:POL,PM:SPM,PN:PCN,PR:PRI,PS:PSE,PT:PRT,PW:PLW,PY:PRY,QA:QAT,RE:REU,RO:ROU,RS:SRB,RU:RUS,RW:RWA,SA:SAU,SB:SLB,SC:SYC,SD:SDN,SE:SWE,SG:SGP,SH:SHN,SI:SVN,SJ:SJM,SK:SVK,SL:SLE,SM:SMR,SN:SEN,SO:SOM,SR:SUR,SS:SSD,ST:STP,SV:SLV,SX:SXM,SY:SYR,SZ:SWZ,TC:TCA,TD:TCD,TF:ATF,TG:TGO,TH:THA,TJ:TJK,TK:TKL,TL:TLS,TM:TKM,TN:TUN,TO:TON,TR:TUR,TT:TTO,TV:TUV,TW:TWN,TZ:TZA,UA:UKR,UG:UGA,UM:UMI,US:USA,UY:URY,UZ:UZB,VA:VAT,VC:VCT,VE:VEN,VG:VGB,VI:VIR,VN:VNM,VU:VUT,WF:WLF,WS:WSM,YE:YEM,YT:MYT,ZA:ZAF,ZM:ZMB,ZW:ZWE,XK:XKX';

    private const FALLBACK_NAMES = [
        'AD'=>['en'=>'Andorra','it'=>'Andorra'],'AL'=>['en'=>'Albania','it'=>'Albania'],'AM'=>['en'=>'Armenia','it'=>'Armenia'],'AT'=>['en'=>'Austria','it'=>'Austria'],'AZ'=>['en'=>'Azerbaijan','it'=>'Azerbaigian'],'BA'=>['en'=>'Bosnia & Herzegovina','it'=>'Bosnia ed Erzegovina'],'BE'=>['en'=>'Belgium','it'=>'Belgio'],'BG'=>['en'=>'Bulgaria','it'=>'Bulgaria'],'BY'=>['en'=>'Belarus','it'=>'Bielorussia'],'CH'=>['en'=>'Switzerland','it'=>'Svizzera'],'CY'=>['en'=>'Cyprus','it'=>'Cipro'],'CZ'=>['en'=>'Czechia','it'=>'Cechia'],'DE'=>['en'=>'Germany','it'=>'Germania'],'DK'=>['en'=>'Denmark','it'=>'Danimarca'],'EE'=>['en'=>'Estonia','it'=>'Estonia'],'ES'=>['en'=>'Spain','it'=>'Spagna'],'FI'=>['en'=>'Finland','it'=>'Finlandia'],'FR'=>['en'=>'France','it'=>'Francia'],'GB'=>['en'=>'United Kingdom','it'=>'Regno Unito'],'GE'=>['en'=>'Georgia','it'=>'Georgia'],'GR'=>['en'=>'Greece','it'=>'Grecia'],'HR'=>['en'=>'Croatia','it'=>'Croazia'],'HU'=>['en'=>'Hungary','it'=>'Ungheria'],'IE'=>['en'=>'Ireland','it'=>'Irlanda'],'IS'=>['en'=>'Iceland','it'=>'Islanda'],'IT'=>['en'=>'Italy','it'=>'Italia'],'LI'=>['en'=>'Liechtenstein','it'=>'Liechtenstein'],'LT'=>['en'=>'Lithuania','it'=>'Lituania'],'LU'=>['en'=>'Luxembourg','it'=>'Lussemburgo'],'LV'=>['en'=>'Latvia','it'=>'Lettonia'],'MC'=>['en'=>'Monaco','it'=>'Monaco'],'MD'=>['en'=>'Moldova','it'=>'Moldavia'],'ME'=>['en'=>'Montenegro','it'=>'Montenegro'],'MK'=>['en'=>'North Macedonia','it'=>'Macedonia del Nord'],'MT'=>['en'=>'Malta','it'=>'Malta'],'NL'=>['en'=>'Netherlands','it'=>'Paesi Bassi'],'NO'=>['en'=>'Norway','it'=>'Norvegia'],'PL'=>['en'=>'Poland','it'=>'Polonia'],'PT'=>['en'=>'Portugal','it'=>'Portogallo'],'RO'=>['en'=>'Romania','it'=>'Romania'],'RS'=>['en'=>'Serbia','it'=>'Serbia'],'RU'=>['en'=>'Russia','it'=>'Russia'],'SE'=>['en'=>'Sweden','it'=>'Svezia'],'SI'=>['en'=>'Slovenia','it'=>'Slovenia'],'SK'=>['en'=>'Slovakia','it'=>'Slovacchia'],'SM'=>['en'=>'San Marino','it'=>'San Marino'],'TR'=>['en'=>'Türkiye','it'=>'Turchia'],'UA'=>['en'=>'Ukraine','it'=>'Ucraina'],'VA'=>['en'=>'Vatican City','it'=>'Città del Vaticano'],'XK'=>['en'=>'Kosovo','it'=>'Kosovo'],
    ];

    private const EUROPEAN_TIN_LABELS = [
        'ALB'=>'NIPT','AND'=>'NRT','ARM'=>'ՀՎՀՀ','AUT'=>'Steuernummer','AZE'=>'VÖEN','BEL'=>'Numéro national / Rijksregisternummer','BGR'=>'ЕГН (EGN)','BIH'=>'JMBG','CHE'=>'AHV / AVS','CYP'=>'Tax Identification Code','CZE'=>'Rodné číslo','DEU'=>'Steueridentifikationsnummer','DNK'=>'CPR-nummer','ESP'=>'NIF','EST'=>'Isikukood','FIN'=>'Henkilötunnus','FRA'=>'Numéro fiscal','GBR'=>'National Insurance number / UTR','GEO'=>'Personal number','GRC'=>'ΑΦΜ (AFM)','HRV'=>'OIB','HUN'=>'Adóazonosító jel','IRL'=>'PPSN','ISL'=>'Kennitala','ITA'=>'Codice fiscale','LIE'=>'PEID','LTU'=>'Asmens kodas','LUX'=>'Matricule','LVA'=>'Personas kods','MCO'=>'Numéro fiscal','MDA'=>'IDNP','MKD'=>'ЕМБГ (EMBG)','MLT'=>'TIN','MNE'=>'JMBG','NLD'=>'BSN','NOR'=>'Fødselsnummer','POL'=>'PESEL / NIP','PRT'=>'NIF','ROU'=>'CNP','RUS'=>'ИНН (INN)','SMR'=>'Codice fiscale','SRB'=>'JMBG','SVK'=>'Rodné číslo','SVN'=>'Davčna številka','SWE'=>'Personnummer','TUR'=>'T.C. Kimlik No / Vergi Kimlik No','UKR'=>'РНОКПП (RNOKPP)','XKX'=>'Personal number',
    ];

    private static ?array $codes = null;

    public static function countries(string $languageTag = 'en-GB'): array
    {
        $language = str_starts_with(strtolower($languageTag), 'it') ? 'it' : 'en';
        $locale = $language === 'it' ? 'it_IT' : 'en_GB';
        $countries = [];

        foreach (self::codes() as $alpha2 => $alpha3) {
            $name = self::FALLBACK_NAMES[$alpha2][$language] ?? $alpha3;
            if (class_exists('Locale')) {
                $display = \Locale::getDisplayRegion('-' . $alpha2, $locale);
                if (is_string($display) && $display !== '' && strtoupper($display) !== $alpha2) {
                    $name = $display;
                }
            }
            $countries[] = ['alpha2'=>$alpha2,'alpha3'=>$alpha3,'name'=>$name];
        }

        usort($countries, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $countries;
    }

    public static function tinLabels(): array
    {
        return self::EUROPEAN_TIN_LABELS;
    }

    public static function isAlpha2(string $code): bool
    {
        return isset(self::codes()[strtoupper($code)]);
    }

    public static function isAlpha3(string $code): bool
    {
        return in_array(strtoupper($code), self::codes(), true);
    }

    private static function codes(): array
    {
        if (self::$codes !== null) {
            return self::$codes;
        }
        self::$codes = [];
        foreach (explode(',', self::CODE_PAIRS) as $pair) {
            [$alpha2, $alpha3] = explode(':', $pair, 2);
            self::$codes[$alpha2] = $alpha3;
        }
        return self::$codes;
    }
}
