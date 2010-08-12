<?php
// USER CONFIG VARIABLES
$username = 'scotistic'; // this is your KGS username
$cacheFile = './game_cache.json'; // specifies the file to write your game records to
$numGames = 10; // Maximum number of games to retrieve
$hoursFresh = 24; // Number of hours to get games from cache before refetching data from KGS


// You shouldn't need to alter any of the following code.

// Curl utility -- not written by me (should find attribution for this, if possible)
class Curl
{       

    public $cookieJar = "";

    public function __construct($cookieJarFile = 'cookies.txt') {
        $this->cookieJar = $cookieJarFile;
    }

    function setup()
    {


        $header = array();
        $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $header[] =  "Cache-Control: max-age=0";
        $header[] =  "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Pragma: "; // browsers keep this blank.


        curl_setopt($this->curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US; rv:1.8.1.7) Gecko/20070914 Firefox/2.0.0.7');
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($this->curl,CURLOPT_COOKIEJAR, $cookieJar); 
        curl_setopt($this->curl,CURLOPT_COOKIEFILE, $cookieJar);
        curl_setopt($this->curl,CURLOPT_AUTOREFERER, true);
        curl_setopt($this->curl,CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl,CURLOPT_RETURNTRANSFER, true);  
    }


    function get($url)
    { 
        $this->curl = curl_init($url);
        $this->setup();

        return $this->request();
    }

    function getAll($reg,$str)
    {
        preg_match_all($reg,$str,$matches);
        return $matches[1];
    }

    function postForm($url, $fields, $referer='')
    {
        $this->curl = curl_init($url);
        $this->setup();
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, 1);
        curl_setopt($this->curl, CURLOPT_REFERER, $referer);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $fields);
        return $this->request();
    }

    function getInfo($info)
    {
        $info = ($info == 'lasturl') ? curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL) : curl_getinfo($this->curl, $info);
        return $info;
    }

    function request()
    {
        return curl_exec($this->curl);
    }
}

// phpQuery is the bee's knees - we use it to process the raw HTML from KGS's archives
require('./lib/phpQuery/phpQuery/phpQuery.php');

/* Checks to see how recently the cached version of the KGS Archives has been pulled from KGS */
function checkCacheFreshness() {
    global $cacheFile, $hoursFresh;

    $expires = strtotime($hoursFresh . ' hours ago');

    // If cache file does not already exist, create it
    if (!file_exists($cacheFile)) {
        $cf = fopen($cacheFile, 'w') or die('Can\'t open file.');
        fclose($cf);
    }

    // Get the time the cache file was last modified
    $shelfDate = filemtime($cacheFile);

    // Return true if cache file is fresh, false if not
    return (($shelfDate - $expires) > 0);
}

/* Rewrite the cached archive file by fetching archives from KGS */
function updateCache() {
    // The $games array stores game records from KGS
    $games = array();

    // Set initial year and month
    $year = date('Y');
    $month = date('n');


    while (count($games) < $numGames) {
        echo 'Games so far: ' . count($games) . ' numGames: ' . $numGames;

        $games[] = 'new game';

        echo 'Year: ' . $year;
        echo 'Month: ' . $month;

        /*
        $curl = new Curl();
        $html = $curl->get("http://www.gokgs.com/gameArchives.jsp?user=".$username."&year=".$year."&month=".$month);

        $doc = phpQuery::newDocument($html);
        // Identify game records by finding links to sgfs
        $gameLinks = $doc->find('a[href^="http://files.gokgs.com/games/"]');
        // Find our game record table rows by traversing back up the dom tree to the relevant table rows
        $gameRecords = $gameLinks->parents('tr');

        foreach($gameRecords as $game) {
            if(pq($game)->find('td:eq(5)')->text() == 'Ranked') {

                $g = array(); 

                $g['sgf'] = pq($game)->find('td:eq(0) > a')->attr('href');
                $g['white'] = pq($game)->find('td:eq(1)')->text();
                $g['black'] = pq($game)->find('td:eq(2)')->text();
                $g['setup'] = pq($game)->find('td:eq(3)')->text();

                $gameDate = pq($game)->find('td:eq(4)')->text();
                // Convert date string to unix timestamp
                $gameDate = strtotime($gameDate);
                // Customize date formatting
                $gameDate = date('F jS, Y', $gameDate);
                $g['date'] = $gameDate; 

                $g['result'] = pq($game)->find('td:eq(6)')->text();

                $games[] = $g;
            }
        }
        */
        echo implode('', $games);

        /*
        // convert game records to json
        $games = json_encode($games);

        echo $games;

        // Write contents of KGS Game Archives page to user-specified $gameFile 
        $fh = fopen($gameFile, 'w') or die('Can\'t open file.');
        fwrite($fh, $games);
        fclose($fh);
        */

    }
}

/* Outputs the contents of the cached archive file in JSON format */
function outputCache() {
    global $cacheFile;

    if (file_exists($cacheFile)) {
        $output = file_get_contents($cacheFile);
        echo $output;
    } else {
        echo 'Cannot find cache file.';
    }
}

// If checkCacheFreshness returns false, update the file from KGS
if (!checkCacheFreshness()) {
    updateCache();
}

// Output the contents of the cached archive file
outputCache();

?>
