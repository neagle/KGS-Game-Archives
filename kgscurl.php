<?php
// USER CONFIG VARIABLES
$cacheDir = './'; // directory in which to store cached files
$cacheFile = '_game_cache.json'; // specifies the file to write your game records to; prefaced by username

// These options can be passed in as query string parameters in the AJAX call
// Only username is required
$username = $_GET['username'];
$numGames = isset($_GET['numGames']) ? $_GET['numGames'] : 20; // Maximum number of games to retrieve
$hoursFresh = isset($_GET['hoursFresh']) ? $_GET['hoursFresh'] : 24; // Number of hours to get games from cache before refetching data from KGS
$dateFormat = isset($_GET['dateFormat']) ? $_GET['dateFormat'] : 'F jS, Y'; // Default date format 
$ranked = isset($_GET['ranked']) ? $_GET['ranked'] : true; // by default, only fetches ranked games
$tags = isset($_GET['tags']) ? '&tags=' . $_GET['tags'] : ''; 
$widgetName = isset($_GET['widgetName']) ? '_' . $_GET['widgetName'] : ''; 

// You shouldn't need to alter any of the following code.
$cacheFile = $cacheDir . $username . $widgetName . $cacheFile;

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
        // echo "Creating cache file.";
        return false;
    }

    // Get the time the cache file was last modified
    $shelfDate = filemtime($cacheFile);

    // Return true if cache file is fresh, false if not
    return (($shelfDate - $expires) > 0);
}

/* Rewrite the cached archive file by fetching archives from KGS */
function updateCache() {
    global $username, $numGames, $cacheFile, $dateFormat, $ranked, $tags;

    // The $games array stores game records from KGS
    $games = array();

    // Set initial year and month
    $year = date('Y');
    $month = date('n');

    $tries = 0;

    while (count($games) < $numGames) {
        // echo 'Year: ' . $year;
        // echo 'Month: ' . $month;

        $curl = new Curl();
        $htmlURL = 'http://www.gokgs.com/gameArchives.jsp?user='.$username.'&year='.$year.'&month='.$month.$tags;
        if (empty($tags) == false) { $htmlURL = $htmlURL . '&tags=' . $tags; }
        $html = $curl->get($htmlURL);
        // echo $html;

        $doc = phpQuery::newDocument($html);

        // Identify game records by finding links to sgfs
        $gameLinks = $doc->find('a[href^="http://files.gokgs.com/games/"]');
        // Find our game record table rows by traversing back up the dom tree to the relevant table rows
        $gameRecords = $gameLinks->parents('tr');

        foreach($gameRecords as $game) {
            $type = pq($game)->find('td:eq(5)')->text();
            // Test for the type of game. By default, only return ranked games.
            // If $ranked == false, return all games that are not reviews, because reviews mess up our formatting.
            // And we don't care about them anyway.
            $typeFilter = ($ranked == true) ? ($type == 'Ranked') : ($type != 'Review');
            if ($typeFilter) {
                $g = array(); 

                $g['sgf'] = pq($game)->find('td:eq(0) > a')->attr('href');
                $g['white'] = pq($game)->find('td:eq(1)')->text();
                $g['black'] = pq($game)->find('td:eq(2)')->text();
                $g['setup'] = pq($game)->find('td:eq(3)')->text();

                $gameDate = pq($game)->find('td:eq(4)')->text();
                // Convert date string to unix timestamp
                $gameDate = strtotime($gameDate);
                $gameDate = date($dateFormat, $gameDate);
                $g['date'] = $gameDate; 

                $g['result'] = pq($game)->find('td:eq(6)')->text();

                // print_r($g);
                $games[] = $g;
            }
            if (count($games) >= $numGames) { break; }
        }

        $month = $month - 1;
        if ($month < 1) {
            $year = $year - 1;
            $month = 12;
        }

        $tries++;
        // Never try too hard to get game records. A player may not have played enough games to fill the quota, or may not even exist.
        if ($tries > 3) { break; }
    }

    // convert game records to json
    $games = json_encode($games);

    // Write contents of KGS Game Archives page to user-specified $gameFile 
    $fh = fopen($cacheFile, 'w') or die('Can\'t open file.');
    fwrite($fh, $games);
    fclose($fh);
}

/* Outputs the contents of the cached archive file in JSON format */
function outputCache() {
    global $cacheFile;

    $output = file_get_contents($cacheFile);
    echo $output;
}

// If checkCacheFreshness returns false, update the file from KGS
if ((checkCacheFreshness() == false) || ($_GET[noCache] == 'true')) {
    updateCache();
}

// Output the contents of the cached archive file
outputCache();

?>
