<?php
    $options = (object) array(
        // Defaults 
        filename => 'kgs-rank-graph-',
        fileext => 'png',
        graphShelfLife => 24, // How long a local version of the graph is good for before it should be fetched again
        username => 'nate451', // KGS username
        width => 300, //  Width to resize the graph to... height is not specified so that proper aspect ratio is preserved
        location => '/usr/bin/convert', // Path to ImageMagick
        kgsURL => 'http://www.gokgs.com/servlet/graph/',
        kgsClient => 'en_US',
        removeExtras => 'true'
    );

    // Overwrite defaults with any values in the query string
    foreach($_GET as $key => $value) {
        $options->$key = $value; 
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
        global $location;

        $w = exec($location . $file . ' -format "%[fx:w]" info:');

        return ($w == $width);
    }

    $file = $options->filename . $options->username . '.' . $options->fileext;
    $location = $options->location . ' ';
 
    // If a local version of the graph hasn't been fetched in the alotted time...
    if ((checkFreshness($file, $graphShelfLife) == false) || (checkDimensions($file, $options->width) == false)) {

        // Fetch a fresh version of the KGS Rank Graph, store it locally
        $content = file_get_contents($options->kgsURL . $options->username . '-' . $options->kgsClient . '.' . $options->fileext);
        file_put_contents('./' . $file, $content);

        function getRGB($file, $x, $y) {
            global $location;

            $command = $file . '[1x1+' . $x . '+' . $y .'] -format "%[fx:r]%[fx:g]%[fx:b]" info:';
            $convert = $location . $command;
            $output = exec($convert);    
            return $output;
        } 

        // Takes a KGS graph and finds its border
        function findBorder($file) {
            global $location;
            
            $borderColor = '111';
            $width = exec($location . $file . ' -format "%[fx:w]" info:');
            $height = exec($location . $file . ' -format "%[fx:h]" info:');
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
            exec($location . '-crop ' . $dimensions[2] . 'x' . $dimensions[3] . '+' . $dimensions[0] . '+' . $dimensions[1] . ' ' . $file . ' ' . $file);
        }

        // Create a thumbnail version of the graph
        $command = '-thumbnail ' . $options->width;
        $convert = $location . $command . ' ' . $file . ' ' . $file;
        exec ($convert);
    }

    // $w = exec($location . $file . ' -format "%[fx:w]" info:');
    $h = exec($location . $file . ' -format "%[fx:h]" info:');
    echo '<img src="' . $file . '" width="' . $options->width . '" height="' . $h . '" />';
?>
