<?php
namespace Antevenio\SafeUrl\Provider;

use Antevenio\SafeUrl\Threat;
use Google_Client;
use Google_Service_Safebrowsing;
use Google_Service_Safebrowsing_FindThreatMatchesRequest;
use Antevenio\SafeUrl\Provider;
use Google_Service_Safebrowsing_FindThreatMatchesResponse;
use Google_Service_Safebrowsing_ThreatMatch;

class Google implements Provider {
    protected $service;

    public function __construct(Google_Service_Safebrowsing $service)
    {
        $this->service = $service;
    }

    public static function create(Google_Client $client)
    {
        return new self(new Google_Service_Safebrowsing($client));
    }

    protected function buildThreatEntries($urls)
    {
        return array_map( function($url) {
            return [ "url" => $url ];
        }, $urls);
    }

    public function validateUrls(array $urls)
    {
        $request = new Google_Service_Safebrowsing_FindThreatMatchesRequest(
            [
                "threatInfo" => [
                    "threatTypes"=> [
                        "MALWARE",
                        "SOCIAL_ENGINEERING",
                        "UNWANTED_SOFTWARE",
                        "POTENTIALLY_HARMFUL_APPLICATION"
                    ],
                    "platformTypes" => [
                        "ANY_PLATFORM"
                    ],
                    "threatEntryTypes" => [
                        "URL"
                    ],
                    "threatEntries" => $this->buildThreatEntries($urls)
                ]
            ]
        );
        $results = $this->service->threatMatches->find($request);
        return $this->getThreatsFromResults($results);
    }

    protected function getThreatsFromResults(
        Google_Service_Safebrowsing_FindThreatMatchesResponse $results
    )
    {
        $threats = [];
        /** @var Google_Service_Safebrowsing_ThreatMatch $match */
        foreach ($results->getMatches() as $match) {
            $threat = new Threat();
            $threat->setUrl($match->offsetGet("modelData")["threat"]["url"])
                ->setPlatform($match->getPlatformType())
                ->setType($match->getThreatType());

            $threats[] = $threat;
        }

        return ($threats);
    }
}