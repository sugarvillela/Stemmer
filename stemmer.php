<?php
function stemmerTest(){
    $origText='overeatery';
    $rootText='eat';
    echo "<br>For $origText containing $rootText:<br>";
    if( STEM::setRoots( $origText ) ){
        dispArray( STEM::getRoots(), 'roots list returned' );
        STEM::traceback( $rootText, $origText );
        dispArray( STEM::getpref(), 'pref list returned' );
        dispArray( STEM::getSuff(), 'suff list returned' );
    }
    $origText='undersecretary';
    $rootText='secretary';
    echo "<br>For $origText containing $rootText:<br>";
    if( STEM::setRoots( $origText ) ){
        dispArray( STEM::getRoots(), 'roots list returned' );
        STEM::traceback( $rootText, $origText );
        dispArray( STEM::getpref(), 'pref list returned' );
        dispArray( STEM::getSuff(), 'suff list returned' );
    }
    $origText='disproportionately';
    $rootText='proportion';
    echo "<br>For $origText containing $rootText:<br>";
    if( STEM::setRoots( $origText ) ){
        dispArray( STEM::getRoots(), 'roots list returned' );
        STEM::traceback( $rootText, $origText );
        dispArray( STEM::getpref(), 'pref list returned' );
        dispArray( STEM::getSuff(), 'suff list returned' );
    }
    $origText='undying';
    $rootText='die';
    echo "<br>For $origText containing $rootText:<br>";
    if( STEM::setRoots( $origText ) ){
        dispArray( STEM::getRoots(), 'roots list returned' );
        STEM::traceback( $rootText, $origText );
        dispArray( STEM::getpref(), 'pref list returned' );
        dispArray( STEM::getSuff(), 'suff list returned' );
    }
}
/* Keep MAXWORDLEN updated with longest word in word list database */
const MAXWORDLEN=15;
const MINWORDLEN=2;
/* Keep PREF and SUF updated from list in initStemmerGlobals() below */
const MAXPREFLEN=7;
const MAXSUFLEN=6;

class STEM{
    /* For static classes, you must call the init function on startup */
    static function init(){
        $GLOBALS['stem']=new class_stemmer();
        initStemmerGlobals();
    }
    /* Each function below is a wrapper for a class_stemmer function.
     * The point is to have a loose singleton rule: one object is instantiated
     * at startup, and every call to this static class defers to that object
     */
    static function setRoots( $text ){
        return $GLOBALS['stem']->setRoots( $text );
    }
    static function getRoots(){
        return $GLOBALS['stem']->getRoots();
    }
    static function traceback( $rootText, $origText ){
        return $GLOBALS['stem']->traceback( $rootText, $origText );
    }    
    static function getPref(){
        return $GLOBALS['stem']->getPref();
    }
    static function getSuff(){
        return $GLOBALS['stem']->getSuff();
    }
}
class class_stemmer{
    private $prefix, $suffix,$roots;
    function __construct() {
        $this->prefix=new prefix(); 
        $this->suffix=new suffix();
        $this->pTraceback=new pTraceback(); 
        $this->sTraceback=new sTraceback();
        $this->roots=array();
    }
    private function merge(){
        /* Merge found root words into one list.
         * For each prefix root, a new word for eath suffix root must be 
         * created.  That multiplies the size of the list quickly. 
         * Duplicates are removed.
         */
        if( !$this->prefix->havePrefix() ){
            $this->roots=$this->suffix->get();
            return;
        }
        if( !$this->suffix->haveSuffix() ){
            $this->roots=$this->prefix->get();
            return;
        }
        foreach ( $this->prefix->get() as $start => $pRoot ) {
            $this->roots[]=$pRoot;
            foreach ( $this->suffix->get() as $sRoot ) {
                $this->roots[]=$sRoot;
                $merged=substr( $sRoot, $start);
                if( strlen( $merged )>=MINWORDLEN){
                    $this->roots[]=$merged;
                }
            }
        }
        $this->roots = array_unique( $this->roots );
    }
    private function filterLength(){
        $nu=array();
        foreach ( $this->roots as $root ) {
            if( strlen( $root )<=MAXWORDLEN ){
                $nu[]=$root;
            }
        }
        $this->roots=$nu;
    }
    function setRoots( $text ){
        /* Clears every time set is called so this object can be reused */
        $this->roots=array();
        $this->prefix->init();
        $this->suffix->init();
        /* Create root lists independently */
        $this->prefix->set( $text );
        $this->suffix->set( $text );
        /* Join the lists */
        self::merge();
        /* Delete words longer than any in the database. This step is done
         * here because merged list constains roots with pref and suff stripped
         */
        self::filterLength();
        /* Sort words longest to shortest for search precedence */
        usort( $this->roots, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        return count( $this->roots );
    }
    function getRoots(){
        return $this->roots;
    }
    function traceback( $rootText, $origText ){
        /* Traceback initializes lists of the prefixes and suffixes stripped
         * to make $rootText from $origText. To do so it follows the same steps
         * as setRoots, looking for a thread that generates $rootText */
        /* Clears every time set is called so this object can be reused */
        $this->pTraceback->init();
        $this->sTraceback->init();
        $findMe=$rootText;
        $strpos=strpos(  $origText, $rootText );
        while( $strpos===false ){
            /* If we're here, a suffix rule was invoked during setRoots, which 
             * changed the last letter or two of $rootText.  To find where it
             * starts in $origText, remove trailing letters until the effect of 
             * the rule is gone */
            $findMe=substr( $findMe , 0, -1 );
            if( !strlen( $findMe ) ){
                /* If we're here, that word just aint in the original */
                return false;
            }
            $strpos=strpos(  $origText, $findMe );
        }
        /* Have strpos, traceback prefix list */
        $this->pTraceback->set( substr( $origText, 0, $strpos ) );
        /* Remove prefix from $origText */
        $origText=substr( $origText, $strpos );
        if( $origText==$rootText ){
            /* If we're here, no suffixes were stripped by setRoots */
            return true;
        }
        /* sTraceback needs the current root text.
         * Then traceback suffix list */
        $this->sTraceback->setTargetRoot( $rootText );
        $this->sTraceback->set( $origText );
        return true;//always true if some form of $rootText is in $origText
    }
    function getPref(){
        /* Returns list after traceback */
        return $this->pTraceback->get();
    }
    function getSuff(){
        /* Returns list after traceback */
        return $this->sTraceback->get();
    }
}
abstract class affix{//parent for prefix, suffix; grandparent for pTraceback, sTraceback
    protected $roots;
    function init() {
        $this->roots=array();
    }
    abstract function set( $text );
    function get(){
        return $this->roots;
    }
    function vowel( $char ){
        switch ( $char ) {
            case 'a':
            case 'e':
            case 'i':
            case 'o':
            case 'u':
            case 'y':
                return true;
            default:
                return false;
        }
    }
    function hardConsonant( $char ){// b,p, t,d, k and g used by stemmer
        switch ( $char ) {
            case 'b':
            case 'c':
            case 'd':
            case 'g':
            case 'k':
            case 'p':
            case 't':
                return true;
            default:
                return false;
        }
    }
}
class prefix extends affix{
    function havePrefix(){
        return count( $this->roots )>0;
    }
    function startsWell( $root ){
        /* Keeps prefix stripper from making gibberish word */
        return strlen( $root )>=MINWORDLEN    AND
            (   
                self::vowel( $root[0] )       OR 
                self::vowel( $root[1] )       OR
                isset( $GLOBALS['starts'][ $root[0].$root[1] ] )
            );
    }
    function pRoots( $text, $start ){
        if( $start>=strlen( $text )-MINWORDLEN ){
            /* If we're here, this word can't possibly have a prefix */
            return;
        }
        /* Start with longest prefix, strip that length from front of text
         * and see if it's a prefix from the list. If it is, see if the 
         * remaining text is a valid word. 
         */
        for ($len = MAXPREFLEN; $len >0; $len--) {
            $test=substr( $text, $start, $len );
            if( isset( $GLOBALS['prefix'][$test] ) ){
                $root=substr( $text, $start+$len );
                if( !$this->startsWell( $root ) ){
                    /* Invalid word ends the thread */
                    return;
                }
                /* Save info in the array field to keep from having to make a
                 * table. start+len is the string pos where the current root
                 * word begins */
                $this->roots[$start+$len]=$root;
                $this->pRoots( $text, $start+$len );
            }
        }
    }
    function set( $text ){
        $this->pRoots( $text, 0 );
    }
}
class suffix extends affix{
    protected   $log,   //to prevent duplicates
                $sRules;//suffix rule algorithms
    function __construct() {
        $this->sRules=array(
            new s_ly_le(),
            new s_i_y(),
            new s_doubleCons(),
            new s_custom(),
        );
    }
    function haveSuffix(){
        return count( $this->roots )>0;
    }
    function dup( $root ){
        /* Either find it or set it */
        return isset( $this->log[$root] ) OR !( $this->log[$root]=true );
    }
    function pushUQ( $root ){
        /* Only add unique items to roots array 
         * If item exists in log, cancel and return false
         * If item new, add to roots and return true
         */
        return !$this->dup( $root ) AND ( $this->roots[]=$root );
    }
    function endsWell( $root ){
        if( strlen( $root )<MINWORDLEN ){ return false; }
        $sub=substr( $root, -2 );
        return  self::vowel( $sub[0] )  OR 
                self::vowel( $sub[1] )  OR
                $sub[0] == $sub[1]      OR 
                isset( $GLOBALS['ends'][$sub] );
    }
    function sRoots( $text, $end ){
        if( !$end ){
            /* If we're here, this word can't possibly have a suffix. The 
             * presence of prefixes can allow this to overshoot */
            return;
        }
        /* This is like the prefix algo except everything is backwards.
         * The recursive calls can lead to duplicate entries. pushUQ handles 
         * that problem
         * The list of sRules algos handles suffix rules like i to y etc
         */
        for ($len = min( MAXSUFLEN, $end ); $len >0; $len--) {
            $test=substr( $text, $end-$len, $len );
            if( isset( $GLOBALS['suffix'][$test] ) ){
                $root=substr( $text, 0, $end-$len );               
                if( $this->endsWell( $root ) AND $this->pushUQ( $root ) ){
                    /* Invalid or duplicate word ends the thread */
                    $this->sRoots( $text, $end-$len );
                }
                foreach ( $this->sRules as $rule ) {
                    $rule->go( $this, $root, $test );
                }
            }
        }
    }
    function set( $text ){
        /* Clears every time set is called so this object can be reused */
        $this->log=array();
        $this->sRoots( $text, strlen( $text ) );
    }
}
class pTraceback extends prefix{
    function traceback( $text, $curr, $start ){
        /* See comments in prefix->pRoots */
        if( $start>=strlen( $text )){
            return false;
        }
        $remaining=strlen( $text )-$start;
        for ($len = min( MAXPREFLEN, $remaining ); $len >0; $len--) {
            $test=substr( $text, $start, $len );
            if( isset( $GLOBALS['prefix'][$test] ) ){
                $curr[]=$test;
                if( $start+$len==strlen( $text ) ){
                    $this->roots=$curr;
                    return true;
                }
                if( $this->traceback( $text, $curr, $start+$len ) ){
                    return true;
                }
            }
        }
        return false;
    }
    function set( $affixText ){
        return $this->traceback( $affixText, array() , 0, 0 );
    }
}
class sTraceback extends suffix{
    protected $targetRoot;
    function traceback( $text, $curr, $end ){
        /* See comments in suffix->sRoots */
        if( $end<MINWORDLEN ){return;}
        if( $this->targetRoot==$text ){
            $this->roots=$curr;
            return true;
        }
        for ($len = min( MAXSUFLEN, $end ); $len >0; $len--) {
            $test=substr( $text, $end-$len, $len );
            if( isset( $GLOBALS['suffix'][$test] ) ){
                $curr[]=$test;
                $root=substr( $text, 0, $end-$len );
                if( $this->targetRoot==$root ){
                    $this->roots=$curr;
                    return true;
                }
                $root=substr( $text, 0, $end-$len );
                if( 
                    $this->endsWell( $root ) AND
                    !$this->dup( $root ) AND
                    $this->traceback( $text, $curr, $end-$len )
                    ){
                    return true;
                }
                foreach ( $this->sRules as $rule ) {
                    if( $rule->traceback( $this, $curr, $root, $test, $end ) ){
                        return true;
                    }
                }
            }
        }
        return false;
    }
    function set( $origText ){//suffix
        $this->traceback( $origText, array() , strlen( $origText ), 0 );
        $noEmpties=array();
        foreach ( $this->roots as $value){
            if( $value ){
                $noEmpties[]=$value;
            }
        }
        $this->roots=$noEmpties;
    }
    function setTargetRoot( $rootText ){
        $this->targetRoot=$rootText;
    }
}
class s_ly_le{
    function go( $suffix, $root, $s ){
        /* Here, a word like simply becomes simple */
        $len=strlen( $root );
        if( $len<3 ){ return false; }
        if( 
                $s == 'ly' && 
                $suffix->hardConsonant( $root[ $len-1 ] ) && 
                $root[ $len-1 ] != $root[ $len-2 ] 
            ){
            $root.='le';
            if( $suffix->pushUQ( $root ) ){
                $suffix->sRoots( $root, $len );
                return $s;
            }
        }
        return false; 
    }
    function traceback( $suffix, $curr, $root, $s, $end ){
        /* Here, a word like simply becomes simple */
        $len=strlen( $root );
        if( $len<3 ){ return false; }
        if( 
                $s == 'ly' && 
                $suffix->hardConsonant( $root[ $len-1 ] ) && 
                $root[ $len-1 ] != $root[ $len-2 ] 
            ){
            $root.='le';
            if( 
                !$suffix->dup( $root ) AND
                $suffix->traceback( $root, $curr, strlen( $root ) )
            ){
                return true;
            }
        }
        return false; 
    }
}
class s_i_y{
    function go( $suffix, $root, $s ){
        $len=strlen( $root );
        if( $len<3 ){ return false; }
        if( $root[ $len-1 ]=='i' ){
            $root[ $len-1 ]='y';
            if( $suffix->pushUQ( $root ) ){
                $suffix->sRoots( $root, $len );
                return $s; 
            }
        }
        return false; 
    }
    function traceback( $suffix, $curr, $root, $s, $end ){
        $len=strlen( $root );
        if( $len<1 ){ return false; }
        if( $root[ $len-1 ]=='i' ){
            $root[ $len-1 ]='y';
            if( 
                !$suffix->dup( $root ) AND
                $suffix->traceback( $root, $curr, strlen( $root ) )
            ){
                return true;
            }
        }
        return false; 
    }
}
class s_doubleCons{
    function go( $suffix, $root, $s ){       
        $len=strlen( $root );
        if( $len<4 ){ return false; }
        if(//doubled consonants
                !$suffix->vowel( $root[ $len-1 ] ) AND 
                $root[ $len-1 ]==$root[ $len-2 ]
        ){
            $root=substr( $root, 0, -1 );
            if( $suffix->pushUQ( $root ) ){
                $suffix->sRoots( $root, $len );
                return $s; 
            }
        }
        return false; 
    }
    function traceback( $suffix, $curr, $root, $s, $end ){       
        $len=strlen( $root );
        if( $len<4 ){ return false; }
        if(//doubled consonants
                !$suffix->vowel( $root[ $len-1 ] ) AND 
                $root[ $len-1 ]==$root[ $len-2 ]
        ){
            $root=substr( $root, 0, -1 );
            if( 
                !$suffix->dup( $root ) AND
                $suffix->traceback( $root, $curr, strlen( $root ) )
            ){
                return true;
            }
        }
        return false; 
    }
}
class s_custom{
    function go( $suffix, $root, $s ){
        if( !$GLOBALS['suffix'][ $s ] ){//either an array or false
            return;
        }
        $vLast=$suffix->vowel( $root[ strlen( $root )-1 ] );
        foreach ($GLOBALS['suffix'][ $s ] as $addThis=>$callItThat) {
            if( $vLast===$suffix->vowel( $addThis[0] ) ){
                continue;
            }
            $root.=$addThis;
            if( $suffix->pushUQ( $root ) ){
                $suffix->sRoots( $root, strlen( $root ) );
            }
        }
    }
    function traceback( $suffix, $curr, $root, $s, $end ){
        if( !$GLOBALS['suffix'][ $s ] ){//either an array or false
            return false;
        }
        $vLast=$suffix->vowel( $root[ strlen( $root )-1 ] );
        foreach ($GLOBALS['suffix'][ $s ] as $addThis=>$callItThat) {
            if( $vLast===$suffix->vowel( $addThis[0] ) ){
                continue;
            }
            $root.=$addThis;
            $count=count( $curr );
            if( $count ){
                $curr[$count-1]=$callItThat;
            }
            else{
                $curr[]=$callItThat;
            }
            if( 
                !$suffix->dup( $root ) AND
                $suffix->traceback( $root, $curr, strlen( $root ) )
            ){
                return true;
            }
        }
        return false;
    } 
}
function initStemmerGlobals(){
    $GLOBALS['starts']=array(
        'bl'=>true,'br'=>true,'ch'=>true,'cl'=>true,'cr'=>true,'dr'=>true,
        'dw'=>true,'fl'=>true,'fr'=>true,'gl'=>true,'gr'=>true,'kl'=>true,
        'kn'=>true,'kr'=>true,'ll'=>true,'ph'=>true,'pl'=>true,'pn'=>true,
        'pr'=>true,'ps'=>true,'pt'=>true,'rh'=>true,'sc'=>true,'sh'=>true,
        'sl'=>true,'sm'=>true,'sn'=>true,'sp'=>true,'sq'=>true,'st'=>true,
        'sw'=>true,'th'=>true,'tr'=>true,'tw'=>true,'vr'=>true,'wh'=>true,
        'wr'=>true,'xr'=>true, 
    );
    $GLOBALS['ends']=array(
        'bt'=>true,'ch'=>true,'ck'=>true,'ct'=>true,'gh'=>true,'ht'=>true,'lb'=>true,'ld'=>true,'lk'=>true,
        'lm'=>true,'lp'=>true,'lt'=>true,'mb'=>true,'mn'=>true,'mp'=>true,
        'nc'=>true,'nd'=>true,'ng'=>true,'nk'=>true,'nt'=>true,'ph'=>true,
        'pt'=>true,'rb'=>true,'rc'=>true,'rd'=>true,'rf'=>true,'rg'=>true,'rk'=>true,
        'rl'=>true,'rm'=>true,'rn'=>true,'rp'=>true,'rt'=>true,'rv'=>true,
        'sc'=>true,'sh'=>true,'sk'=>true,'sm'=>true,'sp'=>true,'st'=>true,
        'th'=>true,'wd'=>true,'wk'=>true,'wl'=>true,'wn'=>true,
    );
    /* Max is longest in list of prefixes/suffixes; longest is always first, 
     * so check first length*/
    $GLOBALS['prefix']=array(//meta geo anthro, mono, poly, ego, theo
        'counter'=>7,'thermo'=>6,'inter'=>5,'after'=>5,'super'=>5,'hyper'=>5,
        'ultra'=>5,'centi'=>5,'milli'=>5,'micro'=>5,'under'=>5,'macro'=>5,
        'extra'=>5,'multi'=>5,'quadr'=>5,'trans'=>5,'mega'=>4,'deca'=>4,
        'kilo'=>4,'fore'=>4,'nano'=>4,'post'=>4,'semi'=>4,'anti'=>4,'mini'=>4,
        'over'=>4,'uber'=>4,'mis'=>3,'non'=>3,'tri'=>3,'ex-'=>3,'uni'=>3,
        'mid'=>3,'pre'=>3,'sub'=>3,'dis'=>3,'out'=>3,'in'=>2,'de'=>2,
        're'=>2,'ir'=>2,'il'=>2,'co'=>2,'em'=>2,'im'=>2,'en'=>2,'un'=>2,
        'bi'=>2,'a'=>1,
    );
    $GLOBALS['suffix']=array(
        'aholic'=>false,// shopaholic smurfaholic
        'cation'=>array("cate"=>"ion"),// + y implication vs education
        'ssible'=>array("ss"=>"ible","t"=>"ible"),// permissible admissible...pretty irregular
        'arian'=>array("ary"=>"an","y"=>"an"),//totalitarian, authoritarian, sectarian vs veterinarian...can't be exclusive NEW!!!
        //'arily'=>array("ary"=>"ly"),//momentarily 
        'athon'=>false,
        'ative'=>array("ate"=>"ive", "e"=>"ive"), //initiative vs representative vs restorative
        'ctive'=>array("ce"=>"ive","ct"=>"ive"),//deductive seductive vs interactive
        'ility'=>array("le"=>"ity","ile"=>"ity"),//acceptability agility
        'ition'=>array("ish"=>"ion"),//demolition vs partition, ignition ok by max
        'itive'=>array("it"=>"ive","ish"=>"ive"),//exhibitive punitive vs additive 
        'itize'=>array("e"=>"ize","it"=>"ize","ity"=>"ize"),//digitize prioritize vs sensitize "ite"=>"ize"
        'itude'=>array("ite"=>"ude"),//infinitude vs exactitude //roots
        'iture'=>array("ish"=>"ure"),//furniture vs expenditure
        'ocate'=>array("oke"=>"ate"),// provocate
        'ssion'=>array("ss"=>"ion",""=>"ion"),//+de +t procession vs admission vs compression...very irregular NEW!! No, finds process, procede
        
        'uous'=>array("ue"=>"ous"),//continuous vs incestuous
        'able'=>array("e"=>"able","ate"=>"able"),//demonstrable vs lovable
        'ally'=>array("al"=>"ly"),//anatomically vs automatically
        'ator'=>array("e"=>"or", "ate"=>"or"),//inflamator-y circulator vs signatory  circulator
        'ation'=>array("ate"=>"ion"),//violation vs determination
        'cant'=>array("cate"=>"ant"),//lubricant vs applicant
        'cian'=>array("c"=>"ian"),//electrician vs beautician
        'ence'=>array("ent"=>"ence","e"=>"ence"),//different vs adherence
        'iage'=>array("y"=>"age"),// marriage vs verbiage
        'ible'=>false,//no rule  word or root
        'ical'=>array("ic"=>"al","y"=>"al","e"=>"al"),// +y astronomical vs acoustical NEW!!! and kill cal
        'ings'=>false,// no rule!
        'ious'=>array("ion"=>"ous","y"=>"ous","e"=>"ous"),//+ion fallacious vs religious, +e spacious
        'itor'=>array("it"=>"or","e"=>"or"),//depositor vs competitor
        'less'=>false,// no rule!
        'like'=>false,
        'ment'=>false,
        'ness'=>false,//no rule ipos=adjective
        'ress'=>false,//er or?
        'ship'=>false,//No rule
        'sion'=>array("se"=>"ion","de"=>"ion","d"=>"ion"),//+d fusion vs comprehension
        'sive'=>array("s"=>"ive","se"=>"ive","de"=>"ive","re"=>"ive"),//+ convulsive abrasive, elusive + re adhesive vs repulsive obsessive
        'sual'=>array("se"=>"al","t"=>"al",),// consensual vs sensual
        'tize'=>array("t"=>"ize",),// vs dramatize
        'ture'=>array("t"=>"ure"),//moisture vs mixture NEW!!!
        //'ate'=>false,//all roots
        'age'=>array("e"=>"age"),//assemblage usage
        'ant'=>array("ate"=>"ant"),// consultant vs participant
        'ary'=>array("ar"=>"y"),//burglary vs legendary
        'ate'=>array("e"=>"ate"),//all roots but in-between words can exist: inflamate
        'ent'=>array("end"=>"ent"),//dependent vs ascent
        'ess'=>false,//No rule
        'est'=>array("e"=>"est"),//closest
        'ful'=>false,
        'ial'=>array("ia"=>"al","e"=>"al"),//bacterial vs partial vs official
        'ian'=>array("ia"=>"an"),//civilian vs armenian // add countries to database
        'ify'=>array("e"=>"ify", "y"=>"ify", "ic"=>"ify"),//glorify electric vs humidify
        'ile'=>array("e"=>"ile"),//servile vs infantile
        //'ily'=>array("y"=>"ly"),//happily
        'ing'=>array("e"=>"ing"),//behaving
        'ion'=>array("e"=>"ion"),//ignition
        'ior'=>array("e"=>"ior"),//behavior
        'ish'=>false,//no rule
        'ism'=>array("e"=>"ism"),
        'ist'=>array("e"=>"ist"),//no rule
        'ity'=>array("e"=>"ity"),//sanity
        'ive'=>array("e"=>"ive"),//creative
        'ium'=>false,//crematorium, consortium...mostly roots
        'ize'=>array("e"=>"ize","ive"=>"ize","y"=>"ize"),// no rule
        'ous'=>array("e"=>"ous", "y"=>"ous"),// virtuous adulterous vs cavernous roots!!!
        'ual'=>array("ue"=>"al"),// consensual vs sexual
        'ure'=>array("e"=>"ure"),//Forclosure
        'al'=>false,//accidental
        'an'=>array("a"=>"an"),//republican vs american
        'ar'=>array("e"=>"ar"),//burglar
        'ed'=>array("e"=>"ed"),//closed
        'en'=>array("e"=>"en"),//chasten enliven forgiven
        'er'=>array("e"=>"er"),//closer freezer
        'es'=>array("e"=>"s"),//freezes
        'ic'=>array("y"=>"ic", "ia"=>"ic"),//comedic anemic vs idiotic
        'ly'=>false,
        'or'=>array("e"=>"or"),//indicator
        's'=>false,
        'y'=>array("ie"=>""),
        'ies'=>array("y"=>"s"),//
        'ees'=>array("ee"=>"s"),
        'ee'=>false,//array("ee"=>"s"),
    );
    
}

function dispArray( $in, $label="Display Array"){
    //if(!$in){return;}
    echo "<br><table>";
    echo '<tr><th colspan="2">'.$label.': '.count($in).' items</th></tr>';
    foreach($in as $key => $value) {
        if(is_array($value)){
            echo "<tr>&nbsp;<td></td><td>";
            dispArray($value, $key);
            echo "</td></tr>";
        }
        else{
            echo "<tr><td>$key &nbsp;&nbsp;</td><td>$value</td></tr>";

        }
    }
    echo "</table><br>";
}

STEM::init();

?>
