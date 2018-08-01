#!/usr/bin/php
<?php
include('_include.php');

$header = '<!DOCTYPE html>
<html>
<head>

    <meta charset="utf-8">
    <meta name="robots" content="no archive">
    <meta name="viewport" content="user-scalable=0, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="-1">

    <title>Ali Viewer</title>

    <style>
        body{
            font-family: serif;
        }

        .clear{
            clear: both;
        }

        .center{
            text-align: center;
        }

        .item{
            float: left;
            width: 250px;
            height: 265px;
            text-align: center;
        }

        #Info{
            position: fixed;
            top: 0;
            right: 0;
            background-color: #323167;
            color: white;
            padding: 5px;
            font-size: 14px;
            line-height: 18px;
        }
        #Info span{
            font-weight: bold;
        }
        #Info a{
            color: white;
            font-weight: bold;
            padding-top: 4px;
        }
    </style>

</head>
<body>';


write_to_file('mega.html', $header, 'w');


$productCount = 0;
$rowsDisplayed = 0;
//Keeping track of downloaded image filenames
$filenames = array();


//Go to homepage and hope it keeps track as if normal user
$result = get_page("https://www.aliexpress.com/", 'get', '', $emsg);sleep(1);

$result = get_page("https://www.aliexpress.com/wholesale?catId=0&initiative_id=SB_20180801113938&SearchText=soundbar", 'get', '', $emsg);sleep(2);

$result = get_page("https://www.aliexpress.com/w/wholesale-soundbar.html?spm=2114.search0104.0.0.67125000UBdR4W&initiative_id=SB_20180801113938&site=glo&groupsort=1&SortType=price_asc&SearchText=soundbar", 'get', '', $emsg);sleep(2);


//// MEGA LOOP! ////

for($page=1; $page <= 40; $page++){


    //Page counter
    echo "Page: $page\n";


    //// Page split ////
    if($page == 1){
        //Starting fresh search

        $url = "https://www.aliexpress.com/wholesale?minPrice=10&maxPrice=&isBigSale=n&isFreeShip=n&isNew=n&isFavorite=n&shipCountry=US&shipFromCountry=&shipCompanies=&SearchText=soundbar&CatId=0&SortType=price_asc&initiative_id=SB_20180801113938&needQuery=y&groupsort=1";

    }
    else{

        $url = $nextPage;

        //Fix &amp;
        $url = str_replace('&amp;', '&', $url);

        if(substr($url, 0, 2) == '//'){

            $url = 'https:'.$url;

        }

    }

    $result = get_page($url, 'get', '', $emsg);

    $parts = explode('<div id="hs-list-items"', $result);

    if(isset($parts[1])){

        //// Extract items ////

        //Start at list
        $output = '<div id="hs-list-items"'.$parts[1];
        //End at <textarea>
        $parts = explode('<textarea', $output);
        //Fix image links and save rest
        $output = str_replace('image-src=', 'src=', $parts[0]);


        //// Paging ////

        $pageParts = explode('<div id="pagination-bottom"', $result);
        $pageParts = explode('</div>', $pageParts[1]);
        $pageLines = explode("\n", $pageParts[0]);
        unset($pageParts);
        foreach($pageLines as $line){
            if(strstr($line, 'page-next ui-pagination-next')){
                $pageParts = explode('href="', $line);
                $nextPage = substr($pageParts[1], 0, strpos($pageParts[1], '"'));
            }
        }


        //// Initialize variables ////

        $URLs = array();
        $rows = array();
        $counter = 0;
        $item = array();


        //// Build row array ////

        foreach(explode("\n", $output) as $line){

            $line = trim($line)."\n";

            if(substr($line, 0, 17) == '<a class="picRind'){

                $line = str_replace('href="//', 'href="https://', $line);

                if(strstr($line, 'class="picCore') && strstr($line, 'src="')){

                    $parts = explode('src="', $line);

                    $url = substr($parts[1], 0, strpos($parts[1], '"'));

                    //Extract thumbnail filename
                    $localURL = preg_replace('/\/\/ae01.alicdn.com\/(?<=\/)[^\/]+(?=\/)\/(?<=\/)[^\/]+(?=\/)\//i', 'thumbs/', $url);

                    //Fix https getting in the url
                    $localURL = str_replace('https:thumbs/', 'thumbs/', $localURL);

                    //Modify whole line to remove domain
                    $line = str_replace($url, $localURL, $line);

                    //Add https: for curl later
                    if(substr($url, 0, 2) == '//'){
                        $url = 'https:'.$url;
                    }

                    $URLs[] = $url;
                    $item['url'] = $url;

                }

                $item['thumb'] = $line;

            }
            elseif(substr($line, 0, 19) == '<span class="value"'){

                $item['price'] = $line;

                $rows[$counter] = $item;
                $counter++;
                $item = array();

            }

        }


        foreach($rows as $row){

            $productCount++;

            //Grab filename
            $filename = substr($row['url'], (strrpos($row['url'], '/') + 1));

            //Check for filename
            if(!in_array($filename, $filenames)){

                //Add to array
                $filenames[] = $filename;

                //Download image
                $cmd = "wget --no-use-server-timestamps --directory-prefix=".$TempDir." ".$row['url'].' 2>&1';
                echo $cmd."\n";
                $output = shell_exec($cmd);

                //Array of existing images
                $existingImages = explode("\n", shell_exec('ls -t '.$ThumbDir));
                $x = 0;
                $found = false;
                while(count($existingImages) > 0 && $x <= count($existingImages) && $found === false){

                    $file = $existingImages[$x];

                    if($file != '' && $file != '.' && $file != '..'){

                        //Compare image
                        $compareCMD = '/usr/bin/compare -metric RMSE '.$TempDir.$filename.' '.$ThumbDir.$file.' NULL: 2>&1';

                        $compareOutput = shell_exec($compareCMD);

                        if(strlen($compareOutput) > 0 && strpos($compareOutput, '(') !== false){

                            $prefloat = trim($compareOutput);

                            $float = floatval(substr($prefloat, (strpos($prefloat, '(') + 1), -1));

                            if($float < 0.16 || $float == 0){ //Was 0.1
                                //Duplicate found

                                //Erase temp image
                                unlink($TempDir.$filename);

                                //Get out of loop
                                $found = true;

                            }

                        }

                    }

                    $x++;

                }

                if($found === false){
                    //No dupe was found

                    //Move image to thumb directory
                    exec('mv '.$TempDir.$filename.' '.$ThumbDir.$filename);

                    $rowsDisplayed++;

                    $productOutput = "\t".'<div class="item">'."\n"
                                    ."\t\t".$row['thumb']
                                    ."\t\t".'<br>'."\n"
                                    ."\t\t".$row['price']
                                    ."\t".'</div>'."\n";

                    write_to_file('mega.html', $productOutput, 'a');

                }

            }

        }

    }
    else{

        write_to_file('debug.html', $result, 'w');

        //Get out of for loop

        $page = 150;

    }

}

$footer = "\t".'<div class="clear"></div>'."\n\n"
          ."\t".'<div id="Info">'."\n\n"
          ."\t\t".'<div><span>Original Items: </span>'.$productCount.'</div>'."\n"
          ."\t\t".'<div><span>Displayed Items: </span>'.$rowsDisplayed.'</div>'."\n\n"
          ."\t".'</div>'."\n\n".'</body>'."\n".'</html>';

write_to_file('mega.html', $footer, 'a');

?>
