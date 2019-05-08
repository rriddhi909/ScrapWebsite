<?php

error_reporting(0);
//function curl_url($url,$ref=""){
$search_text = isset($_POST['search_text']) ? $_POST['search_text'] : null;

// create Class Object
$obj = new ScrapWebsite();
$return = $obj->scrappingProcess();

//    print_r($return);
echo json_encode($return);

//Create Class
class ScrapWebsite {

    private $qry;
    private $aResult;

    public function __construct() {
        $str = trim($_POST['search_text']) ? trim($_POST['search_text']) : null;
        $this->qry = $str;
        $this->aResult = [];
    }

    //Scrapping Performed
    public function scrappingProcess() {
        $search_value = $this->qry;
        $this->scrappingSnapdealData($search_value);
        $this->scrapAmazonData($search_value);
        $this->showResults($search_value);
    }

    public function scrapAmazonData($search_value) {
        $json_data = $percentage = [];
        $page_content = file_get_contents("https://www.amazon.in/s?k=" . $search_value . "&ajr=0&ref=sr_st_price-asc-rank");
        preg_match_all('/<span class="a-size-medium a-color-base a-text-normal">(.*?)<\/span>/', $page_content, $names);
        preg_match_all('/< *img[^>]*src *= *["\']?([^"\']*)/i', $page_content, $images_url);
        preg_match_all('/data-a-color="price"\><span class=\"a-offscreen\">(.*?)<\/span>/', $page_content, $prices);
        preg_match_all('/<span class=\"a-price-symbol\">(.*?)<\/span>/', $page_content, $currencies);
        preg_match_all('/<span class=\"a-letter-space\"><\/span><span>Save (.*?)<\/span>/', $page_content, $main_price);

        foreach ($main_price[1] as $k => $v) {
            $main_price[$k] = substr($v, 0, strpos($v, '('));
            $percentage[$k] = str_replace(')', '', str_replace('(', '', substr($v, -5)));
            $main_price[$k] = preg_replace('/[^A-Za-z0-9\-]/', '', $main_price[$k]);
        }

        foreach ($names[1] as $key => $val) {
            $json_data[$key]['name'] = $val;
            $json_data[$key]['image_url'] = $images_url[1][$key];
            $json_data[$key]['percentage'] = (isset($percentage[$key]) && $percentage[$key] != "") ? $percentage[$key] : null;
            $json_data[$key]['price'] = $prices[1][$key];
            $json_data[$key]['msrp'] = $currencies[1][$key] . "" . (preg_replace('/[^A-Za-z0-9\-]/', '', $main_price[$key]) + preg_replace('/[^A-Za-z0-9\-]/', '', $prices[1][$key]));
            $json_data[$key]['timestamp'] = time();
        }
        $this->writeData($json_data);
    }

    public function scrappingSnapdealData($search_value) {

        $page_content = file_get_contents("https://www.snapdeal.com/search?keyword=" . $search_value . "&noOfResults=20&sort=plth");

        preg_match_all('/<p class=\"product-title \" title=\"(.*?)">/', $page_content, $names);
        preg_match_all('/<source srcset=\"(.*?)" title=/', $page_content, $images_url);
        preg_match_all('/<span class=\"lfloat product-price\"[^>]*>(.*?)<\/span>/', $page_content, $prices);
        preg_match_all('/<span class=\"lfloat product-desc-price strike \">(.*?)<\/span>/', $page_content, $main_price);
        preg_match_all('/<div class=\"product-discount\">(.*?)<\/span>/', $page_content, $percentage);

        $json_data = [];
        foreach ($names[1] as $key => $val) {

            $json_data[$key]['name'] = $val;
            $json_data[$key]['image_url'] = (isset($images_url[1][$key]) && $images_url[1][$key] != "") ? $images_url[1][$key] : null;
            $json_data[$key]['price'] = $prices[1][$key];
            $json_data[$key]['msrp'] = $main_price[1][$key];
            $json_data[$key]['percentage'] = (isset($percentage[1][$key]) && $percentage[1][$key] != "") ? $percentage[1][$key] : null;
        }
        $this->writeData($json_data);
    }

    public function writeData($json_data) {
        // WRITE DATA HERE TO FILE
        $final_array = $json_data;
        $myFile = 'search_data_file.txt';
        if (file_exists($myFile)) {
            $file_content = file_get_contents($myFile);
            $file_content_array = json_decode($file_content, true);
            if (!empty($file_content_array)) {
                $final_array = array_unique(array_merge($file_content_array, $json_data), SORT_REGULAR);
            }
        }

        $fh = fopen($myFile, 'w');
        fwrite($fh, json_encode($final_array));
        fclose($fh);
    }

    public function showResults($search_value) {
        $myFile = 'search_data_file.txt';
        $file_content = file_get_contents($myFile);
        $file_content_array = json_decode($file_content, true);
        $result = $this->searchData($file_content_array, $key = 'name', $search_value);
        return $this->setData($result);
    }

    public function searchData($array, $sKey, $value) {
//        echo "<pre>";
//        print_r($value);
        $results = array();
        foreach ($array as $key => $val) {
            if (stripos($val[$sKey], $value) !== false) {
                array_push($results, $val);
            }
        }
        return $results;
    }

    public function setData($data) {
        $result = "<table class='data_results'>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Msrp</th>
                            <th>Percentage</th>
                        </tr>";
        foreach ($data as $key => $val) {
            $result .= "<tr>
                            <td>" . $val['image_url'] . "</td>
                            <td>" . $val['name'] . "</td>
                            <td>" . $val['price'] . "</td>
                            <td>" . $val['msrp'] . "</td>
                            <td>" . $val['percentage'] . "</td>
                        </tr>";
        }
        $result .= "</table>";
        echo $result;
    }

}

?>