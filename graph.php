<?php
    // These options may be passed in via query string parameters
    $options = (object) array(
        // Defaults 
        username => 'nate451', // KGS username
        width => 300, //  Width to resize the graph to... height is not specified so that proper aspect ratio is preserved
        kgsClient => 'en_US',
        removeExtras => 'true' // Removes graph data and legend that become illegible at small sizes
    );

    // Configuration variables not accessible via the query string 
    $config = (object) array(
        filename => 'kgs-rank-graph-',
        fileext => 'png',
        graphShelfLife => 24, // How many hours a local version of the graph is good for before it should be fetched again
        location => '/usr/bin/convert', // Path to ImageMagick
        kgsURL => 'http://www.gokgs.com/servlet/graph/',
        maxWidth => 640,
        minWidth => 0
    );

    // Overwrite defaults with any values in the query string
    foreach($options as $key => $value) {
        if  (isset($_GET[$key])) {
            $options->$key = $_GET[$key]; 
            // Strip any non-allowed characters
            $options->$key = preg_replace('[^a-zA-Z0-9]', '', $options->$key);
        }
    }

    // Security checks

    // Make sure that width is an integer and within min and max width
    if (is_numeric($options->width)) {
        $options->width = (int)$options->width; 

        if ($options->width > $config->maxWidth) {
            $options->width = $config->maxWidth;
        }

        if ($options->width < $config->minWidth) {
            $options->width = $config->minWidth;
        }
    } else {
        // If the value isn't numeric, set width to a default number 
        $options->width = 300;
    }



    // Check the freshness of a file
    // Shelf life is in hours 
    function checkFreshness($file, $shelfLife) {
        $expires = strtotime($shelfLife . ' hours ago');

        // File is not fresh if it does not exist
        if (!file_exists($file)) {
            return false;
        }

        // Get the time the cache file was last modified
        $shelfDate = filemtime($file);

        // Return true if file is fresh, false if not
        return (($shelfDate - $expires) > 0);
    }

    // Check to see if a file matches a given width
    function checkDimensions($file, $width) {
        global $config;

        $w = exec($config->location . $file . ' -format "%[fx:w]" info:');

        return ($w == $width);
    }

    $file = $config->filename . $options->username . '.' . $config->fileext;
    $config->location = $config->location . ' ';
 
    // If a local version of the graph hasn't been fetched in the alotted time...
    if ((checkFreshness($file, $graphShelfLife) == false) || (checkDimensions($file, $options->width) == false)) {

        // Fetch a fresh version of the KGS Rank Graph, store it locally
        $content = file_get_contents($config->kgsURL . $options->username . '-' . $options->kgsClient . '.' . $config->fileext);
        file_put_contents('./' . $file, $content);

        function getRGB($file, $x, $y) {
            global $config;

            $command = $file . '[1x1+' . $x . '+' . $y .'] -format "%[fx:r]%[fx:g]%[fx:b]" info:';
            $convert = $config->location . $command;
            $output = exec($convert);    
            return $output;
        } 

        // Takes a KGS graph and finds its border
        function findBorder($file) {
            global $config;
            
            $borderColor = '111';
            $width = exec($config->location . $file . ' -format "%[fx:w]" info:');
            $height = exec($config->location . $file . ' -format "%[fx:h]" info:');
            $dimensions = array();

            // Find where the border starts on the left
            for ($i = 1; $i < $width; $i++) {
                $c = getRGB($file, $i, round($height/2));
                if ($c == $borderColor) {
                    $dimensions[0] = $i;
                    break;
                }
            }

            // Find where the border starts on the top 
            for ($i = 1; $i < $height; $i++) {
                $c = getRGB($file, round($width/2), $i);
                if ($c == $borderColor) {
                    $dimensions[1] = $i;
                    break;
                }
            }

            // Find where the border stops on the right 
            for ($i = $width; $i > 0; $i--) {
                $c = getRGB($file, $i, round($height/2));
                if ($c == $borderColor) {
                    $dimensions[2] = $i - $dimensions[0];
                    break;
                }
            }

            // Find where the border stops on the bottom 
            for ($i = $height; $i > 0; $i--) {
                $c = getRGB($file, round($width/2), $i);
                if ($c == $borderColor) {
                    $dimensions[3] = $i - $dimensions[1];
                    break;
                }
            }

            // We don't want the white border on the left
            $dimensions[0] = $dimensions[0] + 1;
            $dimensions[1] = $dimensions[1] + 1;
            return $dimensions;
        }

        if ($options->removeExtras == 'true') {
            // Find the dimensions of the graph itself
            $dimensions = findBorder($file);

            // Crop the image
            exec($config->location . '-crop ' . $dimensions[2] . 'x' . $dimensions[3] . '+' . $dimensions[0] . '+' . $dimensions[1] . ' ' . $file . ' ' . $file);
        }

        // Create a thumbnail version of the graph
        $command = '-thumbnail ' . $options->width;
        $convert = $config->location . $command . ' ' . $file . ' ' . $file;
        exec ($convert);
    }

    // $w = exec($config->location . $file . ' -format "%[fx:w]" info:');
    $h = exec($config->location . $file . ' -format "%[fx:h]" info:');
    echo '<img src="' . $file . '" width="' . $options->width . '" height="' . $h . '" />';
?>
