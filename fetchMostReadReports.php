<?php

require      __DIR__ . '/parameters.php';
require_once __DIR__ . '/vendor/autoload.php';

/*
 * Initialize the connection to Google Analtycs account
 */
function initializeAnalytics()
{
    $KEY_FILE_LOCATION = __DIR__ . '/credentials.json';

    $client = new Google_Client();
    $client->setAuthConfig($KEY_FILE_LOCATION);
    $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
    $analytics = new Google_Service_Analytics($client);

    return $analytics;
}

/*
 * Return the most read reports in the Google Analytics format
 */
function getResults($analytics, $profileId, $startDate, $endDate, $numberOfResults)
{
    return $analytics->data_ga->get(
        'ga:' . $profileId,
	$startDate,
        $endDate,
        'ga:sessions, ga:pageviews, ga:socialInteractions',
         array('dimensions'    => 'ga:pagePath, ga:pageTitle',
	       'sort'          => '-ga:pageviews',
	       'filters'       => 'ga:dimension1==Report',
	       'samplingLevel' => 'HIGHER_PRECISION',
	       'max-results'   => $numberOfResults
		)
	);
}

/*
 * Use the RW API to get the reports with the url_alias provided by Google Analytics
 */
function getReports($results, $webhost, $apiEndPoint)
{
    $jsons = array();

    foreach($results["rows"] as $result)
    {
        $url = urlencode($webhost . $result[0]);
        $content = file_get_contents($apiEndPoint . "?filter[field]=url_alias&filter[value]=" . $url);

	$content = json_decode($content, true);
	$href = $content["data"][0]["href"];

	$jsons[] = file_get_contents($href); 
    }

    return $jsons;
}

$analytics = initializeAnalytics();
$results = getResults($analytics, $profileId, $startDate, $endDate, $numberOfResults);
$reports = getReports($results, $webhost, $apiEndPoint);

/*
 * Write the reports in the gist
 */
$data   = array("files" => array($gistFilename => array( "content" => json_encode($reports))));
$header = array("Authorization: token " . $gistToken, "User-Agent: ReliefWeb API");

$curl = curl_init();

curl_setopt($curl, CURLOPT_URL, $gist);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

curl_exec($curl);

curl_close($curl);
?>
